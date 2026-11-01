@extends('layouts.app')
@section('title', 'Catatan Dokter')
@section('content')
<div class="page-head">
    <div>
        <h1>Catatan Dokter</h1>
        <div class="page-desc">
            Catatan pemeriksaan dari dokter penanggung jawab Anda.
            Catatan bersifat hanya-baca dan tampil apa adanya dari dokter.
        </div>
    </div>
</div>

@if(!$patient)
    <div class="card">
        <x-empty-state title="Profil pasien belum ditautkan"
            message="Akun Anda belum terhubung ke profil pasien. Silakan hubungi petugas Puskesmas."
            icon="users"/>
    </div>
@else
    @if($notes->isEmpty())
        <div class="card">
            <x-empty-state title="Belum ada catatan dari dokter"
                message="Ketika dokter menulis catatan pemeriksaan untuk Anda, catatan akan tampil di halaman ini."
                icon="file-text"/>
        </div>
    @else
        @foreach($notes as $note)
            <div class="card">
                <div class="card-head">
                    <div>
                        <h2 class="card-title"><x-icon name="file-text"/> {{ $note->doctor?->name ?? 'Dokter' }}</h2>
                        <p class="card-sub">
                            {{ $note->created_at?->locale('id')->translatedFormat('d M Y H:i') }}
                            @if($note->updated_at?->ne($note->created_at))
                                · diperbarui {{ $note->updated_at?->locale('id')->translatedFormat('d M Y H:i') }}
                            @endif
                        </p>
                    </div>
                </div>
                <div class="note-body">{{ $note->body }}</div>
            </div>
        @endforeach
        {{ $notes->links() }}
    @endif
@endif
@endsection
