@extends('layouts.app')
@section('title', 'Sesi Pemeriksaan')
@section('content')
<div class="page-head">
    <div>
        <h1>Sesi Pemeriksaan</h1>
        <div class="page-desc">Metadata administrasi sesi EKG yang benar-benar diterima dari perangkat.</div>
    </div>
</div>

<div class="card">
    <form method="GET" class="toolbar">
        <div class="field" style="flex:1;max-width:240px">
            <label>Cari</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Session ID atau nama pasien">
        </div>
        <div class="field">
            <label>Status</label>
            <select name="status">
                <option value="">Semua</option>
                <option value="aktif" {{ request('status') === 'aktif' ? 'selected' : '' }}>Berjalan</option>
                <option value="selesai" {{ request('status') === 'selesai' ? 'selected' : '' }}>Selesai</option>
                <option value="dihentikan" {{ request('status') === 'dihentikan' ? 'selected' : '' }}>Dihentikan</option>
                <option value="gagal" {{ request('status') === 'gagal' ? 'selected' : '' }}>Gagal</option>
            </select>
        </div>
        <div class="field">
            <label>Device ID</label>
            <input type="text" name="device" value="{{ request('device') }}" placeholder="DEV-...">
        </div>
        <div class="field">
            <label>Dari Tanggal</label>
            <input type="date" name="from" value="{{ request('from') }}">
        </div>
        <div class="field">
            <label>Sampai</label>
            <input type="date" name="to" value="{{ request('to') }}">
        </div>
        <button type="submit" class="btn btn-primary"><x-icon name="search"/> Filter</button>
        <a href="{{ route('admin.sessions.index') }}" class="btn btn-outline">Reset</a>
    </form>

    @if($sessions->isEmpty())
        <x-empty-state title="Belum ada sesi pemeriksaan" message="Sesi dibuat otomatis oleh worker ketika perangkat mengirim paket EKG pertama untuk pasien terdaftar." icon="activity"/>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Session ID</th><th>Pasien</th><th>Dokter</th><th>Device</th>
                    <th>Mulai</th><th>Selesai</th><th>Sampel</th><th>Status</th><th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($sessions as $s)
                    <tr>
                        <td class="mono small">{{ \Illuminate\Support\Str::limit($s->session_code, 16) }}</td>
                        <td>{{ $s->patient?->name }}</td>
                        <td class="small">{{ $s->doctor?->name ?? '-' }}</td>
                        <td class="mono small">{{ $s->device?->device_uid }}</td>
                        <td class="small">{{ $s->started_at?->locale('id')->translatedFormat('d M Y H:i') }}</td>
                        <td class="small">{{ $s->ended_at?->locale('id')->translatedFormat('d M H:i') ?? '-' }}</td>
                        <td>{{ number_format($s->sample_count, 0, ',', '.') }}</td>
                        <td><x-badge :status="$s->status"/></td>
                        <td><a class="btn btn-outline btn-sm" href="{{ route('admin.sessions.show', $s) }}">Detail</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $sessions->links() }}
    @endif
</div>
@endsection
