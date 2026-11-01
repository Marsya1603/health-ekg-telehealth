<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\EkgSession;
use App\Models\Doctor;
use App\Models\ExaminationNote;
use App\Models\Patient;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        // Semua angka dihitung dari database aktual.
        // Angka 0 hanya ditampilkan bila query berhasil dan datanya memang nol.
        $stats = [
            'patients' => Patient::count(),
            'doctors_active' => Doctor::where('is_active', true)->count(),
            'users' => User::count(),
            'devices' => Device::count(),
        ];

        // "Perangkat terverifikasi aktif" = perangkat yang pesan terakhirnya
        // berada dalam ambang heartbeat (data nyata, bukan asumsi).
        $timeout = (int) config('healthekg.device.heartbeat_timeout', 300);
        $stats['devices_active'] = Device::where('last_message_at', '>=', now()->subSeconds($timeout))->count();

        $activities = AuditLog::with('user')
            ->latest('created_at')
            ->limit(8)
            ->get();

        $recentSessions = EkgSession::with(['patient', 'doctor', 'device'])
            ->latest('started_at')
            ->limit(8)
            ->get();

        $recentNotes = ExaminationNote::with(['patient', 'doctor'])
            ->latest('created_at')
            ->limit(5)
            ->get();

        return view('admin.dashboard', compact(
            'stats', 'activities', 'recentSessions', 'recentNotes'
        ));
    }
}
