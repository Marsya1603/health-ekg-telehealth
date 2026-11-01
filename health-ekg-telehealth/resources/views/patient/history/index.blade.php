@extends('layouts.app')
@section('title', 'Riwayat EKG')
@section('content')
<div class="page-head">
    <div>
        <h1>Riwayat EKG Saya</h1>
        <div class="page-desc">Riwayat perekaman EKG dari perangkat Anda.</div>
    </div>
</div>

<div class="card">
    @if(!$patient)
        <x-empty-state title="Profil pasien belum ditautkan" message="Hubungi petugas Puskesmas." icon="users"/>
    @else
        <form method="GET" class="toolbar">
            <div class="field">
                <label>Dari</label>
                <input type="date" name="from" value="{{ request('from') }}">
            </div>
            <div class="field">
                <label>Sampai</label>
                <input type="date" name="to" value="{{ request('to') }}">
            </div>
            <button type="submit" class="btn btn-primary"><x-icon name="search"/> Filter</button>
            <a href="{{ route('patient.history.index') }}" class="btn btn-outline">Reset</a>
        </form>

        @if($sessions->isEmpty())
            <x-empty-state title="Belum ada riwayat pemeriksaan" message="Riwayat Anda akan muncul setelah ada pemeriksaan yang dilakukan." icon="clock"/>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr><th>Session ID</th><th>Tanggal Pemeriksaan</th><th>Durasi</th><th>Status</th><th>Dokter PJ</th><th>Aksi</th></tr>
                    </thead>
                    <tbody>
                    @foreach($sessions as $s)
                        <tr>
                            <td class="mono small">{{ \Illuminate\Support\Str::limit($s->session_code, 12) }}</td>
                            <td class="small">{{ $s->started_at?->locale('id')->translatedFormat('d M Y H:i') }}</td>
                            <td class="small">{{ $s->durationSeconds() !== null ? $s->durationSeconds().' dtk' : '-' }}</td>
                            <td><x-badge :status="$s->status"/></td>
                            <td class="small">{{ $s->doctor?->name ?? '-' }}</td>
                            <td><a class="btn btn-outline btn-sm" href="{{ route('patient.ecg.show', $s) }}">Detail</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $sessions->links() }}
        @endif
    @endif
</div>
@endsection
