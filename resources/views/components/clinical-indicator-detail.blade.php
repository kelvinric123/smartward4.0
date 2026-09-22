@props(['definition'])

{{--
    The clinical content of one assessment scale, from
    app/Support/ClinicalIndicatorLibrary.php. Shared by the Ward Type admin
    page and the patient details modal on the ward dashboard so both always
    show the same instrument.
--}}

@php
    $tones = [
        'high' => 'bg-red-50 text-red-800 border-red-200',
        'moderate' => 'bg-amber-50 text-amber-800 border-amber-200',
        'low' => 'bg-green-50 text-green-800 border-green-200',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border border-blue-100 bg-white p-6']) }}>
    @if (!empty($definition['purpose']))
        <p class="text-sm text-gray-700">{{ $definition['purpose'] }}</p>
    @endif

    @if (!empty($definition['population']))
        <p class="mt-2 text-xs text-gray-500">
            <span class="font-semibold text-gray-600">Applies to</span> {{ $definition['population'] }}
        </p>
    @endif

    @if (!empty($definition['items']))
        <div class="mt-5">
            <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">What is scored</h4>
            <div class="divide-y divide-gray-100 rounded-lg border border-gray-200 overflow-hidden">
                @foreach ($definition['items'] as $item)
                    <div class="px-4 py-2.5 flex flex-col gap-0.5 sm:flex-row sm:gap-4">
                        <span class="text-sm font-medium text-gray-800 sm:w-64 sm:shrink-0">{{ $item['name'] }}</span>
                        <span
                            class="text-sm text-gray-600">{{ \App\Support\ClinicalIndicatorLibrary::itemScoringText($item) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if (!empty($definition['bands']))
        <div class="mt-5">
            <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                Total score
                @if ($definition['score_min'] !== null)
                    <span class="font-medium text-gray-500 normal-case tracking-normal">({{ $definition['score_min'] }}
                        to {{ $definition['score_max'] }})</span>
                @endif
            </h4>
            <div class="flex flex-wrap gap-2">
                @foreach ($definition['bands'] as $band)
                    <span
                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border text-xs font-medium {{ $tones[$band['tone']] ?? $tones['low'] }}">
                        <span class="font-bold">{{ $band['range'] }}</span>
                        <span>{{ $band['label'] }}</span>
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    @if (!empty($definition['note']))
        <div class="mt-5 flex gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
            <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <p class="text-xs text-amber-800">{{ $definition['note'] }}</p>
        </div>
    @endif

    @if (!empty($definition['reference']))
        <p class="mt-4 text-xs text-gray-400">Source: {{ $definition['reference'] }}</p>
    @endif
</div>
