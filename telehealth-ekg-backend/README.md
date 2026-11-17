# Telehealth EKG — Dokumentasi per Langkah

Setiap langkah dibuat sebagai file Markdown tersendiri di folder `docs/`. Dengan begitu, satu tahap dapat dibuka, diperiksa, diberi screenshot, dan diperbarui tanpa mengedit satu dokumen panjang.

## Struktur

```text
telehealth-ekg-per-langkah/
├── README.md
├── .gitignore
├── SECURITY.md
├── docs/
│   ├── 01-identifikasi-server.md
│   ├── 02-status-service.md
│   ├── 03-nginx.md
│   ├── 04-php-fpm.md
│   ├── 05-laravel-environment.md
│   ├── 06-laravel-structure-routes.md
│   ├── 07-database.md
│   ├── 08-api-routes-controller.md
│   ├── 09-auth-middleware.md
│   ├── 10-dashboard.md
│   ├── 11-monitoring-ekg.md
│   ├── 12-grafik-javascript.md
│   ├── 13-cloudflare-deployment.md
│   └── 14-final-validation.md
├── scripts/
│   ├── check-server.sh
│   └── check-laravel.sh
└── screenshots/
    ├── 01-identifikasi-server/
    ├── 02-status-service/
    └── ... (folder per langkah)
```

## Cara memakai
1. Buka `docs/01-identifikasi-server.md`.
2. Jalankan perintah pemeriksaan pada server.
3. Cocokkan hasil dengan bagian "Yang harus diperiksa".
4. Ambil screenshot terminal atau browser.
5. Simpan screenshot pada folder yang sesuai.
6. Lanjutkan ke file berikutnya sampai `14-final-validation.md`.

## Pemeriksaan ringkas otomatis

```bash
chmod +x scripts/*.sh
./scripts/check-server.sh
APP_DIR=/var/www/telehealth-ekg ./scripts/check-laravel.sh
```

## Push ke GitHub

```bash
git init
git add .
git commit -m "Add step-by-step Telehealth EKG documentation"
git branch -M main
git remote add origin https://github.com/USERNAME/REPOSITORY.git
git push -u origin main
```

Ganti `USERNAME` dan `REPOSITORY` sesuai repository GitHub kamu.

> Cakupan dokumen adalah Backend Laravel dan Infrastruktur Server. MQTT dan fitur Network Engineer tidak dimasukkan.
