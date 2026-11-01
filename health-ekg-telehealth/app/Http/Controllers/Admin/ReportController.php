<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\EkgSession;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        [$from, $to] = $this->range($request);

        $summary = [
            'patients' => Patient::whereBetween('registered_at', [$from->toDateString(), $to->toDateString()])->count(),
            'patients_total' => Patient::count(),
            'doctors_active' => User::where('role', 'dokter')->where('is_active', true)->count(),
            'devices' => Device::count(),
        ];

        $byStatus = EkgSession::query()
            ->whereBetween('started_at', [$from, $to->endOfDay()])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $byDay = EkgSession::query()
            ->whereBetween('started_at', [$from, $to->endOfDay()])
            ->get(['started_at'])
            ->groupBy(fn ($s) => $s->started_at?->format('Y-m-d'))
            ->map(fn ($rows) => count($rows))
            ->sortKeys();

        $topDevices = Device::query()
            ->withCount(['sessions' => fn ($q) => $q->whereBetween('started_at', [$from, $to->endOfDay()])])
            ->orderByDesc('sessions_count')
            ->limit(10)
            ->get();

        return view('admin.reports.index', compact('summary', 'byStatus', 'byDay', 'topDevices', 'from', 'to'));
    }

    public function export(Request $request)
    {
        abort_unless(config('healthekg.exports.enabled'), 403, 'Fitur ekspor dinonaktifkan oleh konfigurasi.');

        [$from, $to] = $this->range($request);
        AuditLog::record('ekspor_laporan_admin', null, null, ['from' => $from->toDateString(), 'to' => $to->toDateString()]);

        $filename = 'laporan-admin-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($from, $to) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Ringkasan Administrasi HealthEKG']);
            fputcsv($out, ['Periode', $from->format('Y-m-d').' s/d '.$to->format('Y-m-d')]);
            fputcsv($out, []);

            fputcsv($out, ['Metrik', 'Nilai']);
            fputcsv($out, ['Pasien terdaftar pada periode', Patient::whereBetween('registered_at', [$from->toDateString(), $to->toDateString()])->count()]);
            fputcsv($out, ['Total pasien terdaftar', Patient::count()]);
            fputcsv($out, ['Dokter aktif', User::where('role', 'dokter')->where('is_active', true)->count()]);
            fputcsv($out, ['Perangkat terdaftar', Device::count()]);
            fputcsv($out, []);
            fputcsv($out, ['Sesi per status']);

            EkgSession::query()
                ->whereBetween('started_at', [$from, $to->endOfDay()])
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->get()
                ->each(fn ($r) => fputcsv($out, [$r->status, $r->total]));

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function range(Request $request): array
    {
        $from = $request->filled('from')
            ? \Carbon\CarbonImmutable::parse($request->input('from'))->startOfDay()
            : \Carbon\CarbonImmutable::now()->subDays(29)->startOfDay();

        $to = $request->filled('to')
            ? \Carbon\CarbonImmutable::parse($request->input('to'))->endOfDay()
            : \Carbon\CarbonImmutable::now()->endOfDay();

        if ($to->lt($from)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        return [$from->startOfDay(), $to->endOfDay()];
    }
}
