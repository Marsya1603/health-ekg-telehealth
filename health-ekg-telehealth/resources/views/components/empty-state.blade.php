@props(['title' => 'Belum ada data', 'message' => null, 'icon' => 'inbox'])

<div class="empty-state">
    <div class="empty-icon"><x-icon name="{{ $icon }}" :size="30"/></div>
    <div class="empty-title">{{ $title }}</div>
    @if($message)
        <div class="empty-message">{{ $message }}</div>
    @endif
</div>
