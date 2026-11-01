@extends('layouts.app')
@section('title', 'Riwayat EKG — Grafik')
@section('content')
<div class="page-head">
    <div>
        <h1>Grafik EKG Riwayat</h1>
        <div class="page-desc">Akses administrasi — dibuka oleh {{ auth()->user()->name }} dan tercatat pada audit log.</div>
    </div>
    <div class="page-actions">
        <a href="{{ route('admin.ecg.index') }}" class="btn btn-outline">Kembali ke Riwayat</a>
        <a href="{{ route('admin.sessions.show', $session) }}" class="btn btn-outline">Metadata Sesi</a>
    </div>
</div>

@include('partials.ecg-view', [
    'session' => $session,
    'initialSamples' => $initialSamples,
    'pollUrl' => $pollUrl,
    'canEnd' => false,
])
@endsection
