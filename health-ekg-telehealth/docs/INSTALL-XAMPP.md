# PANDUAN SETUP DENGAN XAMPP — HealthEKG Telehealth

Panduan ini khusus untuk menjalankan proyek di laptop dengan **XAMPP**
(Windows/Mac/Linux). Prinsipnya sama dengan `docs/INSTALL.md` (versi Ubuntu),
tetapi bagian PHP/MySQL digantikan oleh XAMPP, tanpa perlu Nginx.

> Syarat versi: proyek memakai **Laravel 12** yang membutuhkan **PHP 8.2 atau
> lebih baru**. Unduh XAMPP versi **8.2.x atau 8.3.x** — jangan pakai XAMPP
> dengan PHP 8.1 ke bawah karena `composer install` akan gagal.

---

## 0. Unduhan yang Disiapkan

| Perangkat | Sumber | Catatan |
|-----------|--------|---------|
| XAMPP 8.2+ | https://www.apachefriends.org | Pilih installer dengan PHP 8.2 / 8.3 |
| Composer 2.x | https://getcomposer.org/download/ | Pakai `Composer-Setup.exe` di Windows |
| (Opsional) Python 3.10+ | https://www.python.org | Hanya untuk worker MQTT nanti |
| (Opsional) Mosquitto broker | https://mosquitto.org/download | Hanya saat uji perangkat ESP32 |

Untuk tahap awal (aplikasi jalan + login + dashboard), cukup **XAMPP + Composer** saja.

---

## 1. Install XAMPP

1. Jalankan installer XAMPP, klik Next sampai selesai (lokasi default
   `C:\xampp` di Windows, `/Applications/XAMPP` di Mac).
2. Buka **XAMPP Control Panel**.
3. Klik **Start** pada **Apache** dan **MySQL**. Kedua modul harus berwarna
   hijau. Kalau MySQL gagal jalan, lihat bagian Troubleshooting di bawah.
4. Uji: buka `http://localhost/phpmyadmin` di browser — halaman phpMyAdmin
   harus tampil.

Ekstensi PHP yang dibutuhkan proyek (`mbstring`, `pdo_mysql`, `openssl`,
`ctype`, `json`, `bcmath`, `tokenizer`, `xml`, `fileinfo`, `curl`) **sudah
aktif bawaan XAMPP** — tidak perlu diubah. Bila nanti `composer install`
menyinggung ekstensi yang hilang, aktifkan lewat `C:\xampp\php\php.ini`
(hapus `;` di depan baris `extension=xxx`) lalu restart Apache.

---

## 2. Install Composer

1. Jalankan `Composer-Setup.exe`.
2. Saat diminta **command-line PHP**, arahkan ke:
   `C:\xampp\php\php.exe`
3. Biarkan opsi "Add to PATH" tetap tercentang, lalu selesaikan instalasi.
4. Verifikasi di **Command Prompt baru** (tutup dulu CMD lama agar PATH
   diperbarui):

```bat
composer --version
php -v
```

Kedua perintah harus berhasil; `php -v` harus menunjukkan versi 8.2+.

---

## 3. Ekstrak Proyek

Ekstrak `health-ekg-telehealth.zip` ke folder kerja. Lokasi bebas karena
aplikasi dijalankan lewat `php artisan serve` (tidak wajib di htdocs).
Contoh yang rapi:

```bat
:: Windows — buat folder lalu ekstrak di dalamnya
mkdir C:\laravel
:: ekstrak ZIP sehingga menghasilkan
C:\laravel\health-ekg-telehealth
```

Ciri folder yang benar: di dalamnya ada `artisan`, `composer.json`,
folder `app/`, `routes/`, `docs/`, `worker/`.

Buka **Command Prompt** (atau PowerShell) lalu masuk ke folder proyek —
**semua perintah berikutnya dijalankan dari sini**:

```bat
cd C:\laravel\health-ekg-telehealth
```

---

## 4. Pasang Dependensi PHP

```bat
composer install
```

Proses ini mengunduh Laravel 12 + seluruh paket ke folder `vendor/`.
Tidak perlu `npm`/`yarn` — CSS, JS, dan Chart.js sudah dimuat lokal di
`public/assets/`. Setelah selesai, folder `vendor/` akan muncul.

---

## 5. Buat Database di phpMyAdmin

1. Buka `http://localhost/phpmyadmin`.
2. Klik **New** di panel kiri.
3. Isi **Database name**: `healthekg`
4. Pilih **Collation**: `utf8mb4_unicode_ci`
5. Klik **Create**. (Biarkan kosong — tabel akan dibuat oleh `php artisan
   migrate` pada langkah berikutnya.)

Akun MySQL default XAMPP: **user `root`, password kosong**.

---

## 6. Konfigurasi File .env

```bat
copy .env.example .env
php artisan key:generate
```

Buka `.env` dengan Notepad/VS Code, lalu sesuaikan bagian ini agar cocok
dengan XAMPP:

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=healthekg
DB_USERNAME=root
DB_PASSWORD=
```

> `DB_PASSWORD` dibiarkan **kosong** (default XAMPP). Jika Anda mengganti
> password root, isikan di sini. `APP_DEBUG=true` hanya untuk laptop
> development — saat pindah ke server produksi, kembalikan ke `false`.

Simpan file, lalu tutup editor.

---

## 7. Jalankan Migration (Buat 17 Tabel)

```bat
php artisan migrate
```

Output yang benar berisi daftar migration dengan status `DONE`, misalnya:
users, sessions, cache, jobs, doctors, patients, devices, ekg_sessions,
ekg_packets, ekg_samples, transmission_logs, security_logs, system_events,
service_status, network_metrics, examination_notes, audit_logs,
app_notifications.

Cek phpMyAdmin → database `healthekg` → 17 tabel harus sudah muncul.

> ⚠️ Jangan pernah menjalankan `migrate:fresh` pada database yang sudah
> berisi data asli — perintah itu menghapus semua tabel.

---

## 8. Buat Akun Admin Pertama

```bat
php artisan app:create-admin
```

Perintah ini interaktif — akan menanyakan:

1. **Nama lengkap** — contoh: `Admin Puskesmas`
2. **Email login** — contoh: `admin@puskesmas.test`
3. **Password awal** — minimal **10 karakter** (tidak terlihat saat
   diketik; itu normal)

Alternatif non-interaktif:

```bat
php artisan app:create-admin --name="Admin Puskesmas" --email=admin@puskesmas.test
```

> 🔒 **Role Network Engineer sengaja disembunyikan** dari form
> "Buat Akun Pengguna" di dashboard Admin (role internal yang hanya
> diketahui orang tertentu). Satu-satunya cara membuatnya adalah lewat
> terminal dengan opsi rahasia berikut:
>
> ```bat
> php artisan app:create-admin --role=network_engineer
> ```
>
> Akun **dokter** dan **pasien** dibuat kemudian melalui dashboard Admin.

---

## 9. Jalankan Aplikasi

```bat
php artisan serve
```

Buka browser: **http://localhost:8000**

1. Login dengan email + password admin yang tadi dibuat.
2. Karena `must_change_password` aktif, sistem akan **memaksa ganti
   password** pada login pertama — ikuti prosesnya.
3. Setelah masuk, sesuaikan menu sidebar sesuai peran: Admin Puskesmas,
   Dokter, Pasien, dan Network Engineer (NOC).

Jika port 8000 sudah terpakai aplikasi lain:

```bat
php artisan serve --port=8080
```

(ganti APP_URL di `.env` menjadi `http://localhost:8080` agar konsisten).

---

## 10. (Opsional) Worker MQTT + Python

Bagian ini **boleh dilewati** untuk tahap awal. Aplikasi tetap berjalan;
hanya status Broker MQTT dan Worker di halaman NOC yang akan tampil
"offline" sampai worker dijalankan. Jalankan langkah ini nanti saat
siap menghubungkan perangkat ESP32:

```bat
python -m venv worker\.venv
worker\.venv\Scripts\activate
pip install -r worker\requirements.txt
copy worker\config.example.ini worker\config.ini
:: edit worker\config.ini: alamat broker, kredensial DB (root/""), kunci ASCON
python worker\mqtt_worker.py
```

Detail format payload perangkat ada di `docs/PAYLOAD-FORMAT.md`, uji coba
publish pakai `worker/ascon_tool.py`.

---

## 11. (Alternatif) Lewat Apache XAMPP tanpa `artisan serve`

`php artisan serve` memang paling mudah, tetapi kalau ingin lewat Apache
XAMPP (URL tanpa port 8000):

1. Buat file `C:\xampp\apache\conf\extra\httpd-healthekg.conf`:

```apache
Listen 8080
<VirtualHost *:8080>
    DocumentRoot "C:/laravel/health-ekg-telehealth/public"
    <Directory "C:/laravel/health-ekg-telehealth/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

2. Di XAMPP Control Panel, klik **Config** pada Apache → aktifkan baris
   `Include conf/extra/httpd-healthekg.conf` pada `httpd.conf`, lalu
   restart Apache. Atau cukup tambahkan `Include` di `httpd.conf`.
3. Akses: `http://localhost:8080`
4. Sesuaikan `APP_URL` di `.env` menjadi `http://localhost:8080`.

Poin penting: **DocumentRoot harus menunjuk ke folder `public/`**, bukan
root proyek — ini keamanan standar Laravel.

---

## 12. Troubleshooting Umum XAMPP

| Gejala | Penyebab & Solusi |
|--------|-------------------|
| MySQL di XAMPP tidak mau Start (port 3306 dipakai) | Ada MySQL/Skype/layanan lain yang memakai 3306. Ubah port MySQL di XAMPP (`Config` → `my.ini`, ubah `port=3306` → `3307`) lalu sesuaikan `DB_PORT=3307` di `.env`. |
| `composer install` error "could not find driver" | PHP yang dipakai Composer bukan milik XAMPP. Pastikan saat instalasi Composer memilih `C:\xampp\php\php.exe`. Cek dengan `where php` — hasil pertama harus di `C:\xampp\php\`. |
| Error ekstensi (mbstring/curl/zip) saat composer install | Buka `C:\xampp\php\php.ini`, hapus `;` pada baris `extension=xxx` terkait, simpan, lalu jalankan ulang `composer install`. |
| Halaman error `500` setelah login/akses | `APP_KEY` kosong. Jalankan `php artisan key:generate` lalu bersihkan cache: `php artisan config:clear`. |
| Error `419 Page Expired` saat login | Sesi bermasalah: pastikan MySQL jalan (session driver = database), lalu `php artisan cache:clear`. |
| `SQLSTATE[HY000] [1049] Unknown database` | Database `healthekg` belum dibuat di phpMyAdmin — ulangi Langkah 5 (perhatikan ejaan huruf kecil semua). |
| `SQLSTATE[28000] access denied` | Kredensial DB salah. Default XAMPP: user `root`, password kosong. Sesuaikan `DB_USERNAME`/`DB_PASSWORD` di `.env`. |
| Port 8000 dipakai saat `artisan serve` | Jalankan `php artisan serve --port=8080`. |
| Login sukses tapi langsung diminta ganti password | Itu **memang disengaja** (kebijakan `must_change_password`) — bukan bug. Ganti password sesuai ketentuan minimal 10 karakter. |

Kalau menemui kendala lain, periksa log di `storage/logs/laravel.log`.
