# API HealthEKG Telehealth

Aplikasi ini adalah aplikasi web server-rendered (Blade) dengan endpoint JSON
untuk pembaruan real-time. **Seluruh endpoint memerlukan autentikasi sesi
(login Laravel) dan otorisasi role di backend** — cookie sesi + CSRF.
Tidak ada endpoint publik; tidak ada kredensial/secret yang dikirim ke browser.

## Endpoint JSON (dipakai dashboard)

| Method | Endpoint | Role | Fungsi |
|--------|----------|------|--------|
| GET | `/admin/ecg-history/{session}/data?after_id={id}` | admin | Sampel EKG baru (cursor by ID) |
| GET | `/doctor/monitoring/{session}/data?after_id={id}` | dokter | Sampel EKG milik pasien dalam kewenangan |
| GET | `/patient/ecg/{session}/data?after_id={id}` | pasien | Sampel EKG milik sendiri saja |

### Format respons

```json
{
  "session": { "status": "aktif", "sample_count": 42000, "packet_count": 42 },
  "device":  { "uid": "DEV-0001", "last_message_at": "2026-10-21T09:30:00.000Z" },
  "last_packet_number": 42,
  "samples": [ { "id": 41001, "t": 1761029400123, "v": 0.83 } ]
}
```

- `samples` maksimum 2000 baris per permintaan, urut naik berdasarkan ID.
- `t` = waktu sampling perangkat (epoch ms); `v` = amplitudo (mV).
- Keamanan kepemilikan data diterapkan di controller (join ke patients/doctors).

## Endpoint Web utama (HTML, memerlukan login + role)

```
POST /login, /logout, /ubah-password
GET  /admin/dashboard | /admin/patients | /admin/doctors | /admin/users
     /admin/devices   | /admin/sessions  | /admin/ecg-history | /admin/reports
GET  /doctor/dashboard | /doctor/patients | /doctor/monitoring | /doctor/sessions
     /doctor/notes
GET  /patient/dashboard | /patient/ecg | /patient/history | /patient/device
GET  /network/dashboard | /network/devices | /network/mqtt | /network/qos
     /network/payload-analysis | /network/security | /network/server-health
     /network/logs | /network/reports | /network/events
GET  /notifikasi, /profil, /pengaturan-akun
GET  /up  (health check Laravel)
```

## Catatan Desain

1. **Autentikasi**: sesi Laravel (database session), rate limit login 5/menit.
2. **Otorisasi**: middleware `role:` + filter query per controller (anti manipulasi URL).
3. **CSRF**: token di meta header; form POST wajib `@csrf`.
4. **Tidak ada mock API**: semua angka dihitung dari database/broker/worker aktual.
5. Ingest data IoT **tidak** melalui API ini — worker Python menulis langsung ke
   MySQL (lebih tahan gangguan jaringan); arsitektur dapat diperluas nanti.
