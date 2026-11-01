<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\EkgSession;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PatientController extends Controller
{
    public function index(Request $request)
    {
        $patients = Patient::query()
            ->with('doctor')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w
                    ->where('name', 'like', $term)
                    ->orWhere('medical_record_number', 'like', $term)
                    ->orWhere('code', 'like', $term));
            })
            ->when($request->filled('doctor'), fn ($q) => $q->where('doctor_id', $request->input('doctor')))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->input('status') === 'aktif'))
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        $doctors = Doctor::orderBy('name')->get();

        return view('admin.patients.index', compact('patients', 'doctors'));
    }

    public function create()
    {
        $doctors = Doctor::where('is_active', true)->orderBy('name')->get();

        return view('admin.patients.form', [
            'patient' => null,
            'doctors' => $doctors,
            'linkableUsers' => $this->linkableUsers(null),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $linkUserId = $this->validatedLink($request);

        $patient = DB::transaction(function () use ($data, $linkUserId) {
            // Patient ID dibuat unik oleh backend berdasarkan ID baris.
            $patient = Patient::create($data + ['code' => 'TMP']);
            $patient->forceFill(['code' => 'PST-'.str_pad((string) $patient->id, 5, '0', STR_PAD_LEFT)])->save();

            if ($linkUserId !== null) {
                $this->applyLink($patient, $linkUserId);
            }

            return $patient;
        });

        AuditLog::record('tambah_pasien', 'patient', $patient->id, [
            'code' => $patient->code, 'akun_ditautkan' => $linkUserId,
        ]);

        return redirect()->route('admin.patients.show', $patient)
            ->with('success', 'Pasien '.$patient->name.' berhasil didaftarkan dengan ID '.$patient->code.'.');
    }

    public function show(Patient $patient)
    {
        $patient->load(['doctor', 'devices', 'user']);
        $sessions = $patient->sessions()->with('device')->latest('started_at')->limit(10)->get();
        $sessionCount = $patient->sessions()->count();

        return view('admin.patients.show', compact('patient', 'sessions', 'sessionCount'));
    }

    public function edit(Patient $patient)
    {
        $doctors = Doctor::orderBy('name')->get();

        return view('admin.patients.form', [
            'patient' => $patient,
            'doctors' => $doctors,
            'linkableUsers' => $this->linkableUsers($patient),
        ]);
    }

    public function update(Request $request, Patient $patient)
    {
        $data = $this->validated($request, $patient->id);
        $linkUserId = $this->validatedLink($request, $patient);

        DB::transaction(function () use ($patient, $data, $linkUserId) {
            $patient->update($data);
            $this->applyLink($patient, $linkUserId);
        });

        AuditLog::record('ubah_pasien', 'patient', $patient->id, [
            'akun_ditautkan' => $linkUserId,
        ]);

        return redirect()->route('admin.patients.show', $patient)
            ->with('success', 'Data pasien berhasil diperbarui.');
    }

    public function toggle(Request $request, Patient $patient)
    {
        $patient->update(['is_active' => ! $patient->is_active]);

        AuditLog::record('ubah_status_pasien', 'patient', $patient->id, [
            'is_active' => $patient->is_active,
        ]);

        return back()->with('success', 'Status pasien diperbarui: '.($patient->is_active ? 'Aktif' : 'Nonaktif').'.');
    }

    public function export(Request $request)
    {
        abort_unless(config('healthekg.exports.enabled'), 403, 'Fitur ekspor dinonaktifkan oleh konfigurasi.');

        AuditLog::record('ekspor_pasien', null, null);

        $filename = 'pasien-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Patient ID', 'No Rekam Medis', 'Nama', 'Tanggal Lahir', 'Jenis Kelamin', 'Dokter', 'Telepon', 'Email', 'Tanggal Registrasi', 'Status']);

            Patient::query()->with('doctor')->orderBy('id')->chunk(200, function ($rows) use ($out) {
                foreach ($rows as $p) {
                    fputcsv($out, [
                        $p->code, $p->medical_record_number, $p->name,
                        $p->birth_date?->format('Y-m-d'),
                        $p->gender === 'L' ? 'Laki-laki' : ($p->gender === 'P' ? 'Perempuan' : null),
                        $p->doctor?->name, $p->phone, $p->email,
                        $p->registered_at?->format('Y-m-d'),
                        $p->is_active ? 'Aktif' : 'Nonaktif',
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $uniqueMrn = 'unique:patients,medical_record_number';
        if ($ignoreId) {
            $uniqueMrn .= ','.$ignoreId;
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'medical_record_number' => ['required', 'string', 'max:64', $uniqueMrn],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', 'in:L,P'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:190'],
            'address' => ['nullable', 'string', 'max:500'],
            'doctor_id' => ['required', 'exists:doctors,id'],
            'registered_at' => ['nullable', 'date'],
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'medical_record_number.required' => 'Nomor rekam medis wajib diisi.',
            'medical_record_number.unique' => 'Nomor rekam medis sudah digunakan.',
            'doctor_id.required' => 'Dokter penanggung jawab wajib dipilih.',
        ]);
    }

    /**
     * Akun ber-role Pasien yang masih bebas ditautkan:
     - belum menjadi milik profil pasien lain (akun milik profil ini sendiri tetap ikut tampil agar terlihat di form).
     */
    private function linkableUsers(?Patient $patient)
    {
        return User::where('role', 'pasien')
            ->where('is_active', true)
            ->whereNotIn('id', Patient::whereNotNull('user_id')
                ->when($patient !== null, fn ($q) => $q->where('id', '!=', $patient->id))
                ->pluck('user_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    /** Validasi pilihan penautan akun pada form pasien. Null berarti lepas tautan. */
    private function validatedLink(Request $request, ?Patient $patient = null): ?int
    {
        $value = $request->input('link_user_id');

        if ($value === null || $value === '') {
            return null;
        }

        $user = User::where('role', 'pasien')->where('is_active', true)->find((int) $value);

        if (! $user) {
            throw ValidationException::withMessages([
                'link_user_id' => 'Akun yang dipilih bukan akun Pasien aktif.',
            ]);
        }

        $takenBy = Patient::where('user_id', $user->id)
            ->when($patient !== null, fn ($q) => $q->where('id', '!=', $patient->id))
            ->first();

        if ($takenBy) {
            throw ValidationException::withMessages([
                'link_user_id' => 'Akun '.$user->email.' sudah ditautkan ke profil pasien '.$takenBy->code.'. Lepaskan dulu dari profil tersebut.',
            ]);
        }

        return $user->id;
    }

    /** Terapkan penautan akun ke profil pasien + isi email profil bila masih kosong. */
    private function applyLink(Patient $patient, ?int $userId): void
    {
        $patient->update([
            'user_id' => $userId,
            'email' => $patient->email ?: ($userId !== null ? User::find($userId)->email : $patient->email),
        ]);
    }
}
