@extends('layouts.app')
@section('title', 'Manajemen Perangkat')
@section('content')
<div class="page-head">
    <div>
        <h1>Manajemen Perangkat</h1>
        <div class="page-desc">
            Registrasi perangkat ESP32 + Olimex SHIELD-EKG-EMG. Status koneksi dihitung dari pesan terakhir yang benar-benar diterima worker.
        </div>
    </div>
    <div class="page-actions">
        <a href="{{ route('admin.devices.create') }}" class="btn btn-accent"><x-icon name="plus"/> Registrasi Perangkat</a>
    </div>
</div>

<div class="card">
    <form method="GET" class="toolbar">
        <div class="field" style="flex:1;max-width:260px">
            <label>Cari</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Nama atau Device ID">
        </div>
        <div class="field">
            <label>Status Data</label>
            <select name="status">
                <option value="">Semua</option>
                <option value="aktif" {{ request('status') === 'aktif' ? 'selected' : '' }}>Aktif mengirim</option>
                <option value="idle" {{ request('status') === 'idle' ? 'selected' : '' }}>Tidak ada data terbaru</option>
                <option value="unknown" {{ request('status') === 'unknown' ? 'selected' : '' }}>Belum pernah mengirim</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><x-icon name="search"/> Filter</button>
    </form>

    @if($devices->isEmpty())
        <x-empty-state title="Belum ada perangkat terdaftar" message="Registrasikan perangkat IoT pertama agar data EKG dapat diterima sistem." icon="cpu"/>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Device ID</th><th>Nama</th><th>Tipe</th><th>Pasien Terkait</th>
                    <th>Terdaftar</th><th>Pesan Terakhir</th><th>Status</th><th>Registrasi</th><th>Aksi</th>
                </tr>
                </thead>
                <tbody>
                @foreach($devices as $dev)
                    @php [$st, $stText] = $dev->dataStatus(); @endphp
                    <tr>
                        <td class="mono">{{ $dev->device_uid }}</td>
                        <td><strong>{{ $dev->name }}</strong></td>
                        <td class="small">{{ $dev->type ?? '-' }}</td>
                        <td class="small">{{ $dev->patient?->name ?? '-' }}</td>
                        <td class="small">{{ $dev->created_at?->format('d-m-Y') }}</td>
                        <td class="small">{{ $dev->last_message_at?->locale('id')->translatedFormat('d M H:i') ?? 'Belum pernah' }}</td>
                        <td><x-badge :status="$st">{{ $stText }}</x-badge></td>
                        <td><x-badge :status="$dev->is_registered ? 'aktif' : 'nonaktif'"/></td>
                        <td>
                            <div class="actions">
                                <a class="btn btn-outline btn-sm" href="{{ route('admin.devices.show', $dev) }}"><x-icon name="eye"/> Detail</a>
                                <a class="btn btn-outline btn-sm" href="{{ route('admin.devices.edit', $dev) }}"><x-icon name="edit"/> Edit</a>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $devices->links() }}
    @endif
</div>
@endsection
