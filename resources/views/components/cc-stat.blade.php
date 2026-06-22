@props([
    'label' => '',
    'value' => 0,
    'hint' => null,
])

<div class="bg-white rounded-xl border border-slate-200 p-4">
    <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">{{ $label }}</div>
    <div class="mt-1.5 text-2xl font-semibold text-slate-900 tabular-nums">{{ $value }}</div>
    @if($hint)
        <div class="mt-0.5 text-xs text-slate-400">{{ $hint }}</div>
    @endif
</div>
