<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    protected $fillable = [
        'user_id', 'code', 'medical_record_number', 'name', 'birth_date', 'gender',
        'phone', 'email', 'address', 'doctor_id', 'registered_at', 'is_active',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'registered_at' => 'date',
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function devices()
    {
        return $this->hasMany(Device::class);
    }

    public function sessions()
    {
        return $this->hasMany(EkgSession::class);
    }

    public function notes()
    {
        return $this->hasMany(ExaminationNote::class);
    }

    /** Perangkat pertama yang ditautkan (dipakai untuk ringkasan status). */
    public function primaryDevice()
    {
        return $this->hasOne(Device::class)->oldestOfMany();
    }
}
