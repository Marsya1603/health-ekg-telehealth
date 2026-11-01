<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $profile = $user->role === 'dokter' ? $user->doctor : $user->patient;

        $audits = AuditLog::where('user_id', $user->id)
            ->latest('created_at')
            ->limit(10)
            ->get();

        return view('profile.index', [
            'user' => $user,
            'profile' => $profile,
            'audits' => $audits,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        // Hanya field kontak yang boleh diubah pemilik akun.
        // Perubahan identitas medis (nama, rekam medis, tanggal lahir) harus
        // melalui proses administrasi oleh Admin Puskesmas.
        $rules = ['phone' => ['nullable', 'string', 'max:32']];

        if ($user->role === 'pasien') {
            $rules['email'] = ['nullable', 'email', 'max:190'];
            $rules['address'] = ['nullable', 'string', 'max:500'];
        }

        $data = $request->validate($rules, [
            'email.email' => 'Format email tidak valid.',
        ]);

        if ($user->role === 'pasien' && $user->patient) {
            $user->patient->update($data);
        } elseif ($user->role === 'dokter' && $user->doctor) {
            $user->doctor->update(['phone' => $data['phone'] ?? null]);
        } else {
            // Role admin & network engineer tidak menyimpan profil kontak tambahan.
        }

        AuditLog::record('perbarui_profil', 'user', $user->id, array_keys($data));

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    public function changePasswordForm()
    {
        return view('auth.change-password');
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(config('healthekg.password_min_length'))],
        ], [
            'current_password.required' => 'Password saat ini wajib diisi.',
            'password.required' => 'Password baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi password tidak sama.',
            'password.min' => 'Password minimal '.config('healthekg.password_min_length').' karakter.',
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Password saat ini tidak sesuai.']);
        }

        $user->forceFill([
            'password' => $validated['password'],
            'must_change_password' => false,
        ])->save();

        $request->session()->regenerate();

        AuditLog::record('ganti_password', 'user', $user->id);

        return redirect()->route($user->homeRoute())
            ->with('success', 'Password berhasil diperbarui.');
    }
}
