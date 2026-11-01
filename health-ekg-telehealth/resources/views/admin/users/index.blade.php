@extends('layouts.app')
@section('title', 'Manajemen Pengguna')
@section('content')
<div class="page-head">
    <div>
        <h1>Manajemen Pengguna</h1>
        <div class="page-desc">
            Password tidak dapat dilihat oleh siapa pun. Reset password menghasilkan password sementara yang wajib diganti saat login pertama.
        </div>
    </div>
</div>

<div class="card">
    <h2 class="card-title"><x-icon name="plus"/> Buat Akun Pengguna</h2>
    <form method="POST" action="{{ route('admin.users.store') }}">
        @csrf
        <div class="form-grid">
            <div class="field">
                <label for="u_name">Nama Lengkap *</label>
                <input id="u_name" name="name" required maxlength="150">
            </div>
            <div class="field">
                <label for="u_email">Email *</label>
                <input id="u_email" type="email" name="email" required>
            </div>
            <div class="field">
                <label for="u_role">Role *</label>
                @php
                    // Role network_engineer sengaja TIDAK ditawarkan di sini (role internal).
                    // Akun Network Engineer hanya dibuat lewat terminal:
                    // php artisan app:create-admin --role=network_engineer
                    $createRoleOptions = collect(config('healthekg.roles'))->except('network_engineer');
                @endphp
                <select id="u_role" name="role" required>
                    @foreach($createRoleOptions as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="u_link">Tautkan Profil (untuk role Dokter/Pasien)</label>
                <select id="u_link" name="link_id">
                    <option value="">- Tidak menautkan -</option>
                    <optgroup label="Profil Dokter belum bertaut">
                        @foreach($unlinkedDoctors as $d)
                            <option value="{{ $d->id }}">{{ $d->code }} — {{ $d->name }}</option>
                        @endforeach
                    </optgroup>
                    <optgroup label="Profil Pasien belum bertaut">
                        @foreach($unlinkedPatients as $p)
                            <option value="{{ $p->id }}">{{ $p->code }} — {{ $p->name }}</option>
                        @endforeach
                    </optgroup>
                </select>
            </div>
            <div class="field">
                <label for="u_pass">Password Awal (min {{ config('healthekg.password_min_length') }} karakter)</label>
                <input id="u_pass" type="password" name="password" minlength="{{ config('healthekg.password_min_length') }}" placeholder="Kosongkan jika memilih generate otomatis">
            </div>
            <div class="field">
                <label>&nbsp;</label>
                <label style="display:flex;align-items:center;gap:8px;font-weight:500">
                    <input type="checkbox" name="generate_password" value="1" style="width:auto">
                    Generate password otomatis (acak)
                </label>
            </div>
        </div>
        <div style="margin-top:14px">
            <button type="submit" class="btn btn-accent">Buat Akun</button>
        </div>
    </form>
</div>

<div class="card">
    <form method="GET" class="toolbar">
        <div class="field" style="flex:1;max-width:260px">
            <label>Cari</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Nama atau email">
        </div>
        <div class="field">
            <label>Role</label>
            @php
                // Network Engineer disembunyikan juga dari filter agar tidak terlihat.
                $filterRoleOptions = collect(config('healthekg.roles'))->except('network_engineer');
            @endphp
            <select name="role">
                <option value="">Semua</option>
                @foreach($filterRoleOptions as $key => $label)
                    <option value="{{ $key }}" {{ request('role') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Status</label>
            <select name="status">
                <option value="">Semua</option>
                <option value="aktif" {{ request('status') === 'aktif' ? 'selected' : '' }}>Aktif</option>
                <option value="nonaktif" {{ request('status') === 'nonaktif' ? 'selected' : '' }}>Nonaktif</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><x-icon name="search"/> Filter</button>
    </form>

    @if($users->isEmpty())
        <x-empty-state title="Belum ada akun pengguna yang cocok" message="Buat akun administrator pertama melalui perintah artisan app:create-admin (lihat dokumentasi instalasi)." icon="users"/>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>User ID</th><th>Nama</th><th>Email</th><th>Role</th><th>Status</th>
                    <th>Terakhir Login</th><th>Dibuat</th><th>Aksi</th>
                </tr>
                </thead>
                <tbody>
                @foreach($users as $u)
                    <tr>
                        <td class="mono">#{{ $u->id }}</td>
                        <td><strong>{{ $u->name }}</strong>{{ $u->must_change_password ? ' <span class="badge badge-warning">wajib ganti password</span>' : '' }}</td>
                        <td class="small">{{ $u->email }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.users.role', $u) }}" class="actions" data-confirm="Ubah role akun ini? Perubahan tercatat di audit log.">
                                @csrf
                                <select name="role" class="btn btn-outline btn-sm" style="padding:4px 6px" {{ $u->id === auth()->id() ? 'disabled' : '' }}>
                                    @php
                                        // Opsi network_engineer hanya tampil pada baris akun yang
                                        // memang sudah ber-role tersebut (agar tampilan tidak salah);
                                        // role ini tidak bisa diberikan ke akun lain lewat dashboard.
                                        $rowRoleOptions = collect(config('healthekg.roles'))
                                            ->when($u->role !== 'network_engineer', fn ($roles) => $roles->except('network_engineer'));
                                    @endphp
                                    @foreach($rowRoleOptions as $key => $label)
                                        <option value="{{ $key }}" {{ $u->role === $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @if($u->id !== auth()->id())
                                    <button type="submit" class="btn btn-outline btn-sm">Ubah</button>
                                @endif
                            </form>
                        </td>
                        <td><x-badge :status="$u->is_active ? 'aktif' : 'nonaktif'"/></td>
                        <td class="small">{{ $u->last_login_at?->locale('id')->translatedFormat('d M H:i') ?? 'Belum pernah' }}</td>
                        <td class="small">{{ $u->created_at?->format('d-m-Y') }}</td>
                        <td>
                            <div class="actions">
                                <form method="POST" action="{{ route('admin.users.toggle', $u) }}"
                                      data-confirm="{{ $u->is_active ? 'Nonaktifkan akun ini?' : 'Aktifkan kembali akun ini?' }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline btn-sm">{{ $u->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                </form>
                                <form method="POST" action="{{ route('admin.users.reset-password', $u) }}"
                                      data-confirm="Reset password akun ini? Password sementara akan ditampilkan satu kali.">
                                    @csrf
                                    <button type="submit" class="btn btn-outline btn-sm">Reset Password</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $users->links() }}
    @endif
</div>
@endsection
