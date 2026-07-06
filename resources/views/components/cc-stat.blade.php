@props([
    'label' => '',
    'value' => 0,
    'hint' => null,
    'color' => 'slate',
    'icon' => null,
])

@php
    $palette = [
        'slate'   => ['chip' => 'bg-slate-100 text-slate-600',   'bar' => 'from-slate-400 to-slate-500'],
        'indigo'  => ['chip' => 'bg-indigo-50 text-indigo-600',  'bar' => 'from-indigo-500 to-violet-500'],
        'violet'  => ['chip' => 'bg-violet-50 text-violet-600',  'bar' => 'from-violet-500 to-purple-500'],
        'sky'     => ['chip' => 'bg-sky-50 text-sky-600',        'bar' => 'from-sky-500 to-cyan-500'],
        'cyan'    => ['chip' => 'bg-cyan-50 text-cyan-600',      'bar' => 'from-cyan-500 to-sky-500'],
        'teal'    => ['chip' => 'bg-teal-50 text-teal-600',      'bar' => 'from-teal-500 to-emerald-500'],
        'emerald' => ['chip' => 'bg-emerald-50 text-emerald-600','bar' => 'from-emerald-500 to-teal-500'],
        'lime'    => ['chip' => 'bg-lime-50 text-lime-600',      'bar' => 'from-lime-500 to-green-500'],
        'amber'   => ['chip' => 'bg-amber-50 text-amber-600',    'bar' => 'from-amber-400 to-orange-500'],
        'orange'  => ['chip' => 'bg-orange-50 text-orange-600',  'bar' => 'from-orange-500 to-amber-500'],
        'rose'    => ['chip' => 'bg-rose-50 text-rose-600',      'bar' => 'from-rose-500 to-pink-500'],
        'red'     => ['chip' => 'bg-red-50 text-red-600',        'bar' => 'from-red-500 to-rose-500'],
        'pink'    => ['chip' => 'bg-pink-50 text-pink-600',      'bar' => 'from-pink-500 to-fuchsia-500'],
    ];
    $c = $palette[$color] ?? $palette['slate'];
@endphp

<div class="cc-card group relative overflow-hidden bg-white rounded-xl border border-slate-200 p-4 transition-all duration-300 hover:shadow-lg hover:-translate-y-0.5 hover:border-slate-300">
    <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r {{ $c['bar'] }} opacity-80 group-hover:opacity-100 transition-opacity"></div>
    <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
            <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider truncate">{{ $label }}</div>
            <div class="mt-1.5 text-2xl font-bold text-slate-900 tabular-nums" data-countup>{{ $value }}</div>
            @if($hint)
                <div class="mt-0.5 text-xs text-slate-400">{{ $hint }}</div>
            @endif
        </div>
        @if($icon)
            <div class="shrink-0 w-9 h-9 rounded-lg {{ $c['chip'] }} flex items-center justify-center transition-transform duration-300 group-hover:scale-110">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
                </svg>
            </div>
        @endif
    </div>
</div>
