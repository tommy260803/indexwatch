@props([
    'variant' => 'default',
    'dot' => true,
])

@php
$variants = [
    'default' => 'background:var(--panel-2);color:var(--text-dim);border-color:var(--border);',
    'active' => 'color:var(--ok);background:var(--ok-bg);border-color:var(--ok-soft);',
    'inactive' => 'color:var(--warn);background:var(--warn-bg);border-color:var(--warn-soft);',
    'maintenance' => 'color:var(--accent);background:var(--accent-bg);border-color:var(--accent-soft);',
    'dba' => 'color:var(--accent);background:var(--accent-bg);border-color:var(--accent-soft);',
    'approver' => 'color:var(--approver);background:var(--approver-bg);border-color:var(--approver-soft);',
    'viewer' => 'color:var(--text-dim);background:rgba(148,163,184,.15);border-color:rgba(148,163,184,.25);',
    'ok' => 'color:var(--ok);background:var(--ok-bg);border-color:var(--ok-soft);',
    'warn' => 'color:var(--warn);background:var(--warn-bg);border-color:var(--warn-soft);',
    'crit' => 'color:var(--crit);background:var(--crit-bg);border-color:var(--crit-soft);',
    'accent' => 'color:var(--accent);background:var(--accent-bg);border-color:var(--accent-soft);',
];
$style = $variants[$variant] ?? $variants['default'];
@endphp

<span class="badge" style="{{ $style }}">
    @if($dot)
        <span class="dot" style="background:currentColor"></span>
    @endif
    {{ $slot }}
</span>
