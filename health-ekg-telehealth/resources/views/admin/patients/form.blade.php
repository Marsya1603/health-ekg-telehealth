@extends('layouts.app')
@section('title', $patient ? 'Edit Pasien' : 'Tambah Pasien')
@section('content')
<div class="page-head">
    <div>
        <h1>{{ $patient ? 'Edit Pasien — '.$patient->name : 'Tambah Pasien' }}</h1>
        <div class="page-desc">
            Patient ID dibuat otomatis unik oleh backend. Password pasien dikelola melalui sistem autentikasi (akun dibuat terpisah di Manajemen Pengguna).
        </div>
    </div>
    <div class="page-actions">
        <a href="{{ route('admin.patients.index') }}" class="btn btn-outline">Kembali</a>
    </div>
</div>

<div class="card" style="max-width:860px">
    <form method="POST"
          action="{{ $patient ? route('admin.patients.update', $patient) : route('admin.patients.store') }}">
        @csrf
        @if($patient) @method('PUT') @endif

        <div class="form-grid">
            <div class="field">
                <label for="name">Nama Lengkap *</label>
                <input id="name" name="name" required maxlength="150" value="{{ old('name', $patient?->name) }}">
            </div>
            <div class="field">
                <label for="mrn">Nomor Rekam Medis *</label>
                <input id="mrn" name="medical_record_number" required maxlength="64"
                       value="{{ old('medical_record_number', $patient?->medical_record_number) }}">
                <span class="hint">Harus unik, mengikuti aturan keunikan Puskesmas.</span>
            </div>
            <div class="field">
                <label for="birth_date">Tanggal Lahir</label>
                <input id="birth_date" type="date" name="birth_date" value="{{ old('birth_date', $patient?->birth_date?->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}">
            </div>
            <div class="field">
                <label for="gender">Jenis Kelamin</label>
                <select id="gender" name="gender">
                    <option value="">- Pilih -</option>
                    <option value="L" {{ old('gender', $patient?->gender) === 'L' ? 'selected' : '' }}>Laki-laki</option>
                    <option value="P" {{ old('gender', $patient?->gender) === 'P' ? 'selected' : '' }}>Perempuan</option>
                </select>
            </div>
            <div class="field">
                <label for="phone">Nomor Telepon</label>
                <input id="phone" name="phone" maxlength="32" value="{{ old('phone', $patient?->phone) }}">
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email', $patient?->email) }}">
            </div>
            <div class="field full">
                <label for="address">Alamat</label>
                <textarea id="address" name="address" rows="2" maxlength="500">{{ old('address', $patient?->address) }}</textarea>
            </div>
            <div class="field">
                <label for="doctor_id">Dokter Penanggung Jawab *</label>
                <select id="doctor_id" name="doctor_id" required>
                    <option value="">- Pilih dokter -</option>
                    @foreach($doctors as $d)
                        <option value="{{ $d->id }}" {{ old('doctor_id', $patient?->doctor_id) == $d->id ? 'selected' : '' }}>
                            {{ $d->name }} ({{ $d->code }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="registered_at">Tanggal Registrasi</label>
                <input id="registered_at" type="date" name="registered_at"
                       value="{{ old('registered_at', $patient?->registered_at?->format('Y-m-d') ?? now()->format('Y-m-d')) }}">
            </div>
            <div class="field full">
                <label for="link_user">Tautkan Akun Pasien</label>
                <select id="link_user" name="link_user_id">
                    <option value="">- Belum ditautkan / lepas tautan -</option>
                    @foreach($linkableUsers as $lu)
                        <option value="{{ $lu->id }}" {{ (string) old('link_user_id', $patient?->user_id) === (string) $lu->id ? 'selected' : '' }}>
                            {{ $lu->name }} ({{ $lu->email }})
                        </option>
                    @endforeach
                </select>
                @if($linkableUsers->isEmpty())
                    <span class="hint">Belum ada akun Pasien yang bisa ditautkan. Buat dulu akun ber-role Pasien melalui menu Manajemen Pengguna, lalu kembali ke form ini.</span>
                @else
                    <span class="hint">Hanya akun ber-role Pasien yang belum tertaut ke profil lain. Setelah ditautkan, akun tersebut dapat login dan melihat EKG milik pasien ini.</span>
                @endif
            </div>
        </div>

        <div style="margin-top:18px;display:flex;gap:10px">
            <button type="submit" class="btn btn-accent"><x-icon name="check-circle"/> Simpan</button>
            <a href="{{ route('admin.patients.index') }}" class="btn btn-outline">Batal</a>
        </div>
    </form>
</div>
@endsection
