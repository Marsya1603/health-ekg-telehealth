# Pemeriksaan Dashboard Admin, Dokter, dan Pasien

## Tujuan
Memeriksa controller, query, data yang dikirim ke Blade, dan tampilan tiap role.

## Admin
```bash
cd /var/www/telehealth-ekg
sed -n '1,360p' app/Http/Controllers/Admin/DashboardController.php
grep -nE 'patients|doctors|users|devices|audit|ekg_sessions|examination_notes|heartbeat_timeout' \
  app/Http/Controllers/Admin/DashboardController.php
find resources/views/admin -maxdepth 2 -type f
```

Dashboard Admin menampilkan ringkasan pasien, dokter aktif, pengguna, perangkat, aktivitas audit, sesi terbaru, dan catatan pemeriksaan. Batas default heartbeat perangkat yang diperiksa adalah 300 detik.

## Dokter
```bash
sed -n '1,380p' app/Http/Controllers/Doctor/DashboardController.php
grep -nE 'patients|sessions|activeSession|initialSamples|pollUrl' \
  app/Http/Controllers/Doctor/DashboardController.php
find resources/views/doctor -maxdepth 2 -type f
```

Dashboard Dokter menampilkan pasien dalam tanggung jawab dokter, sesi hari ini, sesi aktif, riwayat terbaru, dan data awal grafik.

## Pasien
```bash
sed -n '1,380p' app/Http/Controllers/Patient/DashboardController.php
grep -nE 'patient|device|sessions|patient_ecg_visible|initialSamples|pollUrl' \
  app/Http/Controllers/Patient/DashboardController.php
find resources/views/patient -maxdepth 2 -type f
```

Dashboard Pasien menampilkan profil, perangkat, status data, jumlah sesi, riwayat, dan sesi aktif. Grafik mengikuti konfigurasi `patient_ecg_visible`.

## Bukti screenshot
Simpan di `screenshots/10-dashboard/`. Samarkan identitas pasien dan informasi pribadi.
