<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Doctor extends Model
{
    protected $fillable = [
        'user_id', 'code', 'name', 'registration_number', 'specialization',
        'phone', 'email', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function patients()
    {
        return $this->hasMany(Patient::class);
    }

    public function sessions()
    {
        return $this->hasMany(EkgSession::class);
    }

    public function notes()
    {
        return $this->hasMany(ExaminationNote::class);
    }
}
