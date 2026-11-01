<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Konfigurasi Khusus HealthEKG Telehealth
    |--------------------------------------------------------------------------
    | Semua nilai diambil dari environment variables (.env) agar tidak ada
    | kredensial yang tertulis di source code.
    */

    'roles' => [
        'admin' => 'Admin Puskesmas',
        'dokter' => 'Dokter',
        'pasien' => 'Pasien',
        'network_engineer' => 'Network Engineer',
    ],

    'mqtt' => [
        // Hanya host/port untuk CEK STATUS koneksi broker dari dashboard.
        // Kredensial broker tidak pernah disimpan atau ditampilkan di aplikasi web.
        'host' => env('HEALTHEKG_MQTT_HOST', '127.0.0.1'),
        'port' => (int) env('HEALTHEKG_MQTT_PORT', 1883),
        'topic_prefix' => env('HEALTHEKG_MQTT_TOPIC_PREFIX', 'healthekg'),
        'qos' => (int) env('HEALTHEKG_MQTT_QOS', 1),
    ],

    'worker_timeout' => (int) env('HEALTHEKG_WORKER_TIMEOUT', 120),

    'device' => [
        // Perangkat dianggap tidak mengirim data bila pesan terakhir lebih tua dari nilai ini (detik).
        'heartbeat_timeout' => (int) env('HEALTHEKG_HEARTBEAT_TIMEOUT', 300),
        // Interval pemeriksaan sesi yang berhenti mengirim data (menit).
        'gap_alert_minutes' => (int) env('HEALTHEKG_GAP_ALERT_MINUTES', 10),
    ],

    // Apakah pasien boleh melihat grafik EKG miliknya melalui dashboard.
    'patient_ecg_visible' => (bool) env('HEALTHEKG_PATIENT_ECG_VISIBLE', true),

    'exports' => [
        'enabled' => (bool) env('HEALTHEKG_EXPORTS_ENABLED', true),
    ],

    // Wajib ganti password pada login pertama / setelah reset password.
    'force_password_change' => (bool) env('HEALTHEKG_FORCE_PASSWORD_CHANGE', true),

    'password_min_length' => 10,

];
