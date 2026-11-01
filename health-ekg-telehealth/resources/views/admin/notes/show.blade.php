@extends('layouts.app')
@section('title', 'Detail Catatan Pemeriksaan')
@section('content')
<div class="page-head">
    <div>
        <h1>Detail Catatan Pemeriksaan</h1>
        <div class="page-desc">Catatan klinis dibuat oleh dokter penanggung jawab. Admin hanya dapat melihat.</div>
    </div>
    <div class="page-actions">
        <a href="{{ route('admin.notes.index') }}" class="btn btn-outline">Kembali</a>
    </div>
</div>

<div class="card">
    <dl class="detail-list">
        <dt>Pasien</dt><dd>{{ $note->patient?->name ?? '-' }} ({{ $note->patient?->code ?? '-' }})</dd>
        <dt>Dokter</dt><dd>{{ $note->doctor?->name ?? '-' }}</dd>
        <dt>Dibuat</dt><dd>{{ $note->created_at?->locale('id')->translatedFormat('d M Y H:i:s') }}</dd>
        <dt>Terakhir Diubah</dt><dd>{{ $note->updated_at?->locale('id')->translatedFormat('d M Y H:i:s') }}</dd>
    </dl>
    <div class="note-body">{{ $note->body }}</div>
    <div class="method-note">
        Setiap perubahan catatan tercatat pada audit log oleh sistem.
    </div>
</div>
@endsection
