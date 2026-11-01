<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceStatus extends Model
{
    protected $table = 'service_status';

    protected $fillable = [
        'service', 'status', 'detail', 'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    /** Perbarui atau buat baris status layanan (dipakai worker & scheduler). */
    public static function heartbeat(string $service, string $status, ?string $detail = null): void
    {
        static::updateOrCreate(
            ['service' => $service],
            ['status' => $status, 'detail' => $detail, 'last_seen_at' => now()]
        );
    }

    /** Apakah layanan terakhir kali terlihat dalam batas waktu tertentu. */
    public function isAlive(int $timeoutSeconds): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->diffInSeconds(now()) <= $timeoutSeconds;
    }
}
