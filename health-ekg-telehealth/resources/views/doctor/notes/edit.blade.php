@extends('layouts.app')
@section('title', 'Edit Catatan')
@section('content')
<div class="page-head">
    <div>
        <h1>Edit Catatan Pemeriksaan</h1>
        <div class="page-desc">Setiap perubahan catatan tercatat pada audit log (siapa, kapan, apa yang berubah).</div>
    </div>
    <div class="page-actions">
        <a href="{{ route('doctor.notes.index') }}" class="btn btn-outline">Kembali</a>
    </div>
</div>

<div class="card" style="max-width:760px">
    <form method="POST" action="{{ route('doctor.notes.update', $note) }}">
        @csrf
        @method('PUT')
        <div class="field">
            <label>Pasien</label>
            <input type="text" value="{{ $note->patient?->name ?? '-' }}" disabled>
        </div>
        <div class="field" style="margin-top:12px">
            <label for="body">Isi Catatan *</label>
            <textarea id="body" name="body" rows="6" required maxlength="5000">{{ old('body', $note->body) }}</textarea>
        </div>
        <div style="margin-top:14px;display:flex;gap:10px">
            <button type="submit" class="btn btn-accent">Simpan Perubahan</button>
            <a href="{{ route('doctor.notes.index') }}" class="btn btn-outline">Batal</a>
        </div>
    </form>
</div>
@endsection
