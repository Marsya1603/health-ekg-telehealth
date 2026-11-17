# Pemeriksaan Status Service

## Tujuan
Memastikan layanan utama yang digunakan aplikasi berjalan dan mengetahui apakah service diaktifkan saat boot.

## Perintah pemeriksaan
```bash
sudo systemctl status nginx --no-pager
sudo systemctl status php8.5-fpm --no-pager
sudo systemctl status mysql --no-pager
sudo systemctl status cloudflared --no-pager

systemctl is-active nginx
systemctl is-active php8.5-fpm
systemctl is-active mysql
systemctl is-active cloudflared

systemctl is-enabled nginx
systemctl is-enabled php8.5-fpm
systemctl is-enabled mysql
systemctl is-enabled cloudflared
```

Untuk menemukan nama service PHP-FPM:
```bash
systemctl list-units --type=service --all | grep -E 'php.*fpm'
```

## Yang harus diperiksa
Status `active` menunjukkan service sedang berjalan. Status `enabled` menunjukkan service dikonfigurasi untuk aktif saat boot.

## Bukti screenshot
Simpan hasil di `screenshots/02-status-service/`.

## Catatan
Nama service PHP-FPM dapat berbeda jika versi PHP berubah.
