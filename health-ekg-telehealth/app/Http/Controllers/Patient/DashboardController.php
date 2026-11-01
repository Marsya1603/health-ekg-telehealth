<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\EkgSample;
use App\Models\EkgSession;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $patient = $request->user()->patient;

        if (! $patient) {
            return view('patient.dashboard', [
                'patient' => null,
                'device' => null,
                'deviceStatus' => null,
                'deviceStatusText' => null,
                'lastDataAt' => null,
                'sessionCount' => 0,
                'recentSessions' => collect(),
                'activeSession' => null,
                'initialSamples' => collect(),
                'pollUrl' => null,
                'ecgVisible' => (bool) config('healthekg.patient_ecg_visible', true),
            ]);
        }

        $device = $patient->devices()->orderBy('id')->first();
        [$statusKey, $statusText] = $device?->dataStatus() ?? ['unknown', 'Belum ada perangkat terdaftar'];

        $sessionCount = $patient->sessions()->count();
        $lastDataAt = $patient->sessions()->latest('started_at')->value('started_at');

        $recentSessions = $patient->sessions()
            ->with('device')
            ->latest('started_at')
            ->limit(5)
            ->get();

        // Grafik EKG realtime di dashboard: sesi aktif milik pasien ini sendiri.
        $activeSession = $patient->sessions()
            ->with('device')
            ->where('status', 'aktif')
            ->latest('started_at')
            ->first();

        $initialSamples = collect();
        $pollUrl = null;

        if ($activeSession && config('healthekg.patient_ecg_visible', true)) {
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

            $pollUrl = route('patient.ecg.data', $activeSession);
        }

        return view('patient.dashboard', [
            'patient' => $patient,
            'device' => $device,
            'deviceStatus' => $statusKey,
            'deviceStatusText' => $statusText,
            'lastDataAt' => $lastDataAt,
            'sessionCount' => $sessionCount,
            'recentSessions' => $recentSessions,
            'activeSession' => $activeSession,
            'initialSamples' => $initialSamples,
            'pollUrl' => $pollUrl,
            'ecgVisible' => (bool) config('healthekg.patient_ecg_visible', true),
        ]);
    }
}
