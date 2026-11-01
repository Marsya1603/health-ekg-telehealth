@extends('layouts.app')
@section('title', $device ? 'Edit Perangkat' : 'Registrasi Perangkat')
@section('content')
<div class="page-head">
    <div>
        <h1>{{ $device ? 'Edit Perangkat — '.$device->device_uid : 'Registrasi Perangkat Baru' }}</h1>
        <div class="page-desc">Device ID harus sama dengan yang dikonfigurasi di ESP32. Kredensial MQTT dikonfigurasi di perangkat/worker, tidak di sini.</div>
    </div>
    <div class="page-actions">
        <a href="{{ route('admin.devices.index') }}" class="btn btn-outline">Kembali</a>
    </div>
</div>

<div class="card" style="max-width:760px">
    <form method="POST"
          action="{{ $device ? route('admin.devices.update', $device) : route('admin.devices.store') }}">
        @csrf
        @if($device) @method('PUT') @endif

        <div class="form-grid">
            <div class="field">
                <label for="uid">Device ID *</label>
                <input id="uid" name="device_uid" required maxlength="64"
                       value="{{ old('device_uid', $device?->device_uid ?? $suggestedUid) }}"
                       pattern="[A-Za-z0-9_-]{4,64}">
                <span class="hint">Huruf, angka, tanda hubung, atau garis bawah (4-64 karakter). Unik.</span>
            </div>
            <div class="field">
                <label for="name">Nama Perangkat *</label>
                <input id="name" name="name" required maxlength="150" value="{{ old('name', $device?->name) }}">
            </div>
            <div class="field">
                <label for="type">Tipe Perangkat</label>
                <input id="type" name="type" maxlength="64" value="{{ old('type', $device?->type ?? 'ESP32 + Olimex SHIELD-EKG-EMG') }}">
            </div>
            <div class="field">
                <label for="patient_id">Pasien yang Dihubungkan</label>
                <select id="patient_id" name="patient_id">
                    <option value="">- Belum dihubungkan -</option>
                    @foreach($patients as $p)
                        <option value="{{ $p->id }}" {{ old('patient_id', $device?->patient_id) == $p->id ? 'selected' : '' }}>
                            {{ $p->code }} — {{ $p->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="fw">Versi Firmware</label>
                <input id="fw" name="firmware_version" maxlength="64" value="{{ old('firmware_version', $device?->firmware_version) }}">
            </div>
            @if($device)
                <div class="field">
                    <label for="isreg">Status Registrasi</label>
                    <select id="isreg" name="is_registered">
                        <option value="1" {{ $device->is_registered ? 'selected' : '' }}>Terdaftar / aktif</option>
                        <option value="0" {{ $device->is_registered ? '' : 'selected' }}>Dinonaktifkan</option>
                    </select>
                </div>
            @endif
            <div class="field full">
                <label for="notes">Catatan Teknis</label>
                <textarea id="notes" name="notes" rows="3" maxlength="1000">{{ old('notes', $device?->notes) }}</textarea>
            </div>
        </div>

        <div style="margin-top:18px;display:flex;gap:10px">
            <button type="submit" class="btn btn-accent"><x-icon name="check-circle"/> Simpan</button>
            <a href="{{ route('admin.devices.index') }}" class="btn btn-outline">Batal</a>
        </div>
    </form>
</div>
@endsection
