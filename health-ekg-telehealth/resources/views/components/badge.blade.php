@props(['status' => null, 'tone' => null])

@php
    $map = [
        // status data / umum
        'aktif' => 'success', 'success' => 'success', 'valid' => 'success', 'terhubung' => 'success',
        'ok' => 'success', 'selesai' => 'info', 'diterima' => 'info',
        'idle' => 'warning', 'warning' => 'warning', 'duplikat' => 'warning', 'menunggu' => 'warning',
        'gagal' => 'danger', 'error' => 'danger', 'invalid' => 'danger', 'nonaktif' => 'danger',
        'unknown' => 'muted', 'unknown_state' => 'muted', 'dihentikan' => 'muted', 'muted' => 'muted',
        'info' => 'info',
    ];
    $t = $tone ?? $map[strtolower((string) $status)] ?? 'muted';
    $label = $status ? ucfirst($status) : $slot;
@endphp
<span class="badge badge-{{ $t }}" {{ $attributes }}>
    @if($status)<span class="dot"></span>@endif
    {{ $label }}
</span>
