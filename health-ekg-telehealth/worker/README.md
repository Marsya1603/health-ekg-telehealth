# HealthEKG Worker — Pemrosesan Payload EKG (MQTT + ASCON-128)

Worker Python ini adalah layanan terpisah dari Laravel. Ia berlangganan topic MQTT,
memvalidasi format frame, mendekripsi **ASCON-128** (autentikasi tag wajib), lalu
menyimpan sampel EKG valid ke database MySQL yang sama dengan Laravel.

```
ESP32 + Olimex SHIELD-EKG-EMG
        │  (frame terenkripsi ASCON-128)
        ▼
Broker MQTT (Mosquitto)  ◄── worker/mqtt_worker.py (subscribe)
                                 │ validasi + dekripsi + verifikasi tag
                                 ▼
                          MySQL (ekg_sessions, ekg_packets, ekg_samples,
                                 transmission_logs, security_logs, dst.)
                                 ▲
                          Laravel dashboard (membaca DB yang sama)
```

## Instalasi

```bash
cd worker
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt

cp config.example.ini config.ini
chmod 600 config.ini
# Edit config.ini: broker, database, dan kunci ASCON.
# Generate kunci: openssl rand -hex 16
```

Jalankan:

```bash
python3 mqtt_worker.py
```

## Menjalankan sebagai service (systemd)

Simpan `/etc/systemd/system/healthekg-worker.service`:

```ini
[Unit]
Description=HealthEKG MQTT Worker
After=network.target mysql.service mosquitto.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/health-ekg-telehealth/worker
ExecStart=/var/www/health-ekg-telehealth/worker/venv/bin/python mqtt_worker.py
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now healthekg-worker
sudo journalctl -u healthekg-worker -f
```

## Menguji pipeline tanpa firmware

Gunakan `ascon_tool.py` (lihat `docs/PAYLOAD-FORMAT.md`):

```bash
# Buat device DEV-TEST01 di dashboard (Admin → Manajemen Perangkat) lalu:
KEY=$(openssl rand -hex 16)   # samakan dengan config.ini worker & perangkat
python3 ascon_tool.py encrypt --key "$KEY" --device DEV-TEST01 \
    --session "$(uuidgen)" --samples samples.csv --publish --host 127.0.0.1
```

Worker akan menerima, mendekripsi, dan menyimpan sampel — kemudian data muncul
di dashboard (Admin, Dokter, dan Network Engineer) sebagai data aktual.

## Keamanan

- Kredensial broker hanya ada di `config.ini` (0600) — tidak pernah masuk aplikasi web.
- Kunci ASCON-128 16 byte; nonce baru acak per paket; pemakaian ulang nonce dideteksi dan ditolak.
- Payload yang gagal verifikasi tag tidak pernah disimpan sebagai sampel valid.
- Log hanya memuat metadata teknis (device, paket, ukuran, error code) — tidak ada isi EKG/kredensial.
