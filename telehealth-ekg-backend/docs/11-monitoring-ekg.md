# Pemeriksaan Monitoring EKG

## Tujuan
Memeriksa pengambilan sampel awal, otorisasi sesi, endpoint polling, dan penghentian sesi.

## Controller Dokter
```bash
cd /var/www/telehealth-ekg
sed -n '1,440p' app/Http/Controllers/Doctor/MonitoringController.php

grep -nE 'function index|function show|function data|samplePayload|function end|ownsSession|after_id|EkgSample' \
  app/Http/Controllers/Doctor/MonitoringController.php
```

Controller menyediakan daftar sesi, detail pemeriksaan, pengambilan sampel, dan penghentian sesi. Akses diperiksa berdasarkan relasi dokter dan pasien.

## Controller Pasien
```bash
sed -n '1,380p' app/Http/Controllers/Patient/EcgController.php

grep -nE 'function index|function show|function data|patient_id|patient_ecg_visible|samplePayload' \
  app/Http/Controllers/Patient/EcgController.php
```

Pasien hanya dapat mengakses sesi miliknya sendiri dan grafik mengikuti pengaturan visibilitas.

## Yang harus diperiksa
- Pengambilan sampel awal maksimal 1.500 data.
- Parameter `after_id` untuk mengambil sampel baru.
- Pemeriksaan kepemilikan sesi.
- Perubahan status saat sesi dihentikan.

## Bukti screenshot
Simpan di `screenshots/11-monitoring-ekg/`.
