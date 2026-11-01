<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory hanya untuk pengujian (PHPUnit). TIDAK digunakan untuk membuat
 * data demo — prinsip aplikasi: tidak ada data pasien/dokter buatan.
 */
class UserFactory extends Factory
{
    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => 'Pengguna Uji',
            'email' => 'uji-'.uniqid().'@example.test',
            'password' => static::$password ??= bcrypt('PasswordUji123'),
            'role' => 'admin',
            'is_active' => true,
            'must_change_password' => false,
        ];
    }
}
