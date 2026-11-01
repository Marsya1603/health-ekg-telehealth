@extends('layouts.app')
@section('title', 'Pemantauan EKG Saya')
@section('content')
<div class="page-head">
    <div>
        <h1>Pemantauan EKG Saya</h1>
        <div class="page-desc">Sesi perekaman milik Anda. Hanya sesi dengan Patient ID milik Anda yang dapat dibuka.</div>
    </div>
</div>

<div class="card">
    @if(!$patient)
        <x-empty-state title="Profil pasien belum ditautkan" message="Hubungi petugas Puskesmas untuk menautkan akun Anda." icon="users"/>
    @elseif($sessions->isEmpty())
        <x-empty-state title="Belum ada data EKG yang tersedia"
                       message="Belum ada data EKG yang tersedia. Grafik akan muncul setelah pemeriksaan dilakukan oleh tenaga kesehatan menggunakan perangkat pemantauan. Tidak ada data simulasi yang ditampilkan." icon="heart"/>
    @else
        @if(!$visible)
            <div class="alert alert-info">
                Menurut kebijakan Puskesmas, isi grafik EKG hanya dapat diakses oleh tenaga kesehatan.
                Silakan hubungi dokter penanggung jawab Anda untuk informasi hasil pemeriksaan.
            </div>
        @endif
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Tanggal</th><th>Session ID</th><th>Device</th><th>Sampel</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                @foreach($sessions as $s)
                    <tr>
                        <td class="small">{{ $s->started_at?->locale('id')->translatedFormat('d M Y H:i') }}</td>
                        <td class="mono small">{{ \Illuminate\Support\Str::limit($s->session_code, 12) }}</td>
                        <td class="mono small">{{ $s->device?->device_uid }}</td>
                        <td>{{ number_format($s->sample_count, 0, ',', '.') }}</td>
                        <td><x-badge :status="$s->status"/></td>
                        <td><a class="btn btn-accent btn-sm" href="{{ route('patient.ecg.show', $s) }}">Lihat</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $sessions->links() }}
    @endif
</div>
@endsection
