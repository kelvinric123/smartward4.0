@props(['label', 'placement' => 'top', 'align' => 'center'])

{{--
    Wraps a single icon-only control and gives it a label on hover.

    The admin lists show their row actions as icons so the table fits without
    sideways scrolling; the icon alone is not self-explanatory, so each one
    carries its name here. `title` is also set on the control itself by the
    caller, as a fallback.

    align="right" for the last icon in a row: a centred label there is wider
    than the space left beside it and gets clipped by the card.
--}}
<div class="relative inline-flex group/tip">
    {{ $slot }}
    <span
        class="pointer-events-none absolute z-30 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-[11px] font-medium text-white opacity-0 shadow-lg transition-opacity duration-150 group-hover/tip:opacity-100 {{ $placement === 'bottom' ? 'top-full mt-1.5' : 'bottom-full mb-1.5' }} {{ $align === 'right' ? 'right-0' : ($align === 'left' ? 'left-0' : 'left-1/2 -translate-x-1/2') }}">
        {{ $label }}
    </span>
</div>
