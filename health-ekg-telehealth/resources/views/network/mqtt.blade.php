@extends('layouts.app')
@section('title', 'MQTT Monitoring')
@section('content')
<div class="page-head">
    <div>
        <h1>MQTT Monitoring</h1>
        <div class="page-desc">
            Statistik pesan dari log transmisi aktual 24 jam terakhir. Isi payload kesehatan tidak ditampilkan di halaman ini.
        </div>
    </div>
</div>

<div class="grid grid-4">
    <x-stat-card title="Broker" value="{{ config('healthekg.mqtt.host') }}:{{ config('healthekg.mqtt.port') }}" hint="Cek TCP dilakukan pada dashboard utama" icon="radio" tone="navy"/>
    <x-stat-card title="Topic Pattern" value="{{ $topic }}" hint="Kredensial tidak ditampilkan" icon="shield" tone="cyan"/>
    <x-stat-card title="QoS Level" value="QoS {{ $qos }}" icon="settings" tone="cyan"/>
    <x-stat-card title="Pesan Terakhir" value="{{ $lastMessage?->received_at?->locale('id')->translatedFormat('H:i:s.v') ?? 'Belum ada' }}"
                 hint="{{ $lastMessage ? 'dari '.$lastMessage->device_uid : 'menunggu pesan pertama' }}" icon="clock" tone="navy"/>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2 class="card-title">Status Pesan (24 jam)</h2>
        @if($stats24h->isEmpty())
            <x-empty-state title="Belum ada pesan" message="Belum ada pesan yang diproses worker pada 24 jam terakhir." icon="radio"/>
        @else
            <dl class="detail-list">
                @foreach(['valid' => 'Berhasil diproses', 'diterima' => 'Masuk (belum final)', 'duplikat' => 'Duplikat ditolak', 'invalid' => 'Invalid', 'gagal' => 'Gagal diproses'] as $key => $label)
                    <dt>{{ $label }}</dt>
                    <dd>{{ number_format($stats24h[$key] ?? 0, 0, ',', '.') }}</dd>
                @endforeach
            </dl>
        @endif
    </div>

    <div class="card">
        <h2 class="card-title">Detail Validasi (24 jam)</h2>
        @if($statsByValidation->isEmpty())
            <x-empty-state title="Belum ada data validasi" icon="shield"/>
        @else
            <dl class="detail-list">
                @foreach($statsByValidation as $key => $total)
                    <dt class="mono">{{ $key }}</dt>
                    <dd>{{ number_format($total, 0, ',', '.') }}</dd>
                @endforeach
            </dl>
        @endif

        <h2 class="card-title" style="margin-top:14px">Status Worker & Reconnect</h2>
        @if($reconnects->isEmpty())
            <p class="muted small">Belum ada event reconnect tercatat.</p>
        @else
            <ul class="small" style="padding-left:18px;margin:0">
                @foreach($reconnects as $r)
                    <li>{{ $r->occurred_at?->locale('id')->translatedFormat('d M H:i') }} — {{ $r->event_type }} ({{ $r->status }})</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-head">
        <h2 class="card-title">Log Pesan MQTT</h2>
        <form method="GET" class="toolbar" style="margin:0">
            <div class="field">
                <label>Device</label>
                <input type="text" name="device" value="{{ request('device') }}" placeholder="DEV-...">
            </div>
            <div class="field">
                <label>Status</label>
                <select name="status">
                    <option value="">Semua</option>
                    @foreach(['valid', 'diterima', 'duplikat', 'invalid', 'gagal'] as $st)
                        <option value="{{ $st }}" {{ request('status') === $st ? 'selected' : '' }}>{{ $st }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        </form>
    </div>

    @if($logs->isEmpty())
        <x-empty-state title="Belum ada log" message="Log muncul setelah worker menerima pesan dari broker." icon="list"/>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Timestamp</th><th>Device</th><th>Paket</th><th>QoS Level</th>
                    <th>Message Status</th><th>Processing Status</th><th>Error Code</th>
                </tr>
                </thead>
                <tbody>
                @foreach($logs as $l)
                    <tr>
                        <td class="small">{{ $l->received_at?->locale('id')->translatedFormat('d M H:i:s.v') }}</td>
                        <td class="mono small">{{ $l->device_uid }}</td>
                        <td class="mono small">{{ $l->packet_number ? '#'.$l->packet_number : '-' }}</td>
                        <td class="small">QoS {{ $qos }}</td>
                        <td><x-badge :status="$l->status"/></td>
                        <td class="mono small">{{ $l->validation_status ?? '-' }}</td>
                        <td class="mono small">{{ $l->error_code ?? '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $logs->links() }}
    @endif
</div>
@endsection
