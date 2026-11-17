# Pemeriksaan PHP dan PHP-FPM

## Tujuan
Memeriksa versi PHP, konfigurasi CLI, ekstensi, service PHP-FPM, dan socket yang digunakan Nginx.

## Perintah pemeriksaan
```bash
php -v
php --ini
php -m | sort

systemctl list-units --type=service --all | grep -E 'php.*fpm'
sudo systemctl status php8.5-fpm --no-pager
ls -lah /run/php/

test -S /run/php/php8.5-fpm.sock \
  && echo "PHP-FPM socket ditemukan" \
  || echo "PHP-FPM socket tidak ditemukan"
```

## Hasil yang tercatat
PHP `8.5.4`, PHP-FPM service `php8.5-fpm`, dan socket `/run/php/php8.5-fpm.sock`.

## Yang harus diperiksa
Pastikan versi PHP sesuai dengan runtime aplikasi dan socket yang dituju Nginx benar-benar tersedia.

## Bukti screenshot
Simpan di `screenshots/04-php-fpm/`.
