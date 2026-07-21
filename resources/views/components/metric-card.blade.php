@props([
    'label' => '',
    'value' => 0,
    'hint' => '',
    'icon' => null,
])

<div class="metric-card">
    @if($icon)
        <div class="metric-card-icon" style="margin-bottom:10px;width:34px;height:34px;border-radius:10px;background:var(--accent-bg);color:var(--accent);display:flex;align-items:center;justify-content:center;">
            {{ $icon }}
        </div>
    @endif
    <div class="label">{{ $label }}</div>
    <div class="value">{{ $value }}</div>
    @if($hint)
        <div class="hint">{{ $hint }}</div>
    @endif
</div>
