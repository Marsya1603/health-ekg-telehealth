<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * SESUAI PRD: TIDAK ADA DATA DEMO.
     * Seeder sengaja tidak membuat pasien, dokter, pemeriksaan, perangkat,
     * metrik, atau aktivitas palsu.
     *
     * Akun administrator dibuat aman melalui perintah:
     *   php artisan app:create-admin
     *
     * Data referensi aplikasi ini (role) adalah enum pada tabel users,
     * sehingga tidak memerlukan seeding.
     */
    public function run(): void
    {
        //
    }
}
