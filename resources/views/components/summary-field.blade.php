@props([
    'label' => '',
    'value' => null,
])

{{-- One labelled fact in a discharge summary section.

     A field with nothing recorded still prints its label and an em dash, so a
     reader of the printed copy can tell "not recorded" apart from a field the
     form never had. --}}
<div>
    <dt class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</dt>
    <dd class="mt-0.5 text-sm text-gray-900 break-words">
        @if (filled($value))
            {{ $value }}
        @elseif ($slot->isNotEmpty())
            {{ $slot }}
        @else
            <span class="text-gray-400">&mdash;</span>
        @endif
    </dd>
</div>
