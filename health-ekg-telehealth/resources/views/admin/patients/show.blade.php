@extends('layouts.app')
@section('title', 'Detail Pasien')
@section('content')
<div class="page-head">
    <div>
        <h1>{{ $patient->name }}</h1>
        <div class="page-desc">Patient ID <span class="mono">{{ $patient->code }}</span> · Halaman administrasi — data klinis tidak ditampilkan di sini.</div>
    </div>
    <div class="page-actions">
        <a href="{{ route('admin.patients.index') }}" class="btn btn-outline">Kembali</a>
        <a href="{{ route('admin.patients.edit', $patient) }}" class="btn btn-primary"><x-icon name="edit"/> Edit</a>
        <form method="POST" action="{{ route('admin.patients.toggle', $patient) }}"
              data-confirm="{{ $patient->is_active ? 'Nonaktifkan pasien ini? Data tidak dihapus dan dapat diaktifkan kembali.' : 'Aktifkan kembali pasien ini?' }}">
            @csrf
            <button type="submit" class="btn {{ $patient->is_active ? 'btn-danger' : 'btn-accent' }}">
                {{ $patient->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
            </button>
        </form>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2 class="card-title">Informasi Dasar</h2>
        <dl class="detail-list">
            <dt>Patient ID</dt><dd class="mono">{{ $patient->code }}</dd>
            <dt>Nama Lengkap</dt><dd>{{ $patient->name }}</dd>
            <dt>Nomor Rekam Medis</dt><dd class="mono">{{ $patient->medical_record_number }}</dd>
            <dt>Tanggal Lahir</dt><dd>{{ $patient->birth_date?->format('d-m-Y') ?? '-' }}</dd>
            <dt>Jenis Kelamin</dt><dd>{{ $patient->gender === 'L' ? 'Laki-laki' : ($patient->gender === 'P' ? 'Perempuan' : '-') }}</dd>
            <dt>Telepon</dt><dd>{{ $patient->phone ?? '-' }}</dd>
            <dt>Email</dt><dd>{{ $patient->email ?? '-' }}</dd>
            <dt>Alamat</dt><dd>{{ $patient->address ?? '-' }}</dd>
            <dt>Dokter Penanggung Jawab</dt><dd>{{ $patient->doctor?->name ?? '-' }} {{ $patient->doctor?->code ? '('.$patient->doctor->code.')' : '' }}</dd>
            <dt>Tanggal Registrasi</dt><dd>{{ $patient->registered_at?->format('d-m-Y') ?? '-' }}</dd>
            <dt>Status</dt><dd><x-badge :status="$patient->is_active ? 'aktif' : 'nonaktif'"/></dd>
            <dt>Akun Login</dt><dd>{{ $patient->user ? $patient->user->email : 'Belum ditautkan (opsional)' }}</dd>
            <dt>Dibuat / Diubah</dt>
            <dd class="small">{{ $patient->created_at?->format('d-m-Y H:i') }} / {{ $patient->updated_at?->format('d-m-Y H:i') }}</dd>
        </dl>
    </div>

    <div class="card">
        <h2 class="card-title">Perangkat Terhubung</h2>
        @if($patient->devices->isEmpty())
            <x-empty-state title="Belum ada perangkat" message="Registrasikan perangkat IoT melalui menu Manajemen Perangkat lalu hubungkan dengan pasien ini." icon="cpu"/>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Device ID</th><th>Nama</th><th>Pesan Terakhir</th><th>Status</th></tr></thead>
                    <tbody>
                    @foreach($patient->devices as $dev)
                        @php [$st, $stText] = $dev->dataStatus(); @endphp
                        <tr>
                            <td class="mono">{{ $dev->device_uid }}</td>
                            <td>{{ $dev->name }}</td>
                            <td class="small">{{ $dev->last_message_at?->locale('id')->translatedFormat('d M H:i') ?? 'Belum pernah' }}</td>
                            <td><x-badge :status="$st">{{ $stText }}</x-badge></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-head">
        <h2 class="card-title">Sesi Pemeriksaan ({{ number_format($sessionCount, 0, ',', '.') }})</h2>
    </div>
    @if($sessions->isEmpty())
        <x-empty-state title="Belum ada sesi pemeriksaan" message="Sesi muncul otomatis ketika perangkat pasien ini mengirim data EKG." icon="activity"/>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Session ID</th><th>Device</th><th>Mulai</th><th>Selesai</th><th>Sampel</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @foreach($sessions as $s)
                    <tr>
                        <td class="mono small">{{ \Illuminate\Support\Str::limit($s->session_code, 16) }}</td>
                        <td class="mono small">{{ $s->device?->device_uid }}</td>
                        <td class="small">{{ $s->started_at?->locale('id')->translatedFormat('d M Y H:i') }}</td>
                        <td class="small">{{ $s->ended_at?->locale('id')->translatedFormat('d M H:i') ?? '-' }}</td>
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
