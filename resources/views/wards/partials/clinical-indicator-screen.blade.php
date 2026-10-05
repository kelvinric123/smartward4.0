{{--
    Patient details panel for a clinical indicator recorded as a screen (the
    C-SSRS, under Mental state): questions asked in order, some only after a
    given answer to an earlier one, with the most serious answer setting the
    risk and the response it calls for. Top to bottom: what the latest screen
    calls for, the screen itself, and the screens recorded before.

    The questions, when each is asked and the triage come from the library
    definition, which is what the Ward Types page shows; the server works the
    result out again when it is saved (ClinicalIndicatorScreen).
--}}
@php
    $definition = $indicator['definition'];
    $items = array_values($definition['items']);
    $latest = $indicator['latest'];
    $latestBand = $latest ? \App\Support\ClinicalIndicatorLibrary::bandFor($indicator['code'], $latest->score) : null;
    $formId = 'ci' . $indicator['id'];

    $chipClasses = [
        'high' => 'bg-red-100 text-red-800 border-red-200',
        'moderate' => 'bg-amber-100 text-amber-800 border-amber-200',
        'low' => 'bg-green-100 text-green-800 border-green-200',
    ];
    // Only a result that calls for something is called out above the form
    $calloutClasses = [
        'high' => 'border-red-300 bg-red-50 text-red-900',
        'moderate' => 'border-amber-300 bg-amber-50 text-amber-900',
    ];
    // Each answer in the colour of the risk it points to, as on the Columbia form:
    // 1 yellow (low), 2 orange (moderate), 3 red (high)
    $levelDots = [1 => 'bg-yellow-400', 2 => 'bg-orange-500', 3 => 'bg-red-600'];
    $levelChosen = [
        'border-gray-600 bg-gray-600 text-white',
        'border-yellow-400 bg-yellow-100 text-yellow-900 ring-1 ring-yellow-400',
        'border-orange-400 bg-orange-100 text-orange-900 ring-1 ring-orange-400',
        'border-red-500 bg-red-100 text-red-900 ring-1 ring-red-500',
    ];

    // After a refused save the answers come back as they were posted
    $retry = session('error') && (string) old('clinical_indicator_id') === (string) $indicator['id'];
    $oldAnswers = $retry ? (array) old('item_scores', []) : [];

    $panel = [
        'items' => array_map(fn (array $item) => [
            'abbr' => $item['abbr'],
            'askedWhen' => $item['asked_when'] ?? null,
            'options' => array_map(fn (array $option) => ['label' => $option['label'], 'value' => (string) $option['value']], $item['options']),
        ], $items),
        'answers' => array_map(fn (int $index) => isset($oldAnswers[$index]) ? (string) $oldAnswers[$index] : '', array_keys($items)),
        'bands' => array_values($definition['bands']),
        'chips' => $chipClasses,
        'chosen' => $levelChosen,
    ];
@endphp

@once
    <script>
        // One screen panel (the C-SSRS form) per clinical indicator recorded as a screen
        window.clinicalScreenPanel = function (config) {
            return {
                ...config,
                showReference: false,

                // The label of the answer given to a question that was asked, by its abbr
                answerTo(abbr) {
                    const i = this.items.findIndex(item => item.abbr === abbr);
                    if (i < 0 || this.answers[i] === '' || !this.asked(i)) return null;
                    const option = this.items[i].options.find(o => o.value === this.answers[i]);
                    return option ? option.label : null;
                },
                // Asked, unless it waits on an answer to an earlier question that was not given
                asked(i) {
                    const when = this.items[i].askedWhen;
                    return !when || this.answerTo(when[0]) === when[1];
                },
                choose(i, value) {
                    this.answers[i] = value;
                    // A changed answer can stop a later question being asked: its answer goes too
                    this.items.forEach((item, j) => {
                        if (!this.asked(j)) this.answers[j] = '';
                    });
                },
                get answered() {
                    return this.items.filter((item, i) => this.asked(i) && this.answers[i] !== '').length;
                },
                get remaining() {
                    return this.items.filter((item, i) => this.asked(i) && this.answers[i] === '').length;
                },
                // The most serious answer so far sets the risk, as the server will work it out
                get band() {
                    let level = null;
                    this.items.forEach((item, i) => {
                        if (this.asked(i) && this.answers[i] !== '') {
                            level = Math.max(level === null ? 0 : level, Number(this.answers[i]));
                        }
                    });
                    if (level === null) return null;
                    return this.bands.find(b => (b.min === null || level >= b.min) && (b.max === null || level <= b.max)) || null;
                },
                // Shown once every question asked is answered, or as soon as an answer points to any risk
                get showRisk() {
                    return this.band !== null && (this.remaining === 0 || this.band.min > 0);
                },
                optionClass(i, value) {
                    return this.answers[i] === value
                        ? (this.chosen[Number(value)] || this.chosen[this.chosen.length - 1])
                        : 'border-gray-300 bg-white text-gray-700 hover:border-gray-400 hover:bg-gray-50';
                },
            };
        };
    </script>
@endonce

<div x-show="activeTab === 'indicator-{{ $indicator['id'] }}'" x-cloak>
<div x-data="clinicalScreenPanel(@js($panel))">
    {{-- Title, and the latest result --}}
    <div class="flex flex-col gap-2 mb-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-2">
            <h3 class="text-lg font-semibold text-gray-800">{{ $indicator['name'] }}</h3>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-violet-100 text-violet-800">
                {{ $indicator['code'] }}
            </span>
        </div>
        @if ($latest)
            <div class="flex items-center gap-2 text-xs text-gray-500">
                <span>
                    Last screened {{ $latest->recorded_at->format('d M Y H:i') }}
                    @if ($latest->recordedBy)
                        &middot; {{ $latest->recordedBy->name }}
                    @endif
                </span>
                @if ($latest->band_label)
                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg border text-xs font-semibold {{ $chipClasses[$latest->band_tone] ?? $chipClasses['low'] }}">
                        {{ $latest->band_label }}
                    </span>
                @endif
            </div>
        @endif
    </div>

    {{-- What the latest screen calls for --}}
    @if ($latest && isset($calloutClasses[$latest->band_tone]))
        <div class="mb-3 flex gap-3 rounded-lg border px-4 py-3 {{ $calloutClasses[$latest->band_tone] }}">
            <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div class="min-w-0">
                <p class="text-sm font-semibold">
                    {{ $latest->band_label }} on the last screen
                    @if ($latest->breakdown())
                        <span class="font-normal">&middot; {{ $latest->breakdown() }}</span>
                    @endif
                </p>
                @if (!empty($latestBand['action']))
                    <p class="mt-0.5 text-sm">{{ $latestBand['action'] }}</p>
                @endif
                <p class="mt-1 text-xs opacity-75">Screened {{ $latest->recorded_at->diffForHumans() }}</p>
            </div>
        </div>
    @endif

    @if ($indicator['monitoring'])
        @php
            $monitoring = $indicator['monitoring'];
            $monitoringStyles = [
                'overdue' => ['box' => 'border-red-200 bg-red-50 text-red-800', 'badge' => 'bg-red-600 text-white', 'word' => 'Overdue'],
                'due' => ['box' => 'border-amber-200 bg-amber-50 text-amber-800', 'badge' => 'bg-amber-400 text-white', 'word' => 'Due'],
            ];
            $monitoringStyle = $monitoringStyles[$monitoring['state']] ?? null;
        @endphp
        <div class="mb-3 flex items-center gap-2 rounded-lg border px-3 py-2 text-sm {{ $monitoringStyle['box'] ?? 'border-gray-200 bg-gray-50 text-gray-600' }}">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            @if ($monitoringStyle)
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $monitoringStyle['badge'] }}">
                    {{ $monitoringStyle['word'] }}
                </span>
            @endif
            <span>
                <span class="font-semibold">Screen every {{ $monitoring['interval'] }}</span>
                &middot; {{ $monitoring['state'] === 'ok' ? $monitoring['label'] : ucfirst($monitoring['history']) }}
            </span>
        </div>
    @endif

    {{-- The screen, as on the Columbia form --}}
    <form method="POST" action="{{ route('ward.clinical-indicator-score.store') }}"
        class="rounded-xl border border-gray-200 bg-white overflow-hidden">
        @csrf
        <input type="hidden" name="patient_id" value="{{ $patient->id }}">
        <input type="hidden" name="clinical_indicator_id" value="{{ $indicator['id'] }}">
        <input type="hidden" name="active_tab" value="indicator-{{ $indicator['id'] }}">

        <div class="flex flex-col gap-1 border-b border-gray-200 px-4 py-3 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
            <h4 class="text-sm font-bold text-gray-800">{{ $definition['form_title'] ?? $definition['name'] }}</h4>
            @if (!empty($definition['how_to_ask']))
                <p class="text-xs text-gray-500 sm:shrink-0">{{ $definition['how_to_ask'] }}</p>
            @endif
        </div>

        <ol>
            @foreach ($items as $index => $item)
                @if (!empty($item['before']))
                    <li class="{{ $index === 0 ? '' : 'border-t border-gray-200' }} bg-gray-50 px-4 py-2 text-xs font-semibold text-gray-700">
                        {{ $item['before'] }}
                    </li>
                @endif
                <li @if (!empty($item['asked_when'])) x-show="asked({{ $index }})" x-cloak @endif
                    class="border-t border-gray-100 px-4 py-3 {{ !empty($item['asked_when']) ? 'sm:pl-10' : '' }}">
                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between md:gap-6">
                        <div class="min-w-0">
                            <p id="{{ $formId }}_q{{ $index }}" class="text-sm text-gray-900">
                                <span class="font-bold">{{ $index + 1 }})</span>
                                <span class="font-semibold underline decoration-gray-300 underline-offset-2">{{ $item['question'] }}</span>
                            </p>
                            <p class="mt-0.5 text-xs text-gray-500">
                                {{ $item['name'] }}@if (!empty($item['period'])) &middot; {{ $item['period'] }}@endif
                            </p>
                            @if (!empty($item['prompt']))
                                <p class="mt-1 text-xs italic text-gray-600">{{ $item['prompt'] }}</p>
                            @endif
                            @if (!empty($item['follow_up']))
                                <p class="mt-1 text-xs font-semibold text-gray-700">{{ $item['follow_up'] }}</p>
                            @endif
                        </div>
                        <div role="radiogroup" aria-labelledby="{{ $formId }}_q{{ $index }}"
                            class="flex flex-wrap gap-2 md:shrink-0 md:justify-end">
                            @foreach ($item['options'] as $option)
                                <label class="cursor-pointer">
                                    <input type="radio" class="peer sr-only" name="item_scores[{{ $index }}]"
                                        value="{{ $option['value'] }}"
                                        :checked="answers[{{ $index }}] === '{{ $option['value'] }}'"
                                        :disabled="!asked({{ $index }})"
                                        @change="choose({{ $index }}, '{{ $option['value'] }}')">
                                    <span class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm font-semibold transition-colors peer-focus-visible:ring-2 peer-focus-visible:ring-blue-500 peer-focus-visible:ring-offset-1"
                                        :class="optionClass({{ $index }}, '{{ $option['value'] }}')">
                                        @if ($option['value'] > 0)
                                            <span class="h-2 w-2 shrink-0 rounded-full {{ $levelDots[$option['value']] ?? 'bg-red-600' }}" aria-hidden="true"></span>
                                        @endif
                                        {{ $option['label'] }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </li>
            @endforeach
        </ol>

        {{-- The risk the answers point to, the response it calls for, and saving --}}
        <div class="border-t border-gray-200 bg-gray-50 px-4 py-4">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="min-w-0 lg:max-w-md" aria-live="polite">
                    <div class="text-xs text-gray-500">Risk</div>
                    <div x-show="showRisk" x-cloak class="mt-1">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg border text-xs font-semibold"
                            :class="band ? chips[band.tone] : ''" x-text="band ? band.label : ''"></span>
                        <span x-show="remaining > 0" class="ml-1 text-xs text-gray-500">so far</span>
                        <p class="mt-1 text-sm font-medium text-gray-800" x-text="band ? band.action : ''"></p>
                    </div>
                    <p x-show="!showRisk" class="mt-1 text-sm text-gray-500">Answer the questions to see the risk.</p>
                </div>

                <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-end lg:max-w-xl">
                    <div class="flex-1">
                        <label for="{{ $formId }}_notes" class="block text-xs font-semibold text-gray-700 mb-1">Notes (optional)</label>
                        <input type="text" id="{{ $formId }}_notes" name="notes" maxlength="1000" value="{{ $retry ? old('notes') : '' }}"
                            class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500"
                            placeholder="{{ $definition['notes_example'] ?? '' }}">
                    </div>
                    <button type="submit"
                        class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:bg-gray-300 disabled:cursor-not-allowed"
                        :disabled="answered === 0 || remaining > 0">
                        Save screen
                    </button>
                </div>
            </div>
            <p x-show="answered > 0 && remaining > 0" x-cloak class="mt-2 text-xs text-gray-500"
                x-text="remaining + (remaining === 1 ? ' question' : ' questions') + ' still to answer'"></p>
        </div>
    </form>

    {{-- Screens recorded before, newest first --}}
    <div class="mt-4">
        <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Recent screens</h4>
        @if ($indicator['history']->isNotEmpty())
            <div class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">When</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Risk</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Answers</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">By</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        @foreach ($indicator['history'] as $record)
                            <tr class="align-top {{ $record->band_tone === 'high' ? 'bg-red-50' : '' }}">
                                <td class="px-3 py-2 whitespace-nowrap text-gray-600">{{ $record->recorded_at->format('d M H:i') }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    @if ($record->band_label)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border {{ $chipClasses[$record->band_tone] ?? $chipClasses['low'] }}">
                                            {{ $record->band_label }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-gray-700">{{ $record->breakdown() ?? 'No to every question asked' }}</td>
                                <td class="px-3 py-2 text-gray-600 whitespace-nowrap">{{ $record->recordedBy->name ?? '-' }}</td>
                                <td class="px-3 py-2 min-w-[10rem] text-gray-600">{{ $record->notes ?: '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="rounded-lg border border-dashed border-gray-300 px-4 py-4 text-center text-sm text-gray-500">No screens recorded yet.</p>
        @endif
    </div>

    <div class="mt-4">
        <button type="button" @click="showReference = !showReference"
            class="inline-flex items-center text-xs font-medium text-blue-600 hover:text-blue-800">
            <svg class="w-3.5 h-3.5 mr-1 transition-transform" :class="showReference ? 'rotate-180' : ''" fill="none"
                stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
            <span x-text="showReference ? 'Hide scale details' : 'Show scale details'">Show scale details</span>
        </button>
        <div x-show="showReference" x-cloak class="mt-2">
            <x-clinical-indicator-detail :definition="$definition" />
        </div>
    </div>
</div>
</div>
