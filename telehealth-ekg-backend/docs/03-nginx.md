# Pemeriksaan Nginx

## Tujuan
Memeriksa versi, status service, site yang aktif, konfigurasi virtual host, dan port listen Nginx.

## Perintah pemeriksaan
```bash
nginx -v
sudo systemctl status nginx --no-pager
systemctl is-active nginx

ls -lah /etc/nginx/sites-available/
ls -lah /etc/nginx/sites-enabled/

sudo nginx -t
sudo nginx -T 2>&1 | less
sudo ss -ltnp | grep -E ':8080|:80|:443'
```

Melihat konfigurasi site proyek:
```bash
sudo sed -n '1,240p' /etc/nginx/sites-available/telehealth-ekg
```

## Konfigurasi yang tercatat
- Site: `telehealth-ekg`.
- Listener: `127.0.0.1:8080`.
- Document root: `/var/www/telehealth-ekg/public`.
- PHP-FPM socket: `/run/php/php8.5-fpm.sock`.

## Yang harus diperiksa
Pastikan `nginx -t` menyatakan syntax OK dan configuration test successful.

## Bukti screenshot
Simpan di `screenshots/03-nginx/`.

## Catatan
`nginx -t` hanya memvalidasi sintaks. Reload merupakan tindakan perubahan dan hanya dilakukan setelah konfigurasi valid.
