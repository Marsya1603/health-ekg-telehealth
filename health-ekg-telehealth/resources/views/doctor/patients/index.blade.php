@extends('layouts.app')
@section('title', 'Pasien Saya')
@section('content')
<div class="page-head">
    <div>
        <h1>Pasien Saya</h1>
        <div class="page-desc">Hanya pasien yang ditugaskan kepada Anda — diterapkan pada query backend, bukan hanya menu.</div>
    </div>
</div>

<div class="card">
    <form method="GET" class="toolbar">
        <div class="field" style="flex:1;max-width:280px">
            <label>Cari pasien</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Nama, Patient ID, atau No RM">
        </div>
        <button type="submit" class="btn btn-primary"><x-icon name="search"/> Cari</button>
        @if(request()->filled('q'))
            <a href="{{ route('doctor.patients.index') }}" class="btn btn-outline">Reset</a>
        @endif
    </form>

    @if($patients->isEmpty())
        <x-empty-state title="Belum ada pasien yang ditugaskan" message="Belum ada pasien dalam kewenangan Anda. Hubungi Admin Puskesmas untuk penugasan." icon="users"/>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Patient ID</th><th>Nama</th><th>Tgl Lahir</th><th>Device ID</th>
                    <th>Sesi Terakhir</th><th>Status Data Terbaru</th><th>Aksi</th>
                </tr>
                </thead>
                <tbody>
                @foreach($patients as $p)
                    @php
                        $dev = $p->primaryDevice;
                        [$st, $stText] = $dev?->dataStatus() ?? ['unknown', 'Belum ada perangkat'];
                    @endphp
                    <tr>
                        <td class="mono">{{ $p->code }}</td>
                        <td><strong>{{ $p->name }}</strong></td>
                        <td class="small">{{ $p->birth_date?->format('d-m-Y') ?? '-' }}</td>
                        <td class="mono small">{{ $dev?->device_uid ?? '-' }}</td>
                        <td class="small">{{ $p->sessions_max_started_at ? \Illuminate\Support\Carbon::parse($p->sessions_max_started_at)->locale('id')->translatedFormat('d M H:i') : 'Belum ada' }}</td>
                        <td><x-badge :status="$st">{{ $st }}</x-badge></td>
                        <td>
                            <div class="actions">
                                <a class="btn btn-outline btn-sm" href="{{ route('doctor.patients.show', $p) }}"><x-icon name="eye"/> Detail</a>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $patients->links() }}
    @endif
</div>
@endsection
