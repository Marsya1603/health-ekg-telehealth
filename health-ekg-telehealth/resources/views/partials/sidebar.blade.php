@php
    $user = auth()->user();
    $role = $user?->role;

    $menus = match ($role) {
        'admin' => [
            ['route' => 'admin.dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard'],
            ['route' => 'admin.patients.index', 'label' => 'Data Pasien', 'icon' => 'users'],
            ['route' => 'admin.doctors.index', 'label' => 'Data Dokter', 'icon' => 'heart'],
            ['route' => 'admin.users.index', 'label' => 'Manajemen Pengguna', 'icon' => 'users'],
            ['route' => 'admin.devices.index', 'label' => 'Manajemen Perangkat', 'icon' => 'cpu'],
            ['route' => 'admin.notes.index', 'label' => 'Catatan Pemeriksaan', 'icon' => 'file-text'],
            ['route' => 'admin.reports.index', 'label' => 'Laporan', 'icon' => 'bar-chart'],
        ],
        'dokter' => [
            ['route' => 'doctor.dashboard', 'label' => 'Dashboard Dokter', 'icon' => 'dashboard'],
            ['route' => 'doctor.patients.index', 'label' => 'Pasien Saya', 'icon' => 'users'],
            ['route' => 'doctor.monitoring.index', 'label' => 'Monitoring EKG', 'icon' => 'activity'],
            ['route' => 'doctor.history.index', 'label' => 'Riwayat EKG', 'icon' => 'clock'],
            ['route' => 'doctor.notes.index', 'label' => 'Catatan Pemeriksaan', 'icon' => 'file-text'],
        ],
        'pasien' => [
            ['route' => 'patient.dashboard', 'label' => 'Dashboard Saya', 'icon' => 'dashboard'],
            ['route' => 'patient.ecg.index', 'label' => 'Pemantauan EKG Saya', 'icon' => 'heart'],
            ['route' => 'patient.history.index', 'label' => 'Riwayat EKG', 'icon' => 'clock'],
            ['route' => 'patient.notes.index', 'label' => 'Catatan Dokter', 'icon' => 'file-text'],
            ['route' => 'patient.device.show', 'label' => 'Informasi Perangkat', 'icon' => 'cpu'],
        ],
        'network_engineer' => [
            ['route' => 'network.dashboard', 'label' => 'Network Operations', 'icon' => 'dashboard'],
            ['route' => 'network.devices', 'label' => 'Device Connectivity', 'icon' => 'wifi'],
            ['route' => 'network.mqtt', 'label' => 'MQTT Monitoring', 'icon' => 'radio'],
            ['route' => 'network.qos', 'label' => 'QoS Monitoring', 'icon' => 'activity'],
            ['route' => 'network.payload', 'label' => 'Payload Analysis', 'icon' => 'bar-chart'],
            ['route' => 'network.security', 'label' => 'ASCON-128 Security', 'icon' => 'shield'],
            ['route' => 'network.server-health', 'label' => 'Server Health', 'icon' => 'server'],
            ['route' => 'network.logs', 'label' => 'Transmission Logs', 'icon' => 'list'],
            ['route' => 'network.reports', 'label' => 'QoS Test Reports', 'icon' => 'file-text'],
            ['route' => 'network.events', 'label' => 'System Events', 'icon' => 'alert-triangle'],
        ],
        default => [],
    };
@endphp
<aside class="app-sidebar" id="appSidebar">
    <div class="sidebar-brand">
        <span class="brand-icon"><x-icon name="heart"/></span>
        <span class="brand-text">
            <strong>HealthEKG</strong>
            <small>Telehealth</small>
        </span>
    </div>

    <div class="sidebar-role">{{ $user?->roleLabel() ?? 'Tidak dikenal' }}</div>

    <nav class="sidebar-nav">
        @foreach($menus as $menu)
            <a href="{{ route($menu['route']) }}"
               class="nav-item {{ request()->routeIs($menu['route'].'*') ? 'is-active' : '' }}">
                <x-icon name="{{ $menu['icon'] }}"/>
                <span>{{ $menu['label'] }}</span>
            </a>
        @endforeach
    </nav>

    @if($user)
        <div class="sidebar-bottom">
            <a href="{{ route('notifications.index') }}" class="nav-item {{ request()->routeIs('notifications.*') ? 'is-active' : '' }}">
                <x-icon name="bell"/><span>Notifikasi</span>
            </a>
            <a href="{{ route('profile.show') }}" class="nav-item {{ request()->routeIs('profile.*') ? 'is-active' : '' }}">
                <x-icon name="user"/><span>Profil Saya</span>
            </a>
            <a href="{{ route('account.settings') }}" class="nav-item {{ request()->routeIs('account.settings') ? 'is-active' : '' }}">
                <x-icon name="settings"/><span>Pengaturan Akun</span>
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="nav-item nav-logout">
                    <x-icon name="logout"/><span>Logout</span>
                </button>
            </form>
        </div>
    @endif
</aside>
