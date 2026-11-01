@extends('layouts.app')
@section('title', 'Catatan Pemeriksaan')
@section('content')
<div class="page-head">
    <div>
        <h1>Catatan Pemeriksaan</h1>
        <div class="page-desc">
            Seluruh catatan klinis yang dibuat dokter. Halaman ini hanya-baca —
            pembuatan dan pengubahan catatan adalah kewenangan dokter.
        </div>
    </div>
    <div class="page-actions">
        <form method="GET" class="search-inline">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari isi catatan / nama / kode pasien…">
            <button type="submit" class="btn btn-outline btn-sm">Cari</button>
            @if(request('q'))
                <a href="{{ route('admin.notes.index') }}" class="btn btn-outline btn-sm">Reset</a>
            @endif
        </form>
    </div>
</div>

<div class="card">
    @if($notes->isEmpty())
        <x-empty-state title="Belum ada catatan pemeriksaan"
            message="Catatan yang dibuat dokter melalui menu Catatan Pemeriksaan (Dokter) akan tampil di sini."
            icon="file-text"/>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr><th>Waktu</th><th>Pasien</th><th>Dokter</th><th>Isi Catatan</th><th></th></tr>
                </thead>
                <tbody>
                @foreach($notes as $note)
                    <tr>
                        <td class="small">{{ $note->created_at?->locale('id')->translatedFormat('d M Y H:i') }}</td>
                        <td>{{ $note->patient?->name ?? '-' }}</td>
                        <td>{{ $note->doctor?->name ?? '-' }}</td>
                        <td class="small">{{ \Illuminate\Support\Str::limit($note->body, 160) }}</td>
                        <td><a class="btn btn-outline btn-sm" href="{{ route('admin.notes.show', $note) }}"><x-icon name="eye"/> Detail</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $notes->links() }}
    @endif
</div>
@endsection
