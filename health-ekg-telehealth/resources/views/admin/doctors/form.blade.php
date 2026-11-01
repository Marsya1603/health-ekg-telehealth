@extends('layouts.app')
@section('title', $doctor ? 'Edit Dokter' : 'Tambah Dokter')
@section('content')
<div class="page-head">
    <div>
        <h1>{{ $doctor ? 'Edit Dokter — '.$doctor->name : 'Tambah Dokter' }}</h1>
        <div class="page-desc">Doctor ID dibuat otomatis unik oleh backend. Relasi dengan akun pengguna dijaga agar tidak duplikat.</div>
    </div>
    <div class="page-actions">
        <a href="{{ route('admin.doctors.index') }}" class="btn btn-outline">Kembali</a>
    </div>
</div>

<div class="card" style="max-width:760px">
    <form method="POST"
          action="{{ $doctor ? route('admin.doctors.update', $doctor) : route('admin.doctors.store') }}">
        @csrf
        @if($doctor) @method('PUT') @endif

        <div class="form-grid">
            <div class="field">
                <label for="name">Nama Lengkap *</label>
                <input id="name" name="name" required maxlength="150" value="{{ old('name', $doctor?->name) }}">
            </div>
            <div class="field">
                <label for="reg">Nomor Registrasi Profesional</label>
                <input id="reg" name="registration_number" maxlength="64" value="{{ old('registration_number', $doctor?->registration_number) }}">
            </div>
            <div class="field">
                <label for="spec">Spesialisasi</label>
                <input id="spec" name="specialization" maxlength="100" value="{{ old('specialization', $doctor?->specialization) }}">
            </div>
            <div class="field">
                <label for="phone">Nomor Telepon</label>
                <input id="phone" name="phone" maxlength="32" value="{{ old('phone', $doctor?->phone) }}">
            </div>
            <div class="field full">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email', $doctor?->email) }}">
                <span class="hint">Email profil dokter. Akun login dibuat terpisah melalui Manajemen Pengguna lalu ditautkan ke profil ini.</span>
            </div>
            <div class="field full">
                <label for="link_user">Tautkan Akun Dokter</label>
                <select id="link_user" name="link_user_id">
                    <option value="">- Belum ditautkan / lepas tautan -</option>
                    @foreach($linkableUsers as $lu)
                        <option value="{{ $lu->id }}" {{ (string) old('link_user_id', $doctor?->user_id) === (string) $lu->id ? 'selected' : '' }}>
                            {{ $lu->name }} ({{ $lu->email }})
                        </option>
                    @endforeach
                </select>
                @if($linkableUsers->isEmpty())
                    <span class="hint">Belum ada akun Dokter yang bisa ditautkan. Buat dulu akun ber-role Dokter melalui menu Manajemen Pengguna, lalu kembali ke form ini.</span>
                @else
                    <span class="hint">Hanya akun ber-role Dokter yang belum tertaut ke profil lain. Setelah ditautkan, akun tersebut otomatis melihat pasien-pasien yang ditugaskan ke profil dokter ini.</span>
                @endif
            </div>
        </div>

        <div style="margin-top:18px;display:flex;gap:10px">
            <button type="submit" class="btn btn-accent"><x-icon name="check-circle"/> Simpan</button>
            <a href="{{ route('admin.doctors.index') }}" class="btn btn-outline">Batal</a>
        </div>
    </form>
</div>
@endsection
