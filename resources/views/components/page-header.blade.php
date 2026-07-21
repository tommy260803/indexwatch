@props([
    'eyebrow' => null,
    'title' => '',
    'subtitle' => null,
    'actions' => null,
])

<div class="page-header">
    <div>
        @if($eyebrow)
            <div class="eyebrow">{{ $eyebrow }}</div>
        @endif
        <h1>{{ $title }}</h1>
        @if($subtitle)
            <p>{{ $subtitle }}</p>
        @endif
    </div>
    @if($actions)
        <div class="header-actions">
            {{ $actions }}
        </div>
    @endif
</div>
