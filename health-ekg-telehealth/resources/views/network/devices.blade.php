@extends('layouts.app')
@section('title', 'Device Connectivity')
@section('content')
<div class="page-head">
    <div>
        <h1>Device Connectivity</h1>
        <div class="page-desc">
            Status koneksi perangkat dihitung dari pesan terakhir yang benar-benar diterima worker (ambang
            {{ $timeout }} detik). Sistem tidak menyatakan offline tanpa aturan pemeriksaan yang jelas.
        </div>
    </div>
</div>

<div class="card">
    <form method="GET" class="toolbar">
        <div class="field" style="flex:1;max-width:240px">
            <label>Cari perangkat</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Device ID">
        </div>
        <div class="field">
            <label>Connection State</label>
            <select name="state">
                <option value="">Semua</option>
                <option value="aktif" {{ request('state') === 'aktif' ? 'selected' : '' }}>Aktif (data terbaru)</option>
                <option value="idle" {{ request('state') === 'idle' ? 'selected' : '' }}>Tidak ada data terbaru</option>
                <option value="unknown" {{ request('state') === 'unknown' ? 'selected' : '' }}>Belum pernah mengirim</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><x-icon name="search"/> Filter</button>
        <a href="{{ route('network.devices') }}" class="btn btn-outline">Reset</a>
    </form>

    @if($devices->isEmpty())
        <x-empty-state title="Belum ada perangkat" message="Registrasi perangkat dilakukan oleh Admin Puskesmas." icon="wifi"/>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Device ID</th><th>Connection State</th><th>Endpoint</th><th>Last Seen</th>
                    <th>Heartbeat Status</th><th>Paket Terakhir</th><th>Terhubung Pasien</th>
                </tr>
                </thead>
                <tbody>
                @foreach($devices as $dev)
                    @php [$st, $stText] = $dev->dataStatus(); @endphp
                    <tr>
                        <td class="mono">{{ $dev->device_uid }}</td>
                        <td><x-badge :status="$st">{{ $st }}</x-badge></td>
                        <td class="mono small">{{ config('healthekg.mqtt.host') }}:{{ config('healthekg.mqtt.port') }}</td>
                        <td class="small">{{ $dev->last_message_at?->locale('id')->translatedFormat('d M Y H:i:s') ?? 'Belum pernah' }}</td>
                        <td class="small">{{ $stText }}</td>
                        <td class="mono small">{{ $dev->last_packet_number ? '#'.$dev->last_packet_number : '-' }}</td>
                        <td class="small">{{ $dev->patient?->name ?? '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $devices->links() }}
    @endif

    <div class="method-note">
        <strong>Catatan metode:</strong> jika sistem tidak memiliki heartbeat khusus, status diturunkan dari waktu pesan
        terakhir; perangkat yang belum pernah mengirim ditampilkan "Belum pernah mengirim" (abu-abu), bukan offline.
    </div>
</div>

<div class="card">
    <h2 class="card-title"><x-icon name="alert-triangle"/> Connection Events Terbaru</h2>
    @if($events->isEmpty())
        <x-empty-state title="Belum ada event koneksi" icon="alert-triangle"/>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Waktu</th><th>Kejadian</th><th>Status</th><th>Pesan</th></tr></thead>
                <tbody>
                @foreach($events as $e)
                    <tr>
                        <td class="small">{{ $e->occurred_at?->locale('id')->translatedFormat('d M H:i:s') }}</td>
                        <td class="small">{{ $e->event_type }}</td>
                        <td><x-badge :status="$e->status"/></td>
                        <td class="small">{{ \Illuminate\Support\Str::limit($e->message, 90) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
