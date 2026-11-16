/*
 * ============================================================
 *  ESP32 - Telehealth EKG (Production + ASCON-128 AEAD)
 *  - Pembacaan Serial Arduino + Moving Average + R-Peak BPM
 *  - Tampilan OLED U8g2 Real-time
 *  - Enkripsi ASCON-128 AEAD sebelum Publish via MQTT over WSS
 * ============================================================
 */

#include <WiFi.h>
#include <WebServer.h>
#include <DNSServer.h>
#include <Preferences.h>
#include <WebSocketsClient.h>
#include <Wire.h>
#include <U8g2lib.h>
#include <vector>
#include <math.h>
#include "ascon128.h" // Pastikan file ascon128.h (versi P(x,6)) ada di folder yang sama

// ==================================================
// KONFIGURASI WAJIB - GANTI SESUAI SETUP KAMU
// ==================================================
const char* WIFI_SSID        = "Samsung";
const char* WIFI_PASS        = "rafidedenisa";
const char* MQTT_HOST        = "ekgtelehealth.my.id";
const uint16_t MQTT_PORT     = 443;
const char* MQTT_TOPIC       = "telehealth/test";
const char* TARGET_DEVICE_ID = "DEV-VV9ALVAW";

// ==================================================
// KONFIGURASI KRIPTOGRAFI ASCON-128
// ==================================================
static const uint8_t ASCON_KEY[16] = {
    0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07,
    0x08, 0x09, 0x0A, 0x0B, 0x0C, 0x0D, 0x0E, 0x0F
};

// ==================================================
// HARDWARE PIN
// ==================================================
#define ECG_RX_PIN 16
#define ECG_TX_PIN 17
#define OLED_SDA   21
#define OLED_SCL   22

HardwareSerial ECGSerial(2);
U8G2_SSD1306_128X64_NONAME_F_HW_I2C display(U8G2_R0, U8X8_PIN_NONE);

const uint16_t ACQUISITION_RATE_HZ = 250;
const uint32_t MQTT_PUBLISH_INTERVAL_MS = 100;
const int RESET_PIN = 0;
const unsigned long WIFI_CONNECT_TIMEOUT = 15000;

// ==================================================
// KONFIGURASI SINYAL
// ==================================================
const int LEAD_OFF_AMPLITUDE_THRESHOLD = 20;
const float R_PEAK_THRESHOLD_RATIO = 0.75;
const int LEAD_OFF_DEBOUNCE = 50;

// Moving Average Filter (window 5)
#define MA_WINDOW 5
int maBuffer[MA_WINDOW];
int maIdx = 0;
bool maFilled = false;

int applyMovingAverage(int raw) {
    maBuffer[maIdx] = raw;
    maIdx = (maIdx + 1) % MA_WINDOW;
    if (maIdx == 0) maFilled = true;
    
    int count = maFilled ? MA_WINDOW : maIdx;
    long sum = 0;
    for (int i = 0; i < count; i++) {
        sum += maBuffer[i];
    }
    return (int)(sum / count);
}

// ==================================================
// GLOBAL STATE
// ==================================================
WebServer server(80);
DNSServer dnsServer;
Preferences prefs;
WebSocketsClient webSocket;

String statusMsg = "Menunggu WiFi";
bool isConnectingWiFi = false;
unsigned long wifiConnectStart = 0;
bool mqttReady = false;
unsigned long lastPing = 0, lastMqttPublish = 0;
unsigned long resetBtnDownStart = 0;
bool resetBtnPressed = false;

char uartBuffer[16];
uint8_t uartIndex = 0;
volatile int latestAdc = 0;
volatile int latestFiltered = 512;
bool ecgDataReceived = false;
unsigned long lastEcgSampleMs = 0;
int graphY[128];
float baseline = 512;
bool baselineInitialized = false;
int currentBpm = 0;
bool leadOff = true;

#define ECG_BUF_SIZE 625
int ecgBuf[ECG_BUF_SIZE];
int bufIdx = 0, bufFilled = 0;
#define MAX_PEAKS 10
unsigned long peakTimes[MAX_PEAKS];
int peakIdx = 0, peakCount = 0;
unsigned long lastBpmCalc = 0;
int leadOffCounter = 0;

// ==================================================
// HELPER KRIPTOGRAFI ASCON-128
// ==================================================
String bytesToHexString(const uint8_t* src, size_t len) {
    String out = "";
    out.reserve(len * 2);
    for (size_t i = 0; i < len; i++) {
        if (src[i] < 0x10) out += "0";
        out += String(src[i], HEX);
    }
    return out;
}

void generateNonce(uint8_t* nonce, size_t len) {
    for (size_t i = 0; i < len; i++) {
        nonce[i] = (uint8_t)esp_random();
    }
}

// ==================================================
// R-PEAK + LEAD-OFF DETECTION
// ==================================================
void processEcgSampleForBpm(int val, unsigned long t) {
    ecgBuf[bufIdx] = val;
    bufIdx = (bufIdx + 1) % ECG_BUF_SIZE;
    if (bufIdx == 0) bufFilled = ECG_BUF_SIZE;
    int n = bufFilled ? ECG_BUF_SIZE : bufIdx;
    if (n < 50) return;

    long sum = 0; int mx = 0, mn = 1023;
    for (int i = 0; i < n; i++) {
        sum += ecgBuf[i];
        if (ecgBuf[i] > mx) mx = ecgBuf[i];
        if (ecgBuf[i] < mn) mn = ecgBuf[i];
    }
    int mean = sum / n;
    int amplitude = mx - mean;

    if (amplitude < LEAD_OFF_AMPLITUDE_THRESHOLD) {
        leadOffCounter++;
        if (leadOffCounter > LEAD_OFF_DEBOUNCE) {
            leadOff = true;
            currentBpm = 0;
            peakCount = 0;
        }
        return;
    }
    leadOffCounter = 0;
    leadOff = false;

    int thr = mean + (int)((mx - mean) * R_PEAK_THRESHOLD_RATIO);
    int curr = ecgBuf[(bufIdx - 1 + ECG_BUF_SIZE) % ECG_BUF_SIZE];
    int p1 = ecgBuf[(bufIdx - 2 + ECG_BUF_SIZE) % ECG_BUF_SIZE];
    int p2 = ecgBuf[(bufIdx - 3 + ECG_BUF_SIZE) % ECG_BUF_SIZE];
    int nxt = ecgBuf[bufIdx % ECG_BUF_SIZE];

    if (curr > thr && curr > p1 && curr > p2 && curr >= nxt) {
        if (peakCount > 0) {
            unsigned long last = peakTimes[(peakIdx - 1 + MAX_PEAKS) % MAX_PEAKS];
            if (t - last < 300) return;
        }
        peakTimes[peakIdx] = t;
        peakIdx = (peakIdx + 1) % MAX_PEAKS;
        if (peakCount < MAX_PEAKS) peakCount++;
    }

    if (millis() - lastBpmCalc > 1000) {
        lastBpmCalc = millis();
        if (peakCount >= 2) {
            unsigned long sorted[MAX_PEAKS];
            int s = (peakIdx - peakCount + MAX_PEAKS) % MAX_PEAKS;
            for (int i = 0; i < peakCount; i++) sorted[i] = peakTimes[(s + i) % MAX_PEAKS];
            long total = 0; int cnt = 0;
            for (int i = 1; i < peakCount; i++) {
                long iv = sorted[i] - sorted[i - 1];
                if (iv >= 300 && iv <= 2000) { total += iv; cnt++; }
            }
            if (cnt > 0) {
                int bpm = 60000 / (total / cnt);
                if (bpm >= 30 && bpm <= 200) currentBpm = bpm;
                else currentBpm = 0;
            } else currentBpm = 0;
        } else currentBpm = 0;
    }
}

// ==================================================
// MQTT BINARY FRAME
// ==================================================
void addMqttString(std::vector<uint8_t>& out, const char* value) {
    size_t n = strlen(value);
    out.push_back((uint8_t)((n >> 8) & 0xFF));
    out.push_back((uint8_t)(n & 0xFF));
    for (size_t i = 0; i < n; i++) out.push_back((uint8_t)value[i]);
}

void appendRemainingLength(std::vector<uint8_t>& packet, size_t length) {
    do {
        uint8_t encoded = length % 128;
        length /= 128;
        if (length > 0) encoded |= 0x80;
        packet.push_back(encoded);
    } while (length > 0);
}

void sendMqttConnect() {
    std::vector<uint8_t> body;
    addMqttString(body, "MQTT");
    body.push_back(4); body.push_back(2); body.push_back(0); body.push_back(30);
    addMqttString(body, TARGET_DEVICE_ID);
    std::vector<uint8_t> packet;
    packet.push_back(0x10);
    appendRemainingLength(packet, body.size());
    packet.insert(packet.end(), body.begin(), body.end());
    webSocket.sendBIN(packet.data(), packet.size());
    Serial.println("[MQTT] CONNECT dikirim");
}

void publishMqtt(const String& payload) {
    std::vector<uint8_t> body;
    addMqttString(body, MQTT_TOPIC);
    for (size_t i = 0; i < payload.length(); i++) body.push_back((uint8_t)payload[i]);
    std::vector<uint8_t> packet;
    packet.push_back(0x30);
    appendRemainingLength(packet, body.size());
    packet.insert(packet.end(), body.begin(), body.end());
    if (webSocket.sendBIN(packet.data(), packet.size())) {
        Serial.println("[PUBLISH ASCON-128 OK]");
    }
}

void publishLatestEcg() {
    if (!mqttReady || !webSocket.isConnected() || !ecgDataReceived) return;
    if (millis() - lastMqttPublish < MQTT_PUBLISH_INTERVAL_MS) return;
    lastMqttPublish = millis();

    int valueToSend = leadOff ? 0 : latestFiltered;
    int bpmToSend = leadOff ? 0 : currentBpm;

    // 1. Bentuk JSON Plaintext dari Sensor
    String rawPayload = "{";
    rawPayload += "\"device_code\":\"" + String(TARGET_DEVICE_ID) + "\",";
    rawPayload += "\"raw_ecg\":" + String(valueToSend) + ",";
    rawPayload += "\"bpm\":" + String(bpmToSend) + ",";
    rawPayload += "\"sampling_rate\":" + String(ACQUISITION_RATE_HZ);
    rawPayload += "}";

    // 2. Siapkan Buffer Enkripsi ASCON-128
    size_t mlen = rawPayload.length();
    size_t clen = mlen + ASCON_128_TAG_LEN;
    uint8_t cipherBuffer[clen];

    uint8_t nonce[ASCON_128_NONCE_LEN];
    generateNonce(nonce, ASCON_128_NONCE_LEN);

    const uint8_t* ad = (const uint8_t*)"";
    size_t adlen = 0;

    // 3. Enkripsi AEAD
    ascon_aead_encrypt(
        cipherBuffer,
        (const uint8_t*)rawPayload.c_str(), mlen,
        ad, adlen,
        nonce,
        ASCON_KEY
    );

    // 4. Bungkus ke Format JSON Hex untuk Worker Python
    String encryptedJson = "{"
        "\"nonce\":\"" + bytesToHexString(nonce, ASCON_128_NONCE_LEN) + "\","
        "\"ciphertext\":\"" + bytesToHexString(cipherBuffer, clen) + "\""
    "}";

    publishMqtt(encryptedJson);
}

// ==================================================
// PROSES DATA DARI ARDUINO
// ==================================================
void addGraphSample(int adcValue) {
    latestAdc = adcValue;
    ecgDataReceived = true;
    lastEcgSampleMs = millis();

    int filtered = applyMovingAverage(adcValue);
    latestFiltered = filtered;

    processEcgSampleForBpm(filtered, millis());

    if (leadOff) {
        if (!baselineInitialized) { baseline = 512; baselineInitialized = true; }
        for (int i = 0; i < 127; i++) graphY[i] = graphY[i + 1];
        graphY[127] = 47;
    } else {
        if (!baselineInitialized) { baseline = (float)filtered; baselineInitialized = true; }
        else baseline += 0.001f * ((float)filtered - baseline); 

        float centered = (float)filtered - baseline;
        int y = 47 - (int)(centered * 0.8f);
        y = constrain(y, 10, 64);
        for (int i = 0; i < 127; i++) graphY[i] = graphY[i + 1];
        graphY[127] = y;
    }
}

void readArduinoSerial() {
    while (ECGSerial.available() > 0) {
        char c = (char)ECGSerial.read();
        if (c == '\n') {
            uartBuffer[uartIndex] = '\0';
            if (uartIndex > 0) {
                char* endPtr = nullptr;
                long value = strtol(uartBuffer, &endPtr, 10);
                if (endPtr != uartBuffer && *endPtr == '\0' && value >= 0 && value <= 1023) {
                    addGraphSample((int)value);
                }
            }
            uartIndex = 0;
        } else if (c == '\r') {
        } else if (c >= '0' && c <= '9') {
            if (uartIndex < sizeof(uartBuffer) - 1) uartBuffer[uartIndex++] = c;
            else uartIndex = 0;
        } else {
            uartIndex = 0;
        }
    }
}

// ==================================================
// OLED DISPLAY
// ==================================================
void drawOLED() {
    display.clearBuffer();
    display.setFont(u8g2_font_6x10_tr);
    display.drawStr(0, 9, "TELEHEALTH EKG");

    bool sampleFresh = ecgDataReceived && (millis() - lastEcgSampleMs < 500);
    if (leadOff) display.drawStr(95, 9, "NO SIG");
    else display.drawStr(sampleFresh ? 102 : 96, 9, sampleFresh ? "LIVE" : "WAIT");
    display.drawHLine(0, 12, 128);

    display.drawStr(0, 24, "HEART RATE:");
    char bpmText[16];
    if (leadOff || currentBpm == 0) strcpy(bpmText, "-- BPM");
    else snprintf(bpmText, sizeof(bpmText), "%d BPM", currentBpm);
    display.setFont(u8g2_font_7x14B_tr);
    display.drawStr(75, 24, bpmText);

    display.setFont(u8g2_font_6x10_tr);
    display.drawHLine(0, 28, 128);
    display.drawFrame(0, 30, 128, 34);

    if (ecgDataReceived) {
        for (int x = 1; x < 128; x++) {
            display.drawLine(x - 1, graphY[x - 1], x, graphY[x]);
        }
    }

    if (leadOff) display.drawStr(0, 58, "ELEKTRODA BELUM TERPASANG");
    else display.drawStr(0, 58, "MONITORING EKG AKTIF");

    display.sendBuffer();
}

// ==================================================
// WEBSOCKET EVENT
// ==================================================
void onWebSocketEvent(WStype_t type, uint8_t* payload, size_t length) {
    switch (type) {
        case WStype_CONNECTED:
            Serial.println("[WSS] Cloudflare TERHUBUNG");
            mqttReady = false;
            sendMqttConnect();
            break;
        case WStype_BIN:
            if (length >= 4 && payload[0] == 0x20) {
                if (payload[3] == 0) {
                    mqttReady = true;
                    lastPing = millis();
                    lastMqttPublish = 0;
                    Serial.println("[MQTT] CONNECT BERHASIL!");
                }
            }
            break;
        case WStype_DISCONNECTED:
            mqttReady = false;
            Serial.println("[WSS] Terputus; reconnect...");
            break;
        default: break;
    }
}

// ==================================================
// SETUP
// ==================================================
void setup() {
    Serial.begin(115200);
    delay(1000);

    ECGSerial.begin(38400, SERIAL_8N1, ECG_RX_PIN, ECG_TX_PIN);

    Wire.begin(OLED_SDA, OLED_SCL);
    display.begin();
    display.setContrast(255);
    for (int i = 0; i < 128; i++) graphY[i] = 47;
    for (int i = 0; i < MA_WINDOW; i++) maBuffer[i] = 512;

    pinMode(RESET_PIN, INPUT_PULLUP);

    Serial.println("\n==========================================");
    Serial.print("DEVICE ID    : "); Serial.println(TARGET_DEVICE_ID);
    Serial.print("ECG RATE     : "); Serial.print(ACQUISITION_RATE_HZ); Serial.println(" Hz");
    Serial.print("WIFI SSID    : "); Serial.println(WIFI_SSID);
    Serial.print("MQTT HOST    : "); Serial.println(MQTT_HOST);
    Serial.println("==========================================");

    Serial.println("[BOOT] Menghubungkan ke WiFi...");
    WiFi.mode(WIFI_STA);
    WiFi.begin(WIFI_SSID, WIFI_PASS);
    isConnectingWiFi = true;
    wifiConnectStart = millis();
    statusMsg = "Mencoba WiFi: " + String(WIFI_SSID);

    webSocket.onEvent(onWebSocketEvent);
    webSocket.setReconnectInterval(5000);
    webSocket.beginSSL(MQTT_HOST, MQTT_PORT, "/mqtt", nullptr, "mqtt");

    Serial.println("[SYSTEM] ESP32 siap");
}

// ==================================================
// LOOP
// ==================================================
void loop() {
    readArduinoSerial();

    static unsigned long lastOledUpdate = 0;
    if (millis() - lastOledUpdate >= 40) {
        lastOledUpdate = millis();
        drawOLED();
    }

    if (digitalRead(RESET_PIN) == LOW) {
        if (!resetBtnPressed) {
            resetBtnPressed = true;
            resetBtnDownStart = millis();
        } else if (millis() - resetBtnDownStart > 5000) {
            Serial.println("[RESET] Reset WiFi...");
            WiFi.disconnect(true, true);
            webSocket.disconnect();
            mqttReady = false; isConnectingWiFi = false;
            resetBtnPressed = false;
            delay(500); ESP.restart();
        }
    } else {
        resetBtnPressed = false;
    }

    if (WiFi.status() == WL_CONNECTED) {
        if (isConnectingWiFi) {
            isConnectingWiFi = false;
            Serial.print("[WIFI] IP: "); Serial.println(WiFi.localIP());
            statusMsg = "WiFi OK, membuka WSS...";
        }
        webSocket.loop();
    } else {
        if (!isConnectingWiFi) {
            Serial.println("[WIFI] Terputus; reconnect...");
            WiFi.disconnect();
            WiFi.begin(WIFI_SSID, WIFI_PASS);
            isConnectingWiFi = true; wifiConnectStart = millis();
            mqttReady = false;
        } else if (millis() - wifiConnectStart > WIFI_CONNECT_TIMEOUT) {
            Serial.println("[WIFI] Timeout, coba lagi...");
            WiFi.disconnect();
            WiFi.begin(WIFI_SSID, WIFI_PASS);
            wifiConnectStart = millis();
        }
    }

    publishLatestEcg();

    if (mqttReady && webSocket.isConnected() && millis() - lastPing >= 25000) {
        const uint8_t ping[] = {0xC0, 0x00};
        webSocket.sendBIN(ping, 2);
        lastPing = millis();
    }
}



/* ============================================================
 *  ascon128.h - ASCON-128 AEAD Official Reference (FIXED b=6)
 * ============================================================
 */
#ifndef ASCON128_H
#define ASCON128_H

#include <stdint.h>
#include <string.h>

#define ASCON_128_KEY_LEN   16
#define ASCON_128_NONCE_LEN 16
#define ASCON_128_TAG_LEN   16
#define ASCON_128_RATE      8

static inline uint64_t ROR(uint64_t x, int n) {
    return (x >> n) | (x << (64 - n));
}

static inline uint64_t LOAD64(const uint8_t *p) {
    return ((uint64_t)p[0] << 56) | ((uint64_t)p[1] << 48) |
           ((uint64_t)p[2] << 40) | ((uint64_t)p[3] << 32) |
           ((uint64_t)p[4] << 24) | ((uint64_t)p[5] << 16) |
           ((uint64_t)p[6] << 8)  | (uint64_t)p[7];
}

static inline void STORE64(uint8_t *p, uint64_t x) {
    p[0] = (uint8_t)(x >> 56); p[1] = (uint8_t)(x >> 48);
    p[2] = (uint8_t)(x >> 40); p[3] = (uint8_t)(x >> 32);
    p[4] = (uint8_t)(x >> 24); p[5] = (uint8_t)(x >> 16);
    p[6] = (uint8_t)(x >> 8);  p[7] = (uint8_t)(x);
}

static const uint8_t RC[12] = {
    0xf0, 0xe1, 0xd2, 0xc3, 0xb4, 0xa5, 0x96, 0x87, 0x78, 0x69, 0x5a, 0x4b
};

static inline void ROUND(uint64_t *x, uint8_t rc) {
    uint64_t t0, t1, t2, t3, t4;
    x[2] ^= rc;
    x[0] ^= x[4]; x[4] ^= x[3]; x[2] ^= x[1];
    t0 = x[0]; t1 = x[1]; t2 = x[2]; t3 = x[3]; t4 = x[4];
    t0 = ~t0; t1 = ~t1; t2 = ~t2; t3 = ~t3; t4 = ~t4;
    t0 &= x[1]; t1 &= x[2]; t2 &= x[3]; t3 &= x[4]; t4 &= x[0];
    x[0] ^= t1; x[1] ^= t2; x[2] ^= t3; x[3] ^= t4; x[4] ^= t0;
    x[1] ^= x[0]; x[0] ^= x[4]; x[3] ^= x[2]; x[2] = ~x[2];
    x[0] ^= ROR(x[0], 19) ^ ROR(x[0], 28);
    x[1] ^= ROR(x[1], 61) ^ ROR(x[1], 39);
    x[2] ^= ROR(x[2], 1)  ^ ROR(x[2], 6);
    x[3] ^= ROR(x[3], 10) ^ ROR(x[3], 17);
    x[4] ^= ROR(x[4], 7)  ^ ROR(x[4], 41);
}

static void P(uint64_t *x, int rounds) {
    for (int i = 12 - rounds; i < 12; i++) {
        ROUND(x, RC[i]);
    }
}

static int ascon_aead_encrypt(
    uint8_t *c,
    const uint8_t *m, size_t mlen,
    const uint8_t *ad, size_t adlen,
    const uint8_t *npub,
    const uint8_t *k
) {
    uint64_t x[5];
    uint8_t t[8] = {0};
    uint64_t K0 = LOAD64(k);
    uint64_t K1 = LOAD64(k + 8);
    uint64_t N0 = LOAD64(npub);
    uint64_t N1 = LOAD64(npub + 8);

    /* 1. Initialization (a = 12 rounds) */
    x[0] = 0x80400c0600000000ULL;
    x[1] = K0; x[2] = K1; x[3] = N0; x[4] = N1;
    P(x, 12);
    x[3] ^= K0; x[4] ^= K1;

    /* 2. Process Associated Data (b = 6 rounds untuk ASCON-128) */
    if (adlen > 0) {
        while (adlen >= ASCON_128_RATE) {
            x[0] ^= LOAD64(ad);
            P(x, 6);
            ad += ASCON_128_RATE;
            adlen -= ASCON_128_RATE;
        }
        memset(t, 0, 8);
        memcpy(t, ad, adlen);
        t[adlen] = 0x80;
        x[0] ^= LOAD64(t);
        P(x, 6);
    }
    x[4] ^= 1ULL;

    /* 3. Process Plaintext (b = 6 rounds untuk ASCON-128) */
    size_t i = 0;
    while (i + ASCON_128_RATE <= mlen) {
        uint64_t m_block = LOAD64(m + i);
        x[0] ^= m_block;
        STORE64(c + i, x[0]);
        P(x, 6);
        i += ASCON_128_RATE;
    }

    memset(t, 0, 8);
    size_t rem = mlen - i;
    if (rem > 0) {
        memcpy(t, m + i, rem);
    }
    t[rem] = 0x80;
    x[0] ^= LOAD64(t);

    if (rem > 0) {
        uint8_t out[8];
        STORE64(out, x[0]);
        memcpy(c + i, out, rem);
    }

    /* 4. Finalization (a = 12 rounds) */
    x[1] ^= K0; x[2] ^= K1;
    P(x, 12);
    x[3] ^= K0; x[4] ^= K1;

    /* 5. Store Tag tepat di ujung ciphertext */
    STORE64(c + mlen, x[3]);
    STORE64(c + mlen + 8, x[4]);

    return 0;
}

#endif /* ASCON128_H */
