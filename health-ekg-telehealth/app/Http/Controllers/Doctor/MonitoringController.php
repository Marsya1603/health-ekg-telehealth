<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\EkgSample;
use App\Models\EkgSession;
use App\Models\SystemEvent;
use Illuminate\Http\Request;

class MonitoringController extends Controller
{
    public function index(Request $request)
    {
        $patientIds = MyPatientController::patientsQuery($request->user()->id)->pluck('id');

        $activeSessions = EkgSession::with(['patient', 'device'])
            ->whereIn('patient_id', $patientIds)
            ->where('status', 'aktif')
            ->latest('started_at')
            ->get();

        $recentSessions = EkgSession::with(['patient', 'device'])
            ->whereIn('patient_id', $patientIds)
            ->latest('started_at')
            ->limit(10)
            ->get();

        return view('doctor.monitoring.index', compact('activeSessions', 'recentSessions'));
    }

    public function show(Request $request, $session)
    {
        $session = EkgSession::with(['patient', 'doctor', 'device'])->findOrFail($session);

        // Pastikan sesi milik pasien dalam kewenangan dokter (cegah akses via URL).
        abort_unless(MyPatientController::ownsSession($request, $session), 403,
            'Sesi pemeriksaan ini bukan milik pasien yang Anda tanggung jawabkan.');

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

        return view('doctor.monitoring.show', [
            'session' => $session,
            'initialSamples' => $samples,
            'pollUrl' => route('doctor.monitoring.data', $session),
            'endUrl' => route('doctor.monitoring.end', $session),
            'canEnd' => $session->status === 'aktif',
        ]);
    }

    /** Endpoint polling JSON sampel EKG untuk pasien milik dokter ini. */
    public function data(Request $request, $session)
    {
        $session = EkgSession::with('patient')->findOrFail($session);

        abort_unless(MyPatientController::ownsSession($request, $session), 403);

        return $this->samplePayload($session);
    }

    public static function samplePayload($session): \Illuminate\Http\JsonResponse
    {
        $afterId = (int) request()->query('after_id', 0);

        $query = EkgSample::where('ekg_session_id', $session->id)
            ->orderBy('id')
            ->limit(2000);

        if ($afterId > 0) {
            $query->where('id', '>', $afterId);
        }

        $samples = $query->get()->map(fn ($s) => [
            'id' => $s->id,
            't' => (int) ($s->sampled_at?->getPreciseTimestamp(3) ?? 0),
            'v' => (float) $s->value_mv,
        ]);

        $lastPacket = $session->packets()->latest('received_at')->first();

        return response()->json([
            'session' => [
                'status' => $session->status,
                'sample_count' => $session->sample_count,
                'packet_count' => $session->packet_count,
            ],
            'device' => [
                'uid' => $session->device?->device_uid,
                'last_message_at' => $session->device?->last_message_at?->toIso8601String(),
            ],
            'last_packet_number' => $lastPacket?->packet_number,
            'samples' => $samples,
        ]);
    }

    public function end(Request $request, $session)
    {
        $session = EkgSession::findOrFail($session);

        abort_unless(MyPatientController::ownsSession($request, $session), 403);

        if ($session->status === 'aktif') {
            $session->forceFill([
                'ended_at' => now(),
                'status' => 'dihentikan',
            ])->save();

            SystemEvent::log('backend', 'sesi_dihentikan', 'info', null,
                'Sesi '.$session->session_code.' diakhiri oleh dokter '.auth()->user()->email);
        }

        return back()->with('success', 'Sesi pemeriksaan telah diakhiri.');
    }
}
