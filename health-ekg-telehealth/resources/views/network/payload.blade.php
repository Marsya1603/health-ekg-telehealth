@extends('layouts.app')
@section('title', 'Payload Analysis')
@section('content')
<div class="page-head">
    <div>
        <h1>Payload Analysis</h1>
        <div class="page-desc">
            Perbandingan ukuran payload dan waktu pemrosesan berdasarkan data eksperimen/transmisi nyata.
            Tidak ada hasil simulasi — hanya device yang benar-benar mengirim data yang tampil.
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
        <button type="submit" class="btn btn-primary"><x-icon name="refresh"/> Terapkan</button>
        <a href="{{ route('network.payload') }}" class="btn btn-outline">Reset</a>
    </form>
</div>

<div class="card">
    <h2 class="card-title">Perbandingan Kondisi Transmisi per Perangkat</h2>
    @if($rows->isEmpty())
        <x-empty-state title="Belum ada data perbandingan"
                       message="Belum ada paket valid pada periode ini. Jalankan pengiriman dari perangkat (atau uji end-to-end dengan ascon_tool pada folder worker) agar analisis muncul."
                       icon="bar-chart"/>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Device</th><th>Paket</th><th>Rata-rata Payload Asli</th><th>Rata-rata Ciphertext+Tag</th>
                    <th>Tambahan Enkripsi</th><th>Delay Rata-rata</th><th>Waktu Dekripsi</th>
                </tr>
                </thead>
                <tbody>
                @foreach($rows as $r)
                    <tr>
                        <td class="mono">{{ $r['device'] }}</td>
                        <td>{{ number_format($r['packets'], 0, ',', '.') }}</td>
                        <td>{{ $r['avg_plain'] !== null ? number_format($r['avg_plain'], 1).' B' : '-' }}</td>
                        <td>{{ $r['avg_enc'] !== null ? number_format($r['avg_enc'], 1).' B' : '-' }}</td>
                        <td>{{ $r['avg_overhead'] !== null ? '+' . number_format($r['avg_overhead'], 1).' B' : '-' }}</td>
                        <td>{{ $r['avg_delay_ms'] !== null ? number_format($r['avg_delay_ms'], 1).' ms' : '-' }}</td>
                        <td>{{ $r['avg_decrypt_ms'] !== null ? number_format($r['avg_decrypt_ms'], 2).' ms' : '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="method-note">
        <strong>Titik pengukuran:</strong>
        ukuran payload asli & ciphertext dihitung worker pada lapisan aplikasi; nonce 16 B + tag 16 B ASCON-128
        tercakup dalam ciphertext. Ukuran header frame aplikasi: {{ $frameFixedBytes }} byte (magic 4, versi 1,
        tipe 1, len 2, session 36, packet 4, timestamp 8, sampling 2, count 2, nonce 16). Ukuran header MQTT/TCP/IP/TLS
        <em>tidak diukur di server ini</em> — observasi lapisan tersebut memerlukan Wireshark di jaringan perangkat.
    </div>
</div>

<div class="card">
    <h2 class="card-title">Ringkasan Keamanan ASCON-128 (periode)</h2>
    @if(!($sec['has_data'] ?? false))
        <x-empty-state title="Belum ada data keamanan" message="Belum ada event dekripsi ASCON-128 pada periode ini." icon="shield"/>
    @else
        <div class="grid grid-6" style="margin-bottom:4px">
            <div><div class="stat-title">Dekripsi Berhasil</div><div class="stat-value" style="font-size:1.2rem">{{ number_format($sec['ok'], 0, ',', '.') }}</div></div>
            <div><div class="stat-title">Tag Invalid</div><div class="stat-value" style="font-size:1.2rem">{{ number_format($sec['tag_invalid'], 0, ',', '.') }}</div></div>
            <div><div class="stat-title">Format Invalid</div><div class="stat-value" style="font-size:1.2rem">{{ number_format($sec['format_invalid'], 0, ',', '.') }}</div></div>
            <div><div class="stat-title">Dekripsi Gagal</div><div class="stat-value" style="font-size:1.2rem">{{ number_format($sec['decrypt_fail'], 0, ',', '.') }}</div></div>
            <div><div class="stat-title">Rata-rata Dekripsi</div><div class="stat-value" style="font-size:1.2rem">{{ $sec['avg_decrypt_ms'] !== null ? number_format($sec['avg_decrypt_ms'], 2).' ms' : '-' }}</div></div>
            <div><div class="stat-title">Plain vs Cipher</div><div class="stat-value" style="font-size:1.05rem">{{ $sec['avg_plain_bytes'] !== null ? number_format($sec['avg_plain_bytes'], 0).' B' : '-' }} / {{ $sec['avg_cipher_bytes'] !== null ? number_format($sec['avg_cipher_bytes'], 0).' B' : '-' }}</div></div>
        </div>
    @endif
</div>
@endsection
