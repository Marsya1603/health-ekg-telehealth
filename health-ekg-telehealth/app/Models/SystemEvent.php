<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'component', 'event_type', 'status', 'error_code', 'message', 'occurred_at', 'created_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime:Y-m-d H:i:s.v',
        'created_at' => 'datetime',
    ];

    public static function log(string $component, string $eventType, string $status = 'info', ?string $errorCode = null, ?string $message = null): void
    {
        try {
            static::create([
                'component' => $component,
                'event_type' => $eventType,
                'status' => $status,
                'error_code' => $errorCode,
                'message' => $message,
                'occurred_at' => now(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
