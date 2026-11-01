<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\EkgSession;
use App\Models\EkgSample;
use Illuminate\Http\Request;

class EkgHistoryController extends Controller
{
    public function index(Request $request)
    {
        $sessions = EkgSession::query()
            ->with(['patient', 'device'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w
                    ->whereHas('patient', fn ($p) => $p->where('name', 'like', $term)->orWhere('code', 'like', $term))
                    ->orWhereHas('device', fn ($d) => $d->where('device_uid', 'like', $term))
                    ->orWhere('session_code', 'like', $term));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('started_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('started_at', '<=', $request->input('to')))
            ->where('sample_count', '>', 0)
            ->latest('started_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.ecg.index', compact('sessions'));
    }

    public function show(Request $request, EkgSession $session)
    {
        $session->load(['patient', 'doctor', 'device']);

        AuditLog::record('buka_riwayat_ekg', 'ekg_session', $session->id);

        // Sampel awal: batch terakhir dari sesi (maksimal 1500 titik).
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

        return view('admin.ecg.show', [
            'session' => $session,
            'initialSamples' => $samples,
            'pollUrl' => route('admin.ecg.data', $session),
            'canEnd' => false,
        ]);
    }

    /** Endpoint JSON polling sampel baru (cursor berdasarkan ID sampel). */
    public function data(Request $request, EkgSession $session)
    {
        $afterId = (int) $request->query('after_id', 0);

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
}
