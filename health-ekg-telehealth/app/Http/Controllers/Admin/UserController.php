<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->input('role')))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->input('status') === 'aktif'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        // Profil dokter/pasien yang belum ditautkan ke akun (untuk form tambah akun).
        $unlinkedDoctors = Doctor::whereNull('user_id')->orderBy('name')->get();
        $unlinkedPatients = Patient::whereNull('user_id')->orderBy('name')->get();

        return view('admin.users.index', compact('users', 'unlinkedDoctors', 'unlinkedPatients'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            // network_engineer = role internal: tidak dibuat lewat dashboard,
            // hanya lewat terminal: php artisan app:create-admin --role=network_engineer
            'role' => ['required', 'role_valid', 'not_in:network_engineer'],
            'link_id' => ['nullable', 'integer'],
            'generate_password' => ['nullable', 'boolean'],
            'password' => ['required_unless:generate_password,1', 'nullable', 'string', 'min:'.config('healthekg.password_min_length')],
        ], [
            'email.unique' => 'Email sudah terdaftar.',
            'role.not_in' => 'Role tersebut tidak tersedia di dashboard.',
            'password.required_unless' => 'Password awal wajib diisi (atau pilih generate otomatis).',
            'password.min' => 'Password minimal '.config('healthekg.password_min_length').' karakter.',
        ]);

        $tempPassword = ($data['generate_password'] ?? false)
            ? Str::random(12)
            : $data['password'];

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($tempPassword),
            'role' => $data['role'],
            'is_active' => true,
            'must_change_password' => true,
        ]);

        // Penautan profil: akun dokter/pasien harus konsisten, tanpa duplikat.
        $linkedLabel = null;
        if ($data['role'] === 'dokter' && ! empty($data['link_id'])) {
            $doctor = Doctor::whereNull('user_id')->findOrFail($data['link_id']);
            $doctor->update(['user_id' => $user->id, 'email' => $doctor->email ?: $user->email]);
            $linkedLabel = $doctor->code;
        } elseif ($data['role'] === 'pasien' && ! empty($data['link_id'])) {
            $patient = Patient::whereNull('user_id')->findOrFail($data['link_id']);
            $patient->update(['user_id' => $user->id]);
            $linkedLabel = $patient->code;
        }

        AuditLog::record('tambah_pengguna', 'user', $user->id, [
            'role' => $data['role'], 'linked' => $linkedLabel,
        ]);

        $msg = 'Akun '.$user->email.' dibuat.';
        if (($data['generate_password'] ?? false)) {
            $msg .= ' Password awal (tampilkan sekali, simpan dengan aman): '.$tempPassword;
        } else {
            $msg .= ' Pengguna wajib mengganti password pada login pertama.';
        }

        return redirect()->route('admin.users.index')->with('success', $msg);
    }

    public function toggle(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['status' => 'Anda tidak dapat menonaktifkan akun sendiri.']);
        }

        // Cegah menonaktifkan admin aktif terakhir.
        if ($user->role === 'admin' && $user->is_active) {
            $activeAdmins = User::where('role', 'admin')->where('is_active', true)->count();
            if ($activeAdmins <= 1) {
                return back()->withErrors(['status' => 'Minimal satu Admin Puskesmas aktif harus tersisa.']);
            }
        }

        $user->update(['is_active' => ! $user->is_active]);
        AuditLog::record('ubah_status_pengguna', 'user', $user->id, ['is_active' => $user->is_active]);

        return back()->with('success', 'Status akun diperbarui: '.($user->is_active ? 'Aktif' : 'Nonaktif').'.');
    }

    public function resetPassword(User $user)
    {
        $temp = Str::random(12);

        $user->forceFill([
            'password' => Hash::make($temp),
            'must_change_password' => true,
        ])->save();

        AuditLog::record('reset_password', 'user', $user->id);

        return back()->with('reset_password', $temp)->with('reset_email', $user->email);
    }

    public function updateRole(Request $request, User $user)
    {
        $data = $request->validate(['role' => ['required', 'role_valid']]);

        // Role internal: tidak boleh diberikan ke akun lain lewat dashboard.
        if ($data['role'] === 'network_engineer' && $user->role !== 'network_engineer') {
            return back()->withErrors(['role' => 'Role Network Engineer tidak dapat diberikan melalui dashboard.']);
        }

        if ($user->id === auth()->id() && $data['role'] !== 'admin') {
            return back()->withErrors(['role' => 'Anda tidak dapat menurunkan role akun sendiri.']);
        }

        if ($user->role === 'admin' && $data['role'] !== 'admin') {
            $activeAdmins = User::where('role', 'admin')->where('is_active', true)->count();
            if ($activeAdmins <= 1) {
                return back()->withErrors(['role' => 'Minimal satu Admin Puskesmas aktif harus tersisa.']);
            }
        }

        $old = $user->role;
        $user->update(['role' => $data['role']]);
        AuditLog::record('ubah_role', 'user', $user->id, ['dari' => $old, 'ke' => $data['role']]);

        return back()->with('success', 'Role pengguna diperbarui dari '.$old.' menjadi '.$data['role'].'.');
    }
}
