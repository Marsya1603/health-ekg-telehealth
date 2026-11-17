# Struktur Laravel dan Route

## Tujuan
Memeriksa struktur direktori backend dan route web/API yang terdaftar.

## Perintah pemeriksaan
```bash
cd /var/www/telehealth-ekg

ls -lah app
ls -lah app/Http/Controllers
ls -lah app/Http/Middleware
ls -lah app/Models
ls -lah routes
ls -lah resources/views

php artisan route:list
php artisan route:list --except-vendor
php artisan route:list --path=api/v1 -v
```

## Hasil yang tercatat
Pemeriksaan sebelumnya menunjukkan 77 route pada `route:list --except-vendor`.

## Yang harus diperiksa
Pastikan route API EKG dan route dashboard terdaftar serta middleware yang tampil sesuai implementasi aktual.

## Bukti screenshot
Simpan di `screenshots/06-laravel-structure-routes/`.
