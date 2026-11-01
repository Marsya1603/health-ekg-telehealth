@extends('layouts.app')
@section('title', 'Data Pasien')
@section('content')
<div class="page-head">
    <div>
        <h1>Data Pasien</h1>
        <div class="page-desc">Manajemen pasien terdaftar pada Puskesmas.</div>
    </div>
    <div class="page-actions">
        @if(config('healthekg.exports.enabled'))
            <a href="{{ route('admin.patients.export') }}" class="btn btn-outline"><x-icon name="download"/> Ekspor CSV</a>
        @endif
        <a href="{{ route('admin.patients.create') }}" class="btn btn-accent"><x-icon name="plus"/> Tambah Pasien</a>
    </div>
</div>

<div class="card">
    <form method="GET" class="toolbar">
        <div class="field" style="flex:1;max-width:280px">
            <label>Cari</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Nama, No RM, atau Patient ID">
        </div>
        <div class="field">
            <label>Dokter</label>
            <select name="doctor">
                <option value="">Semua dokter</option>
                @foreach($doctors as $d)
                    <option value="{{ $d->id }}" {{ request('doctor') == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                @endforeach
            </select>
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
        @if(request()->filled('q') || request()->filled('doctor') || request()->filled('status'))
            <a href="{{ route('admin.patients.index') }}" class="btn btn-outline">Reset</a>
        @endif
    </form>

    @if($patients->isEmpty())
        <x-empty-state title="Belum ada data pasien" message="Tambahkan pasien pertama melalui tombol Tambah Pasien. Data ditampilkan dari database aktual."/>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Patient ID</th><th>Nama Lengkap</th><th>Tgl Lahir</th><th>JK</th>
                    <th>No Rekam Medis</th><th>Dokter PJ</th><th>Registrasi</th><th>Status</th><th>Aksi</th>
                </tr>
                </thead>
                <tbody>
                @foreach($patients as $p)
                    <tr>
                        <td class="mono">{{ $p->code }}</td>
                        <td><strong>{{ $p->name }}</strong></td>
                        <td class="small">{{ $p->birth_date?->format('d-m-Y') ?? '-' }}</td>
                        <td>{{ $p->gender === 'L' ? 'L' : ($p->gender === 'P' ? 'P' : '-') }}</td>
                        <td class="mono small">{{ $p->medical_record_number }}</td>
                        <td class="small">{{ $p->doctor?->name ?? '-' }}</td>
                        <td class="small">{{ $p->registered_at?->format('d-m-Y') ?? '-' }}</td>
                        <td><x-badge :status="$p->is_active ? 'aktif' : 'nonaktif'"/></td>
                        <td>
                            <div class="actions">
                                <a class="btn btn-outline btn-sm" href="{{ route('admin.patients.show', $p) }}"><x-icon name="eye"/> Detail</a>
                                <a class="btn btn-outline btn-sm" href="{{ route('admin.patients.edit', $p) }}"><x-icon name="edit"/> Edit</a>
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
