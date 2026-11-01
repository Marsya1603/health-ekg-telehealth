@extends('layouts.app')
@section('title', 'Monitoring EKG — '.$session->patient?->name)
@section('content')
<div class="page-head">
    <div>
        <h1>Monitoring EKG</h1>
        <div class="page-desc">
            Halaman pemantauan utama. Grafik diperbarui otomatis saat sampel baru benar-benar tersimpan (polling),
            dan berhenti ketika koneksi/sesi berakhir.
        </div>
    </div>
    <div class="page-actions">
        <a href="{{ route('doctor.monitoring.index') }}" class="btn btn-outline">Kembali</a>
        <a href="{{ route('doctor.patients.show', $session->patient) }}" class="btn btn-outline">Detail Pasien</a>
    </div>
</div>

@include('partials.ecg-view', [
    'session' => $session,
    'initialSamples' => $initialSamples,
    'pollUrl' => $pollUrl,
    'canEnd' => $canEnd,
    'endUrl' => $endUrl,
    'hideMetaCards' => true,
    'hideNote' => true,
])

<div class="card">
    <h2 class="card-title">Informasi Pengukuran & Kualitas Data</h2>
    <dl class="detail-list">
        <dt>Durasi Data</dt>
        <dd>{{ $session->durationSeconds() !== null ? number_format($session->durationSeconds(), 0, ',', '.').' detik' : '-' }}</dd>
        <dt>Waktu Data Terakhir Diterima</dt>
        <dd>{{ $session->device?->last_message_at?->locale('id')->translatedFormat('d M Y H:i:s') ?? 'Belum ada' }}</dd>
    </dl>
    {{-- BPM pasien ditampilkan live pada kartu "Detak Jantung (BPM)" di grafik di atas,
         dihitung otomatis dari sampel EKG asli sesi pasien ini (deteksi puncak-R). --}}
</div>
@endsection
