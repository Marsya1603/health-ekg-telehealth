<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\EkgSample;
use App\Models\EkgSession;
use Illuminate\Http\Request;

class EcgController extends Controller
{
    public function index(Request $request)
    {
        $patient = $request->user()->patient;

        $sessions = $patient
            ? $patient->sessions()->with('device')->latest('started_at')->paginate(15)->withQueryString()
            : collect();

        return view('patient.ecg.index', [
            'patient' => $patient,
            'sessions' => $sessions,
            'visible' => (bool) config('healthekg.patient_ecg_visible', true),
        ]);
    }

    public function show(Request $request, EkgSession $session)
    {
        // Pasien hanya dapat melihat sesi miliknya sendiri (anti manipulasi URL).
        $patient = $request->user()->patient;
        abort_unless($patient && $session->patient_id === $patient->id, 403,
            'Data pemeriksaan ini bukan milik Anda.');

        if (! config('healthekg.patient_ecg_visible', true)) {
            // Kebijakan: grafik hanya boleh diakses tenaga kesehatan.
            return view('patient.ecg.restricted', ['session' => $session]);
        }

        $session->load(['patient', 'doctor', 'device']);

        $samples = EkgSample::where('ekg_session_id', $session->id)
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

        return view('patient.ecg.show', [
            'session' => $session,
            'initialSamples' => $samples,
            'pollUrl' => route('patient.ecg.data', $session),
            'canEnd' => false,
        ]);
    }

    public function data(Request $request, EkgSession $session)
    {
        $patient = $request->user()->patient;

        abort_unless($patient && $session->patient_id === $patient->id, 403);

        return \App\Http\Controllers\Doctor\MonitoringController::samplePayload($session);
    }
}
