<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'action', 'entity_type', 'entity_id', 'ip', 'details', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Catat aktivitas ke audit log. Jangan pernah mengirim password/secret ke $details.
     */
    public static function record(string $action, ?string $entityType = null, $entityId = null, array $details = []): void
    {
        try {
            static::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'ip' => request()?->ip(),
                'details' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Audit tidak boleh memutus alur utama aplikasi.
            report($e);
        }
    }
}
