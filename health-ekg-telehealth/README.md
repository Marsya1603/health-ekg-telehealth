# HealthEKG Telehealth

Aplikasi web pemantauan **Elektrokardiogram (EKG) jarak jauh berbasis IoT**
sesuai PRD: ESP32 + Olimex SHIELD-EKG-EMG → MQTT over TCP/IP → Worker Python
(ASCON-128) → MySQL → Dashboard Laravel.

**Bahasa antarmuka:** Indonesia · **Deployment:** server fisik Ubuntu ·
**Framework:** Laravel 12 (PHP 8.2+) ·
**Prinsip:** tanpa data demo/dummy/simulasi — semua angka dari sumber aktual;
keadaan kosong ditampilkan apa adanya.

## Empat Role

| Role | Fokus | Menu utama |
|------|-------|-----------|
| Admin Puskesmas | Administrasi | Dashboard, pasien, dokter, pengguna, perangkat, sesi, riwayat EKG, laporan |
| Dokter | Pemantauan klinis | Dashboard, pasien saya, monitoring EKG live, riwayat, catatan |
| Pasien | Informasi mandiri | Dashboard, EKG saya, riwayat, info perangkat |
| Network Engineer | Infrastruktur & QoS | NOC dashboard, connectivity, MQTT, QoS, payload analysis, ASCON-128, server health, logs, events |

Hak akses diterapkan di **backend** (middleware role + filter query per
controller), bukan sekadar menyembunyikan menu. Semua tindakan sensitif
tercatat pada audit log.

## Keamanan

- Autentikasi sesi Laravel; password hash; rate limit login; CSRF; wajib ganti
  password awal.
- ASCON-128 (authenticated encryption) pada payload EKG: verifikasi tag sebelum
  data dipakai, deteksi pemakaian ulang nonce, kunci hanya di worker/perangkat.
- Kredensial broker tidak pernah masuk aplikasi web/frontend/log.
- Network Engineer hanya melihat metadata teknis, bukan isi data kesehatan.

## Struktur Proyek

```
health-ekg-telehealth/
├── app/                # Models, Controllers (4 role), Middleware, Services, Commands
├── bootstrap/          # Bootstrap Laravel 12
├── config/             # app, database, healthekg (khusus aplikasi)
├── database/migrations # 17 migration (additive, aman dijalankan)
├── docs/               # INSTALL.md (langkah migrate), API.md, TESTING.md, PAYLOAD-FORMAT.md
├── public/assets/      # CSS, JS, Chart.js (lokal, tanpa CDN)
├── resources/views     # Blade: layouts, komponen, 4 modul role
├── routes/             # web.php, console.php (scheduler)
└── worker/             # mqtt_worker.py (MQTT+ASCON), ascon_tool.py (uji), README
```

## Mulai Cepat

Panduan lengkap (instalasi, migrate, akun awal, worker, broker, produksi):

```bash
docs/INSTALL.md        # ← mulai dari sini
```

Ringkas:

```bash
composer install
cp .env.example .env && php artisan key:generate   # isi DB di .env
php artisan migrate                                # MIGRASI database
php artisan app:create-admin                       # akun admin pertama
php artisan serve                                  # jalankan web
```

Lalu (opsional saat uji): scheduler + Mosquitto + worker Python
(`worker/README.md`).

## Dokumentasi

| Dokumen | Isi |
|---------|-----|
| `docs/INSTALL.md` | Langkah instalasi & migrasi lengkap + troubleshooting |
| `docs/API.md` | Endpoint JSON & model keamanan API |
| `docs/PAYLOAD-FORMAT.md` | Kontrak frame MQTT + ASCON-128 untuk firmware ESP32 |
| `docs/TESTING.md` | Test otomatis + checklist uji manual end-to-end |
| `worker/README.md` | Worker Python, systemd, uji pipeline |

## Catatan Transparansi

- BPM/diagnosis otomatis **belum** diimplementasikan (tidak ditampilkan nilai buatan).
- Header MQTT/TCP/IP/TLS tidak diukur di server — observasi lapisan itu
  memerlukan Wireshark di jaringan perangkat; ukuran lapisan aplikasi terukur penuh.
- Real-time memakai polling API (sesuai opsi PRD); WebSocket dapat ditambah nanti.
