# Identifikasi Server Ubuntu

## Tujuan
Mencatat sistem operasi, hostname, kernel, interface jaringan, routing, kapasitas disk, memori, dan waktu server.

## Perintah pemeriksaan
```bash
cat /etc/os-release
hostnamectl
uname -a
ip -br address
ip route
df -h
free -h
timedatectl
```

## Yang harus diperiksa
- Versi dan distribusi Ubuntu.
- Hostname dan kernel.
- Interface serta alamat IP.
- Default route.
- Kapasitas disk dan memori.
- Zona waktu.

## Bukti screenshot
!(telehealth-ekg-backend/Screenshots/1.png)

## Catatan
Jangan tampilkan alamat IP publik atau detail jaringan internal jika dokumentasi akan dipublikasikan ke GitHub.
