<?php

namespace App\Services;

use App\Models\ServiceStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Memeriksa status layanan yang benar-benar dapat diverifikasi:
 * - database   : koneksi PDO aktif
 * - backend    : aplikasi Laravel (yang menjalankan pemeriksaan ini)
 * - mqtt_broker: koneksi TCP ke host:port broker dari konfigurasi
 * - mqtt_worker: heartbeat worker pada tabel service_status
 * - scheduler  : heartbeat command app:collect-server-health pada tabel service_status
 *
 * Tidak ada status yang dikarang; jika tidak dapat ditentukan hasilnya 'unknown'.
 */
class StatusService
{
    /** @return array{0:string,1:string} */
    public static function database(): array
    {
        try {
            DB::select('select 1');

            return ['ok', 'Terhubung'];
        } catch (\Throwable $e) {
            return ['error', 'Tidak dapat terhubung'];
        }
    }

    /** @return array{0:string,1:string} */
    public static function backend(): array
    {
        return ['ok', 'Berjalan (permintaan ini diproses oleh backend)'];
    }

    /** @return array{0:string,1:string} */
    public static function broker(): array
    {
        $host = (string) config('healthekg.mqtt.host');
        $port = (int) config('healthekg.mqtt.port', 1883);

        if ($host === '' || $port <= 0) {
            return ['unknown', 'Host broker belum dikonfigurasi'];
        }

        $errNo = 0;
        $errStr = '';
        $connected = @fsockopen($host, $port, $errNo, $errStr, 2.0);

        if ($connected) {
            fclose($connected);

            return ['ok', 'Port broker '.$host.':'.$port.' merespons'];
        }

        return ['error', 'Tidak dapat menghubungi '.$host.':'.$port];
    }

    /** @return array{0:string,1:string} */
    public static function worker(): array
    {
        $row = ServiceStatus::where('service', 'mqtt_worker')->first();
        $timeout = (int) config('healthekg.worker_timeout', 120);

        if (! $row || ! $row->last_seen_at) {
            return ['unknown', 'Belum ada heartbeat dari worker'];
        }

        if ($row->isAlive($timeout)) {
            return ['ok', 'Heartbeat '.(int) abs($row->last_seen_at->diffInSeconds(now())).' detik lalu'];
        }

        return ['error', 'Heartbeat terakhir '.$row->last_seen_at->locale('id')->diffForHumans()];
    }

    /** @return array{0:string,1:string} */
    public static function scheduler(): array
    {
        $row = ServiceStatus::where('service', 'scheduler')->first();
        $timeout = 180; // command berjalan tiap menit; beri toleransi 3 menit

        if (! $row || ! $row->last_seen_at) {
            return ['unknown', 'Scheduler belum pernah berjalan'];
        }

        if ($row->isAlive($timeout)) {
            return ['ok', 'Terakhir berjalan '.(int) abs($row->last_seen_at->diffInSeconds(now())).' detik lalu'];
        }

        return ['error', 'Terakhir berjalan '.$row->last_seen_at->locale('id')->diffForHumans()];
    }

    /** @return array<string, array{status:string,detail:string,tone:string}> */
    public static function all(): array
    {
        $checks = [
            'database' => static::database(),
            'backend' => static::backend(),
            'broker' => static::broker(),
            'worker' => static::worker(),
            'scheduler' => static::scheduler(),
        ];

        $tone = ['ok' => 'success', 'error' => 'danger', 'unknown' => 'muted'];

        $out = [];
        foreach ($checks as $key => $c) {
            $out[$key] = [
                'status' => $c[0],
                'detail' => $c[1],
                'tone' => $tone[$c[0]],
            ];
        }

        return $out;
    }

    /** Ringkasan untuk topbar (cache 30 detik agar tidak membebani setiap halaman). */
    public static function quick(): array
    {
        return Cache::remember('status:quick', 30, function () {
            $db = static::database();
            $worker = static::worker();

            return [
                'db' => $db[0],
                'worker' => $worker[0],
            ];
        });
    }
}
