# Pemeriksaan Database dan Migration

## Tujuan
Memeriksa service MySQL, koneksi Laravel, struktur tabel utama, dan status migration.

## Perintah pemeriksaan
```bash
sudo systemctl status mysql --no-pager
mysql --version

cd /var/www/telehealth-ekg
php artisan db:show
php artisan migrate:status

php artisan db:table devices
php artisan db:table ekg_sessions
php artisan db:table ekg_packets
php artisan db:table ekg_samples
```

## Hasil yang tercatat
- MySQL `8.4.13`.
- Database `telehealth_ekg`.
- Host `127.0.0.1`, port `3306`.
- 24 tabel.
- Ukuran sekitar `72.05 MB`.
- 19 migration berstatus `Ran` pada pemeriksaan sebelumnya.

## Yang harus diperiksa
Cocokkan nama database, jumlah tabel, struktur kolom, indeks, foreign key, dan status migration.

## Bukti screenshot
Simpan di `screenshots/07-database/`.

## Peringatan
Jangan menjalankan `php artisan migrate:fresh` pada database produksi karena dapat menghapus tabel dan data.
