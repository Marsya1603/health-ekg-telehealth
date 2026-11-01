@extends('layouts.app')
@section('title', 'ASCON-128 Security Monitoring')
@section('content')
<div class="page-head">
    <div>
        <h1>ASCON-128 Security Monitoring</h1>
        <div class="page-desc">
            Pemantauan integritas payload terenkripsi. Secret key, password, token, dan isi data kesehatan
            tidak pernah ditampilkan di halaman ini.
        </div>
    </div>
</div>

<div class="card">
    <form method="GET" class="toolbar">
        <div class="field">
            <label>Dari</label>
            <input type="date" name="from" value="{{ request('from', $from->format('Y-m-d')) }}">
        </div>
        <div class="field">
            <label>Sampai</label>
            <input type="date" name="to" value="{{ request('to', $to->format('Y-m-d')) }}">
        </div>
        <div class="field">
            <label>Device</label>
            <select name="device">
                <option value="">Semua</option>
                @foreach($devices as $d)
                    <option value="{{ $d->device_uid }}" {{ $device === $d->device_uid ? 'selected' : '' }}>{{ $d->device_uid }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><x-icon name="refresh"/> Terapkan</button>
        <a href="{{ route('network.security') }}" class="btn btn-outline">Reset</a>
    </form>
</div>

@if(!($summary['has_data'] ?? false))
    <div class="card">
        <x-empty-state title="Belum ada data pemrosesan keamanan"
                       message="Belum ada event validasi/dekripsi ASCON-128 pada periode ini. Worker mencatat setiap hasil verifikasi payload di sini." icon="shield"/>
    </div>
@else
    <div class="grid grid-4">
        <x-stat-card title="Pesan Divalidasi (berhasil)" value="{{ number_format($summary['ok'], 0, ',', '.') }}" icon="shield" tone="green"/>
        <x-stat-card title="Tag Autentikasi Invalid" value="{{ number_format($summary['tag_invalid'], 0, ',', '.') }}" icon="alert-triangle" tone="red"/>
        <x-stat-card title="Dekripsi Gagal / Format Invalid" value="{{ number_format($summary['decrypt_fail'] + $summary['format_invalid'], 0, ',', '.') }}" icon="x-circle" tone="red"/>
        <x-stat-card title="Rata-rata Waktu Dekripsi" value="{{ $summary['avg_decrypt_ms'] !== null ? number_format($summary['avg_decrypt_ms'], 2).' ms' : 'Belum diukur' }}" icon="clock" tone="cyan"/>
    </div>

    <div class="card">
        <h2 class="card-title">Rincian Event</h2>
        <dl class="detail-list">
            <dt>Total Event</dt><dd>{{ number_format($summary['total'], 0, ',', '.') }}</dd>
            <dt>Format Invalid</dt><dd>{{ number_format($summary['format_invalid'], 0, ',', '.') }}</dd>
            <dt>Error Nonce / Pemakaian Ulang</dt><dd>{{ number_format($summary['nonce_error'], 0, ',', '.') }}</dd>
            <dt>Perangkat Tidak Dikenal</dt><dd>{{ number_format($summary['unknown_device'], 0, ',', '.') }}</dd>
            <dt>Rata-rata Ukuran Plain vs Cipher</dt>
            <dd>{{ $summary['avg_plain_bytes'] !== null ? number_format($summary['avg_plain_bytes'], 1).' B' : '-' }} vs {{ $summary['avg_cipher_bytes'] !== null ? number_format($summary['avg_cipher_bytes'], 1).' B' : '-' }}</dd>
        </dl>
    </div>
@endif

<div class="card">
    <h2 class="card-title">Event Terakhir</h2>
    @if($events->isEmpty())
        <x-empty-state title="Belum ada event keamanan" icon="shield"/>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Waktu</th><th>Device</th><th>Paket</th><th>Event</th><th>Error Code</th>
                    <th>Waktu Proses</th><th>Plain / Cipher</th>
                </tr>
                </thead>
                <tbody>
                @foreach($events as $e)
                    <tr>
                        <td class="small">{{ $e->occurred_at?->locale('id')->translatedFormat('d M H:i:s.v') }}</td>
                        <td class="mono small">{{ $e->device_uid }}</td>
                        <td class="mono small">{{ $e->packet_number ? '#'.$e->packet_number : '-' }}</td>
                        <td class="small">{{ $e->event_type }}</td>
                        <td class="mono small">{{ $e->error_code ?? '-' }}</td>
                        <td class="small">{{ $e->processing_ms !== null ? number_format($e->processing_ms, 2).' ms' : '-' }}</td>
                        <td class="small">{{ $e->payload_plain_size !== null ? $e->payload_plain_size.' B' : '-' }} / {{ $e->payload_cipher_size !== null ? $e->payload_cipher_size.' B' : '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="method-note">
        <strong>Prinsip:</strong> worker memvalidasi authentication tag SEBELUM data dipakai. Payload yang gagal
        verifikasi tidak disimpan sebagai sampel EKG valid. Kunci dikelola melalui konfigurasi worker (file 0600 /
        environment) dan tidak pernah masuk source code, frontend, atau log.
    </div>
</div>
@endsection
