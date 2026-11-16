import json
import ascon
import requests
import paho.mqtt.client as mqtt

# ==================================================
# 1. KONFIGURASI BROKER & API LOKAL
# ==================================================
MQTT_BROKER = "127.0.0.1"
MQTT_PORT = 1883
MQTT_TOPIC = "telehealth/test"

API_ENDPOINT = "http://127.0.0.1:8080/api/v1/ekg/send"

# ==================================================
# 2. KONFIGURASI KRIPTOGRAFI ASCON-128
# ==================================================
# Kunci 16 Byte (128-bit) - Sama persis dengan ESP32
ASCON_KEY = bytes([
    0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07,
    0x08, 0x09, 0x0A, 0x0B, 0x0C, 0x0D, 0x0E, 0x0F
])
ASSOCIATED_DATA = b""

# ==================================================
# 3. CALLBACK MQTT (PAHO MQTT v2)
# ==================================================
def on_connect(client, userdata, flags, reason_code, properties):
    if reason_code == 0:
        print(f"\n[CONNECTED] Terhubung ke Broker ({MQTT_BROKER}:{MQTT_PORT})")
        client.subscribe(MQTT_TOPIC)
        print(f"[SUBSCRIBED] Memantau topic '{MQTT_TOPIC}'...\n")
    else:
        print(f"[ERROR] Gagal terhubung ke Broker, kode: {reason_code}")

def on_message(client, userdata, msg):
    raw_msg = msg.payload.decode("utf-8", errors="ignore")
    print(f"[PESAN MASUK] {raw_msg}")

    try:
        # 1. Parse JSON terenkripsi dari ESP32
        encrypted_pkg = json.loads(raw_msg)
        nonce_hex = encrypted_pkg.get("nonce")
        cipher_hex = encrypted_pkg.get("ciphertext")

        if not nonce_hex or not cipher_hex:
            print("[ERROR] Format JSON tidak memiliki 'nonce' atau 'ciphertext'.\n")
            return

        nonce = bytes.fromhex(nonce_hex)
        ciphertext = bytes.fromhex(cipher_hex)

        # 2. Dekripsi & Verifikasi Tag ASCON-128
        plaintext_bytes = ascon.decrypt(
            ASCON_KEY,
            nonce,
            ASSOCIATED_DATA,
            ciphertext,
            variant="Ascon-128"
        )

        if plaintext_bytes is None:
            print("[ERROR] Dekripsi Gagal! Tag Autentikasi tidak valid.\n")
            return

        # 3. Parse JSON hasil dekripsi
        ecg_data = json.loads(plaintext_bytes.decode("utf-8"))

        print("==========================================")
        print(" [DEKRIPSI ASCON-128 BERHASIL]")
        print(f" Device Code   : {ecg_data.get('device_code')}")
        print(f" Raw ECG       : {ecg_data.get('raw_ecg')}")
        print(f" BPM           : {ecg_data.get('bpm')}")
        print(f" Sampling Rate : {ecg_data.get('sampling_rate')} Hz")
        print("==========================================")

        # 4. Teruskan data ke backend web (opsional, tidak menghentikan worker jika API mati)
        try:
            resp = requests.post(API_ENDPOINT, json=ecg_data, timeout=3)
            print(f"[API FORWARD] Status HTTP: {resp.status_code}\n")
        except requests.RequestException as api_err:
            print(f"[API FORWARD] Dilewati ({api_err})\n")

    except json.JSONDecodeError:
        print("[ERROR] Payload bukan format JSON yang valid.\n")
    except Exception as e:
        print(f"[ERROR] Terjadi kesalahan: {e}\n")

# ==================================================
# 4. MAIN LOOP
# ==================================================
def main():
    print("[SYSTEM] Memulai Python Worker ASCON-128...")
    client = mqtt.Client(mqtt.CallbackAPIVersion.VERSION2, client_id="PythonWorkerASCON")
    client.on_connect = on_connect
    client.on_message = on_message

    client.connect(MQTT_BROKER, MQTT_PORT, keepalive=60)
    client.loop_forever()

if __name__ == "__main__":
    main()
