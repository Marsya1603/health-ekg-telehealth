<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransmissionLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'device_uid', 'ekg_session_id', 'packet_number', 'message_type', 'topic',
        'frame_size', 'payload_size', 'encrypted_size', 'status', 'validation_status',
        'delay_ms', 'processing_ms', 'error_code', 'source', 'received_at', 'created_at',
    ];

    protected $casts = [
        'received_at' => 'datetime:Y-m-d H:i:s.v',
        'created_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(EkgSession::class, 'ekg_session_id');
    }

    public function scopeFilter($query, array $f)
    {
        return $query
            ->when($f['device'] ?? null, fn ($q, $v) => $q->where('device_uid', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['type'] ?? null, fn ($q, $v) => $q->where('message_type', $v))
            ->when($f['from'] ?? null, fn ($q, $v) => $q->where('received_at', '>=', $v.' 00:00:00'))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->where('received_at', '<=', $v.' 23:59:59'));
    }
}
