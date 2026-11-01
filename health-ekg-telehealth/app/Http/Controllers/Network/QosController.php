<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\EkgSession;
use App\Models\NetworkMetric;
use App\Services\QosService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class QosController extends Controller
{
    public function monitoring(Request $request)
    {
        [$from, $to] = $this->range($request);
        $device = $request->input('device');

        $qos = QosService::summary($from, $to, $device);
        $series = QosService::timeSeries($from, $to, $device, $from->diffInMinutes($to) <= 360 ? 5 : 15);

        $devices = Device::orderBy('device_uid')->get(['device_uid']);

        return view('network.qos', [
            'qos' => $qos,
            'series' => $series,
            'devices' => $devices,
            'from' => $from,
            'to' => $to,
            'device' => $device,
        ]);
    }

    public function payload(Request $request)
    {
        [$from, $to] = $this->range($request);

        $rows = collect(QosService::payloadByDevice($from, $to));
        $sec = QosService::securitySummary($from, $to);

        // Metadata format frame (konstanta terdokumentasi, bukan pengukuran):
        $frameFixed = [
            'magic' => 4, 'version' => 1, 'type' => 1, 'dev_len' => 2,
            'session' => 36, 'packet' => 4, 'timestamp' => 8,
            'sampling_rate' => 2, 'sample_count' => 2, 'nonce' => 16, 'tag' => 16,
        ];
        $frameFixedBytes = array_sum($frameFixed);

        return view('network.payload', [
            'rows' => $rows,
            'sec' => $sec,
            'from' => $from,
            'to' => $to,
            'frameFixed' => $frameFixed,
            'frameFixedBytes' => $frameFixedBytes,
        ]);
    }

    public function reports(Request $request)
    {
        [$from, $to] = $this->range($request);
        $device = $request->input('device');

        // Agregat per metrik: rata-rata, minimum, maksimum, jumlah sampel.
        $delayStats = $this->stats('transmission_logs', 'delay_ms', $from, $to, $device, 'received_at');
        $sizeStats = $this->stats('transmission_logs', 'encrypted_size', $from, $to, $device, 'received_at');
        $decryptStats = $this->stats('security_logs', 'processing_ms', $from, $to, $device, 'occurred_at', 'dekripsi_ok');

        $qos = QosService::summary($from, $to, $device);
        $metrics = NetworkMetric::query()
            ->when($device, fn ($q) => $q->where('device_uid', $device))
            ->whereBetween('recorded_at', [$from, $to])
            ->orderByDesc('recorded_at')
            ->limit(50)
            ->get();

        return view('network.reports', [
            'delayStats' => $delayStats,
            'sizeStats' => $sizeStats,
            'decryptStats' => $decryptStats,
            'qos' => $qos,
            'metrics' => $metrics,
            'devices' => Device::orderBy('device_uid')->get(['device_uid']),
            'from' => $from,
            'to' => $to,
            'device' => $device,
        ]);
    }

    public function export(Request $request)
    {
        abort_unless(config('healthekg.exports.enabled'), 403, 'Fitur ekspor dinonaktifkan oleh konfigurasi.');

        [$from, $to] = $this->range($request);
        $device = $request->input('device');

        AuditLog::record('ekspor_laporan_qos', null, null, [
            'from' => $from->toIso8601String(), 'to' => $to->toIso8601String(), 'device' => $device,
        ]);

        $filename = 'laporan-qos-'.$from->format('Ymd-Hi').'-'.$to->format('Ymd-Hi').'.csv';

        return response()->streamDownload(function () use ($from, $to, $device) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Laporan QoS HealthEKG (sumber: transmission_logs / security_logs)']);
            fputcsv($out, ['Periode', $from->toIso8601String(), $to->toIso8601String()]);
            fputcsv($out, ['Device', $device ?? 'Semua']);
            fputcsv($out, []);
            fputcsv($out, ['Metrik', 'Rata-rata', 'Minimum', 'Maksimum', 'Jumlah Sampel', 'Satuan', 'Metode']);

            $delay = $this->stats('transmission_logs', 'delay_ms', $from, $to, $device, 'received_at');
            fputcsv($out, array_merge(['Delay one-way (estimasi)'], $delay ? [$delay['avg'], $delay['min'], $delay['max'], $delay['count'], 'ms', 'received_at - device_time'] : ['-', '-', '-', 0, 'ms', 'received_at - device_time']));

            $size = $this->stats('transmission_logs', 'encrypted_size', $from, $to, $device, 'received_at');
            fputcsv($out, array_merge(['Ukuran ciphertext+tag'], $size ? [$size['avg'], $size['min'], $size['max'], $size['count'], 'byte', 'ukuran lapisan aplikasi'] : ['-', '-', '-', 0, 'byte', 'ukuran lapisan aplikasi']));

            $dec = $this->stats('security_logs', 'processing_ms', $from, $to, $device, 'occurred_at', 'dekripsi_ok');
            fputcsv($out, array_merge(['Waktu dekripsi ASCON-128'], $dec ? [$dec['avg'], $dec['min'], $dec['max'], $dec['count'], 'ms', 'durasi proses worker'] : ['-', '-', '-', 0, 'ms', 'durasi proses worker']));

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Agregat min/avg/max/count portable (dihitung di PHP). */
    private function stats(string $table, string $column, $from, $to, ?string $device, string $timeCol, ?string $eventFilter = null): ?array
    {
        if ($table === 'security_logs') {
            $rows = \App\Models\SecurityLog::query()
                ->when($device, fn ($q) => $q->where('device_uid', $device))
                ->when($eventFilter, fn ($q) => $q->where('event_type', $eventFilter))
                ->whereBetween($timeCol, [$from, $to])
                ->get([$column]);
        } else {
            $rows = \App\Models\TransmissionLog::query()
                ->when($device, fn ($q) => $q->where('device_uid', $device))
                ->whereBetween($timeCol, [$from, $to])
                ->get([$column]);
        }

        $values = $rows->pluck($column)->filter(fn ($v) => $v !== null)->map(fn ($v) => (float) $v);

        if ($values->isEmpty()) {
            return null;
        }

        return [
            'avg' => round($values->avg(), 2),
            'min' => round($values->min(), 2),
            'max' => round($values->max(), 2),
            'count' => $values->count(),
        ];
    }

    private function range(Request $request): array
    {
        $from = $request->filled('from')
            ? CarbonImmutable::parse($request->input('from'))->startOfDay()
            : CarbonImmutable::now()->subDay()->startOfDay();

        $to = $request->filled('to')
            ? CarbonImmutable::parse($request->input('to'))->endOfDay()
            : CarbonImmutable::now();

        if ($to->lt($from)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        return [$from, $to];
    }
}
