# Pemeriksaan Autentikasi dan Middleware

## Tujuan
Memeriksa proses login/logout, pemeriksaan akun aktif, pembatasan role, dan kewajiban penggantian password.

## Perintah pemeriksaan
```bash
cd /var/www/telehealth-ekg

sed -n '1,320p' app/Http/Controllers/AuthController.php

sed -n '1,240p' app/Http/Middleware/EnsureRole.php
sed -n '1,240p' app/Http/Middleware/EnsureActive.php
sed -n '1,260p' app/Http/Middleware/EnsurePasswordChanged.php

grep -nE 'role|active|password|withMiddleware|alias' bootstrap/app.php

php artisan route:list --except-vendor
```

## Yang harus diperiksa
- Login menggunakan validasi dan `Auth::attempt`.
- Session diregenerasi setelah login.
- Akun nonaktif ditolak.
- Logout menginvalidasi session.
- Role yang tidak sesuai ditolak dengan HTTP 403.
- Penggantian password awal mengikuti konfigurasi aplikasi.

## Bukti screenshot
Simpan di `screenshots/09-auth-middleware/`.

## Keamanan
Jangan menampilkan password, session cookie, token CSRF, atau kredensial pengguna di screenshot.
