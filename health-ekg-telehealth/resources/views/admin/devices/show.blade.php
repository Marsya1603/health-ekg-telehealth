@extends('layouts.app')
@section('title', 'Detail Perangkat')
@section('content')
<div class="page-head">
    <div>
        <h1>Perangkat <span class="mono">{{ $device->device_uid }}</span></h1>
        <div class="page-desc">{{ $device->name }} · {{ $device->type ?? 'Tipe belum diisi' }}</div>
    </div>
    <div class="page-actions">
        <a href="{{ route('admin.devices.index') }}" class="btn btn-outline">Kembali</a>
        <a href="{{ route('admin.devices.edit', $device) }}" class="btn btn-primary"><x-icon name="edit"/> Edit</a>
        <form method="POST" action="{{ route('admin.devices.toggle', $device) }}"
              data-confirm="{{ $device->is_registered ? 'Nonaktifkan registrasi perangkat ini? Worker akan menolak paket dari perangkat yang sudah nonaktif.' : 'Aktifkan kembali registrasi perangkat?' }}">
            @csrf
            <button type="submit" class="btn {{ $device->is_registered ? 'btn-danger' : 'btn-accent' }}">
                {{ $device->is_registered ? 'Nonaktifkan Registrasi' : 'Aktifkan Registrasi' }}
            </button>
        </form>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2 class="card-title">Informasi Perangkat</h2>
        <dl class="detail-list">
            <dt>Device ID</dt><dd class="mono">{{ $device->device_uid }}</dd>
            <dt>Nama</dt><dd>{{ $device->name }}</dd>
            <dt>Tipe</dt><dd>{{ $device->type ?? '-' }}</dd>
            <dt>Pasien Terkait</dt><dd>{{ $device->patient?->name ?? 'Belum dihubungkan' }}</dd>
            <dt>Firmware</dt><dd class="mono">{{ $device->firmware_version ?? '-' }}</dd>
            <dt>Terdaftar Pada</dt><dd>{{ $device->created_at?->format('d-m-Y H:i') }}</dd>
            <dt>Status Registrasi</dt><dd><x-badge :status="$device->is_registered ? 'aktif' : 'nonaktif'"/></dd>
            <dt>Catatan Teknis</dt><dd>{{ $device->notes ?? '-' }}</dd>
        </dl>
    </div>

    <div class="card">
        <h2 class="card-title">Konektivitas</h2>
        <dl class="detail-list">
            <dt>Pesan Terakhir</dt><dd>{{ $device->last_message_at?->locale('id')->translatedFormat('d M Y H:i:s') ?? 'Belum pernah menerima pesan' }}</dd>
            <dt>Nomor Paket Terakhir</dt><dd class="mono">{{ $device->last_packet_number ? '#'.$device->last_packet_number : '-' }}</dd>
        </dl>
        <div style="margin-top:8px">
            <x-badge :status="$statusKey">{{ $statusText }}</x-badge>
        </div>
        <div class="method-note">
            Status dihitung dari pesan terakhir yang diproses worker dengan ambang
            {{ config('healthekg.device.heartbeat_timeout') }} detik. Sistem tidak menyatakan perangkat offline
            tanpa aturan pemeriksaan yang jelas.
        </div>

        <h2 class="card-title" style="margin-top:16px">Event Konektivitas</h2>
        @if($events->isEmpty())
            <p class="muted small">Belum ada event konektivitas tercatat.</p>
        @else
            <ul class="small" style="margin:4px 0 0;padding-left:18px">
                @foreach($events as $e)
                    <li>{{ $e->occurred_at?->locale('id')->translatedFormat('d M H:i') }} — {{ $e->event_type }} ({{ $e->status }})</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>

<div class="card">
    <h2 class="card-title">Riwayat Sesi Perangkat</h2>
    @if($sessions->isEmpty())
        <x-empty-state title="Belum ada sesi" message="Sesi pemeriksaan dari perangkat ini akan muncul setelah perangkat mengirim data." icon="activity"/>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Session ID</th><th>Pasien</th><th>Mulai</th><th>Sampel</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @foreach($sessions as $s)
                    <tr>
                        <td class="mono small">{{ \Illuminate\Support\Str::limit($s->session_code, 16) }}</td>
                        <td>{{ $s->patient?->name }}</td>
                        <td class="small">{{ $s->started_at?->locale('id')->translatedFormat('d M Y H:i') }}</td>
                        <td>{{ number_format($s->sample_count, 0, ',', '.') }}</td>
                        <td><x-badge :status="$s->status"/></td>
                        <td></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="card">
    <h2 class="card-title">Log Komunikasi Terakhir</h2>
    @if($logs->isEmpty())
        <x-empty-state title="Belum ada log" message="Log transmisi perangkat ini akan muncul setelah ada pertukaran data." icon="list"/>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Waktu</th><th>Paket</th><th>Tipe</th><th>Status</th><th>Error</th></tr></thead>
                <tbody>
                @foreach($logs as $l)
                    <tr>
                        <td class="small">{{ $l->received_at?->locale('id')->translatedFormat('d M H:i:s') }}</td>
                        <td class="mono small">{{ $l->packet_number ? '#'.$l->packet_number : '-' }}</td>
                        <td class="small">{{ $l->message_type }}</td>
                        <td><x-badge :status="$l->status"/></td>
                        <td class="mono small">{{ $l->error_code ?? '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
