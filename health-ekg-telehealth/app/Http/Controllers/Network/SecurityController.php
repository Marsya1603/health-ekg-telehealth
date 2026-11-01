<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\TransmissionLog;
use App\Services\QosService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class SecurityController extends Controller
{
    public function index(Request $request)
    {
        [$from, $to] = $this->range($request);

        $summary = QosService::securitySummary($from, $to);

        $events = \App\Models\SecurityLog::query()
            ->when($request->filled('device'), fn ($q) => $q->where('device_uid', $request->input('device')))
            ->latest('occurred_at')
            ->limit(30)
            ->get(['device_uid', 'packet_number', 'event_type', 'error_code', 'processing_ms', 'payload_plain_size', 'payload_cipher_size', 'occurred_at']);

        return view('network.security', [
            'summary' => $summary,
            'events' => $events,
            'devices' => Device::orderBy('device_uid')->get(['device_uid']),
            'from' => $from,
            'to' => $to,
            'device' => $request->input('device'),
        ]);
    }

    public function logs(Request $request)
    {
        $logs = TransmissionLog::query()
            ->when($request->filled('device'), fn ($q) => $q->where('device_uid', $request->input('device')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('received_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('received_at', '<=', $request->input('to')))
            ->when($request->filled('packet'), fn ($q, $v) => $q->where('packet_number', (int) $v))
            ->latest('received_at')
            ->paginate(25)
            ->withQueryString();

        return view('network.logs', [
            'logs' => $logs,
            'devices' => Device::orderBy('device_uid')->get(['device_uid']),
        ]);
    }

    public function exportLogs(Request $request)
    {
        abort_unless(config('healthekg.exports.enabled'), 403, 'Fitur ekspor dinonaktifkan oleh konfigurasi.');

        AuditLog::record('ekspor_log_transmisi', null, null, [
            'device' => $request->input('device'),
            'status' => $request->input('status'),
        ]);

        $filename = 'log-transmisi-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($request) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Timestamp', 'Device ID', 'Packet Number', 'Message Type', 'Frame Size', 'Payload Size', 'Encrypted Size', 'Status', 'Validation', 'Delay (ms)', 'Processing (ms)', 'Error Code', 'Source']);

            TransmissionLog::query()
                ->when($request->filled('device'), fn ($q) => $q->where('device_uid', $request->input('device')))
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
                ->latest('received_at')
                ->limit(50000)
                ->chunk(1000, function ($rows) use ($out) {
                    foreach ($rows as $r) {
                        fputcsv($out, [
                            $r->received_at?->toIso8601String(), $r->device_uid, $r->packet_number,
                            $r->message_type, $r->frame_size, $r->payload_size, $r->encrypted_size,
                            $r->status, $r->validation_status, $r->delay_ms, $r->processing_ms,
                            $r->error_code, $r->source,
                        ]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function range(Request $request): array
    {
        $from = $request->filled('from')
            ? CarbonImmutable::parse($request->input('from'))->startOfDay()
            : CarbonImmutable::now()->subDay();

        $to = $request->filled('to')
            ? CarbonImmutable::parse($request->input('to'))->endOfDay()
            : CarbonImmutable::now();

        return [$from, $to];
    }
}
