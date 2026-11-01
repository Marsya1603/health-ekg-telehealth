<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EkgSession extends Model
{
    protected $fillable = [
        'session_code', 'patient_id', 'doctor_id', 'device_id', 'sampling_rate',
        'started_at', 'ended_at', 'sample_count', 'packet_count', 'status', 'gap_notified_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'gap_notified_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function packets()
    {
        return $this->hasMany(EkgPacket::class);
    }

    public function samples()
    {
        return $this->hasMany(EkgSample::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'aktif' => 'Berjalan',
            'selesai' => 'Selesai',
            'dihentikan' => 'Dihentikan',
            'gagal' => 'Gagal',
            default => $this->status,
        };
    }

    public function durationSeconds(): ?int
    {
        if (! $this->started_at) {
            return null;
        }

        return (int) $this->started_at->diffInSeconds($this->ended_at ?? now());
    }
}
