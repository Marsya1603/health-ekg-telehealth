<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\EkgSession;
use App\Models\Patient;
use Illuminate\Http\Request;

class MyPatientController extends Controller
{
    /** Data pasien milik dokter - selalu difilter berdasarkan penugasan (backend enforcement). */
    public static function patientsQuery(int $userId)
    {
        $doctor = Doctor::where('user_id', $userId)->first();

        return $doctor ? Patient::where('doctor_id', $doctor->id) : Patient::whereRaw('1 = 0');
    }

    public static function currentDoctor(Request $request): ?Doctor
    {
        return Doctor::where('user_id', $request->user()->id)->first();
    }

    public function index(Request $request)
    {
        $timeout = (int) config('healthekg.device.heartbeat_timeout', 300);

        $patients = self::patientsQuery($request->user()->id)
            ->with(['primaryDevice', 'doctor'])
            ->withMax('sessions', 'started_at')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w
                    ->where('name', 'like', $term)
                    ->orWhere('code', 'like', $term)
                    ->orWhere('medical_record_number', 'like', $term));
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('doctor.patients.index', compact('patients', 'timeout'));
    }

    public function show(Request $request, Patient $patient)
    {
        // Kewenangan: dokter hanya boleh melihat pasien yang ditugaskan kepadanya.
        abort_unless($patient->doctor_id === self::currentDoctor($request)?->id, 403,
            'Pasien ini bukan dalam kewenangan Anda.');

        $patient->load(['doctor', 'devices', 'user']);
        $sessions = $patient->sessions()->with('device')->latest('started_at')->limit(15)->get();
        $notes = $patient->notes()->with('doctor')->latest('created_at')->take(10)->get();

        return view('doctor.patients.show', compact('patient', 'sessions', 'notes'));
    }

    public static function ownsSession(Request $request, EkgSession $session): bool
    {
        $doctor = self::currentDoctor($request);

        return $doctor !== null && $session->patient?->doctor_id === $doctor->id;
    }
}
