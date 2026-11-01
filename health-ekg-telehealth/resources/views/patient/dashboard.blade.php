@extends('layouts.app')
@section('title', 'Dashboard Saya')
@section('content')
<div class="page-head">
    <div>
        <h1>Dashboard Saya</h1>
        <div class="page-desc">Informasi pemantauan milik Anda sendiri, dari data aktual sistem.</div>
    </div>
    <div class="page-actions">
        <a href="{{ route('patient.ecg.index') }}" class="btn btn-accent"><x-icon name="heart"/> Pemantauan EKG Saya</a>
    </div>
</div>

@if(!$patient)
    <div class="card">
        <x-empty-state title="Profil pasien belum ditautkan" message="Akun Anda belum terhubung ke profil pasien. Silakan hubungi petugas Puskesmas." icon="users"/>
    </div>
@else
    {{-- Grafik EKG realtime: tampil saat ada sesi pemeriksaan Anda yang sedang berjalan --}}
    @if($activeSession && $ecgVisible)
        @include('partials.ecg-view', [
            'session' => $activeSession,
            'initialSamples' => $initialSamples,
            'pollUrl' => $pollUrl,
            'canEnd' => false,
            'hideMetaCards' => true,
            'hideNote' => true,
        ])
    @elseif($activeSession && !$ecgVisible)
        <div class="card">
            <h2 class="card-title"><x-icon name="activity"/> EKG Realtime</h2>
            <p>Anda memiliki sesi pemeriksaan yang sedang berjalan. Sesuai kebijakan puskesmas,
                grafik EKG hanya dapat dilihat oleh tenaga kesehatan.</p>
        </div>
    @else
        <div class="card">
            <div class="card-head">
                <div>
                    <h2 class="card-title"><x-icon name="activity"/> EKG Realtime</h2>
                    <p class="card-sub">Grafik langsung dari perangkat Anda.</p>
                </div>
            </div>
            <x-empty-state title="Tidak ada sesi pemeriksaan yang sedang berjalan"
                message="Ketika perangkat Anda mulai dipantau (sesi aktif), grafik EKG realtime akan tampil otomatis di sini."
                icon="activity"/>
        </div>
    @endif

    <div class="grid grid-6">
        <x-stat-card title="Nama Pasien" value="{{ $patient->name }}" icon="user" tone="cyan"/>
        <x-stat-card title="Patient ID" value="{{ $patient->code }}" icon="users" tone="navy"/>
        <x-stat-card title="Dokter Penanggung Jawab" value="{{ $patient->doctor?->name ?? 'Belum ditetapkan' }}" icon="heart" tone="cyan"/>
        <x-stat-card title="Jumlah Sesi Tercatat" value="{{ number_format($sessionCount, 0, ',', '.') }}" icon="activity" tone="navy"/>
    </div>

    <div class="grid grid-2">
        <div class="card">
            <h2 class="card-title">Status Pemantauan</h2>
            <p><x-badge :status="$deviceStatus === 'aktif' ? 'aktif' : ($deviceStatus === 'idle' ? 'menunggu' : 'unknown')">{{ $deviceStatusText }}</x-badge></p>
            <dl class="detail-list" style="margin-top:10px">
                <dt>Perangkat</dt><dd>{{ $device?->name ?? 'Belum ada perangkat terdaftar' }}</dd>
                <dt>Waktu Data Terakhir</dt>
                <dd>{{ $device?->last_message_at?->locale('id')->translatedFormat('d M Y H:i:s') ?? 'Belum pernah menerima data' }}</dd>
            </dl>
            <div class="method-note">
                Keterangan: "Menunggu data" berarti perangkat terdaftar tetapi belum mengirim data baru.
                Sistem tidak mengklaim perangkat offline tanpa pemeriksaan yang terverifikasi.
            </div>
        </div>

        <div class="card">
            <h2 class="card-title">Pemeriksaan Terbaru</h2>
            @if($recentSessions->isEmpty())
                <x-empty-state title="Belum ada pemeriksaan" message="Pemeriksaan Anda akan tampil di sini setelah perangkat mulai mengirim data." icon="activity"/>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Tanggal</th><th>Session ID</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        @foreach($recentSessions as $s)
                            <tr>
                                <td class="small">{{ $s->started_at?->locale('id')->translatedFormat('d M Y H:i') }}</td>
                                <td class="mono small">{{ \Illuminate\Support\Str::limit($s->session_code, 12) }}</td>
                                <td><x-badge :status="$s->status"/></td>
                                <td><a class="btn btn-outline btn-sm" href="{{ route('patient.ecg.show', $s) }}">Lihat Detail</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endif
@endsection
