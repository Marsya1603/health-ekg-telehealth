# Format Payload / Frame MQTT — HealthEKG Telehealth

Dokumen ini adalah **kontrak integrasi** antara perangkat (ESP32 + Olimex SHIELD-EKG-EMG),
broker MQTT, dan worker HealthEKG. Implementasi firmware harus mengikuti format ini.

## 1. Topik & QoS

| Item | Nilai (default, dapat dikonfigurasi) |
|------|--------------------------------------|
| Topic publikasi | `healthekg/telemetry/{device_id}` |
| QoS | 1 (at-least-once) |
| Koneksi | MQTT over TCP/IP (TLS menyusul kebijakan server) |

`device_id` pada topic harus sama dengan field `device_uid` di dalam frame.
Worker menolak pesan dari device yang tidak terdaftar / dinonaktifkan di dashboard.

## 2. Struktur Frame (binary, big-endian)

```
Offset  Ukuran          Field
0       4               Magic bytes "HEK1" (0x48 0x45 0x4B 0x31)
4       1               Frame version (0x01)
5       1               Message type: 0x01 = ECG data, 0x02 = session_end
6       2               Device ID length (uint16, L1)
8       L1              Device ID (UTF-8), mis. "DEV-0001"
8+L1    36              Session ID (ASCII UUID, 36 karakter, dibuat perangkat)
        4               Packet number (uint32, mulai 1, increment per paket)
        8               Timestamp perangkat (uint64, epoch milidetik)
        2               Sampling rate (uint16, Hz)
        2               Sample count (uint16, N)
        16              Nonce ASCON-128 (acak unik per paket)
        M               Ciphertext = ASCON-128(key, nonce, plaintext, aad)
```

- `M` = panjang plaintext + 16 (ASCON-128 tag 128-bit).
- Untuk `message_type = 0x02` (session_end), plaintext kosong sehingga `M = 16`.

## 3. ASCON-128

| Parameter | Nilai |
|-----------|-------|
| Varian | Ascon-128 (key 128-bit, nonce 128-bit, tag 128-bit) |
| Key | 16 byte, dibagikan terproteksi (worker: `config.ini` 0600; perangkat: firmware) |
| Nonce | 16 byte acak, **wajib unik per paket** (deteksi reuse di server) |
| AAD (Associated Data) | Seluruh header frame dari offset 0 **hingga sebelum nonce** |
| Plaintext | `N × int16` **little-endian** — sampel EKG dalam **mV × 100** |

Aturan server:
- Verifikasi tag dilakukan **sebelum** data dipakai; kegagalan → paket ditolak
  dan dicatat pada `security_logs` (`tag_invalid`).
- Nonce yang dipakai ulang per perangkat ditolak (`nonce_error`).
- Kunci tidak pernah muncul di aplikasi web, API, atau log.

## 4. Konversi Nilai

Firmware mengonversi tegangan ADC Olimex SHIELD-EKG-EMG menjadi milivolt:

```
value_mv = (voltage_after_offset - offset) / gain     # sesuai kalibrasi perangkat
sample_i16 = round(value_mv * 100)                    # skala x100, int16 LE
```

Worker menyimpan `value_mv = sample_i16 / 100` (kolom `value_mv` decimal).

## 5. Timestamp & Delay

- `timestamp` (epoch ms) dibuat perangkat pada saat paket dikirim.
- Aktifkan **SNTP di ESP32** agar jam perangkat sinkron; delay one-way
  (`received_at − device_time`) hanya valid bila jam tersinkron.
- Timestamp sampel dihitung: `device_ts + i × (1000 / sampling_rate)` ms.

## 6. Siklus Sesi

1. Perangkat membuat `session_id` UUID baru ketika perekaman dimulai.
2. Paket pertama dengan session baru → worker membuat baris `ekg_sessions`
   (status `aktif`) + notifikasi ke admin/dokter terkait.
3. Perekaman berakhir dengan pesan `message_type = 0x02` → status `selesai`.
4. Admin/dokter juga dapat mengakhiri sesi dari dashboard (status `dihentikan`).

## 7. Contoh Uji (tanpa firmware)

```bash
cd worker
KEY=$(openssl rand -hex 16)          # samakan dengan config.ini worker
# buat CSV samples.csv: satu kolom nilai mV per baris
python3 ascon_tool.py encrypt --key "$KEY" --device DEV-TEST01 \
    --session "$(uuidgen)" --rate 250 --samples samples.csv --publish
```

Worker akan menerima, memverifikasi, menyimpan, dan data tampil di dashboard.
