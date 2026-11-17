# Pemeriksaan API EKG

## Tujuan
Memeriksa route API, validasi request, proses penyimpanan data, dan endpoint pengambilan data terbaru.

## Perintah pemeriksaan
```bash
cd /var/www/telehealth-ekg

sed -n '1,220p' routes/api.php

php artisan route:list --path=api/v1 -v

sed -n '1,360p' app/Http/Controllers/Api/EkgDataController.php

grep -nE 'function store|function latest|validate|DB::transaction|EkgSample|EkgSession|Device' \
  app/Http/Controllers/Api/EkgDataController.php
```

## Endpoint yang diperiksa
| Method | Endpoint | Fungsi |
|---|---|---|
| POST | `/api/v1/ekg/send` | Menerima data EKG |
| GET | `/api/v1/ekg/latest` | Mengambil data terbaru |

## Alur yang diperiksa
1. Validasi `device_code`, `raw_ecg`, `bpm`, `packet_number`, `sampling_rate`, dan `sampled_at`.
2. Pencarian atau pembuatan perangkat.
3. Pencarian atau pembuatan sesi aktif.
4. Penyimpanan informasi paket.
5. Penyimpanan sampel EKG.
6. Pembaruan penghitung sesi dan informasi perangkat.
7. Pengembalian respons JSON.

## Catatan implementasi
Kode yang ditinjau menggunakan pasien pertama sebagai fallback jika `patient_id` perangkat tidak valid atau belum tersedia. Pengaitan perangkat-pasien perlu diperketat sebelum penggunaan operasional.

Route API yang diperiksa memperlihatkan middleware `api`. Jangan mengklaim endpoint sudah dilindungi autentikasi perangkat tanpa bukti.

## Pengujian POST
Gunakan hanya lingkungan uji dan database uji yang sudah diotorisasi. Payload berikut contoh dummy, bukan data aktual:

```bash
curl -i -X POST "http://127.0.0.1:8080/api/v1/ekg/send" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "device_code": "DEVICE-TEST-001",
    "raw_ecg": 1.25,
    "bpm": 75,
    "packet_number": 1,
    "sampling_rate": 250,
    "sampled_at": "2026-11-16T10:00:00+07:00"
  }'
```

Jangan menjalankan request dummy ke database produksi tanpa memastikan dampak dan prosedur pembersihan data uji.

## Bukti screenshot
Simpan di `screenshots/08-api-routes-controller/`.
