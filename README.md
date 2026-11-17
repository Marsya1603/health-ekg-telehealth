# Telehealth EKG

**Sistem Telehealth EKG Berbasis Sensor Olimex Menggunakan Algoritma Kriptografi Ringan ASCON-128**

Proyek ini bertujuan membangun sistem pemantauan aktivitas jantung secara jarak jauh menggunakan sensor EKG Olimex dan ESP32. Data EKG dikirimkan ke server untuk diproses, disimpan, dan ditampilkan melalui dashboard web secara real-time.

## Teknologi
- Olimex SHIELD-EKG-EMG
- ESP32
- MQTT / TCP/IP
- Laravel REST API
- MySQL
- Ubuntu Server, Nginx
- ASCON-128
- Cloudflare Tunnel

## Tim dan Pembagian Tugas

## Pembagian Tugas Tim

| Nama Anggota | Domain Utama | Rincian Tanggung Jawab (Scope) |
|---|---|---|
| Doni Alamsyah | Monitoring & Data Acquisition (Pemantauan & Akuisisi) | Bertanggung jawab pada akuisisi sinyal EKG menggunakan sensor Olimex dan ESP32, serta pengembangan Dashboard Web Telehealth (UI/UX) agar dokter dapat memantau grafik EKG dan BPM secara real-time. |
| Muhammad Marsya Nazmul Hakim | System Architecture & Infrastructure (Arsitektur & Infrastruktur) | Bertanggung jawab pada perancangan topologi jaringan Edge-to-Cloud, penyediaan Virtual Private Server (VPS), pembuatan REST API menggunakan Laravel, dan manajemen database MySQL untuk rekam medis historis. |
| Rafi Zinedine Rahman | Network Security & QoS Analysis (Keamanan & Analisis Performa) | Bertanggung jawab pada konfigurasi MQTT Broker, implementasi enkripsi jalur transmisi (MQTTS) dan eksperimen Payload (E2EE), serta analisis Quality of Service (Delay, Packet Loss, dan Overhead) menggunakan Wireshark. |

## Dokumentasi

Repository ini berisi dokumentasi konfigurasi server, deployment Laravel, pengelolaan database, REST API, monitoring, dan infrastruktur sistem Telehealth EKG.

## Repository

Dikembangkan sebagai proyek akademik mahasiswa Politeknik Negeri Jakarta.
