@extends('layouts.app')
@section('title', 'Grafik EKG Saya')
@section('content')
<div class="page-head">
    <div>
        <h1>Grafik EKG Saya</h1>
        <div class="page-desc">
            Grafik ini adalah hasil rekaman perangkat Anda, bukan diagnosis medis.
            Untuk penjelasan hasil, hubungi dokter penanggung jawab.
        </div>
    </div>
    <div class="page-actions">
        <a href="{{ route('patient.ecg.index') }}" class="btn btn-outline">Kembali</a>
    </div>
</div>

@include('partials.ecg-view', [
    'session' => $session,
    'initialSamples' => $initialSamples,
    'pollUrl' => $pollUrl,
    'canEnd' => false,
    'hideMetaCards' => true,
    'hideNote' => true,
])

<div class="card">
    <h2 class="card-title">Informasi Pemeriksaan</h2>
    <dl class="detail-list">
        <dt>Tanggal Pemeriksaan</dt><dd>{{ $session->started_at?->locale('id')->translatedFormat('d M Y H:i') ?? '-' }}</dd>
        <dt>Status Penerimaan Data</dt><dd>{{ $session->status === 'aktif' ? 'Sedang berjalan / menunggu data' : 'Sesi berakhir' }}</dd>
        <dt>Status Perangkat</dt><dd>{{ $session->device?->dataStatus()[1] ?? '-' }}</dd>
        <dt>Dokter Penanggung Jawab</dt><dd>{{ $session->doctor?->name ?? '-' }}</dd>
    </dl>
</div>
@endsection
