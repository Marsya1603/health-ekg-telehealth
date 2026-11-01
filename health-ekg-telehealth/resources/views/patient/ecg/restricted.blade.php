@extends('layouts.app')
@section('title', 'Grafik Terbatas')
@section('content')
<div class="page-head">
    <div>
        <h1>Grafik EKG Saya</h1>
    </div>
    <div class="page-actions">
        <a href="{{ route('patient.ecg.index') }}" class="btn btn-outline">Kembali</a>
    </div>
</div>

<div class="card">
    <x-empty-state
        title="Akses grafik dibatasi oleh kebijakan Puskesmas"
        message="Menurut kebijakan yang berlaku, isi grafik EKG hanya dapat dilihat oleh dokter dan tenaga kesehatan yang berwenang. Silakan hubungi dokter penanggung jawab Anda untuk membahas hasil pemeriksaan."
        icon="lock"/>
    <dl class="detail-list" style="max-width:600px;margin:0 auto">
        <dt>Session ID</dt><dd class="mono small">{{ $session->session_code }}</dd>
        <dt>Tanggal</dt><dd>{{ $session->started_at?->locale('id')->translatedFormat('d M Y H:i') ?? '-' }}</dd>
        <dt>Status Sesi</dt><dd><x-badge :status="$session->status"/></dd>
    </dl>
</div>
@endsection
