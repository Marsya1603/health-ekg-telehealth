@extends('layouts.app')
@section('title', 'Informasi Perangkat')
@section('content')
<div class="page-head">
    <div>
        <h1>Informasi Perangkat</h1>
        <div class="page-desc">Informasi perangkat pemantauan Anda. Kredensial dan konfigurasi rahasia tidak ditampilkan di halaman ini.</div>
    </div>
</div>

<div class="card" style="max-width:680px">
    @if(!$device)
        <x-empty-state title="Belum ada perangkat terdaftar untuk Anda"
                       message="Perangkat akan tercantum di sini setelah petugas meregistrasikan dan menghubungkan perangkat pemantauan dengan akun Anda."
                       icon="cpu"/>
    @else
        <dl class="detail-list">
            <dt>Label Perangkat</dt><dd>{{ $device->name }}</dd>
            <dt>Device ID</dt><dd class="mono">{{ $device->device_uid }}</dd>
            <dt>Tipe Perangkat</dt><dd>{{ $device->type ?? '-' }}</dd>
            <dt>Status Perangkat</dt><dd><x-badge :status="$statusKey === 'aktif' ? 'aktif' : ($statusKey === 'idle' ? 'menunggu' : 'unknown')">{{ $statusText }}</x-badge></dd>
            <dt>Waktu Komunikasi Terakhir</dt>
            <dd>{{ $device->last_message_at?->locale('id')->translatedFormat('d M Y H:i:s') ?? 'Belum pernah berkomunikasi' }}</dd>
        </dl>

        <div class="method-note">
            Untuk keamanan, halaman ini tidak menampilkan MQTT username/password, secret key ASCON-128,
            konfigurasi server, atau alamat internal yang tidak diperlukan pasien.
        </div>
    @endif
</div>
@endsection
