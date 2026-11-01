@extends('layouts.app')
@section('title', 'QoS Test Reports')
@section('content')
<div class="page-head">
    <div>
        <h1>QoS Test Reports</h1>
        <div class="page-desc">
            Laporan agregat per periode/perangkat/sesi: rata-rata, minimum, maksimum, dan jumlah sampel.
            Hasil yang belum tersedia ditampilkan apa adanya (tidak dibuat-buat).
        </div>
    </div>
    <div class="page-actions">
        @if(config('healthekg.exports.enabled'))
            <a href="{{ route('network.reports.export', array_filter(['from' => request('from'), 'to' => request('to'), 'device' => request('device')])) }}"
               class="btn btn-outline"><x-icon name="download"/> Ekspor CSV</a>
        @endif
    </div>
</div>

<div class="card">
    <form method="GET" class="toolbar">
        <div class="field">
            <label>Dari</label>
            <input type="date" name="from" value="{{ request('from', $from->format('Y-m-d')) }}">
        </div>
        <div class="field">
            <label>Sampai</label>
            <input type="date" name="to" value="{{ request('to', $to->format('Y-m-d')) }}">
        </div>
        <div class="field">
            <label>Device</label>
            <select name="device">
                <option value="">Semua perangkat</option>
                @foreach($devices as $d)
                    <option value="{{ $d->device_uid }}" {{ $device === $d->device_uid ? 'selected' : '' }}>{{ $d->device_uid }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><x-icon name="refresh"/> Terapkan</button>
        <a href="{{ route('network.reports') }}" class="btn btn-outline">Reset</a>
    </form>
</div>

<div class="card">
    <h2 class="card-title">Agregat Metrik</h2>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr><th>Metrik</th><th>Rata-rata</th><th>Minimum</th><th>Maksimum</th><th>Jumlah Sampel</th><th>Sumber & Metode</th></tr>
            </thead>
            <tbody>
            <tr>
                <td>Delay one-way (estimasi)</td>
                <td>{{ $delayStats['avg'] ?? '-' }}{{ isset($delayStats) ? ' ms' : '' }}</td>
                <td>{{ $delayStats['min'] ?? '-' }}</td>
                <td>{{ $delayStats['max'] ?? '-' }}</td>
                <td>{{ $delayStats['count'] ?? 0 }}</td>
                <td class="small">transmission_logs · received_at − device_time</td>
            </tr>
            <tr>
                <td>Ukuran ciphertext+tag</td>
                <td>{{ $sizeStats['avg'] ?? '-' }}{{ isset($sizeStats) ? ' B' : '' }}</td>
                <td>{{ $sizeStats['min'] ?? '-' }}</td>
                <td>{{ $sizeStats['max'] ?? '-' }}</td>
                <td>{{ $sizeStats['count'] ?? 0 }}</td>
                <td class="small">transmission_logs · ukuran lapisan aplikasi</td>
            </tr>
            <tr>
                <td>Waktu dekripsi ASCON-128</td>
                <td>{{ $decryptStats['avg'] ?? '-' }}{{ isset($decryptStats) ? ' ms' : '' }}</td>
                <td>{{ $decryptStats['min'] ?? '-' }}</td>
                <td>{{ $decryptStats['max'] ?? '-' }}</td>
                <td>{{ $decryptStats['count'] ?? 0 }}</td>
                <td class="small">security_logs · durasi proses worker (dekripsi_ok)</td>
            </tr>
            <tr>
                <td>Throughput</td>
                <td>{{ ($qos['has_data'] ?? false) && $qos['throughput_bps'] !== null ? number_format($qos['throughput_bps'] / 1000, 2).' kbps' : '-' }}</td>
                <td>-</td><td>-</td>
                <td>{{ $qos['valid_packets'] ?? 0 }} paket</td>
                <td class="small">total bit / durasi rentang ({{ $qos['window_seconds'] ?? 0 }} dtk)</td>
            </tr>
            <tr>
                <td>Packet loss (estimasi gap)</td>
                <td>{{ ($qos['has_data'] ?? false) && $qos['loss_pct'] !== null ? number_format($qos['loss_pct'], 2).' %' : '-' }}</td>
                <td>-</td><td>-</td>
                <td>gap {{ $qos['loss_gap'] ?? 0 }}</td>
                <td class="small">gap nomor paket per sesi</td>
            </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h2 class="card-title">Metrik Pengukuran Tercatat (network_metrics)</h2>
    @if($metrics->isEmpty())
        <x-empty-state title="Belum ada metrik tercatat"
                       message="Tabel ini menyimpan hasil pengukuran/pengujian eksplisit (mis. dari sesi uji terdokumentasi). Agregasi harian dihitung dari log transmisi."
                       icon="file-text"/>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr><th>Waktu</th><th>Metrik</th><th>Nilai</th><th>Unit</th><th>Sampel</th><th>Periode</th><th>Metode</th><th>Sumber</th></tr>
                </thead>
                <tbody>
                @foreach($metrics as $m)
                    <tr>
                        <td class="small">{{ $m->recorded_at?->locale('id')->translatedFormat('d M Y H:i') }}</td>
                        <td class="mono small">{{ $m->metric }}</td>
                        <td><strong>{{ $m->value }}</strong></td>
                        <td>{{ $m->unit }}</td>
                        <td>{{ $m->samples ?? '-' }}</td>
                        <td class="small">{{ $m->period_start?->format('d M H:i') ?? '-' }} — {{ $m->period_end?->format('d M H:i') ?? '-' }}</td>
                        <td class="small">{{ $m->method ?? '-' }}</td>
                        <td class="small">{{ $m->source ?? '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
