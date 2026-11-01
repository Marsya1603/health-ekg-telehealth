@extends('layouts.app')
@section('title', 'Dashboard Dokter')
@section('content')
<div class="page-head">
    <div>
        <h1>Dashboard Dokter</h1>
        <div class="page-desc">
            @if($doctor)
                Selamat datang, {{ $doctor->name }}. Semua angka dihitung hanya dari pasien yang menjadi kewenangan Anda.
            @else
                Akun Anda belum ditautkan ke profil dokter. Hubungi Admin Puskesmas.
            @endif
        </div>
    </div>
    <div class="page-actions">
        <a href="{{ route('doctor.monitoring.index') }}" class="btn btn-accent"><x-icon name="activity"/> Buka Monitoring</a>
    </div>
</div>

@if(!$doctor)
    <div class="card">
        <x-empty-state title="Profil dokter belum ditautkan" message="Akun ini valid, tetapi belum terhubung ke profil dokter di sistem. Hubungi Admin Puskesmas untuk penautan akun." icon="users"/>
    </div>
@else
    {{-- Grafik EKG realtime: tampil otomatis ketika ada sesi aktif milik pasien dalam kewenangan dokter --}}
    @if($activeSession)
        @include('partials.ecg-view', [
            'session' => $activeSession,
            'initialSamples' => $initialSamples,
            'pollUrl' => $pollUrl,
            'canEnd' => false,
            'hideMetaCards' => true,
            'hideNote' => true,
        ])
    @else
        <div class="card">
            <div class="card-head">
                <div>
                    <h2 class="card-title"><x-icon name="activity"/> EKG Realtime</h2>
                    <p class="card-sub">Grafik langsung dari perangkat pasien Anda.</p>
                </div>
            </div>
            <x-empty-state title="Tidak ada sesi pemeriksaan yang sedang berjalan"
                message="Ketika pasien Anda mulai dipantau perangkat (sesi aktif), grafik EKG realtime akan tampil otomatis di sini — tanpa perlu membuka halaman monitoring."
                icon="activity"/>
        </div>
    @endif

    <div class="grid grid-3">
        <x-stat-card title="Pasien Ditugaskan" value="{{ number_format($stats['patients'], 0, ',', '.') }}" icon="users" tone="cyan"/>
        <x-stat-card title="Sesi Hari Ini" value="{{ number_format($stats['sessions_today'], 0, ',', '.') }}" icon="clock" tone="navy"/>
        <x-stat-card title="Sesi Sedang Berjalan" value="{{ number_format($stats['sessions_active'], 0, ',', '.') }}" icon="activity" tone="green"/>
    </div>

    <div class="grid grid-2">
        <div class="card">
            <div class="card-head">
                <h2 class="card-title"><x-icon name="users"/> Pasien Terbaru</h2>
                <a class="btn btn-outline btn-sm" href="{{ route('doctor.patients.index') }}">Semua Pasien</a>
            </div>
            @if($patients->isEmpty())
                <x-empty-state title="Belum ada pasien yang ditugaskan" message="Admin Puskesmas menugaskan pasien melalui Data Pasien." icon="users"/>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                        <tr><th>Patient ID</th><th>Nama</th><th>Sesi Terakhir</th><th>Status Data</th><th></th></tr>
                        </thead>
                        <tbody>
                        @foreach($patients as $p)
                            @php
                                $dev = $p->primaryDevice;
                                [$st, $stText] = $dev?->dataStatus() ?? ['unknown', 'Belum ada perangkat'];
                            @endphp
                            <tr>
                                <td class="mono">{{ $p->code }}</td>
                                <td>{{ $p->name }}</td>
                                <td class="small">{{ $p->sessions_max_started_at ? \Illuminate\Support\Carbon::parse($p->sessions_max_started_at)->locale('id')->translatedFormat('d M H:i') : 'Belum ada' }}</td>
                                <td><x-badge :status="$st">{{ $st }}</x-badge></td>
                                <td><a class="btn btn-outline btn-sm" href="{{ route('doctor.patients.show', $p) }}">Detail</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="card">
            <div class="card-head">
                <h2 class="card-title"><x-icon name="activity"/> Aktivitas Monitoring</h2>
            </div>
            @if($recentSessions->isEmpty())
                <x-empty-state title="Belum ada sesi pemeriksaan" message="Sesi muncul ketika pasien Anda mulai dipantau oleh perangkat." icon="activity"/>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                        <tr><th>Pasien</th><th>Mulai</th><th>Sampel</th><th>Status</th><th>Aksi</th></tr>
                        </thead>
                        <tbody>
                        @foreach($recentSessions as $s)
                            <tr>
                                <td>{{ $s->patient?->name }}</td>
                                <td class="small">{{ $s->started_at?->locale('id')->translatedFormat('d M H:i') }}</td>
                                <td>{{ number_format($s->sample_count, 0, ',', '.') }}</td>
                                <td><x-badge :status="$s->status"/></td>
                                <td>
                                    <div class="actions">
                                        <a class="btn btn-accent btn-sm" href="{{ route('doctor.monitoring.show', $s) }}">Monitoring</a>
                                        <a class="btn btn-outline btn-sm" href="{{ route('doctor.patients.show', $s->patient) }}">Riwayat</a>
                                    </div>
                                </td>
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
