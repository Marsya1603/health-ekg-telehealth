# Pemeriksaan Cloudflare Tunnel dan Deployment

## Tujuan
Memeriksa service Cloudflare Tunnel, log koneksi, ingress, port lokal, serta akses domain.

## Service dan versi
```bash
sudo systemctl status cloudflared --no-pager
cloudflared --version
systemctl is-enabled cloudflared
```

## Log
```bash
sudo journalctl -u cloudflared -n 100 --no-pager

sudo journalctl -u cloudflared \
  --since "30 minutes ago" --no-pager

sudo journalctl -u cloudflared -n 200 --no-pager \
  | grep -Ei 'registered tunnel connection|ingress|error|timeout'
```

## Port
```bash
sudo ss -ltnp | grep -E ':8080|:80|:443'
```

Ingress yang tercatat mengarah dari domain `ekgtelehealth.my.id` ke `http://127.0.0.1:8080`.

## Uji lokal
```bash
curl -I http://127.0.0.1:8080

curl -sS -o /dev/null \
  -w 'HTTP: %{http_code}; URL: %{url_effective}\n' \
  http://127.0.0.1:8080/login
```

## Uji publik
```bash
curl -I https://ekgtelehealth.my.id

curl -sS -o /dev/null \
  -w 'HTTP: %{http_code}; URL: %{url_effective}\n' \
  https://ekgtelehealth.my.id/login
```

Hasil sebelumnya: root mengarahkan ke `/login` dan halaman login menghasilkan HTTP 200, baik lokal maupun publik.

## Bukti screenshot
Simpan di `screenshots/13-cloudflare-deployment/`.

## Catatan
Status service aktif saja tidak membuktikan tunnel selalu stabil. Cocokkan status, log, dan akses publik.
