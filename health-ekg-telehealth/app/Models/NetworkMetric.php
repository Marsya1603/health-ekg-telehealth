<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NetworkMetric extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'device_uid', 'ekg_session_id', 'metric', 'value', 'unit', 'samples',
        'period_start', 'period_end', 'method', 'source', 'notes', 'recorded_at', 'created_at',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'recorded_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
