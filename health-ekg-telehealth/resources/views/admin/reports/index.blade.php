@extends('layouts.app')
@section('title', 'Laporan Admin')
@section('content')
<div class="page-head">
    <div>
        <h1>Laporan Administrasi</h1>
        <div class="page-desc">Ringkasan statistik berdasarkan periode. Laporan tidak memuat data kesehatan yang sensitif.</div>
    </div>
    <div class="page-actions">
        @if(config('healthekg.exports.enabled'))
            <a href="{{ route('admin.reports.export', array_filter(['from' => request('from'), 'to' => request('to')])) }}"
               class="btn btn-outline"><x-icon name="download"/> Ekspor CSV</a>
        @endif
    </div>
</div>

<div class="card">
    <form method="GET" class="toolbar">
        <div class="field">
            <label>Dari Tanggal</label>
            <input type="date" name="from" value="{{ request('from', $from->format('Y-m-d')) }}">
        </div>
        <div class="field">
            <label>Sampai</label>
            <input type="date" name="to" value="{{ request('to', $to->format('Y-m-d')) }}">
        </div>
        <button type="submit" class="btn btn-primary"><x-icon name="refresh"/> Terapkan</button>
    </form>

    <div class="grid grid-4" style="margin-bottom:0">
        <x-stat-card title="Pasien Baru (periode)" value="{{ number_format($summary['patients'], 0, ',', '.') }}" icon="users" tone="cyan"/>
        <x-stat-card title="Total Pasien" value="{{ number_format($summary['patients_total'], 0, ',', '.') }}" icon="users" tone="navy"/>
        <x-stat-card title="Dokter Aktif" value="{{ number_format($summary['doctors_active'], 0, ',', '.') }}" icon="heart" tone="green"/>
        <x-stat-card title="Perangkat Terdaftar" value="{{ number_format($summary['devices'], 0, ',', '.') }}" icon="cpu" tone="cyan"/>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2 class="card-title">Sesi per Status (periode)</h2>
        @if($byStatus->isEmpty())
            <x-empty-state title="Belum ada sesi pada periode ini" icon="activity"/>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Status</th><th>Jumlah</th></tr></thead>
                    <tbody>
                    @foreach($byStatus as $status => $total)
                        <tr><td><x-badge :status="$status"/></td><td>{{ number_format($total, 0, ',', '.') }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="card">
        <h2 class="card-title">Perangkat Paling Aktif (periode)</h2>
        @if($topDevices->where('sessions_count', '>', 0)->isEmpty())
            <x-empty-state title="Belum ada aktivitas perangkat" icon="cpu"/>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Device</th><th>Nama</th><th>Sesi</th></tr></thead>
                    <tbody>
                    @foreach($topDevices->where('sessions_count', '>', 0) as $d)
                        <tr>
                            <td class="mono">{{ $d->device_uid }}</td>
                            <td>{{ $d->name }}</td>
                            <td>{{ number_format($d->sessions_count, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<div class="card">
    <h2 class="card-title">Sesi per Hari</h2>
    @if($byDay->isEmpty())
        <x-empty-state title="Belum ada data pada periode ini" icon="bar-chart"/>
    @else
        <div class="chart-box">
            <canvas id="reportChart"></canvas>
        </div>
    @endif
</div>

@once
    @push('scripts')
    @if($byDay->isNotEmpty())
    <script>
        (function () {
            const labels = @json($byDay->keys()->values());
            const values = @json($byDay->values());
            const start = new Date(labels[0] + 'T00:00:00').getTime();
            const day = 86400000;
            const points = labels.map((l, i) => ({
                x: start + i * day, // posisi merata per hari (indeks)
                y: values[i],
            }));
            // gunakan kategori via label manual
            const chart = new Chart(document.getElementById('reportChart'), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Sesi pemeriksaan',
                        data: values,
                        backgroundColor: 'rgba(14, 165, 201, .55)',
                        borderColor: '#0EA5C9',
                        borderWidth: 1,
                        borderRadius: 6,
                    }],
                },
                options: {
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#E4E9F2' } }, x: { grid: { display: false } } },
                    plugins: { legend: { display: false } },
                },
            });
        })();
    </script>
    @endif
    @endpush
@endonce
@endsection
