@extends('layouts.auth')
@section('content')
<div class="auth-bg">
    <div class="auth-card">
        <div class="auth-brand">
            <span class="brand-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </span>
            <div>
                <strong>Ubah Password</strong>
                <small>WAJIB SEBELUM MELANJUTKAN</small>
            </div>
        </div>

        <h1>Ganti password awal Anda</h1>

        @if(session('warning'))
            <div class="alert alert-warning">{{ session('warning') }}</div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <div class="field auth-field">
                <label for="current_password">Password saat ini</label>
                <input id="current_password" type="password" name="current_password" required autofocus autocomplete="current-password">
            </div>
            <div class="field auth-field">
                <label for="password">Password baru (minimal {{ config('healthekg.password_min_length') }} karakter)</label>
                <input id="password" type="password" name="password" required autocomplete="new-password" minlength="{{ config('healthekg.password_min_length') }}">
            </div>
            <div class="field auth-field">
                <label for="password_confirmation">Ulangi password baru</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" minlength="{{ config('healthekg.password_min_length') }}">
            </div>
            <button type="submit" class="btn btn-accent">Simpan Password Baru</button>
        </form>

        <div class="auth-foot">
            Password baru digunakan pada login berikutnya.
        </div>
    </div>
</div>
@endsection
