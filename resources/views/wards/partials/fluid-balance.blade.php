{{--
    Patient Details > I/O Chart: intake and output for one chart day, the
    fluid plan (intake limit and minimum urine output) and signs of fluid
    overload. Expects $patient and $chart (App\Support\FluidBalanceChart::forPatient).
--}}
@php
    $fbe = \App\Models\FluidBalanceEntry::class;
    $fbp = \App\Models\FluidBalancePlan::class;
    $foa = \App\Models\FluidOverloadAssessment::class;

    $day = $chart['day'];
    $totals = $chart['totals'];
    $status = $chart['status'];
    $limit = $status['limit'];
    $urine = $status['urine'];
    $currentPlan = $chart['current_plan'];
    $latest = $chart['latest_assessment'];
    $weight = $chart['weight'];
    $days = $chart['days'];
    $isToday = $day['is_current'];

    $ml = fn (int $value) => $fbe::formatMl($value);
    $signed = fn (int $value) => $fbe::formatBalance($value);
    $dayUrl = fn (?string $key) => route('ward.patient-details', array_filter([
        'patient_id' => $patient->id,
        'active_tab' => 'io',
        'io_day' => $key,
    ]));
    $balanceClass = fn (int $value) => $value > 0 ? 'text-sky-700' : ($value < 0 ? 'text-amber-700' : 'text-gray-700');

    // A form that failed validation opens again with what was entered
    $failedForm = old('active_tab') === 'io' ? old('_form') : null;
    $recordFailed = $failedForm === 'record';
    $planFailed = $failedForm === 'plan';
    $assessFailed = $failedForm === 'assess';
    $voidFailed = $failedForm && str_starts_with($failedForm, 'void-') ? (int) substr($failedForm, 5) : null;

    $recordDirection = $recordFailed && old('direction') === $fbe::DIRECTION_OUTPUT ? $fbe::DIRECTION_OUTPUT : $fbe::DIRECTION_INTAKE;
    $recordState = [
        'direction' => $recordDirection,
        'category' => $recordFailed && array_key_exists((string) old('category'), $fbe::typesFor($recordDirection))
            ? (string) old('category')
            : array_key_first($fbe::typesFor($recordDirection)),
        'volume' => $recordFailed ? (string) old('volume_ml', '') : '',
        'description' => $recordFailed ? (string) old('description', '') : '',
        'earlier' => $recordFailed && filled(old('recorded_at')),
        'at' => $recordFailed ? (string) old('recorded_at', '') : '',
    ];

    $planState = [
        'limit' => $planFailed ? (string) old('intake_limit_ml', '') : (string) ($currentPlan?->intake_limit_ml ?? ''),
        'urineMin' => $planFailed ? (string) old('urine_min_ml_per_hour', '') : (string) ($currentPlan?->urine_min_ml_per_hour ?? ''),
    ];

    $assessState = [
        'grade' => $assessFailed ? (int) old('edema_grade', 0) : 0,
        'sites' => $assessFailed ? array_values((array) old('edema_sites', [])) : [],
        'signs' => $assessFailed ? array_values((array) old('signs', [])) : [],
        'earlier' => $assessFailed && filled(old('assessed_at')),
        'at' => $assessFailed ? (string) old('assessed_at', '') : '',
    ];

    $fieldClass = 'block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500';
    $labelClass = 'block text-xs font-semibold text-gray-700 mb-1';
    $chipClass = 'inline-flex items-center px-2.5 py-1.5 rounded-lg border text-xs font-semibold transition-colors';
    $alertTone = [
        'critical' => 'border-red-300 bg-red-50 text-red-800',
        'warning' => 'border-amber-300 bg-amber-50 text-amber-800',
        'info' => 'border-blue-200 bg-blue-50 text-blue-800',
    ];
@endphp

<div x-data="{
        panel: @js($planFailed ? 'plan' : ($assessFailed ? 'assess' : null)),
        striking: @js($voidFailed),
        localNow() {
            const d = new Date(), pad = n => String(n).padStart(2, '0');
            return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
        },
        toggle(name) { this.panel = this.panel === name ? null : name; },
    }">
    {{-- Header: title, flags, and the chart day being shown --}}
    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <div class="flex flex-wrap items-center gap-2">
            <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                <svg class="w-5 h-5 mr-2 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z" />
                </svg>
                I/O Chart
            </h3>
            @if ($isToday && $limit && $limit['state'] === 'over')
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-red-600 text-white">Over limit</span>
            @elseif ($isToday && $limit && $limit['state'] === 'near')
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">Near limit</span>
            @endif
            @if ($latest && $latest->hasOverloadSigns())
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800">Overload signs</span>
            @endif
        </div>

        {{-- Chart day: 24 h from the start of the ward's first shift --}}
        <div class="flex items-center gap-1 text-sm">
            @if ($day['previous'])
                <a href="{{ $dayUrl($day['previous']) }}" title="Previous day"
                    class="inline-flex items-center justify-center w-8 h-8 rounded-md border border-gray-300 bg-white text-gray-600 hover:bg-gray-50">&lsaquo;</a>
            @endif
            <div class="px-2 text-center leading-tight">
                <div class="font-semibold text-gray-800">{{ $isToday ? 'Today' : $day['start']->format('D j M') }}</div>
                <div class="text-[11px] text-gray-500">
                    {{ $day['start']->format('j M H:i') }} &ndash; {{ $day['end']->format('j M H:i') }}
                </div>
            </div>
            @if ($day['next'])
                <a href="{{ $dayUrl($day['next']) }}" title="Next day"
                    class="inline-flex items-center justify-center w-8 h-8 rounded-md border border-gray-300 bg-white text-gray-600 hover:bg-gray-50">&rsaquo;</a>
                <a href="{{ $dayUrl(null) }}"
                    class="ml-1 inline-flex items-center px-3 h-8 rounded-md bg-blue-600 text-white text-xs font-semibold hover:bg-blue-700">Today</a>
            @endif
        </div>
    </div>

    {{-- Why the last form was not saved --}}
    @if ($failedForm && $errors->any())
        <div class="mb-3 rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-xs text-red-800">
            <div class="font-semibold">Not saved</div>
            <ul class="mt-0.5 list-disc ml-4">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Flags, worst first --}}
    @if ($status['alerts'])
        <div class="mb-3 space-y-2">
            @foreach ($status['alerts'] as $alert)
                <div class="flex items-start gap-2 rounded-lg border px-3 py-2 {{ $alertTone[$alert['level']] }}">
                    <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div class="text-xs">
                        <span class="font-semibold">{{ $alert['title'] }}</span>
                        <div class="mt-0.5">{{ $alert['detail'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Day at a glance --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        {{-- Intake against the limit --}}
        @php
            $intakeTone = match ($limit['state'] ?? null) {
                'over' => ['card' => 'border-red-300 bg-red-50/60', 'bar' => 'bg-red-500'],
                'near' => ['card' => 'border-amber-300 bg-amber-50/60', 'bar' => 'bg-amber-400'],
                default => ['card' => 'border-gray-200 bg-white', 'bar' => 'bg-sky-500'],
            };
        @endphp
        <div class="rounded-xl border p-3 {{ $intakeTone['card'] }}">
            <div class="text-[11px] font-bold uppercase tracking-wide text-sky-700">Intake{{ $isToday ? ' so far' : '' }}</div>
            <div class="mt-1 text-2xl font-bold text-gray-900">
                {{ number_format($totals['intake']) }} <span class="text-sm font-medium text-gray-500">mL</span>
            </div>
            @if ($limit)
                <div class="mt-2 h-2 rounded-full bg-gray-200 overflow-hidden"
                    role="progressbar" aria-valuemin="0" aria-valuemax="{{ $limit['limit'] }}" aria-valuenow="{{ $limit['taken'] }}"
                    aria-label="Intake against the limit">
                    <div class="h-full rounded-full {{ $intakeTone['bar'] }}" style="width: {{ min(100, $limit['percent']) }}%"></div>
                </div>
                <div class="mt-1 text-[11px] text-gray-600">
                    {{ $limit['percent'] }}% of the {{ $ml($limit['limit']) }} limit
                    <span class="text-gray-400">&middot;</span>
                    @if ($limit['state'] === 'over')
                        <span class="font-semibold text-red-700">{{ $ml($limit['over_by']) }} over</span>
                    @else
                        {{ $ml($limit['remaining']) }} left{{ $isToday ? ' until ' . $day['end']->format('H:i') : '' }}
                    @endif
                </div>
            @else
                <div class="mt-2 text-[11px] text-gray-500">No intake limit set</div>
            @endif
        </div>

        {{-- Output, with urine against its target --}}
        <div class="rounded-xl border p-3 {{ ($urine['state'] ?? null) === 'low' ? 'border-amber-300 bg-amber-50/60' : 'border-gray-200 bg-white' }}">
            <div class="text-[11px] font-bold uppercase tracking-wide text-amber-700">Output{{ $isToday ? ' so far' : '' }}</div>
            <div class="mt-1 text-2xl font-bold text-gray-900">
                {{ number_format($totals['output']) }} <span class="text-sm font-medium text-gray-500">mL</span>
            </div>
            <div class="mt-2 text-[11px] text-gray-600">
                Urine {{ $ml($totals['urine']) }}@if ($status['urine_average'] !== null)<span class="text-gray-400"> &middot; </span>{{ $status['urine_average'] }} mL/h avg @endif
            </div>
            @if ($urine)
                <div class="mt-0.5 text-[11px]">
                    <span class="text-gray-500">Target at least {{ $urine['min'] }} mL/h</span>
                    @if ($urine['state'] === 'low')
                        <span class="ml-1 inline-flex px-1.5 rounded bg-amber-200 text-amber-900 font-semibold">Low</span>
                    @elseif ($urine['state'] === 'ok')
                        <span class="ml-1 inline-flex px-1.5 rounded bg-emerald-100 text-emerald-800 font-semibold">On target</span>
                    @else
                        <span class="ml-1 text-gray-400">(judged after {{ \App\Support\FluidBalanceChart::URINE_CHECK_AFTER_HOURS }} h)</span>
                    @endif
                </div>
            @endif
        </div>

        {{-- Balance: the day, and the stay so far --}}
        <div class="rounded-xl border border-gray-200 bg-white p-3">
            <div class="text-[11px] font-bold uppercase tracking-wide text-gray-600">Balance{{ $isToday ? ' so far' : '' }}</div>
            <div class="mt-1 text-2xl font-bold {{ $balanceClass($totals['balance']) }}">{{ $signed($totals['balance']) }}</div>
            <div class="mt-2 text-[11px] text-gray-600">
                {{ $totals['balance'] > 0 ? 'More in than out' : ($totals['balance'] < 0 ? 'More out than in' : 'In and out even') }}
            </div>
            @if ($days['stay_balance'] !== null)
                <div class="mt-0.5 text-[11px] text-gray-500">
                    Since admission ({{ $days['stay_since']->format('j M') }}):
                    <span class="font-semibold {{ $balanceClass($days['stay_balance']) }}">{{ $signed($days['stay_balance']) }}</span>
                </div>
            @endif
        </div>

        {{-- Latest overload check and weight --}}
        <div class="rounded-xl border p-3 {{ $latest && $latest->hasOverloadSigns() ? 'border-rose-300 bg-rose-50/60' : 'border-gray-200 bg-white' }}">
            <div class="text-[11px] font-bold uppercase tracking-wide text-rose-700">Overload signs</div>
            @if ($latest)
                <div class="mt-1 text-sm font-semibold {{ $latest->hasOverloadSigns() ? 'text-rose-800' : 'text-emerald-700' }}">
                    {{ $latest->hasOverloadSigns() ? implode(', ', $latest->findings()) : 'None found' }}
                </div>
                <div class="mt-0.5 text-[11px] text-gray-500">
                    {{ $latest->assessed_at->format('j M H:i') }}{{ $latest->recordedBy ? ' by ' . $latest->recordedBy->name : '' }}
                </div>
            @else
                <div class="mt-1 text-sm text-gray-500">Not assessed yet</div>
            @endif
            @if ($weight)
                <div class="mt-1 text-[11px] text-gray-600">
                    Weight <span class="font-semibold">{{ number_format($weight['kg'], 1) }} kg</span>
                    @if ($weight['change'] !== null)
                        <span class="{{ $weight['gain'] !== null ? 'font-semibold text-red-700' : ($weight['change'] > 0 ? 'text-amber-700' : 'text-gray-500') }}">
                            ({{ $weight['change'] > 0 ? '+' : '' }}{{ number_format($weight['change'], 1) }} since {{ $weight['previous_at']->format('j M') }})
                        </span>
                    @endif
                </div>
            @endif
        </div>
    </div>

    @if ($isToday)
        {{-- Record intake or output: the most frequent job, so it sits at the top and stays open --}}
        <div x-data="{
                ...@js($recordState),
                types: @js([$fbe::DIRECTION_INTAKE => $fbe::INTAKE_TYPES, $fbe::DIRECTION_OUTPUT => $fbe::OUTPUT_TYPES]),
                suggestions: @js($fbe::SUGGESTIONS),
                get intake() { return this.direction === '{{ $fbe::DIRECTION_INTAKE }}'; },
                choose(direction) {
                    if (this.direction === direction) return;
                    this.direction = direction;
                    this.category = Object.keys(this.types[direction])[0];
                    this.description = '';
                },
                pick(category) {
                    if (this.category !== category) this.description = '';
                    this.category = category;
                },
                useEarlier() { this.earlier = true; if (!this.at) this.at = this.localNow(); },
            }" class="mb-4 rounded-xl border p-4 transition-colors"
            :class="intake ? 'border-sky-200 bg-sky-50/40' : 'border-amber-200 bg-amber-50/40'">
            <form method="POST" action="{{ route('ward.fluid-balance.store') }}">
                @csrf
                <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                <input type="hidden" name="active_tab" value="io">
                <input type="hidden" name="_form" value="record">
                <input type="hidden" name="direction" value="{{ $recordState['direction'] }}" :value="direction">
                <input type="hidden" name="category" value="{{ $recordState['category'] }}" :value="category">

                <div class="flex flex-wrap items-center gap-3">
                    <div class="inline-flex rounded-lg border border-gray-300 bg-white p-0.5" role="group" aria-label="Intake or output">
                        <button type="button" @click="choose('{{ $fbe::DIRECTION_INTAKE }}')"
                            class="px-4 py-2 rounded-md text-sm font-bold transition-colors"
                            :class="intake ? 'bg-sky-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'">
                            Intake
                        </button>
                        <button type="button" @click="choose('{{ $fbe::DIRECTION_OUTPUT }}')"
                            class="px-4 py-2 rounded-md text-sm font-bold transition-colors"
                            :class="!intake ? 'bg-amber-500 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'">
                            Output
                        </button>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <template x-for="(label, key) in types[direction]" :key="direction + key">
                            <button type="button" @click="pick(key)" x-text="label"
                                class="inline-flex items-center px-3 py-2 rounded-lg border text-sm font-semibold transition-colors"
                                :class="category === key
                                    ? (intake ? 'bg-sky-600 border-sky-600 text-white' : 'bg-amber-500 border-amber-500 text-white')
                                    : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'"></button>
                        </template>
                    </div>
                </div>

                <div class="mt-3 grid grid-cols-1 md:grid-cols-12 gap-3">
                    <div class="md:col-span-5">
                        <label class="{{ $labelClass }}">Volume (mL) <span class="text-red-500">*</span></label>
                        <input type="number" name="volume_ml" x-model="volume" min="1" max="{{ $fbe::VOLUME_MAX }}" step="1" required
                            inputmode="numeric" placeholder="mL" class="{{ $fieldClass }} text-base font-semibold">
                        <div class="mt-1.5 flex flex-wrap gap-1">
                            @foreach ($fbe::QUICK_VOLUMES as $quick)
                                <button type="button" @click="volume = '{{ $quick }}'"
                                    class="px-2 py-1 rounded-md border text-xs font-semibold transition-colors"
                                    :class="String(volume) === '{{ $quick }}' ? 'bg-gray-800 border-gray-800 text-white' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'">
                                    {{ $quick }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                    <div class="md:col-span-4">
                        <label class="{{ $labelClass }}">Details</label>
                        <input type="text" name="description" x-model="description" maxlength="120"
                            :placeholder="intake ? 'e.g. what was drunk, which fluid' : 'e.g. which drain, colour'" class="{{ $fieldClass }}">
                        <div class="mt-1.5 flex flex-wrap gap-1">
                            <template x-for="suggestion in (suggestions[direction][category] || [])" :key="suggestion">
                                <button type="button" @click="description = suggestion" x-text="suggestion"
                                    class="px-2 py-1 rounded-md border text-xs transition-colors"
                                    :class="description === suggestion ? 'bg-gray-800 border-gray-800 text-white' : 'bg-white border-gray-300 text-gray-600 hover:bg-gray-50'"></button>
                            </template>
                        </div>
                    </div>
                    <div class="md:col-span-3">
                        <label class="{{ $labelClass }}">Time</label>
                        <div x-show="!earlier" class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm py-2">
                            <span class="font-semibold text-gray-800">Now</span>
                            <button type="button" @click="useEarlier()" class="text-xs font-semibold text-blue-700 hover:underline">
                                Earlier time
                            </button>
                        </div>
                        <div x-show="earlier" x-cloak class="flex items-center gap-2">
                            <input type="datetime-local" name="recorded_at" x-model="at"
                                :disabled="!earlier" :required="earlier" class="{{ $fieldClass }}">
                            <button type="button" @click="earlier = false; at = ''"
                                class="shrink-0 text-xs font-semibold text-blue-700 hover:underline">Now</button>
                        </div>
                    </div>
                </div>

                <div class="mt-3 flex items-center justify-end gap-3">
                    <span class="text-xs text-gray-500" x-show="volume > 0" x-cloak
                        x-text="(intake ? 'In: ' : 'Out: ') + types[direction][category] + (description ? ' - ' + description : '') + ', ' + Number(volume).toLocaleString() + ' mL'"></span>
                    <button type="submit"
                        class="inline-flex items-center px-5 py-2 text-white text-sm font-semibold rounded-md shadow-sm"
                        :class="intake ? 'bg-sky-600 hover:bg-sky-700' : 'bg-amber-600 hover:bg-amber-700'">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span x-text="intake ? 'Save intake' : 'Save output'">Save intake</span>
                    </button>
                </div>
            </form>
        </div>
    @else
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-600">
            <span>
                Viewing the chart for {{ $day['start']->format('D j M') }}. New entries are recorded on today's chart
                (an earlier time up to 24 hours back is allowed).
            </span>
            <a href="{{ $dayUrl(null) }}" class="font-semibold text-blue-700 hover:underline">Go to today &rarr;</a>
        </div>
    @endif

    {{-- The chart: entries under the shift they fall in, with a running balance --}}
    <div class="overflow-x-auto rounded-lg border border-gray-200">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-600">
                <tr>
                    <th class="px-3 py-2 text-left font-semibold w-16">Time</th>
                    <th class="px-3 py-2 text-left font-semibold text-sky-700">Intake</th>
                    <th class="px-3 py-2 text-right font-semibold text-sky-700 w-20">In mL</th>
                    <th class="px-3 py-2 text-left font-semibold text-amber-700">Output</th>
                    <th class="px-3 py-2 text-right font-semibold text-amber-700 w-20">Out mL</th>
                    <th class="px-3 py-2 text-right font-semibold w-24" title="Running balance for the day">Balance</th>
                    <th class="px-3 py-2 text-left font-semibold">By</th>
                    <th class="px-3 py-2 w-20"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            @forelse ($chart['shifts'] as $shift)
                <tbody class="border-t border-gray-200">
                    {{-- Shift subtotal --}}
                    <tr class="{{ $shift['current'] ? 'bg-blue-50' : 'bg-gray-50/70' }} text-xs font-semibold text-gray-700">
                        <td class="px-3 py-1.5" colspan="2">
                            @if ($shift['code'])
                                {{ $shift['code'] }}
                                <span class="font-normal text-gray-500">{{ $shift['name'] }} &middot; {{ $shift['time'] }}</span>
                            @else
                                {{ $shift['name'] }}
                            @endif
                            @if ($shift['current'])
                                <span class="ml-1 inline-flex px-1.5 py-0.5 rounded bg-blue-600 text-white text-[10px]">Now</span>
                            @endif
                        </td>
                        <td class="px-3 py-1.5 text-right text-sky-800">{{ number_format($shift['intake']) }}</td>
                        <td></td>
                        <td class="px-3 py-1.5 text-right text-amber-800">{{ number_format($shift['output']) }}</td>
                        <td class="px-3 py-1.5 text-right {{ $balanceClass($shift['balance']) }}" title="Balance for the shift">{{ $signed($shift['balance']) }}</td>
                        <td class="px-3 py-1.5 font-normal text-gray-400" colspan="2">shift total</td>
                    </tr>

                    @forelse ($shift['rows'] as $row)
                        @php
                            $entry = $row['entry'];
                            $voided = $entry->isVoided();
                            $late = $entry->created_at && $entry->created_at->diffInMinutes($entry->recorded_at, true) >= 10;
                        @endphp
                        <tr class="border-t border-gray-100 {{ $voided ? 'text-gray-400' : 'text-gray-800' }}">
                            <td class="px-3 py-1.5 whitespace-nowrap {{ $voided ? 'line-through' : '' }}"
                                @if ($late) title="Entered {{ $entry->created_at->format('j M H:i') }}" @endif>
                                {{ $entry->recorded_at->format('H:i') }}@if ($late)<span class="text-gray-400">*</span>@endif
                            </td>
                            @if ($entry->isIntake())
                                <td class="px-3 py-1.5 {{ $voided ? 'line-through' : '' }}">{{ $entry->label() }}</td>
                                <td class="px-3 py-1.5 text-right font-semibold {{ $voided ? 'line-through' : 'text-sky-800' }}">{{ number_format($entry->volume_ml) }}</td>
                                <td></td>
                                <td></td>
                            @else
                                <td></td>
                                <td></td>
                                <td class="px-3 py-1.5 {{ $voided ? 'line-through' : '' }}">{{ $entry->label() }}</td>
                                <td class="px-3 py-1.5 text-right font-semibold {{ $voided ? 'line-through' : 'text-amber-800' }}">{{ number_format($entry->volume_ml) }}</td>
                            @endif
                            <td class="px-3 py-1.5 text-right whitespace-nowrap {{ $voided ? '' : 'text-gray-500' }}">
                                {{ $voided ? '' : $signed($row['running']) }}
                            </td>
                            <td class="px-3 py-1.5 text-xs {{ $voided ? '' : 'text-gray-500' }}">
                                {{ $entry->recordedBy?->name ?? '-' }}
                                @if ($voided)
                                    <span class="block text-[11px] text-red-600 no-underline">
                                        Struck out{{ $entry->voidedBy ? ' by ' . $entry->voidedBy->name : '' }}: {{ $entry->void_reason }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-1.5 text-right">
                                @unless ($voided)
                                    <button type="button" @click="striking = striking === {{ $entry->id }} ? null : {{ $entry->id }}"
                                        class="text-[11px] font-semibold text-gray-400 hover:text-red-600">
                                        Strike out
                                    </button>
                                @endunless
                            </td>
                        </tr>
                        @unless ($voided)
                            <tr x-show="striking === {{ $entry->id }}" x-cloak>
                                <td colspan="8" class="bg-red-50/70 px-3 py-2">
                                    <form method="POST" action="{{ route('ward.fluid-balance.void', $entry) }}"
                                        class="flex flex-wrap items-end gap-2">
                                        @csrf
                                        <input type="hidden" name="active_tab" value="io">
                                        <input type="hidden" name="_form" value="void-{{ $entry->id }}">
                                        <div class="flex-1 min-w-[14rem]">
                                            <label class="{{ $labelClass }}">
                                                Strike out {{ $entry->label() }}, {{ $ml($entry->volume_ml) }} at {{ $entry->recorded_at->format('H:i') }}.
                                                Reason <span class="text-red-500">*</span>
                                            </label>
                                            <input type="text" name="void_reason" list="io-void-reasons" required maxlength="255"
                                                value="{{ $voidFailed === $entry->id ? old('void_reason') : '' }}"
                                                placeholder="e.g. wrong volume, recorded twice" class="{{ $fieldClass }}">
                                        </div>
                                        <button type="button" @click="striking = null"
                                            class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-md hover:bg-gray-50">
                                            Cancel
                                        </button>
                                        <button type="submit"
                                            class="inline-flex items-center px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-red-700">
                                            Strike out
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endunless
                    @empty
                        <tr class="border-t border-gray-100">
                            <td colspan="8" class="px-3 py-2 text-xs text-gray-400">Nothing recorded{{ $shift['current'] ? ' yet' : '' }}</td>
                        </tr>
                    @endforelse
                </tbody>
            @empty
                <tbody>
                    <tr>
                        <td colspan="8" class="px-3 py-4 text-center text-sm text-gray-500">Nothing recorded for this day.</td>
                    </tr>
                </tbody>
            @endforelse
            <tfoot class="border-t-2 border-gray-300 bg-gray-50 text-sm font-bold text-gray-900">
                <tr>
                    <td class="px-3 py-2" colspan="2">
                        {{ $isToday ? 'Total so far' : '24 h total' }}
                        <span class="font-normal text-xs text-gray-500">from {{ $day['start']->format('H:i') }}</span>
                    </td>
                    <td class="px-3 py-2 text-right text-sky-800">{{ number_format($totals['intake']) }}</td>
                    <td></td>
                    <td class="px-3 py-2 text-right text-amber-800">{{ number_format($totals['output']) }}</td>
                    <td class="px-3 py-2 text-right {{ $balanceClass($totals['balance']) }}">{{ $signed($totals['balance']) }}</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Totals by type --}}
    @if ($totals['intake'] > 0 || $totals['output'] > 0)
        <div class="mt-2 flex flex-wrap gap-x-6 gap-y-1 text-xs text-gray-600">
            @foreach ([$fbe::DIRECTION_INTAKE => $fbe::INTAKE_TYPES, $fbe::DIRECTION_OUTPUT => $fbe::OUTPUT_TYPES] as $direction => $types)
                @if ($totals['by_type'][$direction])
                    <div>
                        <span class="font-semibold {{ $direction === $fbe::DIRECTION_INTAKE ? 'text-sky-700' : 'text-amber-700' }}">
                            {{ ucfirst($direction) }} by type:
                        </span>
                        {{ collect($types)
                            ->filter(fn ($typeLabel, $key) => isset($totals['by_type'][$direction][$key]))
                            ->map(fn ($typeLabel, $key) => $typeLabel . ' ' . number_format($totals['by_type'][$direction][$key]))
                            ->implode(' · ') }}
                    </div>
                @endif
            @endforeach
        </div>
    @endif

    {{-- Fluid plan and overload signs --}}
    <div class="mt-5 grid grid-cols-1 lg:grid-cols-2 gap-4">
        {{-- Fluid plan --}}
        <div class="rounded-xl border border-gray-200 p-4">
            <div class="flex items-center justify-between gap-2">
                <h4 class="text-sm font-bold text-gray-800">Fluid plan</h4>
                <button type="button" @click="toggle('plan')"
                    class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-md border transition-colors"
                    :class="panel === 'plan' ? 'bg-gray-200 border-gray-200 text-gray-700' : 'bg-white border-gray-300 text-blue-700 hover:bg-gray-50'"
                    x-text="panel === 'plan' ? 'Cancel' : @js($currentPlan?->hasLimits() ? 'Change plan' : 'Set limits')"></button>
            </div>
            @if ($currentPlan && ($currentPlan->hasLimits() || $currentPlan->notes))
                <dl class="mt-2 grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <dt class="text-[11px] text-gray-500">Intake limit</dt>
                        <dd class="font-semibold text-gray-900">
                            {{ $currentPlan->intake_limit_ml !== null ? $ml($currentPlan->intake_limit_ml) . ' per day' : 'None' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[11px] text-gray-500">Minimum urine output</dt>
                        <dd class="font-semibold text-gray-900">
                            {{ $currentPlan->urine_min_ml_per_hour !== null ? $currentPlan->urine_min_ml_per_hour . ' mL/h' : 'None' }}
                        </dd>
                    </div>
                </dl>
                @if ($currentPlan->notes)
                    <p class="mt-2 text-xs text-gray-700 bg-gray-50 rounded px-2 py-1.5">{{ $currentPlan->notes }}</p>
                @endif
                <p class="mt-2 text-[11px] text-gray-500">
                    Set {{ $currentPlan->created_at->format('j M H:i') }}{{ $currentPlan->setBy ? ' by ' . $currentPlan->setBy->name : '' }}
                </p>
            @else
                <p class="mt-2 text-sm text-gray-500">
                    No limits set. Set how much this patient may take in per day, and the least urine expected per hour,
                    to have them checked as you record.
                </p>
            @endif

            <form x-show="panel === 'plan'" x-cloak method="POST" action="{{ route('ward.fluid-balance.plan') }}"
                x-data="@js($planState)" class="mt-3 rounded-lg border border-blue-200 bg-blue-50/40 p-3">
                @csrf
                <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                <input type="hidden" name="active_tab" value="io">
                <input type="hidden" name="_form" value="plan">

                <label class="{{ $labelClass }}">Intake limit per day (mL)</label>
                <input type="number" name="intake_limit_ml" x-model="limit" min="{{ $fbp::LIMIT_MIN }}" max="{{ $fbp::LIMIT_MAX }}" step="50"
                    inputmode="numeric" placeholder="No limit" class="{{ $fieldClass }}">
                <div class="mt-1.5 flex flex-wrap gap-1">
                    @foreach ($fbp::LIMIT_PRESETS as $preset)
                        <button type="button" @click="limit = '{{ $preset }}'"
                            class="px-2 py-1 rounded-md border text-xs font-semibold"
                            :class="String(limit) === '{{ $preset }}' ? 'bg-gray-800 border-gray-800 text-white' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'">
                            {{ number_format($preset) }}
                        </button>
                    @endforeach
                    <button type="button" @click="limit = ''"
                        class="px-2 py-1 rounded-md border text-xs font-semibold"
                        :class="limit === '' ? 'bg-gray-800 border-gray-800 text-white' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'">
                        No limit
                    </button>
                </div>

                <label class="{{ $labelClass }} mt-3">Minimum urine output (mL per hour)</label>
                <input type="number" name="urine_min_ml_per_hour" x-model="urineMin" min="{{ $fbp::URINE_MIN }}" max="{{ $fbp::URINE_MAX }}" step="1"
                    inputmode="numeric" placeholder="No target" class="{{ $fieldClass }}">
                <div class="mt-1.5 flex flex-wrap gap-1">
                    @foreach ($fbp::URINE_PRESETS as $preset)
                        <button type="button" @click="urineMin = '{{ $preset }}'"
                            class="px-2 py-1 rounded-md border text-xs font-semibold"
                            :class="String(urineMin) === '{{ $preset }}' ? 'bg-gray-800 border-gray-800 text-white' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'">
                            {{ $preset }}
                        </button>
                    @endforeach
                    <button type="button" @click="urineMin = ''"
                        class="px-2 py-1 rounded-md border text-xs font-semibold"
                        :class="urineMin === '' ? 'bg-gray-800 border-gray-800 text-white' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'">
                        None
                    </button>
                </div>
                <p class="mt-1 text-[11px] text-gray-500">
                    Checked against the average since the day started, once {{ \App\Support\FluidBalanceChart::URINE_CHECK_AFTER_HOURS }} hours have passed.
                </p>

                <label class="{{ $labelClass }} mt-3">Order / notes</label>
                <input type="text" name="notes" maxlength="255"
                    value="{{ $planFailed ? old('notes') : $currentPlan?->notes }}"
                    placeholder="e.g. Strict I/O. Inform doctor if urine under 30 mL/h for 2 h" class="{{ $fieldClass }}">

                <div class="mt-3 flex justify-end gap-2">
                    <button type="button" @click="panel = null"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-md hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-blue-700">
                        Save plan
                    </button>
                </div>
            </form>

            @if ($chart['plan_history']->count() > 1)
                <div class="mt-3" x-data="{ open: false }">
                    <button type="button" @click="open = !open" class="flex items-center text-[11px] font-semibold text-gray-600">
                        <svg class="w-3 h-3 mr-1 transition-transform" :class="open ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                        Plan history ({{ $chart['plan_history']->count() }})
                    </button>
                    <ul x-show="open" x-cloak class="mt-1.5 space-y-1 text-[11px] text-gray-600">
                        @foreach ($chart['plan_history'] as $pastPlan)
                            <li class="flex gap-2">
                                <span class="shrink-0 text-gray-400 w-20">{{ $pastPlan->created_at->format('j M H:i') }}</span>
                                <span>
                                    {{ $pastPlan->summary() }}{{ $pastPlan->notes ? ' - ' . $pastPlan->notes : '' }}
                                    <span class="text-gray-400">{{ $pastPlan->setBy ? '(' . $pastPlan->setBy->name . ')' : '' }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        {{-- Signs of fluid overload --}}
        <div class="rounded-xl border border-gray-200 p-4">
            <div class="flex items-center justify-between gap-2">
                <h4 class="text-sm font-bold text-gray-800">Signs of fluid overload</h4>
                <button type="button" @click="toggle('assess')"
                    class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-md border transition-colors"
                    :class="panel === 'assess' ? 'bg-gray-200 border-gray-200 text-gray-700' : 'bg-white border-gray-300 text-rose-700 hover:bg-gray-50'"
                    x-text="panel === 'assess' ? 'Cancel' : 'Record check'"></button>
            </div>
            <p class="mt-1 text-xs text-gray-500">Pitting edema, breathing, and daily weight.</p>

            @if ($chart['assessments']->isNotEmpty())
                <div class="mt-2 overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead class="text-gray-500">
                            <tr>
                                <th class="py-1 pr-2 text-left font-semibold">When</th>
                                <th class="py-1 pr-2 text-left font-semibold">Edema</th>
                                <th class="py-1 pr-2 text-left font-semibold">Other signs</th>
                                <th class="py-1 pr-2 text-right font-semibold">Weight</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($chart['assessments']->take(6) as $assessment)
                                <tr class="align-top">
                                    <td class="py-1.5 pr-2 whitespace-nowrap text-gray-600" title="{{ $assessment->recordedBy?->name }}">
                                        {{ $assessment->assessed_at->format('j M H:i') }}
                                    </td>
                                    <td class="py-1.5 pr-2 {{ $assessment->hasEdema() ? 'font-semibold text-rose-800' : 'text-gray-500' }}">
                                        {{ $assessment->edemaShort() }}
                                        @if ($assessment->siteLabels())
                                            <span class="block font-normal text-gray-600">{{ implode(', ', $assessment->siteLabels()) }}</span>
                                        @endif
                                    </td>
                                    <td class="py-1.5 pr-2 {{ $assessment->signs ? 'text-rose-800' : 'text-gray-500' }}">
                                        {{ $assessment->signLabels() ? implode(', ', $assessment->signLabels()) : 'None' }}
                                        @if ($assessment->notes)
                                            <span class="block italic text-gray-500">{{ $assessment->notes }}</span>
                                        @endif
                                    </td>
                                    <td class="py-1.5 text-right whitespace-nowrap text-gray-800">
                                        {{ $assessment->weight_kg !== null ? $assessment->weight_kg . ' kg' : '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="mt-2 text-sm text-gray-500">No checks recorded this stay.</p>
            @endif

            {{-- Record a check --}}
            <form x-show="panel === 'assess'" x-cloak method="POST" action="{{ route('ward.fluid-balance.assessments.store') }}"
                x-data="{
                    ...@js($assessState),
                    grades: @js($foa::EDEMA_GRADES),
                    useEarlier() { this.earlier = true; if (!this.at) this.at = this.localNow(); },
                }" class="mt-3 rounded-lg border border-rose-200 bg-rose-50/40 p-3">
                @csrf
                <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                <input type="hidden" name="active_tab" value="io">
                <input type="hidden" name="_form" value="assess">
                <input type="hidden" name="edema_grade" value="{{ $assessState['grade'] }}" :value="grade">

                <label class="{{ $labelClass }}">Pitting edema</label>
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($foa::EDEMA_GRADES as $value => $grade)
                        <button type="button" @click="grade = {{ $value }}"
                            class="{{ $chipClass }} min-w-[3rem] justify-center"
                            :class="grade === {{ $value }}
                                ? '{{ $value > 0 ? 'bg-rose-600 border-rose-600 text-white' : 'bg-emerald-600 border-emerald-600 text-white' }}'
                                : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'">
                            {{ $grade['short'] }}
                        </button>
                    @endforeach
                </div>
                <p class="mt-1 text-[11px] text-gray-600" x-text="grades[grade] ? grades[grade].label : ''"></p>

                <div x-show="grade > 0" x-cloak class="mt-3">
                    <label class="{{ $labelClass }}">Where</label>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($foa::EDEMA_SITES as $value => $siteLabel)
                            <label class="cursor-pointer">
                                <input type="checkbox" name="edema_sites[]" value="{{ $value }}" x-model="sites" :disabled="grade === 0" class="sr-only">
                                <span class="{{ $chipClass }}"
                                    :class="sites.includes('{{ $value }}') ? 'bg-rose-600 border-rose-600 text-white' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'">
                                    {{ $siteLabel }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <label class="{{ $labelClass }} mt-3">Other signs</label>
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($foa::SIGNS as $value => $signLabel)
                        <label class="cursor-pointer">
                            <input type="checkbox" name="signs[]" value="{{ $value }}" x-model="signs" class="sr-only">
                            <span class="{{ $chipClass }}"
                                :class="signs.includes('{{ $value }}') ? 'bg-rose-600 border-rose-600 text-white' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'">
                                {{ $signLabel }}
                            </span>
                        </label>
                    @endforeach
                </div>
                <p x-show="signs.includes('frothy_sputum')" x-cloak class="mt-1.5 text-[11px] font-semibold text-red-700">
                    Pink frothy sputum can mean fluid on the lungs. Escalate to the doctor now.
                </p>

                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="{{ $labelClass }}">Weight (kg)</label>
                        <input type="number" name="weight_kg" step="0.1" min="{{ $foa::WEIGHT_MIN }}" max="{{ $foa::WEIGHT_MAX }}"
                            inputmode="decimal" value="{{ $assessFailed ? old('weight_kg') : '' }}"
                            placeholder="{{ $weight ? 'Last ' . number_format($weight['kg'], 1) : 'Optional' }}" class="{{ $fieldClass }}">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Time</label>
                        <div x-show="!earlier" class="flex items-center gap-3 text-sm py-2">
                            <span class="font-semibold text-gray-800">Now</span>
                            <button type="button" @click="useEarlier()" class="text-xs font-semibold text-blue-700 hover:underline">
                                Earlier time
                            </button>
                        </div>
                        <div x-show="earlier" x-cloak class="flex items-center gap-2">
                            <input type="datetime-local" name="assessed_at" x-model="at"
                                :disabled="!earlier" :required="earlier" class="{{ $fieldClass }}">
                            <button type="button" @click="earlier = false; at = ''"
                                class="shrink-0 text-xs font-semibold text-blue-700 hover:underline">Now</button>
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="{{ $labelClass }}">Notes</label>
                        <input type="text" name="notes" maxlength="255" value="{{ $assessFailed ? old('notes') : '' }}"
                            placeholder="e.g. SpO2 94% on room air, needs 3 pillows" class="{{ $fieldClass }}">
                    </div>
                </div>

                <div class="mt-3 flex justify-end gap-2">
                    <button type="button" @click="panel = null"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-md hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-rose-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-rose-700">
                        Save check
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Recent chart days --}}
    @if (count($days['rows']) > 1 || !$isToday)
        <div class="mt-5">
            <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Recent days</h4>
            <div class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-600">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold">Day (from {{ $day['start']->format('H:i') }})</th>
                            <th class="px-3 py-2 text-right font-semibold text-sky-700">Intake</th>
                            <th class="px-3 py-2 text-right font-semibold text-amber-700">Output</th>
                            <th class="px-3 py-2 text-right font-semibold">Balance</th>
                            <th class="px-3 py-2 text-left font-semibold">Limit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($days['rows'] as $summaryDay)
                            <tr class="{{ $summaryDay['key'] === $day['key'] ? 'bg-blue-50' : 'hover:bg-gray-50' }}">
                                <td class="px-3 py-1.5 whitespace-nowrap">
                                    <a href="{{ $dayUrl($summaryDay['is_current'] ? null : $summaryDay['key']) }}"
                                        class="font-medium text-blue-700 hover:underline">
                                        {{ $summaryDay['is_current'] ? 'Today' : $summaryDay['start']->format('D j M') }}
                                    </a>
                                    @if ($summaryDay['is_current'])
                                        <span class="text-[11px] text-gray-400">so far</span>
                                    @endif
                                </td>
                                <td class="px-3 py-1.5 text-right">{{ number_format($summaryDay['intake']) }}</td>
                                <td class="px-3 py-1.5 text-right">{{ number_format($summaryDay['output']) }}</td>
                                <td class="px-3 py-1.5 text-right font-semibold {{ $balanceClass($summaryDay['balance']) }}">{{ $signed($summaryDay['balance']) }}</td>
                                <td class="px-3 py-1.5 text-xs">
                                    @if ($summaryDay['limit'] === null)
                                        <span class="text-gray-400">-</span>
                                    @elseif ($summaryDay['over'])
                                        <span class="inline-flex px-1.5 py-0.5 rounded bg-red-100 text-red-800 font-semibold">
                                            Over {{ number_format($summaryDay['limit']) }}
                                        </span>
                                    @else
                                        <span class="text-gray-600">Within {{ number_format($summaryDay['limit']) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <datalist id="io-void-reasons">
        <option value="Wrong volume"></option>
        <option value="Recorded twice"></option>
        <option value="Wrong patient"></option>
        <option value="Wrong type"></option>
        <option value="Entered in error"></option>
    </datalist>

    <p class="mt-4 text-[11px] text-gray-400">
        The chart day runs from {{ $day['start']->format('H:i') }} to {{ $day['end']->format('H:i') }}, from the start of the ward's
        first shift, and is subtotalled by shift. Struck-out entries stay on the chart but are not counted. Times marked * were
        entered later than they happened.
    </p>
</div>
