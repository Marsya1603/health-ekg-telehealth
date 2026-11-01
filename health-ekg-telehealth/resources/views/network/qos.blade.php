@extends('layouts.app')
@section('title', 'QoS Monitoring')
@section('content')
<div class="page-head">
    <div>
        <h1>QoS Monitoring</h1>
        <div class="page-desc">
            Delay, throughput, dan packet loss dari pengukuran aktual worker. Semua metrik menyertakan satuan,
            sumber data, rentang pengukuran, dan metode.
        </div>
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
            <label>Device ID</label>
            <select name="device">
                <option value="">Semua perangkat</option>
                @foreach($devices as $d)
                    <option value="{{ $d->device_uid }}" {{ $device === $d->device_uid ? 'selected' : '' }}>{{ $d->device_uid }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><x-icon name="refresh"/> Terapkan</button>
        <a href="{{ route('network.qos') }}" class="btn btn-outline">Reset</a>
    </form>
</div>

@if(!($qos['has_data'] ?? false))
    <div class="card">
        <x-empty-state title="Belum ada data pengukuran"
                       message="Tidak ada paket transmisi pada rentang waktu/device ini. Metrik QoS hanya dihitung dari paket yang benar-benar diterima." icon="activity"/>
    </div>
@else
    <div class="grid grid-3">
        <div class="card">
            <h2 class="card-title"><x-icon name="clock"/> Delay</h2>
            <div class="stat-value">{{ $qos['avg_delay_ms'] !== null ? number_format($qos['avg_delay_ms'], 1).' ms' : 'Belum ada' }}</div>
            <p class="muted small">Rata-rata one-way · Maksimum: {{ $qos['max_delay_ms'] !== null ? number_format($qos['max_delay_ms'], 1).' ms' : '-' }}</p>
            <div class="method-note">
                <strong>Metode:</strong> received_at (server) − device_time (perangkat), per paket valid.
                Satuan: milidetik. Memerlukan NTP aktif di perangkat; nilai mencakup clock offset.
            </div>
        </div>

        <div class="card">
            <h2 class="card-title"><x-icon name="activity"/> Throughput</h2>
            <div class="stat-value">{{ $qos['throughput_bps'] !== null ? number_format($qos['throughput_bps'] / 1000, 2).' kbps' : 'Belum ada' }}</div>
            <p class="muted small">Total {{ number_format($qos['total_bytes'], 0, ',', '.') }} byte · Durasi rentang {{ number_format($qos['window_seconds'], 0, ',', '.') }} dtk</p>
            <div class="method-note">
                <strong>Rumus:</strong> Throughput = total bit diterima / durasi pengukuran.
                Titik ukur: payload ciphertext pada lapisan aplikasi (bukan byte frame TCP/IP).
            </div>
        </div>

        <div class="card">
            <h2 class="card-title"><x-icon name="alert-triangle"/> Packet Loss</h2>
            <div class="stat-value">{{ $qos['loss_pct'] !== null ? number_format($qos['loss_pct'], 2).' %' : 'Belum ada' }}</div>
            <p class="muted small">Gap {{ number_format($qos['loss_gap'], 0, ',', '.') }} dari {{ number_format($qos['loss_span'], 0, ',', '.') }} nomor paket yang diamati</p>
            <div class="method-note">
                <strong>Rumus:</strong> Loss% = (paket hilang / paket dikirim) × 100%.
                Paket "hilang" diestimasi dari gap nomor paket per sesi (tidak diarangkan bila jumlah kirim tidak diketahui).
            </div>
        </div>
    </div>

    <div class="card">
        <h2 class="card-title"><x-icon name="bar-chart"/> Grafik Performa</h2>
        <div class="chart-box">
            <canvas id="qosChart"></canvas>
        </div>
    </div>

    <div class="card">
        <h2 class="card-title">Payload Size</h2>
        <dl class="detail-list">
            <dt>Rata-rata ciphertext+tag</dt><dd>{{ $qos['avg_payload_bytes'] !== null ? number_format($qos['avg_payload_bytes'], 1).' byte' : 'Belum ada' }}</dd>
            <dt>Paket total / valid / invalid / duplikat</dt>
            <dd>{{ number_format($qos['total_packets'], 0, ',', '.') }} / {{ number_format($qos['valid_packets'], 0, ',', '.') }} / {{ number_format($qos['invalid_packets'], 0, ',', '.') }} / {{ number_format($qos['duplicate_packets'], 0, ',', '.') }}</dd>
        </dl>
        <div class="method-note">
            Ukuran nonce (16 B) dan authentication tag (16 B) ASCON-128 tercakup dalam ciphertext.
            Perbandingan detail tersedia di halaman Payload Analysis.
        </div>
    </div>
@endif

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
            },
        ], { yLabel: 'Nilai', legend: true });
    })();
</script>
@endif
@endpush
@endonce
@endsection
