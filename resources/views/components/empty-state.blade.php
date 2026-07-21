@props([
    'icon' => true,
    'title' => 'Sin resultados',
    'message' => 'No hay elementos que coincidan con el filtro actual.',
    'colspan' => 1,
])

@if(isset($colspan) && $colspan > 1)
    <tr>
        <td colspan="{{ $colspan }}">
@endif
            <div class="empty-state">
                @if($icon)
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M21 21l-4.35-4.35M17 11a6 6 0 1 1-12 0 6 6 0 0 1 12 0z"/>
                        <path d="M8 15h8M8 11h5"/>
                    </svg>
                @endif
                <h3>{{ $title }}</h3>
                <p>{{ $message }}</p>
            </div>
@if(isset($colspan) && $colspan > 1)
        </td>
    </tr>
@endif
