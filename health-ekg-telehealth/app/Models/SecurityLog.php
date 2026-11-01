<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'device_uid', 'ekg_session_id', 'packet_number', 'event_type', 'error_code',
        'processing_ms', 'payload_plain_size', 'payload_cipher_size', 'occurred_at', 'created_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime:Y-m-d H:i:s.v',
        'created_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(EkgSession::class, 'ekg_session_id');
    }
}
