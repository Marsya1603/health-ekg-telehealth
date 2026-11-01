@extends('layouts.app')
@section('title', 'Detail Dokter')
@section('content')
<div class="page-head">
    <div>
        <h1>{{ $doctor->name }}</h1>
        <div class="page-desc">Doctor ID <span class="mono">{{ $doctor->code }}</span></div>
    </div>
    <div class="page-actions">
        <a href="{{ route('admin.doctors.index') }}" class="btn btn-outline">Kembali</a>
        <a href="{{ route('admin.doctors.edit', $doctor) }}" class="btn btn-primary"><x-icon name="edit"/> Edit</a>
        <form method="POST" action="{{ route('admin.doctors.toggle', $doctor) }}"
              data-confirm="{{ $doctor->is_active ? 'Nonaktifkan akun dokter ini?' : 'Aktifkan kembali dokter ini?' }}">
            @csrf
            <button type="submit" class="btn {{ $doctor->is_active ? 'btn-danger' : 'btn-accent' }}">
                {{ $doctor->is_active ? 'Nonaktifkan Akun' : 'Aktifkan Akun' }}
            </button>
        </form>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2 class="card-title">Informasi Dokter</h2>
        <dl class="detail-list">
            <dt>Doctor ID</dt><dd class="mono">{{ $doctor->code }}</dd>
            <dt>Nama</dt><dd>{{ $doctor->name }}</dd>
            <dt>No Registrasi</dt><dd class="mono">{{ $doctor->registration_number ?? '-' }}</dd>
            <dt>Spesialisasi</dt><dd>{{ $doctor->specialization ?? '-' }}</dd>
            <dt>Telepon</dt><dd>{{ $doctor->phone ?? '-' }}</dd>
            <dt>Email</dt><dd>{{ $doctor->email ?? '-' }}</dd>
            <dt>Akun Pengguna</dt><dd>{{ $doctor->user?->email ?? 'Belum ditautkan' }}</dd>
            <dt>Status</dt><dd><x-badge :status="$doctor->is_active ? 'aktif' : 'nonaktif'"/></dd>
        </dl>
    </div>

    <div class="card">
        <h2 class="card-title">Pasien yang Ditugaskan ({{ $doctor->patients->count() }})</h2>
        @if($doctor->patients->isEmpty())
            <x-empty-state title="Belum ada pasien" message="Tugaskan pasien melalui form Data Pasien (pilih dokter penanggung jawab)." icon="users"/>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Patient ID</th><th>Nama</th><th>Status</th></tr></thead>
                    <tbody>
                    @foreach($doctor->patients as $p)
                        <tr>
                            <td class="mono">{{ $p->code }}</td>
                            <td><a href="{{ route('admin.patients.show', $p) }}">{{ $p->name }}</a></td>
                            <td><x-badge :status="$p->is_active ? 'aktif' : 'nonaktif'"/></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<div class="card">
    <h2 class="card-title">Sesi Terakhir yang Ditangani</h2>
    @if($sessions->isEmpty())
        <x-empty-state title="Belum ada sesi" message="Sesi pemeriksaan pasien yang ditugaskan akan muncul di sini." icon="activity"/>
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
@endsection
