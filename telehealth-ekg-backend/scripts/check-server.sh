#!/usr/bin/env bash
# Pemeriksaan ringkas read-only infrastruktur.
set +e
DOMAIN="${DOMAIN:-https://ekgtelehealth.my.id}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.5-fpm}"
for svc in nginx "$PHP_FPM_SERVICE" mysql cloudflared; do
  echo "===== $svc ====="
  systemctl is-active "$svc"
  systemctl is-enabled "$svc"
done
echo "===== VERSION ====="
nginx -v 2>&1
php -v 2>&1 | head -n 2
mysql --version 2>&1
cloudflared --version 2>&1
echo "===== NGINX TEST ====="
sudo nginx -t
echo "===== PORTS ====="
sudo ss -ltnp | grep -E ':8080|:80|:443'
echo "===== LOCAL LOGIN ====="
curl -sS -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8080/login
echo "===== PUBLIC LOGIN ====="
curl -sS -o /dev/null -w '%{http_code}\n' "${DOMAIN%/}/login"
echo "===== DISK/MEMORY ====="
df -h /
free -h
