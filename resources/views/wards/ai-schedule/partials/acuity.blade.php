{{--
    Patient acuity tab: each patient's care level (1 Minimal to 4 Intensive),
    rated by the nurses and checked against the indicators SmartWard records;
    the registered nurses each shift needs for those patients, beside the
    minimum and the week's roster; and how the ratings compare with the
    indicators over time. Anyone signed in can rate patients; only editors
    change the staffing settings or the minimum staff.
--}}

@php
    $levels = \App\Services\NurseScheduling\PatientAcuity::LEVELS;
    $levelTone = [
        1 => 'bg-green-100 text-green-800 border-green-300',
        2 => 'bg-blue-100 text-blue-800 border-blue-300',
        3 => 'bg-yellow-100 text-yellow-800 border-yellow-300',
        4 => 'bg-red-100 text-red-800 border-red-300',
    ];
    $levelBar = [1 => 'bg-green-500', 2 => 'bg-blue-500', 3 => 'bg-yellow-500', 4 => 'bg-red-500'];
    $chip = [
        'AM' => 'bg-sky-100 text-sky-800 border-sky-200',
        'PM' => 'bg-amber-100 text-amber-800 border-amber-200',
        'ON' => 'bg-indigo-600 text-white border-indigo-700',
    ];
    // Registered nurses on the roster against the recommendation: short, met, over (as the roster's staffing row)
    $weekTone = [
        'short' => 'bg-red-100 text-red-700',
        'met' => 'bg-emerald-100 text-emerald-700',
        'over' => 'bg-sky-100 text-sky-700',
    ];
    $weekValue = $weekStart->toDateString();
    $fmt = fn ($value) => \App\Services\NurseScheduling\AcuitySettings::format((float) $value);
    $summary = $acuitySummary;
    $patients = $summary['patients'];
    $ratedShare = $patients ? (int) round($summary['rated_recently'] / $patients * 100) : null;
    $shortShifts = \App\Services\NurseScheduling\AcuityStaffing::shortShifts($acuityWeek);

    // The settings panel opens after saving or resetting, and when the settings did not validate
    $settingsErrors = collect($errors->keys())->contains(fn ($key) => str_starts_with($key, 'acuity.'));
    $ratingErrors = collect($errors->keys())->contains(fn ($key) => str_starts_with($key, 'levels') || str_starts_with($key, 'notes'));
    $settingsOpen = request()->boolean('acuity_settings') || $settingsErrors;

    // "Use as minimum staff": the recommendation, within the staffing rules' 0 to 50. The
    // minimum counts anyone on the shift, the recommendation registered nurses only
    $newMinimum = collect($acuityStaffing['shifts'])->map(fn (array $need) => min(50, $need['recommended']));
    $minimumConfirm = 'Set the minimum staff per shift from patient acuity: '
        . $newMinimum->map(fn (int $count, string $code) => $code . ' ' . $count . ' (now ' . ($rules->minStaff[$code] ?? 0) . ')')->implode(', ')
        . '? The minimum counts everyone on a shift, students and care assistants too, while the recommendation is registered nurses.'
        . ' The roster changes when it is generated again; then check the registered nurses against the recommendation on this tab.';

    // The cell of the week for the slot on duty now, when it is in the week shown
    $nowCell = fn (string $code) => $nowSlot && $nowSlot['code'] === $code ? ($acuityWeek[$nowSlot['date']][$code] ?? null) : null;
@endphp

<style>
    .acuity-details > summary { list-style: none; }
    .acuity-details > summary::-webkit-details-marker { display: none; }
    .acuity-details[open] > summary .acuity-chevron { transform: rotate(180deg); }
</style>

<div x-show="tab === 'acuity'" x-cloak class="space-y-4" @if ($settingsErrors || $ratingErrors) x-init="tab = 'acuity'" @endif>
    {{-- The ward's patients by care level --}}
    <div class="bg-white rounded-2xl shadow-lg border border-blue-100 p-5">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Patient acuity</h3>
            <p class="text-xs text-gray-500 mt-0.5 max-w-3xl">
                Each patient's care level, from 1 Minimal to 4 Intensive, rated by the nurses and checked against the
                indicators SmartWard records. The levels set the registered nurses each shift needs, shown beside the minimum
                in the staffing rules. A patient not classified yet counts at the suggested level.
            </p>
        </div>

        {{-- Tiles share each row evenly rather than leaving one alone on the last row --}}
        <div class="mt-4 grid gap-2" style="grid-template-columns: repeat(auto-fit, minmax(8.5rem, 1fr))">
            <div class="rounded-xl bg-gray-50 px-4 py-2">
                <div class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Patients</div>
                <div class="text-2xl font-bold text-gray-800">{{ $patients }}</div>
                <div class="text-[11px] text-gray-500">in {{ $summary['beds'] }} {{ \Illuminate\Support\Str::plural('bed', $summary['beds']) }}</div>
            </div>
            @foreach ($levels as $level => $info)
                <div class="rounded-xl border px-4 py-2 {{ $levelTone[$level] }}" title="{{ $info['description'] }}">
                    <div class="text-[11px] font-semibold uppercase tracking-wider">Level {{ $level }} {{ $info['short'] }}</div>
                    <div class="text-2xl font-bold">{{ $summary['by_level'][$level] }}</div>
                    <div class="text-[11px]">{{ $fmt($acuitySettings->hours($level)) }} h of care a day</div>
                </div>
            @endforeach
            <div class="rounded-xl border border-gray-200 bg-white px-4 py-2" title="Counted at the level the indicators suggest until a nurse rates them">
                <div class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Not classified</div>
                <div class="text-2xl font-bold text-gray-800">{{ $summary['unclassified'] }}</div>
                <div class="text-[11px] text-gray-500">counted as suggested</div>
            </div>
            <div class="rounded-xl border px-4 py-2 {{ $summary['review_up'] ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-gray-200 bg-white text-gray-800' }}"
                title="Rated below the level the indicators suggest{{ $summary['review_down'] ? '. ' . $summary['review_down'] . ' more rated above it.' : '' }}">
                <div class="text-[11px] font-semibold uppercase tracking-wider {{ $summary['review_up'] ? '' : 'text-gray-500' }}">Needs review</div>
                <div class="text-2xl font-bold">{{ $summary['review_up'] }}</div>
                <div class="text-[11px] {{ $summary['review_up'] ? '' : 'text-gray-500' }}">
                    indicators suggest more{{ $summary['review_down'] ? ' · ' . $summary['review_down'] . ' rated above' : '' }}
                </div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white px-4 py-2">
                <div class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Rated in the last 24 h</div>
                <div class="text-2xl font-bold text-gray-800">{{ $summary['rated_recently'] }}<span class="text-sm font-semibold text-gray-500"> of {{ $patients }}</span></div>
                <div class="text-[11px] text-gray-500">{{ $ratedShare !== null ? $ratedShare . '% of patients' : 'No patients' }}</div>
            </div>
        </div>

        @if ($patients)
            {{-- The mix at a glance --}}
            <div class="mt-3 flex h-2 overflow-hidden rounded-full bg-gray-100" aria-hidden="true">
                @foreach ($levels as $level => $info)
                    @if ($summary['by_level'][$level])
                        <div class="{{ $levelBar[$level] }}" style="width: {{ round($summary['by_level'][$level] / $patients * 100, 2) }}%"
                            title="Level {{ $level }}: {{ $summary['by_level'][$level] }}"></div>
                    @endif
                @endforeach
            </div>
        @endif

        <p class="mt-3 text-xs text-gray-500">
            Method: patient classification and acuity-based staffing ({{ \App\Support\AcuityReferences::primary()['short'] }}).
            <a href="#acuity-method" class="font-semibold text-indigo-600 hover:text-indigo-800"
                @click.prevent="document.getElementById('acuity-method').open = true; $nextTick(() => document.getElementById('acuity-method').scrollIntoView({ behavior: 'smooth', block: 'start' }))">How the levels are suggested</a>
            &middot;
            <a href="#acuity-references" class="font-semibold text-indigo-600 hover:text-indigo-800"
                @click.prevent="document.getElementById('acuity-references').open = true; $nextTick(() => document.getElementById('acuity-references').scrollIntoView({ behavior: 'smooth', block: 'start' }))">References</a>
        </p>
    </div>

    {{-- Nurses per shift for these patients, and the week's roster against them --}}
    <div class="bg-white rounded-2xl shadow-lg border border-blue-100" x-data="{ settingsOpen: @js($settingsOpen) }">
        <div class="p-5 border-b border-gray-100 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">Staffing by patient acuity</h3>
                <p class="text-xs text-gray-500 mt-0.5 max-w-2xl">
                    The registered nurses each shift needs for the {{ $patients }} {{ \Illuminate\Support\Str::plural('patient', $patients) }}
                    on the ward now: {{ $fmt($acuityStaffing['care_hours']) }} hours of nursing care in 24 h
                    ({{ $fmt($acuityStaffing['hours_per_patient']) }} h a patient), shared over the shifts and divided by each
                    shift's length. Students and care assistants are shown on the roster but not counted.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" @click="settingsOpen = !settingsOpen" :aria-expanded="settingsOpen"
                    class="inline-flex items-center px-3 py-2 text-sm font-semibold rounded-lg border shadow-sm transition-colors"
                    :class="settingsOpen ? 'bg-gray-200 text-gray-800 border-gray-300' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                    </svg>
                    Staffing settings
                    @if ($acuitySettings->changedCount() > 0)
                        <span class="ml-1.5 inline-flex items-center rounded-full bg-amber-100 px-1.5 text-[11px] font-bold text-amber-800"
                            title="{{ $acuitySettings->changedCount() }} changed from the defaults">{{ $acuitySettings->changedCount() }}</span>
                    @endif
                </button>
                @if ($canEdit && $patients)
                    <form method="POST" action="{{ route('ward.ai-schedule.acuity.min-staff') }}" onsubmit="return confirm(@js($minimumConfirm))">
                        @csrf
                        <input type="hidden" name="ward_id" value="{{ $ward->id }}">
                        <input type="hidden" name="week" value="{{ $weekValue }}">
                        <button type="submit" title="Make these the minimum staff per shift in the staffing rules. The roster generator fills the minimum with anyone on the team, so check the registered nurses after generating."
                            class="inline-flex items-center px-3 py-2 bg-white border border-indigo-200 text-indigo-700 text-sm font-semibold rounded-lg shadow-sm hover:bg-indigo-50">
                            Use as minimum staff
                        </button>
                    </form>
                @endif
            </div>
        </div>

        @include('wards.ai-schedule.partials.acuity-settings')

        {{-- Each shift's recommendation --}}
        <div class="grid grid-cols-1 gap-3 p-5 md:grid-cols-3">
            @foreach ($shifts as $code => $info)
                @php
                    $need = $acuityStaffing['shifts'][$code];
                    $minimum = $rules->minStaff[$code] ?? 0;
                    $now = $nowCell($code);
                @endphp
                <div class="rounded-xl border border-gray-200 p-4">
                    <div class="flex items-center justify-between gap-2">
                        <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-gray-800">
                            <span class="inline-flex items-center justify-center w-9 rounded border text-[11px] font-bold {{ $chip[$code] }}">{{ $code }}</span>
                            {{ $info['name'] }}
                        </span>
                        <span class="text-xs text-gray-500">{{ $info['time'] }}</span>
                    </div>
                    @if ($patients)
                        <div class="mt-3 flex items-baseline gap-2">
                            <span class="text-3xl font-bold leading-none text-gray-900">{{ $need['recommended'] }}</span>
                            <span class="text-sm text-gray-600">registered {{ \Illuminate\Support\Str::plural('nurse', $need['recommended']) }} recommended</span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ $fmt($need['exact']) }} nurses &middot; {{ $fmt($need['care_hours']) }} care h over {{ $fmt($need['shift_hours']) }} h
                        </p>
                    @else
                        <p class="mt-3 text-sm text-gray-500">No patients on the ward now.</p>
                    @endif
                    <dl class="mt-3 space-y-1 text-xs">
                        <div class="flex items-center justify-between gap-2">
                            <dt class="text-gray-500">Patients per nurse</dt>
                            <dd class="font-semibold text-gray-800">{{ $need['patients_per_nurse'] !== null ? $fmt($need['patients_per_nurse']) : '-' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <dt class="text-gray-500">Minimum in the staffing rules</dt>
                            <dd class="font-semibold {{ $patients && $minimum < $need['recommended'] ? 'text-amber-700' : 'text-gray-800' }}"
                                @if ($patients && $minimum < $need['recommended']) title="Below what the patients need" @endif>{{ $minimum }}</dd>
                        </div>
                        @if ($now)
                            <div class="flex items-center justify-between gap-2">
                                <dt class="text-gray-500">On duty now</dt>
                                <dd>
                                    <span class="inline-flex items-center rounded px-1.5 py-0.5 font-semibold {{ $weekTone[$now['status']] }}"
                                        title="{{ $now['rostered'] }} rostered, {{ $now['registered'] }} of them registered nurses">
                                        {{ $now['registered'] }} RN of {{ $now['rostered'] }} rostered
                                    </span>
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>
            @endforeach
        </div>

        {{-- The week on the roster against the recommendation --}}
        <div class="border-t border-gray-100">
            <div class="px-5 pt-4 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h4 class="text-sm font-bold text-gray-800">This week's roster against the patients' needs</h4>
                    <p class="text-xs text-gray-500 mt-0.5">Registered nurses rostered / recommended, each shift; the staffing rules' minimum below.</p>
                </div>
                @if ($shortShifts)
                    <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-800">
                        {{ $shortShifts }} {{ \Illuminate\Support\Str::plural('shift', $shortShifts) }} short of registered nurses
                    </span>
                @elseif ($patients)
                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">Every shift has the registered nurses recommended</span>
                @endif
            </div>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-gradient-to-r from-brand-50 to-accent-50">
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Shift</th>
                            @foreach ($board['dates'] as $date)
                                @php $holiday = $board['holidays'][$date->toDateString()] ?? null; @endphp
                                <th class="px-2 py-2 text-center text-xs font-bold uppercase tracking-wider min-w-[4.5rem] {{ $date->isToday() ? 'text-indigo-700' : 'text-gray-700' }} {{ $date->isWeekend() ? 'bg-slate-100/70' : '' }}">
                                    <div>{{ $date->format('D') }}</div>
                                    <div class="text-sm normal-case tracking-normal {{ $date->isToday() ? 'inline-flex items-center justify-center w-7 h-7 rounded-full bg-indigo-600 text-white' : '' }}">{{ $date->format('j') }}</div>
                                    @if ($holiday)
                                        <div class="mt-0.5 truncate max-w-[5.5rem] mx-auto rounded bg-rose-100 px-1 text-[10px] font-semibold normal-case tracking-normal text-rose-700" title="{{ $holiday }}">{{ $holiday }}</div>
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($shifts as $code => $info)
                            <tr>
                                <th class="px-4 py-2 text-left font-normal whitespace-nowrap">
                                    <span class="inline-flex items-center justify-center w-9 rounded border text-[11px] font-bold {{ $chip[$code] }}">{{ $code }}</span>
                                    <span class="ml-1 text-xs text-gray-500">{{ $info['time'] }}</span>
                                </th>
                                @foreach ($board['dates'] as $date)
                                    @php
                                        $dateKey = $date->toDateString();
                                        $cell = $acuityWeek[$dateKey][$code];
                                        $isNow = $nowSlot && $nowSlot['date'] === $dateKey && $nowSlot['code'] === $code;
                                    @endphp
                                    <td class="px-1 py-1.5 text-center {{ $date->isWeekend() ? 'bg-slate-50' : '' }} {{ isset($board['holidays'][$dateKey]) ? 'bg-rose-50/60' : '' }}">
                                        <span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-semibold {{ $weekTone[$cell['status']] }} {{ $isNow ? 'ring-2 ring-indigo-300' : '' }}"
                                            title="{{ $date->format('D j M') }} {{ $code }}{{ $isNow ? ' (on now)' : '' }}: {{ $cell['rostered'] }} rostered, {{ $cell['registered'] }} of them registered nurses; {{ $cell['recommended'] }} recommended for the patients; minimum {{ $cell['min'] }}">
                                            {{ $cell['registered'] }}/{{ $cell['recommended'] }}
                                        </span>
                                        <div class="text-[10px] text-gray-400">min {{ $cell['min'] }}</div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-gray-500">
                <span class="inline-flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded {{ $weekTone['short'] }}"></span> short</span>
                <span class="inline-flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded {{ $weekTone['met'] }}"></span> meets it</span>
                <span class="inline-flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded {{ $weekTone['over'] }}"></span> more than recommended</span>
                <span>
                    Projected from the patients on the ward now, so later days change as patients come and go; earlier days are a
                    comparison only. The minimum in the staffing rules stays the floor: a fixed minimum does not follow the patients'
                    changing needs ({{ \App\Support\AcuityReferences::RATIOS_CITE }}).
                </span>
            </div>
        </div>
    </div>

    @include('wards.ai-schedule.partials.acuity-ratings')

    {{-- How the ratings compare with the indicators --}}
    <div class="bg-white rounded-2xl shadow-lg border border-blue-100">
        <div class="p-5 border-b border-gray-100">
            <h3 class="text-lg font-semibold text-gray-800">Rating check</h3>
            <p class="text-xs text-gray-500 mt-0.5 max-w-3xl">
                The last {{ $acuityCheck['days'] }} days: how often ratings agreed with the level the indicators suggested at the
                time. A rating that differs is often right, since the indicators cannot see everything a nurse can; many that
                differ the same way are worth a look, at the ratings or at the indicators.
            </p>
        </div>

        @if ($acuityCheck['ratings'])
            <div class="grid grid-cols-2 gap-3 p-5 md:grid-cols-5">
                <div class="rounded-xl bg-gray-50 px-4 py-3">
                    <div class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Ratings</div>
                    <div class="text-2xl font-bold text-gray-800">{{ $acuityCheck['ratings'] }}</div>
                    <div class="text-[11px] text-gray-500">last rated {{ $acuityCheck['last_at']?->diffForHumans() }}</div>
                </div>
                <div class="rounded-xl bg-gray-50 px-4 py-3">
                    <div class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Agree with the indicators</div>
                    <div class="text-2xl font-bold text-gray-800">{{ $acuityCheck['agreement'] !== null ? $acuityCheck['agreement'] . '%' : '-' }}</div>
                    <div class="text-[11px] text-gray-500">{{ $acuityCheck['agrees'] }} {{ \Illuminate\Support\Str::plural('rating', $acuityCheck['agrees']) }}</div>
                </div>
                <div class="rounded-xl bg-gray-50 px-4 py-3">
                    <div class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Rated above them</div>
                    <div class="text-2xl font-bold text-gray-800">{{ $acuityCheck['above'] }}</div>
                    <div class="text-[11px] text-gray-500">a higher level than suggested</div>
                </div>
                <div class="rounded-xl bg-gray-50 px-4 py-3">
                    <div class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Rated below them</div>
                    <div class="text-2xl font-bold text-gray-800">{{ $acuityCheck['below'] }}</div>
                    <div class="text-[11px] text-gray-500">a lower level than suggested</div>
                </div>
                <div class="rounded-xl bg-gray-50 px-4 py-3">
                    <div class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Rated in the last 24 h</div>
                    <div class="text-2xl font-bold text-gray-800">{{ $ratedShare !== null ? $ratedShare . '%' : '-' }}</div>
                    <div class="text-[11px] text-gray-500">{{ $summary['rated_recently'] }} of {{ $patients }} patients now</div>
                </div>
            </div>

            <div class="overflow-x-auto border-t border-gray-100">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gradient-to-r from-brand-50 to-accent-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Rated by</th>
                            <th class="px-3 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">Ratings</th>
                            <th class="px-3 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">Agree</th>
                            <th class="px-3 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">Above</th>
                            <th class="px-3 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">Below</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider w-56">Agreement</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($acuityCheck['by_rater'] as $rater)
                            <tr class="hover:bg-blue-50/40">
                                <td class="px-4 py-2 font-medium text-gray-800 whitespace-nowrap">{{ $rater['name'] }}</td>
                                <td class="px-3 py-2 text-center text-gray-700">{{ $rater['ratings'] }}</td>
                                <td class="px-3 py-2 text-center text-gray-700">{{ $rater['agrees'] }}</td>
                                <td class="px-3 py-2 text-center text-gray-700">{{ $rater['above'] ?: '-' }}</td>
                                <td class="px-3 py-2 text-center text-gray-700">{{ $rater['below'] ?: '-' }}</td>
                                <td class="px-4 py-2">
                                    @if ($rater['agreement'] !== null)
                                        <div class="flex items-center gap-2">
                                            <div class="h-2 flex-1 rounded-full bg-gray-100">
                                                <div class="h-2 rounded-full bg-indigo-500" style="width: {{ $rater['agreement'] }}%"></div>
                                            </div>
                                            <span class="w-10 text-right text-sm font-bold text-gray-800">{{ $rater['agreement'] }}%</span>
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-400">No suggestion to compare</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="border-t border-gray-100 px-5 py-3 text-[11px] text-gray-500">
                Agreement with the indicators is not the same as reliability between nurses, but a nurse who often differs is
                worth a conversation. Check each rater's reliability at least once a year
                ({{ \App\Support\AcuityReferences::primary()['short'] }}, p. 14).
            </p>
        @else
            <div class="p-5">
                <div class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500">
                    No ratings in the last {{ $acuityCheck['days'] }} days yet. Ratings saved under Patient classification show here.
                </div>
            </div>
        @endif
    </div>

    {{-- The method and the evidence, open on request --}}
    <details id="acuity-method" class="acuity-details bg-white rounded-2xl shadow-lg border border-blue-100 scroll-mt-4">
        <summary class="flex cursor-pointer select-none items-center justify-between gap-3 p-5">
            <span>
                <span class="block text-lg font-semibold text-gray-800">How the levels are suggested and staffed</span>
                <span class="block text-xs text-gray-500 mt-0.5">The care levels, every indicator with the level it suggests and where it is read from, and the sums from care levels to nurses.</span>
            </span>
            <svg class="acuity-chevron w-5 h-5 shrink-0 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </summary>
        <div class="border-t border-gray-100 p-5">
            @include('wards.ai-schedule.partials.acuity-method', ['acuitySettings' => $acuitySettings, 'shifts' => $shifts])
        </div>
    </details>

    <details id="acuity-references" class="acuity-details bg-white rounded-2xl shadow-lg border border-blue-100 scroll-mt-4">
        <summary class="flex cursor-pointer select-none items-center justify-between gap-3 p-5">
            <span>
                <span class="block text-lg font-semibold text-gray-800">References</span>
                <span class="block text-xs text-gray-500 mt-0.5">The principles this tab applies, where each is in the source, and the reference list.</span>
            </span>
            <svg class="acuity-chevron w-5 h-5 shrink-0 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </summary>
        <div class="border-t border-gray-100 p-5">
            @include('wards.ai-schedule.partials.acuity-references')
        </div>
    </details>
</div>
