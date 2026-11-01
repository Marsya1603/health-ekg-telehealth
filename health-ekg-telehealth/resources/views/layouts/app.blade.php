@php
    $user = auth()->user();
    $quick = \App\Services\StatusService::quick();
    $unreadCount = $user ? $user->unreadNotificationCount() : 0;
    $latestNotifications = $user ? $user->appNotifications()->latest('created_at')->limit(5)->get() : collect();
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — HealthEKG Telehealth</title>
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
</head>
<body>
<div class="app-shell">
    @include('partials.sidebar')

    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <div class="app-main">
        <header class="topbar">
            <button class="topbar-menu" id="sidebarToggle" type="button" aria-label="Buka menu">
                <x-icon name="menu"/>
            </button>

            <div class="topbar-crumb">
                <span class="crumb-role">{{ $user?->roleLabel() ?? 'Sistem' }}</span>
                <span class="crumb-sep">/</span>
                <span class="crumb-page">@yield('title', 'Dashboard')</span>
            </div>

            <div class="topbar-status" title="Status koneksi layanan (diperiksa aktual)">
                <span class="status-chip" data-status="{{ $quick['db'] }}">
                    <span class="dot"></span> DB
                </span>
                <span class="status-chip" data-status="{{ $quick['worker'] }}">
                    <span class="dot"></span> Worker
                </span>
            </div>

            @if($user)
                <div class="topbar-right">
                    <details class="dropdown">
                        <summary class="icon-btn" aria-label="Notifikasi">
                            <x-icon name="bell"/>
                            @if($unreadCount > 0)
                                <span class="badge-count">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
                            @endif
                        </summary>
                        <div class="dropdown-panel dropdown-notif">
                            <div class="dropdown-head">
                                <strong>Notifikasi</strong>
                                <a href="{{ route('notifications.index') }}">Lihat semua</a>
                            </div>
                            @if($latestNotifications->isEmpty())
                                <div class="dropdown-empty">Belum ada notifikasi.</div>
                            @else
                                @foreach($latestNotifications as $n)
                                    <a class="notif-item {{ $n->is_read ? 'is-read' : '' }}"
                                       href="{{ $n->link ? route('notifications.read', $n->id) : route('notifications.index') }}">
                                        <span class="notif-title">{{ $n->title }}</span>
                                        <span class="notif-time">{{ $n->created_at?->locale('id')->diffForHumans() }}</span>
                                    </a>
                                @endforeach
                            @endif
                        </div>
                    </details>

                    <details class="dropdown">
                        <summary class="user-btn">
                            <span class="avatar">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($user->name, 0, 1)) }}</span>
                            <span class="user-name">{{ $user->name }}</span>
                        </summary>
                        <div class="dropdown-panel dropdown-user">
                            <div class="dropdown-head">
                                <strong>{{ $user->name }}</strong>
                                <span class="muted">{{ $user->email }}</span>
                            </div>
                            <a href="{{ route('profile.show') }}"><x-icon name="user"/> Profil Saya</a>
                            <a href="{{ route('account.settings') }}"><x-icon name="settings"/> Pengaturan Akun</a>
                            <a href="{{ route('notifications.index') }}"><x-icon name="bell"/> Notifikasi</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="link-logout"><x-icon name="logout"/> Keluar</button>
                            </form>
                        </div>
                    </details>
                </div>
            @endif
        </header>

        <main class="page">
            @if(session('success'))
                <div class="alert alert-success"><x-icon name="check-circle"/> {{ session('success') }}</div>
            @endif
            @if(session('warning'))
                <div class="alert alert-warning"><x-icon name="alert-triangle"/> {{ session('warning') }}</div>
            @endif
            @if(session('reset_password'))
                <div class="alert alert-warning">
                    <x-icon name="key"/>
                    Password sementara untuk <strong>{{ session('reset_email') }}</strong>:
                    <code class="temp-pass">{{ session('reset_password') }}</code>
                    — tampilkan sekali ini saja, bagikan melalui saluran aman, dan wajib diganti saat login pertama.
                </div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger">
                    <x-icon name="x-circle"/>
                    <div>
                        @foreach($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                </div>
            @endif

            @yield('content')
        </main>

        <footer class="app-footer">
            HealthEKG Telehealth — Pemantauan EKG jarak jauh berbasis IoT · Data hanya dari sumber aktual (tanpa data demo)
        </footer>
    </div>
</div>

<script src="{{ asset('assets/vendor/chart.umd.min.js') }}"></script>
<script src="{{ asset('assets/js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
