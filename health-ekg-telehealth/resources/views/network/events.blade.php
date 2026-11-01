@extends('layouts.app')
@section('title', 'System Events')
@section('content')
<div class="page-head">
    <div>
        <h1>System Events</h1>
        <div class="page-desc">
            Kejadian sistem dari log/monitoring aktual: worker berhenti, broker tidak dapat dihubungi,
            payload tidak valid, perangkat reconnect, dan lainnya.
        </div>
    </div>
</div>

<div class="card">
    <form method="GET" class="toolbar">
        <div class="field">
            <label>Komponen</label>
            <select name="component">
                <option value="">Semua</option>
                @foreach(['worker' => 'Worker Python', 'broker' => 'Broker MQTT', 'backend' => 'Backend Laravel', 'scheduler' => 'Scheduler', 'device' => 'Perangkat'] as $key => $label)
                    <option value="{{ $key }}" {{ request('component') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Status</label>
            <select name="status">
                <option value="">Semua</option>
                @foreach(['info' => 'Info', 'peringatan' => 'Peringatan', 'error' => 'Error', 'pulih' => 'Pulih'] as $key => $label)
                    <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><x-icon name="search"/> Filter</button>
        <a href="{{ route('network.events') }}" class="btn btn-outline">Reset</a>
    </form>

    @if($events->isEmpty())
        <x-empty-state title="Belum ada event" message="Event dicatat otomatis oleh worker, scheduler, dan backend ketika ada kejadian penting." icon="alert-triangle"/>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr><th>Waktu</th><th>Komponen</th><th>Jenis Kejadian</th><th>Status</th><th>Error Code</th><th>Detail</th></tr>
                </thead>
                <tbody>
                @foreach($events as $e)
                    <tr>
                        <td class="small">{{ $e->occurred_at?->locale('id')->translatedFormat('d M Y H:i:s.v') }}</td>
                        <td class="mono small">{{ $e->component }}</td>
                        <td class="small">{{ $e->event_type }}</td>
                        <td><x-badge :status="$e->status"/></td>
                        <td class="mono small">{{ $e->error_code ?? '-' }}</td>
                        <td class="small">{{ \Illuminate\Support\Str::limit($e->message, 120) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $events->links() }}
    @endif
</div>
@endsection
