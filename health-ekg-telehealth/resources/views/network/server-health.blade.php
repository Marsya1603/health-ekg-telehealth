@extends('layouts.app')
@section('title', 'Server Health')
@section('content')
<div class="page-head">
    <div>
        <h1>Server Health</h1>
        <div class="page-desc">
            Metrik aktual dari sistem operasi, dikumpulkan oleh command <code>app:collect-server-health</code>
            melalui scheduler Laravel setiap menit.
        </div>
    </div>
</div>

@if(!$snapshot)
    <div class="card">
        <x-empty-state title="Belum ada data server"
                       message="Snapshot belum tersedia karena scheduler belum pernah berjalan. Jalankan scheduler (php artisan schedule:work atau cron schedule:run) — setelah ±1 menit data akan muncul di halaman ini. Tidak ada angka statis yang ditampilkan."
                       icon="server"/>
    </div>
@else
    <div class="grid grid-4">
        <x-stat-card title="CPU Utilization" value="{{ $snapshot['cpu_percent'] ?? '-' }}%" hint="Rata-rata sampel /proc/stat" icon="cpu" tone="{{ ($snapshot['cpu_percent'] ?? 0) > 85 ? 'red' : 'green' }}"/>
        <x-stat-card title="Memory Used" value="{{ $snapshot['memory']['used_percent'] ?? '-' }}%"
                     hint="{{ $snapshot['memory']['used_mb'] ?? '-' }} / {{ $snapshot['memory']['total_mb'] ?? '-' }} MB"
                     icon="database" tone="{{ ($snapshot['memory']['used_percent'] ?? 0) > 90 ? 'red' : 'green' }}"/>
        <x-stat-card title="Disk Used" value="{{ $snapshot['disk']['used_percent'] ?? '-' }}%"
                     hint="Sisa {{ $snapshot['disk']['free_gb'] ?? '-' }} GB dari {{ $snapshot['disk']['total_gb'] ?? '-' }} GB"
                     icon="server" tone="{{ ($snapshot['disk']['used_percent'] ?? 0) > 90 ? 'red' : 'green' }}"/>
        <x-stat-card title="Uptime" value="{{ isset($snapshot['uptime_seconds']) ? floor($snapshot['uptime_seconds'] / 86400).' hari '.floor(($snapshot['uptime_seconds'] % 86400) / 3600).' jam' : '-' }}"
                     hint="Sampel terakhir: {{ \Carbon\Carbon::parse($snapshot['collected_at'])->locale('id')->translatedFormat('d M Y H:i') }}"
                     icon="clock" tone="cyan"/>
    </div>

    <div class="card">
        <h2 class="card-title">Status Layanan (pemeriksaan aktual)</h2>
        @foreach($snapshot['services'] ?? [] as $name => $s)
            <div class="service-row">
                <strong>{{ ['database' => 'Database MySQL', 'backend' => 'Laravel (PHP-FPM/serve)', 'broker' => 'Broker MQTT', 'worker' => 'Worker Python', 'scheduler' => 'Scheduler'][$name] ?? $name }}</strong>
                <x-badge :status="$s['status'] === 'ok' ? 'aktif' : ($s['status'] === 'error' ? 'error' : 'unknown')"/>
                <span class="service-detail">{{ $s['detail'] }}</span>
            </div>
        @endforeach
        <div class="method-note">
            Catatan: metrik hanya diperoleh melalui mekanisme monitoring yang diizinkan (pembacaan /proc oleh command
            lokal dan pemeriksaan koneksi layanan). Laravel tidak membaca metrik server lain secara ajaib.
        </div>
    </div>
@endif
@endsection
