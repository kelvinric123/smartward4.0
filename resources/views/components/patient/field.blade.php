{{-- Label / value row for the patient view page. Uses "value" when given, otherwise the slot; shows a dash when both are empty. --}}
@props(['label', 'value' => null, 'mono' => false])

@php
    $hasValue = !is_null($value) && $value !== '';
@endphp

<div class="flex items-start justify-between gap-4 py-2.5">
    <dt class="text-sm text-gray-500 shrink-0">{{ $label }}</dt>
    <dd @class(['text-sm font-semibold text-gray-800 text-right min-w-0 break-words', 'font-mono' => $mono])>
        @if ($hasValue)
            {{ $value }}
        @elseif ($slot->hasActualContent())
            {{ $slot }}
        @else
            <span class="font-normal text-gray-300">&mdash;</span>
        @endif
    </dd>
</div>
