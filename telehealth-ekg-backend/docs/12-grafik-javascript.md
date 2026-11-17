# Pemeriksaan Blade dan JavaScript Grafik EKG

## Tujuan
Memeriksa komponen grafik, data awal, pembaruan BPM/status, dan mekanisme polling browser.

## Blade
```bash
cd /var/www/telehealth-ekg
sed -n '1,440p' resources/views/partials/ecg-view.blade.php

grep -nE 'ecgChart|initialSamples|pollUrl|startEcgPolling|interval|device_code|BPM|sampling' \
  resources/views/partials/ecg-view.blade.php
```

## JavaScript
```bash
grep -nE 'startEcgPolling|after_id|pushSamples|MAX_POINTS|HEKG_lastId|setTimeout|busy' \
  public/assets/js/app.js

sed -n '1,360p' public/assets/js/app.js
```

## Yang harus diperiksa
- Canvas grafik dengan ID `ecgChart`.
- Data awal dari `initialSamples`.
- Polling menggunakan `after_id`.
- Interval yang dikirim Blade sebesar 1.000 ms.
- Pembatasan tampilan grafik maksimal 250 titik.
- Penanganan error dan penghentian polling saat sesi selesai.

## Bukti screenshot
Simpan di `screenshots/12-grafik-javascript/`.
