# Telehealth EKG Monitoring System with ASCON-128 AEAD

Sistem pemantauan sinyal Elektrokardiogram (EKG) *real-time* berbasis IoT dengan pengamanan *end-to-end* menggunakan algoritma kriptografi ringan **ASCON-128 AEAD**.

## Struktur Direktori
- `health-ekg-telehealth/` — Aplikasi web dashboard monitoring pasien & dokter.
- `firmware/esp32_ekg_ascon/` — Kode mikrokontroler ESP32 (akuisisi sinyal 250 Hz, filter Moving Average, deteksi R-Peak BPM, tampilan OLED, dan enkripsi ASCON-128).
- `server/worker/` — Python MQTT Worker untuk dekripsi ASCON-128, validasi *Authentication Tag*, dan penerusan data ke REST API.
- `server/config/` — Konfigurasi *reverse proxy* Nginx WebSocket (`/mqtt` ke port `9001`).
- `dokumentasi/` — Laporan dan dokumentasi pengujian sistem.

## Arsitektur Alur Data
`Arduino (Sensor AD8232)` ➔ `ESP32 (Enkripsi ASCON-128)` ➔ `Cloudflare Tunnel (WSS 443)` ➔ `Nginx (8080/mqtt)` ➔ `Mosquitto Broker (9001/1883)` ➔ `Python Worker (Dekripsi)` ➔ `Web Dashboard`
