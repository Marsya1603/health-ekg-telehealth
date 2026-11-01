@extends('layouts.app')
@section('title', 'Profil Saya')
@section('content')
<div class="page-head">
    <div>
        <h1>Profil Saya</h1>
        <div class="page-desc">Informasi akun dan kontak Anda. Perubahan identitas medis penting dilakukan oleh Admin Puskesmas.</div>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2 class="card-title">Informasi Akun</h2>
        <p class="card-sub">Kredensial dikelola melalui sistem autentikasi. Password tidak dapat dilihat siapa pun.</p>
        <dl class="detail-list">
            <dt>Nama Lengkap</dt><dd>{{ $user->name }}</dd>
            <dt>Email</dt><dd>{{ $user->email }}</dd>
            <dt>Role</dt><dd>{{ $user->roleLabel() }}</dd>
            <dt>Status Akun</dt><dd><x-badge :status="$user->is_active ? 'aktif' : 'nonaktif'"/></dd>
            <dt>Terakhir Login</dt><dd>{{ $user->last_login_at?->locale('id')->translatedFormat('d M Y H:i') ?? 'Belum pernah' }}</dd>
            <dt>Dibuat Pada</dt><dd>{{ $user->created_at?->locale('id')->translatedFormat('d M Y') }}</dd>
        </dl>
    </div>

    <div class="card">
        <h2 class="card-title">Kontak</h2>
        <p class="card-sub">
            @if($user->role === 'pasien')
                Pasien dapat memperbarui nomor telepon, email, dan alamat. Perubahan nama/nomor rekam medis harus melalui verifikasi administrasi.
            @else
                Hanya nomor telepon yang dapat diperbarui sendiri.
            @endif
        </p>
        <form method="POST" action="{{ route('profile.update') }}">
            @csrf
            <div class="form-grid">
                <div class="field">
                    <label for="phone">Nomor Telepon</label>
                    <input id="phone" type="text" name="phone" value="{{ old('phone', $profile?->phone) }}" maxlength="32">
                </div>
                @if($user->role === 'pasien')
                    <div class="field">
                        <label for="email_field">Email</label>
                        <input id="email_field" type="email" name="email" value="{{ old('email', $profile?->email) }}">
                    </div>
                    <div class="field full">
                        <label for="address">Alamat</label>
                        <textarea id="address" name="address" rows="3" maxlength="500">{{ old('address', $profile?->address) }}</textarea>
                    </div>
                @endif
            </div>
            <div style="margin-top:14px">
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>

        @if($profile)
            <div class="method-note">
                <strong>Data terkunci (hanya admin dapat mengubah):</strong>
                @if($user->role === 'pasien')
                    {{ $profile->name }} · RMN {{ $profile->medical_record_number }} ·
                    {{ $profile->birth_date?->format('d-m-Y') }} ·
                    {{ $profile->gender === 'L' ? 'Laki-laki' : ($profile->gender === 'P' ? 'Perempuan' : '-') }}
                @else
                    {{ $profile->name }} · {{ $profile->code }} · {{ $profile->specialization ?? 'Spesialisasi belum diisi' }}
                @endif
            </div>
        @endif
    </div>
</div>

<div class="card">
    <h2 class="card-title">Aktivitas Akun Terakhir</h2>
    <p class="card-sub">Rekaman tindakan Anda yang tercatat pada audit log.</p>
    @if($audits->isEmpty())
        <x-empty-state title="Belum ada aktivitas tercatat" message="Aktivitas Anda akan muncul di sini setelah Anda menggunakan aplikasi."/>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr><th>Waktu</th><th>Aktivitas</th><th>IP</th></tr>
                </thead>
                <tbody>
                @foreach($audits as $a)
                    <tr>
                        <td>{{ $a->created_at?->locale('id')->translatedFormat('d M Y H:i:s') }}</td>
                        <td>{{ ucwords(str_replace('_', ' ', $a->action)) }}</td>
                        <td class="mono">{{ $a->ip ?? '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
