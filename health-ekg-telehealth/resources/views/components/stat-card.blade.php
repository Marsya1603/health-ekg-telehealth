@props(['title', 'value', 'hint' => null, 'icon' => 'activity', 'tone' => 'cyan'])

<div class="stat-card tone-{{ $tone }}">
    <div class="stat-icon"><x-icon name="{{ $icon }}" :size="22"/></div>
    <div class="stat-body">
        <div class="stat-title">{{ $title }}</div>
        <div class="stat-value">{{ $value }}</div>
        @if($hint)
            <div class="stat-hint">{{ $hint }}</div>
        @endif
    </div>
</div>
