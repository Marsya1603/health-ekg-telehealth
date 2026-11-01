<?php

namespace App\Services;

use App\Models\SecurityLog;
use App\Models\TransmissionLog;
use Carbon\CarbonImmutable;

/**
 * Perhitungan metrik QoS dari data transmisi aktual (tabel transmission_logs
 * dan security_logs). Semua hasil menyertakan metode perhitungan.
 *
 * Definisi metrik (titik ukur: worker di server):
 * - Delay (one-way, estimasi): received_at (server) - device_time (perangkat).
 *   Membutuhkan sinkronisasi NTP di perangkat; delay mencakup clock offset.
 * - Throughput: total byte ciphertext valid diterima / durasi rentang filter.
 *   Titik ukur: payload terenkripsi pada lapisan aplikasi (bukan frame TCP/IP).
 * - Packet loss (estimasi gap): per sesi, selisih antara rentang nomor paket
 *   yang diamati dengan jumlah nomor paket unik yang diterima.
 * - Payload size: ukuran plaintext dan ciphertext+tag per paket.
 * - Overhead ASCON: encrypted_size - payload_size (nonce+tag; format frame
 *   dihitung terpisah di halaman Payload Analysis).
 */
class QosService
{
    public static function summary(CarbonImmutable $from, CarbonImmutable $to, ?string $deviceUid = null): array
    {
        $logs = TransmissionLog::query()
            ->when($deviceUid, fn ($q, $v) => $q->where('device_uid', $v))
            ->whereBetween('received_at', [$from, $to])
            ->get([
                'device_uid', 'ekg_session_id', 'packet_number', 'status',
                'payload_size', 'encrypted_size', 'delay_ms', 'received_at',
            ]);

        if ($logs->isEmpty()) {
            return ['has_data' => false, 'from' => $from, 'to' => $to, 'device' => $deviceUid];
        }

        $valid = $logs->where('status', 'valid');

        $delays = $valid->pluck('delay_ms')->filter(fn ($v) => $v !== null);
        $encSizes = $valid->pluck('encrypted_size')->filter(fn ($v) => $v !== null);

        $totalBytes = (int) $encSizes->sum();
        $windowSeconds = max(1, $from->diffInSeconds($to));
        $throughputBps = ($totalBytes * 8) / $windowSeconds;

        // Estimasi packet loss dari gap nomor paket per sesi.
        $bySession = $valid->filter(fn ($l) => $l->ekg_session_id && $l->packet_number !== null)
            ->groupBy('ekg_session_id');
        $spanTotal = 0;
        $receivedTotal = 0;
        foreach ($bySession as $rows) {
            $nums = $rows->pluck('packet_number')->unique()->values();
            $span = $nums->max() - $nums->min() + 1;
            $spanTotal += $span;
            $receivedTotal += $nums->count();
        }
        $lossPct = $spanTotal > 0 ? (($spanTotal - $receivedTotal) / $spanTotal) * 100 : null;

        return [
            'has_data' => true,
            'from' => $from,
            'to' => $to,
            'device' => $deviceUid,
            'total_packets' => $logs->count(),
            'valid_packets' => $valid->count(),
            'invalid_packets' => $logs->whereIn('status', ['invalid', 'gagal'])->count(),
            'duplicate_packets' => $logs->where('status', 'duplikat')->count(),
            'avg_delay_ms' => $delays->isNotEmpty() ? $delays->avg() : null,
            'max_delay_ms' => $delays->isNotEmpty() ? $delays->max() : null,
            'throughput_bps' => $encSizes->isNotEmpty() ? $throughputBps : null,
            'avg_payload_bytes' => $encSizes->isNotEmpty() ? $encSizes->avg() : null,
            'loss_pct' => $lossPct,
            'loss_span' => $spanTotal,
            'loss_gap' => $spanTotal - $receivedTotal,
            'window_seconds' => $windowSeconds,
            'total_bytes' => $totalBytes,
        ];
    }

    /** Deret waktu per bucket menit untuk grafik (dihitung di PHP agar portabel). */
    public static function timeSeries(CarbonImmutable $from, CarbonImmutable $to, ?string $deviceUid = null, int $bucketMinutes = 10): array
    {
        $rows = TransmissionLog::query()
            ->when($deviceUid, fn ($q, $v) => $q->where('device_uid', $v))
            ->whereBetween('received_at', [$from, $to])
            ->get(['status', 'delay_ms', 'encrypted_size', 'received_at']);

        $buckets = [];
        $step = max(60, $bucketMinutes * 60);
        $cursor = $from->startOfMinute()->getTimestamp();

        while ($cursor <= $to->getTimestamp()) {
            $buckets[$cursor] = ['t' => $cursor * 1000, 'delay' => [], 'bytes' => 0, 'count' => 0];
            $cursor += $step;
        }

        foreach ($rows as $row) {
            $ts = $row->received_at?->getTimestamp();
            if ($ts === null) {
                continue;
            }
            $key = intdiv($ts, $step) * $step;
            if (! isset($buckets[$key])) {
                continue;
            }
            if ($row->status === 'valid' && $row->delay_ms !== null) {
                $buckets[$key]['delay'][] = (float) $row->delay_ms;
            }
            if ($row->status === 'valid' && $row->encrypted_size !== null) {
                $buckets[$key]['bytes'] += (int) $row->encrypted_size;
            }
            $buckets[$key]['count']++;
        }

        $out = [];
        foreach ($buckets as $b) {
            $out[] = [
                't' => $b['t'],
                'delay_ms' => $b['delay'] ? round(array_sum($b['delay']) / count($b['delay']), 2) : null,
                'kbps' => round(($b['bytes'] * 8) / $step / 1000, 3),
                'packets' => $b['count'],
            ];
        }

        return $out;
    }

    /** Ringkasan keamanan ASCON-128 dari security_logs. */
    public static function securitySummary(CarbonImmutable $from, CarbonImmutable $to, ?string $deviceUid = null): array
    {
        $rows = SecurityLog::query()
            ->when($deviceUid, fn ($q, $v) => $q->where('device_uid', $v))
            ->whereBetween('occurred_at', [$from, $to])
            ->get(['event_type', 'processing_ms', 'payload_plain_size', 'payload_cipher_size']);

        if ($rows->isEmpty()) {
            return ['has_data' => false];
        }

        $ok = $rows->where('event_type', 'dekripsi_ok');
        $times = $ok->pluck('processing_ms')->filter(fn ($v) => $v !== null);
        $plain = $ok->pluck('payload_plain_size')->filter(fn ($v) => $v !== null);
        $cipher = $ok->pluck('payload_cipher_size')->filter(fn ($v) => $v !== null);

        return [
            'has_data' => true,
            'total' => $rows->count(),
            'ok' => $ok->count(),
            'tag_invalid' => $rows->where('event_type', 'tag_invalid')->count(),
            'format_invalid' => $rows->where('event_type', 'format_invalid')->count(),
            'decrypt_fail' => $rows->where('event_type', 'dekripsi_gagal')->count(),
            'nonce_error' => $rows->where('event_type', 'nonce_error')->count(),
            'unknown_device' => $rows->where('event_type', 'perangkat_tak_dikenal')->count(),
            'avg_decrypt_ms' => $times->isNotEmpty() ? $times->avg() : null,
            'avg_plain_bytes' => $plain->isNotEmpty() ? $plain->avg() : null,
            'avg_cipher_bytes' => $cipher->isNotEmpty() ? $cipher->avg() : null,
        ];
    }

    /** Perbandingan payload per perangkat (untuk Payload Analysis). */
    public static function payloadByDevice(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $logs = TransmissionLog::query()
            ->whereBetween('received_at', [$from, $to])
            ->where('status', 'valid')
            ->get(['device_uid', 'payload_size', 'encrypted_size', 'delay_ms']);

        $sec = SecurityLog::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->where('event_type', 'dekripsi_ok')
            ->get(['device_uid', 'processing_ms']);

        if ($logs->isEmpty()) {
            return [];
        }

        $grouped = $logs->groupBy('device_uid');
        $out = [];

        foreach ($grouped as $device => $rows) {
            $plain = $rows->pluck('payload_size')->filter(fn ($v) => $v !== null);
            $enc = $rows->pluck('encrypted_size')->filter(fn ($v) => $v !== null);
            $delays = $rows->pluck('delay_ms')->filter(fn ($v) => $v !== null);
            $decTimes = $sec->where('device_uid', $device)->pluck('processing_ms')->filter(fn ($v) => $v !== null);

            $out[] = [
                'device' => $device,
                'packets' => $rows->count(),
                'avg_plain' => $plain->isNotEmpty() ? round($plain->avg(), 1) : null,
                'avg_enc' => $enc->isNotEmpty() ? round($enc->avg(), 1) : null,
                'avg_overhead' => ($plain->isNotEmpty() && $enc->isNotEmpty()) ? round($enc->avg() - $plain->avg(), 1) : null,
                'avg_delay_ms' => $delays->isNotEmpty() ? round($delays->avg(), 2) : null,
                'avg_decrypt_ms' => $decTimes->isNotEmpty() ? round($decTimes->avg(), 2) : null,
            ];
        }

        return $out;
    }
}
