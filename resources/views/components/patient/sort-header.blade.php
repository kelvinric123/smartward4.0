{{-- Sortable column header for the patient list. Keeps the current filters and flips direction on the active column. --}}
@props(['column', 'label', 'sort', 'direction', 'align' => 'left'])

@php
    $isActive = $sort === $column;
    $nextDirection = $isActive && $direction === 'asc' ? 'desc' : 'asc';
@endphp

<th {{ $attributes->merge(['class' => 'px-4 py-4 text-' . $align . ' text-xs font-bold text-gray-700 uppercase tracking-wider']) }}>
    <a href="{{ request()->fullUrlWithQuery(['sort' => $column, 'dir' => $nextDirection, 'page' => null]) }}"
        @class(['inline-flex items-center gap-1 transition-colors', 'text-blue-600' => $isActive, 'hover:text-blue-600' => !$isActive])>
        {{ $label }}
        <svg @class(['w-3.5 h-3.5', 'text-gray-300' => !$isActive]) fill="none" stroke="currentColor" viewBox="0 0 24 24">
            @if ($isActive && $direction === 'asc')
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
            @elseif ($isActive)
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            @else
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4" />
            @endif
        </svg>
    </a>
</th>
