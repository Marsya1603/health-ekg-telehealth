@extends('layouts.app')
@section('title', 'Monitoring EKG')
@section('content')
<div class="page-head">
    <div>
        <h1>Monitoring EKG</h1>
        <div class="page-desc">Pilih sesi untuk membuka halaman pemantauan. Halaman monitoring adalah halaman utama pemantauan dokter.</div>
    </div>
</div>

<div class="card">
    <h2 class="card-title"><x-icon name="activity"/> Sesi Sedang Berjalan</h2>
    @if($activeSessions->isEmpty())
        <x-empty-state title="Tidak ada sesi yang sedang berjalan"
                       message="Sesi berjalan ketika perangkat pasien mulai mengirim data. Status dihitung dari data aktual." icon="activity"/>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Pasien</th><th>Device</th><th>Mulai</th><th>Sampel</th><th>Terakhir Diterima</th><th></th></tr></thead>
                <tbody>
                @foreach($activeSessions as $s)
                    <tr>
                        <td><strong>{{ $s->patient?->name }}</strong></td>
                        <td class="mono small">{{ $s->device?->device_uid }}</td>
                        <td class="small">{{ $s->started_at?->locale('id')->translatedFormat('d M Y H:i:s') }}</td>
                        <td>{{ number_format($s->sample_count, 0, ',', '.') }}</td>
                        <td class="small">{{ $s->device?->last_message_at?->locale('id')->diffForHumans() ?? '-' }}</td>
                        <td><a class="btn btn-accent btn-sm" href="{{ route('doctor.monitoring.show', $s) }}">Buka Monitoring</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="card">
    <h2 class="card-title"><x-icon name="clock"/> Sesi Terbaru</h2>
    @if($recentSessions->isEmpty())
        <x-empty-state title="Belum ada sesi" icon="clock"/>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Pasien</th><th>Mulai</th><th>Sampel</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @foreach($recentSessions as $s)
                    <tr>
                        <td>{{ $s->patient?->name }}</td>
                        <td class="small">{{ $s->started_at?->locale('id')->translatedFormat('d M Y H:i') }}</td>
                        <td>{{ number_format($s->sample_count, 0, ',', '.') }}</td>
                        <td><x-badge :status="$s->status"/></td>
                        <td><a class="btn btn-outline btn-sm" href="{{ route('doctor.monitoring.show', $s) }}">Buka</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
