@extends('layouts.app')
@section('title', 'Riwayat EKG')
@section('content')
<div class="page-head">
    <div>
        <h1>Riwayat EKG</h1>
        <div class="page-desc">Sesi EKG yang benar-benar diterima dan tersimpan. Akses riwayat tercatat pada audit log.</div>
    </div>
</div>

<div class="card">
    <form method="GET" class="toolbar">
        <div class="field" style="flex:1;max-width:260px">
            <label>Cari</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Pasien, device, atau session ID">
        </div>
        <div class="field">
            <label>Status</label>
            <select name="status">
                <option value="">Semua</option>
                <option value="aktif" {{ request('status') === 'aktif' ? 'selected' : '' }}>Berjalan</option>
                <option value="selesai" {{ request('status') === 'selesai' ? 'selected' : '' }}>Selesai</option>
                <option value="dihentikan" {{ request('status') === 'dihentikan' ? 'selected' : '' }}>Dihentikan</option>
            </select>
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
        <a href="{{ route('admin.ecg.index') }}" class="btn btn-outline">Reset</a>
    </form>

    @if($sessions->isEmpty())
        <x-empty-state title="Belum ada data EKG tersimpan" message="Riwayat berasal dari sesi EKG yang benar-benar diterima dari perangkat melalui worker." icon="monitor"/>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Patient ID</th><th>Pasien</th><th>Session ID</th><th>Device</th>
                    <th>Timestamp Mulai</th><th>Jml Sampel</th><th>Status</th><th>Aksi</th>
                </tr>
                </thead>
                <tbody>
                @foreach($sessions as $s)
                    <tr>
                        <td class="mono">{{ $s->patient?->code }}</td>
                        <td>{{ $s->patient?->name }}</td>
                        <td class="mono small">{{ \Illuminate\Support\Str::limit($s->session_code, 16) }}</td>
                        <td class="mono small">{{ $s->device?->device_uid }}</td>
                        <td class="small">{{ $s->started_at?->locale('id')->translatedFormat('d M Y H:i:s') }}</td>
                        <td>{{ number_format($s->sample_count, 0, ',', '.') }}</td>
                        <td><x-badge :status="$s->status"/></td>
                        <td><a class="btn btn-outline btn-sm" href="{{ route('admin.ecg.show', $s) }}"><x-icon name="eye"/> Buka</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $sessions->links() }}
    @endif
</div>
@endsection
