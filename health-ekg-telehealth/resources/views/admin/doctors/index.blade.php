@extends('layouts.app')
@section('title', 'Data Dokter')
@section('content')
<div class="page-head">
    <div>
        <h1>Data Dokter</h1>
        <div class="page-desc">Profil dokter dan jumlah pasien yang ditugaskan. Akun login ditautkan melalui Manajemen Pengguna.</div>
    </div>
    <div class="page-actions">
        <a href="{{ route('admin.doctors.create') }}" class="btn btn-accent"><x-icon name="plus"/> Tambah Dokter</a>
    </div>
</div>

<div class="card">
    <form method="GET" class="toolbar">
        <div class="field" style="flex:1;max-width:280px">
            <label>Cari</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Nama, ID, atau spesialisasi">
        </div>
        <div class="field">
            <label>Status</label>
            <select name="status">
                <option value="">Semua</option>
                <option value="aktif" {{ request('status') === 'aktif' ? 'selected' : '' }}>Aktif</option>
                <option value="nonaktif" {{ request('status') === 'nonaktif' ? 'selected' : '' }}>Nonaktif</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><x-icon name="search"/> Filter</button>
        @if(request()->filled('q') || request()->filled('status'))
            <a href="{{ route('admin.doctors.index') }}" class="btn btn-outline">Reset</a>
        @endif
    </form>

    @if($doctors->isEmpty())
        <x-empty-state title="Belum ada data dokter" message="Tambahkan dokter pertama melalui tombol Tambah Dokter." icon="heart"/>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Doctor ID</th><th>Nama Dokter</th><th>No Registrasi</th><th>Spesialisasi</th>
                    <th>Telepon</th><th>Email</th><th>Akun</th><th>Jml Pasien</th><th>Status</th><th>Aksi</th>
                </tr>
                </thead>
                <tbody>
                @foreach($doctors as $d)
                    <tr>
                        <td class="mono">{{ $d->code }}</td>
                        <td><strong>{{ $d->name }}</strong></td>
                        <td class="small mono">{{ $d->registration_number ?? '-' }}</td>
                        <td class="small">{{ $d->specialization ?? '-' }}</td>
                        <td class="small">{{ $d->phone ?? '-' }}</td>
                        <td class="small">{{ $d->email ?? '-' }}</td>
                        <td class="small">{{ $d->user_id ? 'Terhubung' : 'Belum' }}</td>
                        <td>{{ number_format($d->patients_count, 0, ',', '.') }}</td>
                        <td><x-badge :status="$d->is_active ? 'aktif' : 'nonaktif'"/></td>
                        <td>
                            <div class="actions">
                                <a class="btn btn-outline btn-sm" href="{{ route('admin.doctors.show', $d) }}"><x-icon name="eye"/> Detail</a>
                                <a class="btn btn-outline btn-sm" href="{{ route('admin.doctors.edit', $d) }}"><x-icon name="edit"/> Edit</a>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $doctors->links() }}
    @endif
</div>
@endsection
