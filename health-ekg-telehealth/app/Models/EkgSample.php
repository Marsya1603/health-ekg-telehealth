<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EkgSample extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ekg_session_id', 'ekg_packet_id', 'seq', 'value_mv', 'sampled_at', 'created_at',
    ];

    protected $casts = [
        'sampled_at' => 'datetime:Y-m-d H:i:s.v',
        'created_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(EkgSession::class, 'ekg_session_id');
    }

    public function packet()
    {
        return $this->belongsTo(EkgPacket::class, 'ekg_packet_id');
    }
}
