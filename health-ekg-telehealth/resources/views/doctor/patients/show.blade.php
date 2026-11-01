@extends('layouts.app')
@section('title', 'Detail Pasien')
@section('content')
<div class="page-head">
    <div>
        <h1>{{ $patient->name }}</h1>
        <div class="page-desc">Patient ID <span class="mono">{{ $patient->code }}</span> · Dokter penanggung jawab: {{ $patient->doctor?->name }}</div>
    </div>
    <div class="page-actions">
        <a href="{{ route('doctor.patients.index') }}" class="btn btn-outline">Kembali</a>
        @if($patient->sessions()->exists())
            <a href="{{ route('doctor.monitoring.index') }}" class="btn btn-accent"><x-icon name="activity"/> Buka Monitoring</a>
        @endif
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2 class="card-title">Identitas Pelayanan</h2>
        <dl class="detail-list">
            <dt>Patient ID</dt><dd class="mono">{{ $patient->code }}</dd>
            <dt>Nama</dt><dd>{{ $patient->name }}</dd>
            <dt>Tanggal Lahir</dt><dd>{{ $patient->birth_date?->format('d-m-Y') ?? '-' }}{{ $patient->birth_date ? ' ('.$patient->birth_date->age.' tahun)' : '' }}</dd>
            <dt>Jenis Kelamin</dt><dd>{{ $patient->gender === 'L' ? 'Laki-laki' : ($patient->gender === 'P' ? 'Perempuan' : '-') }}</dd>
            <dt>Telepon</dt><dd>{{ $patient->phone ?? '-' }}</dd>
            <dt>Waktu Data Terakhir</dt>
            <dd>{{ $patient->devices->max('last_message_at')?->locale('id')->translatedFormat('d M Y H:i') ?? 'Belum ada data' }}</dd>
        </dl>
    </div>

    <div class="card">
        <h2 class="card-title">Perangkat</h2>
        @if($patient->devices->isEmpty())
            <x-empty-state title="Belum ada perangkat terhubung" message="Pasien belum memiliki perangkat pemantauan yang terdaftar." icon="cpu"/>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Device</th><th>Pesan Terakhir</th><th>Status</th></tr></thead>
                    <tbody>
                    @foreach($patient->devices as $dev)
                        @php [$st, $stText] = $dev->dataStatus(); @endphp
                        <tr>
                            <td class="mono">{{ $dev->device_uid }}</td>
                            <td class="small">{{ $dev->last_message_at?->locale('id')->translatedFormat('d M H:i') ?? '-' }}</td>
                            <td><x-badge :status="$st">{{ $st }}</x-badge></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<div class="card">
    <h2 class="card-title">Daftar Sesi EKG</h2>
    @if($sessions->isEmpty())
        <x-empty-state title="Belum ada sesi EKG" message="Sesi akan muncul ketika perangkat pasien ini mengirim data." icon="activity"/>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Session ID</th><th>Mulai</th><th>Durasi</th><th>Sampel</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                @foreach($sessions as $s)
                    <tr>
                        <td class="mono small">{{ \Illuminate\Support\Str::limit($s->session_code, 16) }}</td>
                        <td class="small">{{ $s->started_at?->locale('id')->translatedFormat('d M Y H:i') }}</td>
                        <td class="small">{{ $s->durationSeconds() !== null ? $s->durationSeconds().' dtk' : '-' }}</td>
                        <td>{{ number_format($s->sample_count, 0, ',', '.') }}</td>
                        <td><x-badge :status="$s->status"/></td>
                        <td><a class="btn btn-accent btn-sm" href="{{ route('doctor.monitoring.show', $s) }}">Buka Monitoring</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="card">
    <div class="card-head">
        <h2 class="card-title">Catatan Klinis</h2>
        <a class="btn btn-outline btn-sm" href="{{ route('doctor.notes.index') }}">Kelola Catatan</a>
    </div>
    @if($notes->isEmpty())
        <x-empty-state title="Belum ada catatan klinis" message="Catatan yang Anda buat untuk pasien ini akan muncul di sini dengan jejak audit." icon="file-text"/>
    @else
        <ul style="margin:0;padding-left:18px">
            @foreach($notes as $note)
                <li style="margin-bottom:10px">
                    <strong>{{ $note->doctor?->name }}</strong>
                    <span class="muted small">· {{ $note->created_at?->locale('id')->translatedFormat('d M Y H:i') }}</span>
                    <div class="small">{{ \Illuminate\Support\Str::limit($note->body, 220) }}</div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
