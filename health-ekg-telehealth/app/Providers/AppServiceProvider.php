<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Saat aplikasi dijalankan di balik Cloudflare Tunnel / proxy HTTPS
        // (FORCE_HTTPS=true di .env), semua URL (asset, route, redirect)
        // dipaksa memakai APP_URL dengan skema https — bukan alamat internal
        // seperti http://127.0.0.1:8080 yang dilihat oleh PHP.
        if (filter_var((string) config('app.force_https'), FILTER_VALIDATE_BOOL)) {
            URL::forceRootUrl((string) config('app.url'));
            URL::forceScheme('https');
        }

        // Tampilan pagination khusus (dibuat sendiri, gaya konsisten dengan tema).
        Paginator::defaultView('partials.pagination');
        Paginator::defaultSimpleView('partials.pagination');

        // Pembatasan percobaan login: 5 kali per menit per email+IP.
        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input('email');

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        // Validator: pastikan hanya role yang dikenal.
        \Illuminate\Support\Facades\Validator::extend('role_valid', function ($attribute, $value) {
            return in_array($value, User::ROLES, true);
        }, 'Role tidak dikenal.');
    }
}
