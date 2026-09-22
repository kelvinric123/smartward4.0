{{--
    Bed assignment tab: share one shift's beds out between the nurses on it,
    by workload. "Suggest with AI" opens a review summary of the suggestion,
    where beds can be moved between nurses before anything is applied; the
    nurse cards and the bed table stay in step with it.
--}}

@php
    $weekValue = $weekStart->toDateString();
    $shiftInfo = $shifts[$shift];
    $dayDate = \Illuminate\Support\Carbon::parse($day);
    $rosteredIds = $rostered->pluck('id')->all();
    $scoreStyle = fn (float $score) => $score >= 3 ? 'bg-red-100 text-red-800' : ($score >= 2 ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-700');
    $hasAssignment = $rostered->isNotEmpty() || !empty($mapping);
    // Anyone holding beds without being rostered is offered too, so it can be put right
    $offRoster = $assignNurses->except($rosteredIds);
    // Bed => nurse id as a string, '' for no nurse (or one whose record is gone)
    $bedNurses = fn (array $map) => $beds->keys()
        ->mapWithKeys(fn (int $bedId) => [$bedId => isset($map[$bedId]) && $assignNurses->has($map[$bedId]) ? (string) $map[$bedId] : ''])
        ->all();

    $assignState = [
        'beds' => $beds->map(fn (array $row, int $bedId) => [
            'id' => $bedId,
            'label' => $row['bed']->bed_display_name ?: $row['bed']->bed_number,
            'patient' => $row['patient']?->name,
            'score' => $row['score'],
            'factors' => collect($row['factors'])
                ->reject(fn (array $factor) => in_array($factor['label'], ['Patient', 'Empty bed'], true))
                ->map(fn (array $factor) => $factor['label'] . ' +' . \App\Services\NurseScheduling\WorkloadWeights::format($factor['points']))
                ->values(),
        ])->values(),
        'nurses' => $rostered->map(fn ($nurse) => ['id' => $nurse->id, 'name' => $nurse->name, 'rostered' => true])
            ->concat($offRoster->values()->map(fn ($nurse) => ['id' => $nurse->id, 'name' => $nurse->name, 'rostered' => false]))
            ->values(),
        'mapping' => $bedNurses($mapping),
        'saved' => $bedNurses($saved),
        'band' => $weights->band(),
        // Straight after "Suggest with AI", the review summary opens by itself
        'openReview' => $suggesting,
        // Open after saving weights, or when the weights did not validate
        'weightsOpen' => request()->boolean('weights') || collect($errors->keys())->contains(fn ($key) => str_starts_with($key, 'weights.')),
    ];
@endphp

@include('wards.ai-schedule.partials.assign-script')

<div x-show="tab === 'assign'" x-cloak x-data="bedAssignment(@js($assignState))" class="space-y-4">
    <div class="bg-white rounded-2xl shadow-lg border border-blue-100 p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">Bed assignment</h3>
                <p class="text-xs text-gray-500 mt-0.5 max-w-2xl">
                    AI shares the beds out between the nurses rostered on a shift so everyone carries a similar
                    workload, keeps nurses on the beds they had the day before where it can, and keeps each nurse's
                    beds together. You review it before anything is applied. Applying writes to the ward dashboard,
                    the Ward Schedule page and the bedside screens.
                </p>
            </div>

            <form method="GET" action="{{ route('ward.ai-schedule') }}" class="flex flex-wrap items-end gap-2">
                <input type="hidden" name="ward_id" value="{{ $ward->id }}">
                <input type="hidden" name="week" value="{{ $weekValue }}">
                <input type="hidden" name="tab" value="assign">
                <div>
                    <label for="assign_day" class="block text-xs font-semibold text-gray-700 mb-1">Day</label>
                    <select id="assign_day" name="day" onchange="this.form.submit()"
                        class="rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach ($board['dates'] as $date)
                            <option value="{{ $date->toDateString() }}" @selected($date->toDateString() === $day)>
                                {{ $date->format('D j M') }}{{ $date->isToday() ? ' (today)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="assign_shift" class="block text-xs font-semibold text-gray-700 mb-1">Shift</label>
                    <select id="assign_shift" name="shift" onchange="this.form.submit()"
                        class="rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach ($shifts as $code => $info)
                            <option value="{{ $code }}" @selected($code === $shift)>
                                {{ $code }} &middot; {{ $info['time'] }}{{ $day === $today && $code === $currentCode ? ' (now)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-gray-50 px-4 py-3">
            <p class="text-sm text-gray-700">
                <span class="font-semibold">{{ $shift }} &middot; {{ $dayDate->format('l j M') }}</span>
                <span class="text-gray-500">{{ $shiftInfo['time'] }}</span>
                &middot; {{ $rostered->count() }} {{ \Illuminate\Support\Str::plural('nurse', $rostered->count()) }} rostered
                <span class="{{ $rostered->count() < ($rules->minStaff[$shift] ?? 0) ? 'text-red-600 font-semibold' : 'text-gray-500' }}">(minimum {{ $rules->minStaff[$shift] ?? 0 }})</span>
                &middot; {{ $beds->filter(fn ($row) => $row['patient'])->count() }} patients in {{ $beds->count() }} beds
            </p>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" @click="weightsOpen = !weightsOpen" :aria-expanded="weightsOpen"
                    class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-lg shadow-sm border transition-colors"
                    :class="weightsOpen ? 'bg-gray-200 text-gray-800 border-gray-300' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                    </svg>
                    Workload weights
                    @if ($weights->changedCount() > 0)
                        <span class="ml-1.5 inline-flex items-center rounded-full bg-amber-100 px-1.5 text-[11px] font-bold text-amber-800"
                            title="{{ $weights->changedCount() }} changed from the defaults">{{ $weights->changedCount() }}</span>
                    @endif
                </button>
                @if ($hasAssignment)
                    <button type="button" @click="reviewOpen = true"
                        class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-lg shadow-sm border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                        Review summary
                        <span x-show="changes.length" x-cloak
                            class="ml-1.5 inline-flex items-center rounded-full bg-indigo-100 px-1.5 text-[11px] font-bold text-indigo-800"
                            :title="changes.length + ' beds change from what is saved now'" x-text="changes.length"></span>
                    </button>
                @endif
                @if ($canEdit)
                    @if ($rostered->isNotEmpty())
                        <a href="{{ route('ward.ai-schedule', ['ward_id' => $ward->id, 'week' => $weekValue, 'tab' => 'assign', 'day' => $day, 'shift' => $shift, 'suggest' => 1]) }}"
                            class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-700 hover:to-blue-700 text-white text-sm font-semibold rounded-lg shadow">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                            </svg>
                            Suggest with AI
                        </a>
                    @endif
                    <form method="POST" action="{{ route('ward.ai-schedule.assign-week') }}"
                        onsubmit="return confirm('Share out the beds by AI for every shift left this week that has nurses rostered, and save them to the ward dashboard?')">
                        @csrf
                        <input type="hidden" name="ward_id" value="{{ $ward->id }}">
                        <input type="hidden" name="week" value="{{ $weekValue }}">
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-white border border-indigo-200 text-indigo-700 text-sm font-semibold rounded-lg shadow-sm hover:bg-indigo-50">
                            Assign the rest of the week
                        </button>
                    </form>
                @endif
            </div>
        </div>

        @include('wards.ai-schedule.partials.weights')

        @if ($suggesting)
            <div class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900">
                <div class="flex items-start gap-2">
                    <svg class="w-5 h-5 shrink-0 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    @if ($canEdit)
                        <span>This is the AI suggestion, not yet saved. Review it in the summary, move any beds you want,
                            then press <span class="font-semibold">Apply to ward dashboard</span>.</span>
                    @else
                        <span>This is the AI suggestion, not saved. Nurse managers and admins can apply it.</span>
                    @endif
                </div>
                <button type="button" @click="reviewOpen = true"
                    class="shrink-0 inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-lg shadow-sm hover:bg-indigo-700">
                    Open the summary
                </button>
            </div>
        @endif
    </div>

    @if (!$hasAssignment)
        <div class="bg-white rounded-2xl shadow-lg border border-blue-100 p-10 text-center">
            <p class="text-gray-700 font-medium">Nobody is rostered for {{ $shift }} on {{ $dayDate->format('D j M') }}.</p>
            <p class="mt-1 text-sm text-gray-500">Generate the roster, or set nurses on the Roster tab, then come back to share out the beds.</p>
        </div>
    @else
        {{-- Each nurse's share, kept in step with any change below or in the summary --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <template x-for="load in loads" :key="load.id">
                <div class="rounded-xl border bg-white p-4 shadow-sm" :class="load.rostered ? 'border-gray-200' : 'border-amber-300'">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="truncate font-semibold text-gray-800" x-text="load.name"></div>
                            <div class="text-xs text-gray-500"
                                x-text="load.beds.length + (load.beds.length === 1 ? ' bed' : ' beds') + ' · ' + load.patients + (load.patients === 1 ? ' patient' : ' patients')"></div>
                        </div>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold"
                            :class="load.rostered ? levelBadge(load.level) : 'bg-amber-100 text-amber-800'"
                            x-text="load.rostered ? levelLabel(load.level) : 'Not rostered'"></span>
                    </div>
                    <div class="mt-3 flex items-center gap-2">
                        <div class="h-2 flex-1 rounded-full bg-gray-100">
                            <div class="h-2 rounded-full transition-all" :class="levelBar(load.level)"
                                :style="'width: ' + Math.min(100, Math.round(load.score / maxScore * 100)) + '%'"></div>
                        </div>
                        <span class="w-10 text-right text-sm font-bold text-gray-800" x-text="format(load.score)"></span>
                    </div>
                </div>
            </template>
        </div>

        {{-- Beds --}}
        <form id="bed-assignment-form" method="POST" action="{{ route('ward.ai-schedule.assign') }}" class="bg-white rounded-2xl shadow-lg border border-blue-100">
            @csrf
            <input type="hidden" name="ward_id" value="{{ $ward->id }}">
            <input type="hidden" name="week" value="{{ $weekValue }}">
            <input type="hidden" name="date" value="{{ $day }}">
            <input type="hidden" name="shift" value="{{ $shift }}">

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gradient-to-r from-blue-50 to-cyan-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Bed</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Patient</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Workload</th>
                            <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider w-72">Nurse</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($beds as $bedId => $row)
                            @php $chosen = $mapping[$bedId] ?? null; @endphp
                            <tr :class="isChanged({{ $bedId }}) ? 'bg-indigo-50/60' : ''">
                                <td class="px-4 py-2 whitespace-nowrap font-semibold text-gray-800">{{ $row['bed']->bed_display_name ?: $row['bed']->bed_number }}</td>
                                <td class="px-4 py-2">
                                    @if ($row['patient'])
                                        <div class="font-medium text-gray-800">{{ $row['patient']->name }}</div>
                                        <div class="text-xs text-gray-500">{{ $row['patient']->mrn }}</div>
                                    @else
                                        <span class="text-gray-400">Empty</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    <div class="flex flex-wrap items-center gap-1">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold {{ $scoreStyle($row['score']) }}">{{ number_format($row['score'], 1) }}</span>
                                        @foreach ($row['factors'] as $factor)
                                            @continue(in_array($factor['label'], ['Patient', 'Empty bed'], true))
                                            <span class="inline-flex items-center rounded bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-600">{{ $factor['label'] }} +{{ \App\Services\NurseScheduling\WorkloadWeights::format($factor['points']) }}</span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-4 py-2">
                                    <select name="beds[{{ $bedId }}]" x-model="mapping[{{ $bedId }}]" @disabled(!$canEdit)
                                        aria-label="Nurse for bed {{ $row['bed']->bed_number }}"
                                        :class="isChanged({{ $bedId }}) ? 'border-indigo-400 ring-1 ring-indigo-300' : 'border-gray-300'"
                                        class="block w-full rounded-lg shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">&mdash; Unassigned &mdash;</option>
                                        @if ($rostered->isNotEmpty())
                                            <optgroup label="Rostered on {{ $shift }}">
                                                @foreach ($rostered as $nurse)
                                                    <option value="{{ $nurse->id }}" @selected($chosen === $nurse->id)>{{ $nurse->name }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                        @if ($offRoster->isNotEmpty())
                                            <optgroup label="Not on the roster">
                                                @foreach ($offRoster as $nurse)
                                                    <option value="{{ $nurse->id }}" @selected($chosen === $nurse->id)>{{ $nurse->name }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                    </select>
                                    <p x-show="isChanged({{ $bedId }}) && saved[{{ $bedId }}]" x-cloak class="mt-1 text-[11px] text-indigo-700"
                                        x-text="'Now: ' + savedName({{ $bedId }})"></p>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($canEdit)
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-5 py-4">
                    <p class="text-xs text-gray-500">Each bed's score is the sum of its workload weights, shown beside it.
                        <button type="button" @click="weightsOpen = true; $nextTick(() => document.getElementById('workload-weights')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
                            class="font-semibold text-indigo-600 hover:text-indigo-800">See or change the weights</button></p>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" @click="reviewOpen = true"
                            class="inline-flex items-center px-4 py-2.5 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-lg shadow-sm hover:bg-gray-50">
                            Review summary
                        </button>
                        <button type="submit"
                            class="inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 text-white text-sm font-semibold rounded-lg shadow">
                            Apply to ward dashboard
                        </button>
                    </div>
                </div>
            @endif
        </form>

        @include('wards.ai-schedule.partials.assign-review')
    @endif
</div>
