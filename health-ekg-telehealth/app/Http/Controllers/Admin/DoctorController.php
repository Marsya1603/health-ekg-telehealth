<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DoctorController extends Controller
{
    public function index(Request $request)
    {
        $doctors = Doctor::query()
            ->withCount('patients')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w
                    ->where('name', 'like', $term)
                    ->orWhere('code', 'like', $term)
                    ->orWhere('specialization', 'like', $term));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->input('status') === 'aktif'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.doctors.index', compact('doctors'));
    }

    public function create()
    {
        return view('admin.doctors.form', [
            'doctor' => null,
            'linkableUsers' => $this->linkableUsers(null),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $linkUserId = $this->validatedLink($request);

        $doctor = DB::transaction(function () use ($data, $linkUserId) {
            $doctor = Doctor::create($data + ['code' => 'TMP']);
            $doctor->forceFill(['code' => 'DOK-'.str_pad((string) $doctor->id, 5, '0', STR_PAD_LEFT)])->save();

            if ($linkUserId !== null) {
                $this->applyLink($doctor, $linkUserId);
            }

            return $doctor;
        });

        AuditLog::record('tambah_dokter', 'doctor', $doctor->id, [
            'code' => $doctor->code, 'akun_ditautkan' => $linkUserId,
        ]);

        return redirect()->route('admin.doctors.show', $doctor)
            ->with('success', 'Dokter '.$doctor->name.' berhasil ditambahkan dengan ID '.$doctor->code.'.');
    }

    public function show(Doctor $doctor)
    {
        $doctor->load(['user', 'patients']);
        $sessions = $doctor->sessions()->with(['patient', 'device'])->latest('started_at')->limit(10)->get();

        return view('admin.doctors.show', compact('doctor', 'sessions'));
    }

    public function edit(Doctor $doctor)
    {
        return view('admin.doctors.form', [
            'doctor' => $doctor,
            'linkableUsers' => $this->linkableUsers($doctor),
        ]);
    }

    public function update(Request $request, Doctor $doctor)
    {
        $data = $this->validated($request, $doctor->id);
        $linkUserId = $this->validatedLink($request, $doctor);

        DB::transaction(function () use ($doctor, $data, $linkUserId) {
            $doctor->update($data);
            $this->applyLink($doctor, $linkUserId);
        });

        AuditLog::record('ubah_dokter', 'doctor', $doctor->id, [
            'akun_ditautkan' => $linkUserId,
        ]);

        return redirect()->route('admin.doctors.show', $doctor)
            ->with('success', 'Data dokter berhasil diperbarui.');
    }

    public function toggle(Doctor $doctor)
    {
        $doctor->update(['is_active' => ! $doctor->is_active]);

        AuditLog::record('ubah_status_dokter', 'doctor', $doctor->id, ['is_active' => $doctor->is_active]);

        return back()->with('success', 'Status dokter diperbarui: '.($doctor->is_active ? 'Aktif' : 'Nonaktif').'.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'registration_number' => ['nullable', 'string', 'max:64'],
            'specialization' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:190'],
        ];

        if ($ignoreId) {
            $rules['email'][] = 'unique:doctors,email,'.$ignoreId;
        } else {
            $rules['email'][] = 'unique:doctors,email';
        }

        return $request->validate($rules, [
            'name.required' => 'Nama dokter wajib diisi.',
            'email.unique' => 'Email sudah digunakan profil dokter lain.',
        ]);
    }

    /**
     * Akun ber-role Dokter yang masih bebas ditautkan:
     - belum menjadi milik profil dokter lain (akun milik profil ini sendiri tetap ikut tampil agar terlihat di form).
     */
    private function linkableUsers(?Doctor $doctor)
    {
        return User::where('role', 'dokter')
            ->where('is_active', true)
            ->whereNotIn('id', Doctor::whereNotNull('user_id')
                ->when($doctor !== null, fn ($q) => $q->where('id', '!=', $doctor->id))
                ->pluck('user_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    /** Validasi pilihan penautan akun pada form dokter. Null berarti lepas tautan. */
    private function validatedLink(Request $request, ?Doctor $doctor = null): ?int
    {
        $value = $request->input('link_user_id');

        if ($value === null || $value === '') {
            return null;
        }

        $user = User::where('role', 'dokter')->where('is_active', true)->find((int) $value);

        if (! $user) {
            throw ValidationException::withMessages([
                'link_user_id' => 'Akun yang dipilih bukan akun Dokter aktif.',
            ]);
        }

        $takenBy = Doctor::where('user_id', $user->id)
            ->when($doctor !== null, fn ($q) => $q->where('id', '!=', $doctor->id))
            ->first();

        if ($takenBy) {
            throw ValidationException::withMessages([
                'link_user_id' => 'Akun '.$user->email.' sudah ditautkan ke profil dokter '.$takenBy->code.'. Lepaskan dulu dari profil tersebut.',
            ]);
        }

        return $user->id;
    }

    /** Terapkan penautan akun ke profil dokter + isi email profil bila masih kosong. */
    private function applyLink(Doctor $doctor, ?int $userId): void
    {
        $doctor->update([
            'user_id' => $userId,
            'email' => $doctor->email ?: ($userId !== null ? User::find($userId)->email : $doctor->email),
        ]);
    }
}
