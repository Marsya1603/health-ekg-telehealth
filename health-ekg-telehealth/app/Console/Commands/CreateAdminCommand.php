<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdminCommand extends Command
{
    protected $signature = 'app:create-admin
                            {--name= : Nama lengkap administrator}
                            {--email= : Email login administrator}
                            {--role=admin : Role akun (admin atau network_engineer)}';

    protected $description = 'Membuat akun administrator awal secara aman (tanpa password hardcoded)';

    public function handle(): int
    {
        $role = $this->option('role') ?: 'admin';

        if (! in_array($role, ['admin', 'network_engineer'], true)) {
            $this->error('Role harus admin atau network_engineer. Akun dokter/pasien dibuat melalui dashboard.');

            return self::FAILURE;
        }

        $name = $this->option('name') ?: text(
            label: 'Nama lengkap',
            required: true,
            validate: fn (string $value) => mb_strlen($value) > 100
                ? 'Nama maksimal 100 karakter.'
                : null
        );

        $email = $this->option('email') ?: text(
            label: 'Email login',
            required: true,
            validate: fn (string $value) => User::where('email', $value)->exists()
                ? 'Email sudah terdaftar.'
                : null
        );

        $password = password(
            label: 'Password awal (minimal '.config('healthekg.password_min_length').' karakter)',
            required: true,
            validate: fn (string $value) => strlen($value) < (int) config('healthekg.password_min_length')
                ? 'Password minimal '.config('healthekg.password_min_length').' karakter.'
                : null
        );

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'is_active' => true,
            // Password awal wajib diganti pada login pertama.
            'must_change_password' => (bool) config('healthekg.force_password_change', true),
        ]);

        AuditLog::record('buat_akun_bootstrap', 'user', $user->id, ['role' => $role]);

        $this->info('Akun berhasil dibuat.');
        $this->table(['Nama', 'Email', 'Role'], [[$user->name, $user->email, $user->roleLabel()]]);
        $this->warn('Password tidak ditampilkan kembali. Catat dengan aman; wajib diganti saat login pertama.');

        return self::SUCCESS;
    }
}
