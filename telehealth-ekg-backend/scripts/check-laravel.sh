#!/usr/bin/env bash
# Pemeriksaan ringkas read-only Laravel.
set +e
APP_DIR="${APP_DIR:-/var/www/telehealth-ekg}"
cd "$APP_DIR" || { echo "Direktori tidak ditemukan: $APP_DIR"; exit 1; }
php artisan --version
php artisan about
php artisan route:list --except-vendor
php artisan route:list --path=api/v1 -v
php artisan migrate:status
php artisan db:show
php artisan db:table devices
php artisan db:table ekg_sessions
php artisan db:table ekg_packets
php artisan db:table ekg_samples
