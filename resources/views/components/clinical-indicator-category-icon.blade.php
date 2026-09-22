@props(['category', 'size' => 'md'])

{{--
    The coloured icon a clinical indicator category is shown with, for the
    categories in ClinicalIndicatorLibrary::CATEGORIES. Anything else,
    including Other, gets a plain grey tag.
--}}

@php
    $styles = [
        'Fall risk' => ['bg-amber-100 text-amber-700', 'M13 17h8m0 0V9m0 8l-8-8-4 4-6-6'],
        'Pain' => ['bg-pink-100 text-pink-700', 'M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        'Pressure injury risk' => ['bg-sky-100 text-sky-700', 'M20.618 5.984A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016zM12 9v2m0 4h.01'],
        'Nutrition' => ['bg-emerald-100 text-emerald-700', 'M5 3v5a2 2 0 004 0V3M7 3v18M17 21V3c-1.657 0-3 2.239-3 5v5h3'],
        'Consciousness' => ['bg-indigo-100 text-indigo-700', 'M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z'],
        'Deterioration' => ['bg-red-100 text-red-700', 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9'],
        'Delirium' => ['bg-purple-100 text-purple-700', 'M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z'],
        'Frailty' => ['bg-teal-100 text-teal-700', 'M4 8h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4a2 2 0 012-2zm17 3v2M6 11v2'],
    ];

    [$colour, $path] = $styles[$category] ?? ['bg-gray-100 text-gray-600', 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z'];

    [$box, $icon] = match ($size) {
        'xs' => ['h-5 w-5 rounded-full', 'w-3 h-3'],
        'sm' => ['h-6 w-6 rounded-md', 'w-3.5 h-3.5'],
        default => ['h-9 w-9 rounded-xl', 'w-5 h-5'],
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex shrink-0 items-center justify-center $box $colour"]) }} aria-hidden="true">
    <svg class="{{ $icon }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $path }}" />
    </svg>
</span>
