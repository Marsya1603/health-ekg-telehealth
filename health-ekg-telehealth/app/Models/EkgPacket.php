<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EkgPacket extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ekg_session_id', 'device_id', 'device_uid', 'packet_number', 'sample_count',
        'sampling_rate', 'device_time', 'received_at', 'payload_size', 'encrypted_size',
        'processing_ms', 'nonce',
    ];

    protected $casts = [
        'device_time' => 'datetime:Y-m-d H:i:s.v',
        'received_at' => 'datetime:Y-m-d H:i:s.v',
    ];

    public function session()
    {
        return $this->belongsTo(EkgSession::class, 'ekg_session_id');
    }
}
