{{-- Assessment scales (GCS, AVPU, pain, falls, pressure, ...) and blood
     glucose. Bands are the ones stored when each score was taken, so a later
     change to a scale's bands does not rewrite the record. --}}
@php
    $toneRank = ['high' => 3, 'moderate' => 2, 'low' => 1];
    $toneText = ['high' => 'text-red-700', 'moderate' => 'text-amber-700', 'low' => 'text-gray-900'];
    $scoresByScale = $assessmentScores->groupBy('clinical_indicator_id');
    $lowGlucose = $glucoseReadings->filter(fn($reading) => $reading->isLow())->count();
    $highGlucose = $glucoseReadings->filter(fn($reading) => $reading->isHigh())->count();
    $scoreLine = fn($score) => $score->isReadings()
        // Monitor readings (hemodynamic numerics): the values tell the story, the score is only the worst flag
        ? trim(($score->band_label ? $score->band_label . ' · ' : '') . $score->breakdown())
        : trim($score->score . ($score->band_label ? ' · ' . $score->band_label : '') . ($score->breakdown() ? ' (' . $score->breakdown() . ')' : ''));
@endphp

<section data-section="assessments" class="mb-5 rounded-lg border border-gray-300 bg-white">
    <h3 class="{{ $sectionHeading }}">
        <span>Assessments &amp; blood glucose</span>
        <span class="{{ $sectionCount }}">
            {{ $assessmentScores->count() }} {{ Str::plural('score', $assessmentScores->count()) }},
            {{ $glucoseReadings->count() }} HGT
        </span>
    </h3>

    @if ($assessmentScores->isEmpty() && $glucoseReadings->isEmpty())
        <p class="px-4 py-3 text-sm text-gray-500">No assessment scale or blood glucose was recorded during this admission.</p>
    @else
        @if ($assessmentScores->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="{{ $tableHead }}">
                            <th class="px-4 py-1.5 font-semibold">Scale</th>
                            <th class="px-2 py-1.5 text-center font-semibold">Scores</th>
                            <th class="px-2 py-1.5 font-semibold">First</th>
                            <th class="px-2 py-1.5 font-semibold">Latest</th>
                            <th class="px-4 py-1.5 font-semibold">Highest risk seen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($scoresByScale as $scores)
                            @php
                                $first = $scores->first();
                                $latest = $scores->last();
                                $worst = $scores->sortByDesc(fn($score) => $toneRank[$score->band_tone] ?? 0)->first();
                                $scale = $latest->clinicalIndicator;
                            @endphp
                            <tr class="break-inside-avoid align-top">
                                <td class="px-4 py-1.5">
                                    <span class="font-medium text-gray-900">{{ $scale?->name ?? 'Assessment' }}</span>
                                    @if ($scale?->code)
                                        <span class="ml-1 text-[11px] font-semibold text-gray-500">{{ $scale->code }}</span>
                                    @endif
                                </td>
                                <td class="px-2 py-1.5 text-center">{{ $scores->count() }}</td>
                                <td class="px-2 py-1.5">
                                    <span class="{{ $toneText[$first->band_tone] ?? 'text-gray-900' }}">{{ $scoreLine($first) }}</span>
                                    <span class="block text-[11px] text-gray-500">{{ $first->recorded_at?->format('d M, H:i') }}</span>
                                </td>
                                <td class="px-2 py-1.5">
                                    @if ($scores->count() > 1)
                                        <span class="{{ $toneText[$latest->band_tone] ?? 'text-gray-900' }}">{{ $scoreLine($latest) }}</span>
                                        <span class="block text-[11px] text-gray-500">
                                            {{ $latest->recorded_at?->format('d M, H:i') }}{{ $latest->recordedBy ? ' · ' . $latest->recordedBy->name : '' }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">Same as first</span>
                                    @endif
                                </td>
                                <td class="px-4 py-1.5">
                                    @if ($worst->band_label)
                                        <span class="font-medium {{ $toneText[$worst->band_tone] ?? 'text-gray-900' }}">{{ $worst->band_label }}</span>
                                        <span class="block text-[11px] text-gray-500">{{ $worst->recorded_at?->format('d M, H:i') }}</span>
                                    @else
                                        <span class="text-gray-400">No band for this scale</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($glucoseReadings->isNotEmpty())
            <div class="border-t border-gray-200 px-4 py-3">
                <div class="mb-2 flex flex-wrap items-baseline gap-x-6 gap-y-1">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-gray-600">Blood glucose (HGT), mmol/L</p>
                    <p class="text-xs text-gray-600">
                        Range {{ number_format((float) $glucoseReadings->min('value'), 1) }}&ndash;{{ number_format((float) $glucoseReadings->max('value'), 1) }}
                        &middot; average {{ number_format((float) $glucoseReadings->avg('value'), 1) }}
                        @if ($lowGlucose)
                            &middot; <span class="font-semibold text-red-700">{{ $lowGlucose }} low (&lt; 4.0)</span>
                        @endif
                        @if ($highGlucose)
                            &middot; <span class="font-semibold text-amber-700">{{ $highGlucose }} high (&gt; 11.0)</span>
                        @endif
                    </p>
                </div>
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($glucoseReadings as $reading)
                        <span class="inline-flex items-baseline gap-1 rounded border px-1.5 py-0.5 text-xs {{ $reading->isLow() ? 'border-red-200 bg-red-50 text-red-800' : ($reading->isHigh() ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-gray-200 bg-gray-50 text-gray-800') }}"
                            title="{{ $reading->recordedBy?->name }}">
                            <span class="text-[10px] text-gray-500">{{ $reading->recorded_at?->format('d M H:i') }}</span>
                            <span class="font-semibold">{{ number_format((float) $reading->value, 1) }}</span>
                        </span>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</section>
