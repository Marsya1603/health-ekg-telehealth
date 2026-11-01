@extends('layouts.app')
@section('title', 'Network Operations Dashboard')
@section('content')
<div class="page-head">
    <div>
        <h1>Network Operations Dashboard</h1>
        <div class="page-desc">
            Kesehatan infrastruktur komunikasi dan ringkasan QoS dari data transmisi aktual.
            Metrik tanpa sumber pengukuran ditampilkan sebagai "belum ada data", bukan angka buatan.
        </div>
    </div>
    <div class="page-actions">
        <form method="GET">
            <select name="range" class="btn btn-outline" style="padding:7px 10px" onchange="this.form.submit()">
                <option value="60" {{ $rangeMinutes === 60 ? 'selected' : '' }}>1 jam terakhir</option>
                <option value="360" {{ $rangeMinutes === 360 ? 'selected' : '' }}>6 jam terakhir</option>
                <option value="1440" {{ $rangeMinutes === 1440 ? 'selected' : '' }}>24 jam terakhir</option>
                <option value="4320" {{ $rangeMinutes === 4320 ? 'selected' : '' }}>3 hari terakhir</option>
                <option value="10080" {{ $rangeMinutes === 10080 ? 'selected' : '' }}>7 hari terakhir</option>
            </select>
        </form>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2 class="card-title"><x-icon name="server"/> Infrastruktur</h2>
        @foreach($infra as $name => $s)
            <div class="service-row">
                <strong>{{ ['database' => 'Database', 'backend' => 'API Backend', 'broker' => 'Broker MQTT', 'worker' => 'Worker Pemrosesan', 'scheduler' => 'Scheduler'][$name] ?? $name }}</strong>
                <x-badge :status="$s['status'] === 'ok' ? 'aktif' : ($s['status'] === 'error' ? 'error' : 'unknown')"/>
                <span class="service-detail">{{ $s['detail'] }}</span>
            </div>
        @endforeach
    </div>

    <div class="card">
        <h2 class="card-title"><x-icon name="wifi"/> Perangkat</h2>
        <dl class="detail-list">
            <dt>Perangkat Terdaftar (total)</dt><dd>{{ number_format($deviceStats['total'], 0, ',', '.') }}</dd>
            <dt>Terregistrasi Aktif</dt><dd>{{ number_format($deviceStats['registered'], 0, ',', '.') }}</dd>
            <dt>Terverifikasi Mengirim Data</dt>
            <dd>{{ number_format($deviceStats['active'], 0, ',', '.') }} <span class="muted small">(pesan terakhir &lt; {{ config('healthekg.device.heartbeat_timeout') }} detik)</span></dd>
        </dl>
    </div>
</div>

<div class="card">
    <h2 class="card-title"><x-icon name="activity"/> Ringkasan QoS
        <span class="muted small" style="font-weight:400">({{ $from->locale('id')->translatedFormat('d M H:i') }} — {{ $to->locale('id')->translatedFormat('d M H:i') }})</span>
    </h2>
    @if(!$qos['has_data'])
        <x-empty-state title="Belum ada data pengukuran"
                       message="Tidak ada paket transmisi pada rentang waktu ini. Metrik akan muncul setelah perangkat mengirim data melalui worker." icon="activity"/>
    @else
        <div class="grid grid-6" style="margin-bottom:4px">
            <div>
                <div class="stat-title">Average Delay</div>
                <div class="stat-value" style="font-size:1.2rem">{{ $qos['avg_delay_ms'] !== null ? number_format($qos['avg_delay_ms'], 1).' ms' : 'Belum ada' }}</div>
            </div>
            <div>
                <div class="stat-title">Throughput</div>
                <div class="stat-value" style="font-size:1.2rem">{{ $qos['throughput_bps'] !== null ? number_format($qos['throughput_bps'] / 1000, 2).' kbps' : 'Belum ada' }}</div>
            </div>
            <div>
                <div class="stat-title">Packet Loss (estimasi gap)</div>
                <div class="stat-value" style="font-size:1.2rem">{{ $qos['loss_pct'] !== null ? number_format($qos['loss_pct'], 2).' %' : 'Belum ada' }}</div>
            </div>
            <div>
                <div class="stat-title">Paket Valid Diterima</div>
                <div class="stat-value" style="font-size:1.2rem">{{ number_format($qos['valid_packets'], 0, ',', '.') }}</div>
            </div>
            <div>
                <div class="stat-title">Paket Invalid / Gagal</div>
                <div class="stat-value" style="font-size:1.2rem">{{ number_format($qos['invalid_packets'], 0, ',', '.') }}</div>
            </div>
            <div>
                <div class="stat-title">Avg Payload (cipher+tag)</div>
                <div class="stat-value" style="font-size:1.2rem">{{ $qos['avg_payload_bytes'] !== null ? number_format($qos['avg_payload_bytes'], 0).' B' : 'Belum ada' }}</div>
            </div>
        </div>
        <div class="method-note">
            <strong>Metadata metrik:</strong> Satuan ms / kbps / % · Sumber: tabel transmission_logs (titik ukur worker server) ·
            Rentang: {{ $from->locale('id')->translatedFormat('d M Y H:i') }} s/d {{ $to->locale('id')->translatedFormat('d M Y H:i') }} ·
            Status data: aktual, dari paket yang diterima. Delay = one-way estimasi (received_at − device_time; memerlukan NTP di perangkat).
        </div>
    @endif
</div>

<div class="card">
    <h2 class="card-title"><x-icon name="bar-chart"/> Grafik Performa</h2>
    @if(!$qos['has_data'])
        <x-empty-state title="Belum ada data grafik" message="Grafik delay dan throughput muncul ketika ada transmisi pada rentang ini." icon="bar-chart"/>
    @else
        <div class="chart-box">
            <canvas id="qosChart"></canvas>
        </div>
    @endif
</div>

<div class="card">
    <h2 class="card-title"><x-icon name="alert-triangle"/> System Events Terbaru</h2>
    @if($events->isEmpty())
        <x-empty-state title="Belum ada event" message="Event sistem (worker berhenti, broker tidak dapat dihubungi, perangkat reconnect, dll.) muncul di sini." icon="alert-triangle"/>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Waktu</th><th>Komponen</th><th>Kejadian</th><th>Status</th><th>Pesan</th></tr></thead>
                <tbody>
                @foreach($events as $e)
                    <tr>
                        <td class="small">{{ $e->occurred_at?->locale('id')->translatedFormat('d M H:i:s') }}</td>
                        <td class="mono small">{{ $e->component }}</td>
                        <td class="small">{{ $e->event_type }}</td>
                        <td><x-badge :status="$e->status"/></td>
                        <td class="small">{{ \Illuminate\Support\Str::limit($e->message, 80) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@once
@push('scripts')
@if($qos['has_data'] ?? false)
<script>
    (function () {
        const series = @json($series);
        HEKG.initLineChart('qosChart', [
            {
                label: 'Delay rata-rata (ms)',
                data: series.filter(p => p.delay_ms !== null).map(p => ({ x: p.t, y: p.delay_ms })),
                color: HEKG.COLORS.cyan,
            },
            {
                label: 'Throughput (kbps)',
                data: series.filter(p => p.kbps > 0).map(p => ({ x: p.t, y: p.kbps })),
                color: HEKG.COLORS.navy,
                yAxisID: 'y',
            },
        ], { yLabel: 'Nilai', legend: true });
    })();
</script>
@endif
@endpush
@endonce
@endsection
