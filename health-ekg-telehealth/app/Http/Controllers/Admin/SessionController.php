<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\EkgSession;
use App\Models\SystemEvent;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function index(Request $request)
    {
        $sessions = EkgSession::query()
            ->with(['patient', 'doctor', 'device'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('device'), fn ($q) => $q->whereHas('device', fn ($d) => $d->where('device_uid', $request->input('device'))))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w
                    ->where('session_code', 'like', $term)
                    ->orWhereHas('patient', fn ($p) => $p->where('name', 'like', $term)));
            })
            ->when($request->filled('from'), fn ($q) => $q->whereDate('started_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('started_at', '<=', $request->input('to')))
            ->latest('started_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.sessions.index', compact('sessions'));
    }

    public function show(Request $request, EkgSession $session)
    {
        $session->load(['patient', 'doctor', 'device']);

        // Akses administrasi tercatat pada audit log.
        AuditLog::record('buka_sesi_pemeriksaan', 'ekg_session', $session->id);

        $packetStats = [
            'total' => $session->packet_count,
            'samples' => $session->sample_count,
        ];

        $lastPackets = $session->packets()
            ->latest('received_at')
            ->limit(10)
            ->get(['packet_number', 'sample_count', 'device_time', 'received_at', 'payload_size', 'encrypted_size']);

        return view('admin.sessions.show', compact('session', 'packetStats', 'lastPackets'));
    }

    public function end(Request $request, EkgSession $session)
    {
        if ($session->status === 'aktif') {
            $session->forceFill([
                'ended_at' => now(),
                'status' => 'dihentikan',
            ])->save();

            AuditLog::record('akhiri_sesi', 'ekg_session', $session->id);
            SystemEvent::log('backend', 'sesi_dihentikan', 'info', null,
                'Sesi '.$session->session_code.' diakhiri oleh admin '.auth()->user()->email);
        }

        return back()->with('success', 'Sesi telah ditandai berakhir.');
    }
}
