@extends('layouts.app')
@section('title', 'Dashboard Admin')
@section('content')
<div class="page-head">
    <div>
        <h1>Dashboard Admin Puskesmas</h1>
        <div class="page-desc">Ringkasan administrasi dari database aktual. Angka 0 ditampilkan hanya jika data memang kosong.</div>
    </div>
    <div class="page-actions">
        <a href="{{ route('admin.patients.create') }}" class="btn btn-accent"><x-icon name="plus"/> Tambah Pasien</a>
        <a href="{{ route('admin.doctors.create') }}" class="btn btn-outline"><x-icon name="plus"/> Tambah Dokter</a>
    </div>
</div>

<div class="grid grid-3">
    <x-stat-card title="Total Pasien Terdaftar" value="{{ number_format($stats['patients'], 0, ',', '.') }}" icon="users" tone="cyan"/>
    <x-stat-card title="Dokter Aktif" value="{{ number_format($stats['doctors_active'], 0, ',', '.') }}" icon="heart" tone="navy"/>
    <x-stat-card title="Akun Pengguna" value="{{ number_format($stats['users'], 0, ',', '.') }}" icon="user" tone="cyan"/>
    <x-stat-card title="Perangkat Terdaftar" value="{{ number_format($stats['devices'], 0, ',', '.') }}" icon="cpu" tone="navy"/>
    <x-stat-card title="Perangkat Terverifikasi Aktif" value="{{ number_format($stats['devices_active'], 0, ',', '.') }}"
                 hint="Pesan terakhir < {{ config('healthekg.device.heartbeat_timeout') }} detik" icon="wifi" tone="green"/>
</div>

<div class="card">
    <h2 class="card-title"><x-icon name="clock"/> Aktivitas Terbaru</h2>
    <p class="card-sub">Tindakan yang benar-benar tercatat pada audit log.</p>
    @if($activities->isEmpty())
        <x-empty-state title="Belum ada aktivitas" message="Aktivitas pengguna (tambah data, login, dan lainnya) akan muncul di sini."/>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Waktu</th><th>Aktivitas</th><th>Aktor</th><th>Objek</th></tr></thead>
                <tbody>
                @foreach($activities as $a)
                    <tr>
                        <td class="small">{{ $a->created_at?->locale('id')->translatedFormat('d M H:i') }}</td>
                        <td>{{ ucwords(str_replace('_', ' ', $a->action)) }}</td>
                        <td class="small">{{ $a->user?->name ?? 'Sistem' }}</td>
                        <td class="small mono">{{ $a->entity_type ?? '-' }}{{ $a->entity_id ? '#'.$a->entity_id : '' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="card">
    <div class="card-head">
        <div>
            <h2 class="card-title"><x-icon name="activity"/> Ringkasan Pemeriksaan Terbaru</h2>
            <p class="card-sub">Sesi pemeriksaan terakhir yang benar-benar ada di database. Pengelolaan sesi dilakukan oleh Dokter.</p>
        </div>
    </div>

    @if($recentSessions->isEmpty())
        <x-empty-state title="Belum ada sesi pemeriksaan" message="Sesi akan muncul otomatis ketika perangkat mengirim data pertama untuk pasien terdaftar."/>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Session ID</th><th>Pasien</th><th>Dokter</th><th>Device ID</th>
                    <th>Waktu Mulai</th><th>Status</th><th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($recentSessions as $s)
                    <tr>
                        <td class="mono small">{{ \Illuminate\Support\Str::limit($s->session_code, 14) }}</td>
                        <td>{{ $s->patient?->name }}</td>
                        <td>{{ $s->doctor?->name ?? '-' }}</td>
                        <td class="mono small">{{ $s->device?->device_uid }}</td>
                        <td class="small">{{ $s->started_at?->locale('id')->translatedFormat('d M Y H:i') }}</td>
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
    <div class="card-head">
        <div>
            <h2 class="card-title"><x-icon name="file-text"/> Catatan Pemeriksaan Terbaru</h2>
            <p class="card-sub">Catatan klinis yang ditulis dokter penanggung jawab.</p>
        </div>
        <a href="{{ route('admin.notes.index') }}" class="btn btn-outline btn-sm">Semua Catatan</a>
    </div>

    @if($recentNotes->isEmpty())
        <x-empty-state title="Belum ada catatan pemeriksaan" message="Catatan yang ditulis dokter akan tampil di sini." icon="file-text"/>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr><th>Waktu</th><th>Pasien</th><th>Dokter</th><th>Isi Catatan</th><th></th></tr>
                </thead>
                <tbody>
                @foreach($recentNotes as $note)
                    <tr>
                        <td class="small">{{ $note->created_at?->locale('id')->translatedFormat('d M H:i') }}</td>
                        <td>{{ $note->patient?->name ?? '-' }}</td>
                        <td>{{ $note->doctor?->name ?? '-' }}</td>
                        <td class="small">{{ \Illuminate\Support\Str::limit($note->body, 120) }}</td>
                        <td><a class="btn btn-outline btn-sm" href="{{ route('admin.notes.show', $note) }}">Detail</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
