# Validasi Akhir

## Tujuan
Mengumpulkan hasil akhir pemeriksaan service, konfigurasi, Laravel, database, dan akses HTTP.

## Service
```bash
systemctl is-active nginx
systemctl is-active php8.5-fpm
systemctl is-active mysql
systemctl is-active cloudflared
```

## Nginx
```bash
sudo nginx -t
```

## Laravel
```bash
cd /var/www/telehealth-ekg
php artisan about
php artisan route:list --except-vendor
php artisan route:list --path=api/v1 -v
php artisan migrate:status
php artisan db:show
```

## HTTP
```bash
curl -sS -o /dev/null -w 'LOCAL: %{http_code}\n' \
  http://127.0.0.1:8080/login

curl -sS -o /dev/null -w 'PUBLIC: %{http_code}\n' \
  https://ekgtelehealth.my.id/login
```

## Hasil pemeriksaan proyek yang telah tercatat
- Laravel 12.70.1.
- PHP 8.5.4.
- Nginx 1.28.3.
- MySQL 8.4.13.
- Cloudflared 2026.11.0.
- 77 route.
- 24 tabel, ukuran sekitar 72,05 MB.
- Nginx, PHP-FPM, Cloudflare Tunnel aktif.
- Halaman login lokal dan publik HTTP 200.

## Batasan kesimpulan
Hasil ini membuktikan service dan halaman login dapat diperiksa/diakses. Tidak otomatis membuktikan seluruh fitur fungsional, pengiriman data perangkat, keamanan API, atau ketepatan sinyal EKG. Uji fungsional tersebut harus memiliki bukti tersendiri.

## Bukti screenshot
Simpan di `screenshots/14-final-validation/`.
