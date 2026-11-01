# Pengujian — HealthEKG Telehealth

## 1. Test Otomatis (PHPUnit)

```bash
composer install
php artisan test
```

Test yang tersedia (memakai SQLite in-memory, tidak menyentuh database produksi):

| Test | Memastikan |
|------|-----------|
| `Feature\AuthTest` | Halaman login tampil; tamu dialihkan ke login |
| `Feature\RoleAccessTest` | Pasien ditolak (403) di /admin; dashboard admin tetap 200 dengan database kosong (bukti empty-state bekerja) |

## 2. Checklist Pengujian Manual (sesuai acceptance criteria)

### Autentikasi & Hak Akses
- [ ] Login kredensial salah → pesan error, tidak membocorkan keberadaan akun.
- [ ] Login 6× dalam 1 menit → terkena rate limit.
- [ ] Akun nonaktif tidak dapat masuk.
- [ ] Pasien mengganti URL ke `/admin/dashboard` → 403 (dan tercatat di audit log).
- [ ] Network engineer tidak dapat membuka data klinis pasien.
- [ ] `must_change_password` memaksa halaman ubah password sebelum menu lain.

### Admin
- [ ] CRUD pasien/dokter/perangkat berfungsi; Patient ID & Doctor ID dibuat backend.
- [ ] Nomor rekam medis duplikat ditolak.
- [ ] Menonaktifkan admin terakhir ditolak.
- [ ] Reset password menghasilkan password sementara sekali tampil.

### Integrasi IoT (end-to-end)
- [ ] Mosquitto jalan; worker `systemctl status healthekg-worker` aktif; heartbeat muncul di dashboard.
- [ ] `ascon_tool.py encrypt --publish` → sampel muncul di grafik dokter dalam ±3 detik (polling).
- [ ] Kirim frame dengan kunci salah → `security_logs` bertambah `tag_invalid`, data tidak muncul.
- [ ] Kirim paket nomor sama dua kali → tercatat `duplikat`, sampel tidak ganda.
- [ ] Kirim dari device tidak terdaftar → tercatat `perangkat_tak_dikenal`.
- [ ] Berhenti mengirim data > ambang → notifikasi "Data EKG tidak diterima" muncul.

### QoS & Network
- [ ] Dashboard Network menampilkan delay/throughput/loss hanya setelah ada transmisi.
- [ ] Ekspor CSV log & laporan berjalan bila `HEALTHEKG_EXPORTS_ENABLED=true`.
- [ ] Server Health kosong sebelum scheduler jalan; terisi setelah ±1 menit.

### Ketiadaan Data
- [ ] Database kosong → semua dashboard menampilkan "Belum ada data" (bukan 0 palsu/error).
- [ ] Broker mati → status broker merah, dashboard tetap berfungsi.
- [ ] Worker berhenti → status worker berubah sesuai heartbeat kadaluarsa.
