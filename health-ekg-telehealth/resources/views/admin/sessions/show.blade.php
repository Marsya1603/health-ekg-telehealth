@extends('layouts.app')
@section('title', 'Detail Sesi Pemeriksaan')
@section('content')
<div class="page-head">
    <div>
        <h1>Sesi <span class="mono small">{{ $session->session_code }}</span></h1>
        <div class="page-desc">Metadata administrasi sesi. Akses ini tercatat pada audit log.</div>
    </div>
    <div class="page-actions">
        <a href="{{ route('admin.sessions.index') }}" class="btn btn-outline">Kembali</a>
        @if($session->status === 'aktif')
            <form method="POST" action="{{ route('admin.sessions.end', $session) }}" data-confirm="Tandai sesi ini berakhir?">
                @csrf
                <button type="submit" class="btn btn-danger"><x-icon name="stop"/> Akhiri Sesi</button>
            </form>
        @endif
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2 class="card-title">Metadata Sesi</h2>
        <dl class="detail-list">
            <dt>Pasien</dt><dd>{{ $session->patient?->name }} ({{ $session->patient?->code }})</dd>
            <dt>Dokter PJ</dt><dd>{{ $session->doctor?->name ?? '-' }}</dd>
            <dt>Device ID</dt><dd class="mono">{{ $session->device?->device_uid }}</dd>
            <dt>Waktu Mulai</dt><dd>{{ $session->started_at?->locale('id')->translatedFormat('d M Y H:i:s') ?? '-' }}</dd>
            <dt>Waktu Selesai</dt><dd>{{ $session->ended_at?->locale('id')->translatedFormat('d M Y H:i:s') ?? 'Sedang berjalan' }}</dd>
            <dt>Sampling Rate</dt><dd>{{ $session->sampling_rate ? $session->sampling_rate.' Hz (metadata perangkat)' : 'Belum ada data' }}</dd>
            <dt>Jumlah Sampel</dt><dd>{{ number_format($packetStats['samples'], 0, ',', '.') }}</dd>
            <dt>Jumlah Paket</dt><dd>{{ number_format($packetStats['total'], 0, ',', '.') }}</dd>
            <dt>Status</dt><dd><x-badge :status="$session->status"/></dd>
        </dl>
    </div>

    <div class="card">
        <h2 class="card-title">Paket Terakhir Diterima</h2>
        @if($lastPackets->isEmpty())
            <x-empty-state title="Belum ada paket" message="Paket EKG dari perangkat akan tercantum di sini." icon="list"/>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Paket</th><th>Sampel</th><th>Waktu Perangkat</th><th>Diterima</th><th>Plain</th><th>Cipher</th></tr></thead>
                    <tbody>
                    @foreach($lastPackets as $p)
                        <tr>
                            <td class="mono small">#{{ $p->packet_number }}</td>
                            <td>{{ $p->sample_count }}</td>
                            <td class="small">{{ $p->device_time?->format('H:i:s.v') ?? '-' }}</td>
                            <td class="small">{{ $p->received_at?->format('H:i:s.v') }}</td>
                            <td class="small">{{ $p->payload_size ? $p->payload_size.' B' : '-' }}</td>
                            <td class="small">{{ $p->encrypted_size ? $p->encrypted_size.' B' : '-' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
