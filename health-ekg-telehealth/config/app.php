<?php

use Illuminate\Support\Str;

return [

    'name' => env('APP_NAME', 'HealthEKG Telehealth'),

    'env' => env('APP_ENV', 'production'),

    'debug' => (bool) env('APP_DEBUG', false),

    'url' => env('APP_URL', 'http://localhost'),

    // Set true pada .env server yang berada di balik Cloudflare Tunnel / proxy
    // HTTPS agar semua URL (asset, route, redirect) memakai APP_URL + https.
    'force_https' => env('FORCE_HTTPS', false),

    'timezone' => env('APP_TIMEZONE', 'Asia/Jakarta'),

    'locale' => env('APP_LOCALE', 'id'),

    'fallback_locale' => 'id',

    'faker_locale' => 'id_ID',

    'key' => env('APP_KEY'),

    'cipher' => 'AES-256-CBC',

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
