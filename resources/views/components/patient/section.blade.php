{{-- Card for one section of the patient view page. Pass a "form" slot (plus section + action) to make it editable in place. --}}
@props([
    'title',
    'subtitle' => null,
    'section' => null,
    'action' => null,
    'open' => false,
    'accent' => 'blue',
])

@php
    $accents = [
        'blue' => 'bg-blue-100 text-blue-600',
        'cyan' => 'bg-cyan-100 text-cyan-600',
        'rose' => 'bg-rose-100 text-rose-600',
        'violet' => 'bg-violet-100 text-violet-600',
        'emerald' => 'bg-emerald-100 text-emerald-600',
        'amber' => 'bg-amber-100 text-amber-600',
    ];
    $editable = $section && isset($form);
@endphp

<section x-data="{ editing: @js($editable && $open) }"
    {{ $attributes->merge(['class' => 'flex flex-col bg-white/90 backdrop-blur-sm shadow-lg rounded-2xl border border-blue-100']) }}>
    <header class="flex items-center justify-between gap-3 px-5 py-4 border-b border-gray-100">
        <div class="flex items-center gap-3 min-w-0">
            <div class="p-2 rounded-lg shrink-0 {{ $accents[$accent] ?? $accents['blue'] }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">{{ $icon ?? '' }}</svg>
            </div>
            <div class="min-w-0">
                <h3 class="font-bold text-gray-800 leading-tight">{{ $title }}</h3>
                @if ($subtitle)
                    <p class="text-xs text-gray-500 mt-0.5">{{ $subtitle }}</p>
                @endif
            </div>
        </div>
        @if ($editable)
            <button type="button" x-show="!editing" @click="editing = true"
                class="inline-flex items-center shrink-0 px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg text-xs font-semibold transition-colors">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Edit
            </button>
            <span x-show="editing" x-cloak
                class="shrink-0 px-2.5 py-1 rounded-full bg-amber-100 text-amber-700 text-xs font-semibold">Editing</span>
        @endif
    </header>

    <div x-show="!editing" class="flex-1 px-5 py-4">
        {{ $slot }}
    </div>

    @if ($editable)
        <form x-show="editing" x-cloak x-ref="form" method="POST" action="{{ $action }}"
            x-effect="editing && $nextTick(() => $el.querySelector('input:not([type=hidden]), select, textarea')?.focus())"
            class="flex-1 flex flex-col px-5 py-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="_section" value="{{ $section }}">

            <div class="flex-1 space-y-4">
                {{ $form }}
            </div>

            <div class="flex items-center justify-end gap-2 mt-6 pt-4 border-t border-gray-100">
                <button type="button" @click="editing = false; $refs.form.reset(); $dispatch('section-reset', @js($section))"
                    class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-semibold transition-colors">
                    Cancel
                </button>
                <button type="submit"
                    class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 text-white rounded-lg text-sm font-semibold shadow-md transition-all">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Save
                </button>
            </div>
        </form>
    @endif
</section>
