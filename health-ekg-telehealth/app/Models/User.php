<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'is_active', 'must_change_password', 'last_login_at',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public const ROLES = ['admin', 'dokter', 'pasien', 'network_engineer'];

    public function roleLabel(): string
    {
        return config('healthekg.roles')[$this->role] ?? $this->role;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function homeRoute(): string
    {
        return match ($this->role) {
            'admin' => 'admin.dashboard',
            'dokter' => 'doctor.dashboard',
            'pasien' => 'patient.dashboard',
            'network_engineer' => 'network.dashboard',
            // Role tak dikenal tetap mendapat halaman yang sah untuk user
            // terautentikasi — BUKAN 'login', agar tidak menjadi redirect loop.
            default => 'profile.show',
        };
    }

    public function doctor()
    {
        return $this->hasOne(Doctor::class);
    }

    public function patient()
    {
        return $this->hasOne(Patient::class);
    }

    /** Notifikasi aplikasi (tabel app_notifications). */
    public function appNotifications()
    {
        return $this->hasMany(AppNotification::class);
    }

    public function unreadNotificationCount(): int
    {
        return $this->appNotifications()->unread()->count();
    }
}
