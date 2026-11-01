<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $fillable = [
        'device_uid', 'name', 'type', 'patient_id', 'firmware_version',
        'is_registered', 'notes', 'last_message_at', 'last_packet_number',
    ];

    protected $casts = [
        'is_registered' => 'boolean',
        'last_message_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function sessions()
    {
        return $this->hasMany(EkgSession::class);
    }

    public function transmissionLogs()
    {
        return $this->hasMany(TransmissionLog::class, 'device_uid', 'device_uid');
    }

    /**
     * Status data perangkat dihitung dari pesan terakhir yang benar-benar diterima.
     * Metode ini tidak menyimpulkan offline tanpa aturan; hasilnya:
     * - 'aktif'  : pesan terakhir < heartbeat timeout
     * - 'idle'   : pernah mengirim, tetapi sudah lama tidak ada data
     * - 'unknown': belum pernah mengirim pesan / status tidak dapat ditentukan
     */
    public function dataStatus(): array
    {
        $timeout = (int) config('healthekg.device.heartbeat_timeout', 300);

        if (! $this->last_message_at) {
            return ['unknown', 'Belum pernah mengirim data'];
        }

        $idleSeconds = (int) abs($this->last_message_at->diffInSeconds(now()));

        if ($idleSeconds <= $timeout) {
            return ['aktif', 'Data diterima '.$idleSeconds.' detik lalu'];
        }

        return ['idle', 'Tidak ada data terbaru (terakhir '.$this->last_message_at->locale('id')->diffForHumans().')'];
    }
}
