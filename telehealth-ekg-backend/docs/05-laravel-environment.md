# Pemeriksaan Aplikasi dan Environment Laravel

## Tujuan
Memastikan direktori proyek, versi Laravel, konfigurasi environment, dan dependency aplikasi dapat diperiksa.

## Perintah pemeriksaan
```bash
cd /var/www/telehealth-ekg
pwd
ls -lah
ls -lah artisan composer.json composer.lock
php artisan --version
php artisan about
php artisan env
composer --version
composer check-platform-reqs
composer validate
```

## Hasil yang tercatat
- Laravel `12.70.1`.
- PHP `8.5.4`.
- Composer `2.9.5`.
- Environment `Production`.
- Debug `OFF`.
- Timezone `Asia/Jakarta`.
- Locale `id`.
- Database driver `MySQL`.

## Yang harus diperiksa
Pastikan direktori sesuai, Artisan dapat dijalankan, environment production, dan dependency platform cocok.

## Bukti screenshot
Simpan di `screenshots/05-laravel-environment/`.

## Keamanan
Jangan menjalankan `cat .env` atau mengunggah isi `.env`. File tersebut berisi kredensial.
