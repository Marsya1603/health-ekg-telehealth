@extends('layouts.app')
@section('title', 'Transmission Logs')
@section('content')
<div class="page-head">
    <div>
        <h1>Transmission Logs</h1>
        <div class="page-desc">
            Metadata paket teknis. Log tidak menyimpan isi EKG lengkap, kredensial, atau secret key.
        </div>
    </div>
    <div class="page-actions">
        @if(config('healthekg.exports.enabled'))
            <a href="{{ route('network.logs.export', array_filter(['device' => request('device'), 'status' => request('status')])) }}"
               class="btn btn-outline"><x-icon name="download"/> Ekspor CSV</a>
        @endif
    </div>
</div>

<div class="card">
    <form method="GET" class="toolbar">
        <div class="field">
            <label>Device</label>
            <select name="device">
                <option value="">Semua</option>
                @foreach($devices as $d)
                    <option value="{{ $d->device_uid }}" {{ request('device') === $d->device_uid ? 'selected' : '' }}>{{ $d->device_uid }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Status</label>
            <select name="status">
                <option value="">Semua</option>
                @foreach(['valid', 'diterima', 'duplikat', 'invalid', 'gagal'] as $st)
                    <option value="{{ $st }}" {{ request('status') === $st ? 'selected' : '' }}>{{ $st }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Dari</label>
            <input type="date" name="from" value="{{ request('from') }}">
        </div>
        <div class="field">
            <label>Sampai</label>
            <input type="date" name="to" value="{{ request('to') }}">
        </div>
        <div class="field">
            <label>Paket #</label>
            <input type="number" name="packet" value="{{ request('packet') }}" min="1" style="width:110px">
        </div>
        <button type="submit" class="btn btn-primary"><x-icon name="search"/> Filter</button>
        <a href="{{ route('network.logs') }}" class="btn btn-outline">Reset</a>
    </form>

    @if($logs->isEmpty())
        <x-empty-state title="Belum ada log transmisi" message="Log muncul otomatis setelah ada pertukaran paket antara perangkat, broker, dan worker." icon="list"/>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Timestamp</th><th>Device</th><th>Paket</th><th>Tipe</th><th>Frame</th>
                    <th>Payload</th><th>Status</th><th>Validation</th><th>Delay</th><th>Proses</th><th>Error</th>
                </tr>
                </thead>
                <tbody>
                @foreach($logs as $l)
                    <tr>
                        <td class="small">{{ $l->received_at?->locale('id')->translatedFormat('d M H:i:s.v') }}</td>
                        <td class="mono small">{{ $l->device_uid }}</td>
                        <td class="mono small">{{ $l->packet_number ? '#'.$l->packet_number : '-' }}</td>
                        <td class="small">{{ $l->message_type }}</td>
                        <td class="small">{{ $l->frame_size ? $l->frame_size.' B' : '-' }}</td>
                        <td class="small">{{ $l->payload_size !== null ? $l->payload_size.' B' : '-' }}{{ $l->encrypted_size !== null ? ' → '.$l->encrypted_size.' B' : '' }}</td>
                        <td><x-badge :status="$l->status"/></td>
                        <td class="mono small">{{ $l->validation_status ?? '-' }}</td>
                        <td class="small">{{ $l->delay_ms !== null ? number_format($l->delay_ms, 1).' ms' : '-' }}</td>
                        <td class="small">{{ $l->processing_ms !== null ? number_format($l->processing_ms, 1).' ms' : '-' }}</td>
                        <td class="mono small">{{ $l->error_code ?? '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $logs->links() }}
    @endif
</div>
@endsection
