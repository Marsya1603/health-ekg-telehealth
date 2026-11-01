<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $code }} — HealthEKG Telehealth</title>
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
</head>
<body>
<div class="auth-bg">
    <div class="auth-card" style="text-align:center">
        <div style="font-size:3rem;font-weight:800;color:var(--navy-700)">{{ $code }}</div>
        <h1>{{ $title }}</h1>
        <p class="muted">{{ $message }}</p>
        <a href="{{ url('/') }}" class="btn btn-primary" style="margin-top:10px">Kembali ke Beranda</a>
    </div>
</div>
</body>
</html>
