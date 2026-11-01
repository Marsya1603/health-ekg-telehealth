<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\EkgSample;
use App\Models\EkgSession;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $doctor = Doctor::where('user_id', $request->user()->id)->first();

        // Dokter tanpa profil penugasan: tampilkan dashboard kosong yang jujur.
        if (! $doctor) {
            return view('doctor.dashboard', [
                'doctor' => null,
                'stats' => null,
                'patients' => collect(),
                'recentSessions' => collect(),
                'activeSession' => null,
                'initialSamples' => collect(),
                'pollUrl' => null,
            ]);
        }

        $patientIds = $doctor->patients()->pluck('id');

        $stats = [
            'patients' => count($patientIds),
            'sessions_today' => EkgSession::whereIn('patient_id', $patientIds)->whereDate('started_at', today())->count(),
            'sessions_active' => EkgSession::whereIn('patient_id', $patientIds)->where('status', 'aktif')->count(),
        ];

        $patients = $doctor->patients()
            ->with('primaryDevice')
            ->withMax('sessions', 'started_at')
            ->orderBy('name')
            ->limit(8)
            ->get();

        $recentSessions = EkgSession::with(['patient', 'device'])
            ->whereIn('patient_id', $patientIds)
            ->latest('started_at')
            ->limit(8)
            ->get();

        // Grafik EKG realtime di dashboard: ambil SATU sesi aktif terbaru
        // dari pasien dalam kewenangan dokter (tanpa data simulasi).
        $activeSession = EkgSession::with(['patient', 'device'])
            ->whereIn('patient_id', $patientIds)
            ->where('status', 'aktif')
            ->latest('started_at')
            ->first();

        $initialSamples = collect();
        $pollUrl = null;

        if ($activeSession) {
            $initialSamples = EkgSample::where('ekg_session_id', $activeSession->id)
                ->orderByDesc('id')
                ->limit(1500)
                ->get()
                ->reverse()
                ->values()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    't' => (int) ($s->sampled_at?->getPreciseTimestamp(3) ?? 0),
                    'v' => (float) $s->value_mv,
                ]);

            $pollUrl = route('doctor.monitoring.data', $activeSession);
        }

        return view('doctor.dashboard', compact(
            'doctor', 'stats', 'patients', 'recentSessions', 'activeSession', 'initialSamples', 'pollUrl'
        ));
    }
}
