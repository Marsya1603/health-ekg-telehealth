# PANDUAN INSTALASI & MIGRASI — HealthEKG Telehealth

Panduan lengkap dari nol sampai aplikasi berjalan di **laptop/PC Ubuntu**
(dan server fisik Ubuntu dengan langkah yang sama). Ikuti urutannya.

> Prinsip penting: **jangan pernah** menjalankan `migrate:fresh` pada database
> yang sudah berisi data. Migration di proyek ini bersifat additive dan aman
> dijalankan pada database baru maupun database proyek yang sudah ada.

---

## 0. Prasyarat

| Komponen | Versi minimum |
|----------|---------------|
| PHP | 8.2 |
| Composer | 2.x |
| MySQL / MariaDB | 8.0 / 10.4 |
| Python | 3.10 (untuk worker) |
| Mosquitto (broker MQTT) | 2.x (opsional dulu, wajib saat uji perangkat) |

Ekstensi PHP yang dibutuhkan: `mbstring`, `pdo_mysql`, `openssl`, `ctype`,
`json`, `bcmath`, `tokenizer`, `xml`, `fileinfo`, `curl`.

Ubuntu:

```bash
sudo apt update
sudo apt install -y php8.3 php8.3-cli php8.3-mbstring php8.3-xml php8.3-curl \
    php8.3-mysql php8.3-bcmath php8.3-zip unzip mysql-server composer \
    python3 python3-venv python3-pip mosquitto mosquitto-clients
```

---

## 1. Ekstrak Proyek

```bash
unzip health-ekg-telehealth.zip -d /path/kerja
cd /path/kerja/health-ekg-telehealth
```

(Sebagian besar perintah di bawah dijalankan dari folder proyek ini.)

## 2. Pasang Dependensi PHP

```bash
composer install
```

> Tidak butuh `npm`/`yarn` — CSS, JS, dan Chart.js sudah dimuat lokal
> di `public/assets/`.

## 3. Buat Database

```bash
sudo mysql
```
```sql
CREATE DATABASE healthekg CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'healthekg'@'localhost' IDENTIFIED BY 'GANTI_dengan_password_kuat';
GRANT ALL PRIVILEGES ON healthekg.* TO 'healthekg'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## 4. Konfigurasi Environment

```bash
cp .env.example .env
php artisan key:generate
nano .env
```

Bagian yang wajib disesuaikan:

```env
APP_URL=http://localhost:8000        # atau IP/domain server
DB_DATABASE=healthekg
DB_USERNAME=healthekg
DB_PASSWORD=GANTI_dengan_password_kuat
```

Variabel lain (ambang heartbeat, izin ekspor, dsb.) sudah bernilai wajar —
lihat komentar di `.env`.

## 5. MIGRASI DATABASE

```bash
php artisan migrate
```

Yang dibuat: `users` (+role), `sessions`, `cache`, `doctors`, `patients`,
`devices`, `ekg_sessions`, `ekg_packets`, `ekg_samples`, `transmission_logs`,
`security_logs`, `system_events`, `service_status`, `network_metrics`,
`examination_notes`, `audit_logs`, `app_notifications`.

> Tidak ada seeder data demo — prinsip PRD: dashboard mulai dari data kosong
> yang jujur ("Belum ada data"), bukan data buatan.

## 6. Buat Akun Administrator Pertama

```bash
php artisan app:create-admin
```

Anda akan diminta nama, email, password awal (ditik rahasia). Password awal
**wajib diganti saat login pertama** (kebijakan bawaan).

Bila ingin akun Network Engineer:

```bash
php artisan app:create-admin --role=network_engineer
```

## 7. Jalankan Aplikasi Web

**Cepat (pengembangan di laptop):**

```bash
php artisan serve            # buka http://localhost:8000
```

**Produksi (server fisik):** gunakan Nginx + PHP-FPM — contoh konfigurasi ada
di lampiran A di bawah. Pastikan folder berikut bisa ditulis PHP:
`storage/` dan `bootstrap/cache`.

## 8. Jalankan Scheduler (server health + deteksi jeda data)

Pengembangan:

```bash
php artisan schedule:work
```

Produksi — tambahkan ke cron (`crontab -e`):

```cron
* * * * * cd /path/kerja/health-ekg-telehealth && php artisan schedule:run >> /dev/null 2>&1
```

Setelah ±1 menit, halaman **Server Health** (role Network Engineer) terisi
metrik CPU/RAM/disk aktual.

## 9. Konfigurasi Broker MQTT (Mosquitto)

```bash
sudo mosquitto_passwd -c /etc/mosquitto/passwd healthekg_worker   # kredensial worker
sudo nano /etc/mosquitto/conf.d/healthekg.conf
```
```
listener 1883 0.0.0.0
allow_anonymous false
password_file /etc/mosquitto/passwd
```
```bash
sudo systemctl restart mosquitto
```

Buat juga kredensial **per perangkat** (prinsip least privilege) bila broker
mendukung ACL. Kredensial ini tidak pernah dimasukkan ke aplikasi web.

## 10. Jalankan Worker Python (MQTT + ASCON-128)

```bash
cd worker
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt

# Generate kunci ASCON-128 (16 byte):
openssl rand -hex 16

cp config.example.ini config.ini
chmod 600 config.ini
nano config.ini    # isi broker, database, dan ascon_key_hex

python3 mqtt_worker.py
```

Cek: dashboard (role Network Engineer → Network Operations) harus menampilkan
worker **aktif** dengan heartbeat baru.

Produksi: jadikan service systemd — unit contoh ada di `worker/README.md`.

## 11. Uji End-to-End (tanpa firmware)

1. Login sebagai admin → **Manajemen Perangkat** → Registrasi perangkat
   `DEV-TEST01`.
2. Buat pasien & dokter; hubungkan pasien dengan perangkat.
3. Kirim frame uji (kunci sama dengan worker):
   ```bash
   cd worker && source venv/bin/activate
   python3 ascon_tool.py encrypt --key "$KEY" --device DEV-TEST01 \
       --session "$(uuidgen)" --samples samples.csv --publish
   ```
4. Grafik EKG muncul di monitoring (dokter) dan riwayat (admin).
5. Metrik QoS terisi di dashboard Network Engineer.

## 12. Ringkasan Alur Harian

| Layanan | Perintah |
|---------|----------|
| Web | `php artisan serve` (dev) / Nginx+PHP-FPM (prod) |
| Scheduler | `php artisan schedule:work` (dev) / cron (prod) |
| Worker | `cd worker && source venv/bin/activate && python3 mqtt_worker.py` |

---

## Lampiran A — Nginx + PHP-FPM (produksi)

```nginx
server {
    listen 80;
    server_name healthekg.local;          # ganti domain/IP
    root /path/kerja/health-ekg-telehealth/public;
    index index.php;

    client_max_body_size 10M;

    location / { try_files $uri $uri/ /index.php?$query_string; }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.(env|git) { deny all; }
}
```

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo ln -s /etc/nginx/sites-available/healthekg /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

Aktifkan HTTPS (certbot) sebelum digunakan dengan data nyata.

## Lampiran B — Troubleshooting

| Gejala | Penyebab umum / solusi |
|--------|------------------------|
| `500` saat dibuka | `.env` belum ada/key:generate; izin `storage`; cek `storage/logs/laravel.log` |
| Login gagal terus | Pastikan tabel `sessions` ada (migration jalan) |
| Status DB merah | Cek `DB_*` di `.env`; uji `php artisan migrate:status` |
| Worker "unknown" | Worker tidak jalan / `config.ini` salah / DB belum sama |
| Frame ditolak `tag_invalid` | Kunci ASCON worker ≠ perangkat/ascon_tool |
| Grafik kosong padahal worker jalan | Perangkat belum diregistrasi / nonaktif di dashboard |
| Server Health kosong | Scheduler belum jalan (lihat langkah 8) |
