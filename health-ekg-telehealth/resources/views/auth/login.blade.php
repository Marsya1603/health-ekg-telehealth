@extends('layouts.auth')
@section('content')
<div class="auth-bg">
    <div class="auth-card">
        <div class="auth-brand">
            <span class="brand-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            </span>
            <div>
                <strong>HealthEKG</strong>
                <small>TELEHEALTH</small>
            </div>
        </div>

        <h1>Masuk ke akun Anda</h1>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('login.attempt') }}">
            @csrf
            <div class="field auth-field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            </div>
            <div class="field auth-field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary">Masuk</button>
        </form>

        <div class="auth-foot">
            Sistem pemantauan EKG jarak jauh berbasis IoT.<br>
            Akun dibuat oleh Admin Puskesmas — tidak ada pendaftaran mandiri.
        </div>
    </div>
</div>
@endsection
