@extends('layouts.app')
@section('title', 'Riwayat EKG')
@section('content')
<div class="page-head">
    <div>
        <h1>Riwayat EKG</h1>
        <div class="page-desc">Riwayat perekaman EKG dari perangkat pasien dalam kewenangan Anda, dari data aktual database.</div>
    </div>
</div>

<div class="card">
    <form method="GET" class="toolbar">
        <div class="field" style="flex:1;max-width:240px">
            <label>Cari</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Nama pasien atau session ID">
        </div>
        <div class="field">
            <label>Dari</label>
            <input type="date" name="from" value="{{ request('from') }}">
        </div>
        <div class="field">
            <label>Sampai</label>
            <input type="date" name="to" value="{{ request('to') }}">
        </div>
        <button type="submit" class="btn btn-primary"><x-icon name="search"/> Filter</button>
        <a href="{{ route('doctor.history.index') }}" class="btn btn-outline">Reset</a>
    </form>

    @if($sessions->isEmpty())
        <x-empty-state title="Belum ada riwayat pemeriksaan" message="Sesi yang tercatat akan muncul di sini beserta metadata pemeriksaannya." icon="clock"/>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Session ID</th><th>Pasien</th><th>Waktu</th><th>Durasi</th>
                    <th>Device</th><th>Sampel</th><th>Status</th><th>Aksi</th>
                </tr>
                </thead>
                <tbody>
                @foreach($sessions as $s)
                    <tr>
                        <td class="mono small">{{ \Illuminate\Support\Str::limit($s->session_code, 16) }}</td>
                        <td>{{ $s->patient?->name }}</td>
                        <td class="small">{{ $s->started_at?->locale('id')->translatedFormat('d M Y H:i') }}</td>
                        <td class="small">{{ $s->durationSeconds() !== null ? $s->durationSeconds().' dtk' : '-' }}</td>
                        <td class="mono small">{{ $s->device?->device_uid }}</td>
                        <td>{{ number_format($s->sample_count, 0, ',', '.') }}</td>
                        <td><x-badge :status="$s->status"/></td>
                        <td><a class="btn btn-accent btn-sm" href="{{ route('doctor.monitoring.show', $s) }}">Buka Waveform</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $sessions->links() }}
    @endif
</div>
@endsection
