{{-- Roster tab: who works which shift this week, with staffing against each minimum and each nurse's totals --}}

@php
    $chip = [
        'AM' => 'bg-sky-100 text-sky-800 border-sky-200',
        'PM' => 'bg-amber-100 text-amber-800 border-amber-200',
        'ON' => 'bg-indigo-600 text-white border-indigo-700',
        'OFF' => 'bg-gray-100 text-gray-500 border-gray-200',
    ];
    $leaveChip = [
        'medical' => 'bg-rose-100 text-rose-800 border-rose-200',
        'emergency' => 'bg-rose-100 text-rose-800 border-rose-200',
    ];
    $target = $rules->targetShiftsPerWeek;
    $weekValue = $weekStart->toDateString();
@endphp

<div x-show="tab === 'roster'" x-cloak x-data="{ rulesOpen: false }"
    class="bg-white rounded-2xl shadow-lg border border-blue-100">
    <div class="p-5 border-b border-gray-100 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Roster</h3>
            <p class="text-xs text-gray-500 mt-0.5 max-w-2xl">
                AI fills each shift's minimum staffing fairly (shifts, nights, weekends and holidays evened out,
                nights kept in blocks, no one rostered on leave), then tops nurses up to {{ $target }} shifts a week.
                Click a day to set it by hand; days set by hand are kept when AI runs again.
            </p>
        </div>

        @if ($canEdit)
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" @click="rulesOpen = !rulesOpen"
                    class="inline-flex items-center px-3 py-2 text-sm font-semibold rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                    </svg>
                    Staffing rules
                </button>

                @if ($board['scheduleOnly'] > 0)
                    <form method="POST" action="{{ route('ward.ai-schedule.import') }}">
                        @csrf
                        <input type="hidden" name="ward_id" value="{{ $ward->id }}">
                        <input type="hidden" name="week" value="{{ $weekValue }}">
                        <button type="submit" title="Keep the shifts already planned on the Ward Schedule page"
                            class="inline-flex items-center px-3 py-2 text-sm font-semibold rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                            Bring in Ward Schedule ({{ $board['scheduleOnly'] }})
                        </button>
                    </form>
                @endif

                @if ($board['autoCount'] > 0)
                    <form method="POST" action="{{ route('ward.ai-schedule.clear') }}"
                        onsubmit="return confirm('Remove the AI-planned shifts for this week? Shifts set by hand stay.')">
                        @csrf
                        <input type="hidden" name="ward_id" value="{{ $ward->id }}">
                        <input type="hidden" name="week" value="{{ $weekValue }}">
                        <button type="submit"
                            class="inline-flex items-center px-3 py-2 text-sm font-semibold rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                            Clear AI shifts
                        </button>
                    </form>
                @endif

                <form method="POST" action="{{ route('ward.ai-schedule.generate') }}" class="flex items-center gap-1"
                    onsubmit="return confirm('Plan the roster with AI? AI-planned shifts in that period are replaced; shifts set by hand stay.')">
                    @csrf
                    <input type="hidden" name="ward_id" value="{{ $ward->id }}">
                    <input type="hidden" name="week" value="{{ $weekValue }}">
                    <select name="weeks" aria-label="How many weeks to plan"
                        class="rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="1">This week</option>
                        <option value="2">2 weeks</option>
                        <option value="4">4 weeks</option>
                    </select>
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-700 hover:to-blue-700 text-white text-sm font-semibold rounded-lg shadow">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                        </svg>
                        Generate with AI
                    </button>
                </form>
            </div>
        @endif
    </div>

    {{-- Staffing rules --}}
    @if ($canEdit)
        <div x-show="rulesOpen" x-cloak class="px-5 py-4 bg-gray-50 border-b border-gray-100">
            <form method="POST" action="{{ route('ward.ai-schedule.rules') }}">
                @csrf
                <input type="hidden" name="ward_id" value="{{ $ward->id }}">
                <input type="hidden" name="week" value="{{ $weekValue }}">
                <div class="grid grid-cols-2 gap-4 md:grid-cols-4 lg:grid-cols-7">
                    @foreach ($shifts as $code => $info)
                        <div>
                            <label for="rule_min_{{ $code }}" class="block text-xs font-semibold text-gray-700 mb-1">Minimum on {{ $code }}</label>
                            <input type="number" id="rule_min_{{ $code }}" name="min_staff[{{ $code }}]" min="0" max="50"
                                value="{{ $rules->minStaff[$code] }}"
                                class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <p class="mt-1 text-[11px] text-gray-500">Suggested {{ $rules->suggestedMinStaff[$code] }} for {{ $beds->count() }} beds</p>
                        </div>
                    @endforeach
                    <div>
                        <label for="rule_target" class="block text-xs font-semibold text-gray-700 mb-1">Shifts a week per nurse</label>
                        <input type="number" id="rule_target" name="target_shifts_per_week" min="1" max="7" value="{{ $rules->targetShiftsPerWeek }}"
                            class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="rule_days" class="block text-xs font-semibold text-gray-700 mb-1">Most days in a row</label>
                        <input type="number" id="rule_days" name="max_consecutive_days" min="1" max="14" value="{{ $rules->maxConsecutiveDays }}"
                            class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="rule_nights" class="block text-xs font-semibold text-gray-700 mb-1">Most nights in a row</label>
                        <input type="number" id="rule_nights" name="max_consecutive_nights" min="1" max="7" value="{{ $rules->maxConsecutiveNights }}"
                            class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div class="flex flex-col justify-between">
                        <label class="inline-flex items-start gap-2 text-xs font-semibold text-gray-700">
                            <input type="checkbox" name="avoid_quick_return" value="1" @checked($rules->avoidQuickReturn)
                                class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            Avoid a PM followed by an AM
                        </label>
                        <button type="submit"
                            class="mt-2 inline-flex items-center justify-center px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg shadow-sm hover:bg-indigo-700">
                            Save rules
                        </button>
                    </div>
                </div>
                <p class="mt-3 text-[11px] text-gray-500">
                    Always applied: nobody works on a leave day or twice in a day, and after a night the next day is
                    another night or off.
                </p>
            </form>
        </div>
    @endif

    {{-- Legend --}}
    <div class="px-5 py-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-gray-600 border-b border-gray-100">
        @foreach ($shifts as $code => $info)
            <span class="inline-flex items-center gap-1.5">
                <span class="inline-flex items-center justify-center w-9 rounded border text-[11px] font-bold {{ $chip[$code] }}">{{ $code }}</span>
                {{ $info['time'] }}
            </span>
        @endforeach
        <span class="inline-flex items-center gap-1.5"><span class="inline-flex items-center justify-center w-9 rounded border text-[11px] font-bold {{ $chip['OFF'] }}">OFF</span> day off</span>
        <span class="inline-flex items-center gap-1.5"><span class="inline-flex items-center justify-center w-9 rounded border text-[11px] font-bold bg-emerald-100 text-emerald-800 border-emerald-200">AL</span> leave</span>
        <span class="inline-flex items-center gap-1.5">
            <svg class="w-3 h-3 text-gray-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" /></svg>
            set by hand
        </span>
        <span class="inline-flex items-center gap-1.5"><span class="inline-block w-9 h-4 rounded border border-dashed border-gray-400"></span> from the Ward Schedule</span>
    </div>

    <div class="overflow-x-auto"
        x-data="{
            menu: null,
            open(event, nurse, name, date, label) {
                const box = event.currentTarget.getBoundingClientRect();
                const below = box.bottom + 190 < window.innerHeight;
                this.menu = {
                    nurse, name, date, label,
                    x: Math.min(Math.max(box.left + box.width / 2 - 104, 8), window.innerWidth - 216),
                    y: below ? box.bottom + 4 : box.top - 186,
                };
            },
            set(shift) {
                this.$refs.cellNurse.value = this.menu.nurse;
                this.$refs.cellDate.value = this.menu.date;
                this.$refs.cellShift.value = shift;
                this.$refs.cellForm.submit();
            },
        }"
        @keydown.escape.window="menu = null" x-on:scroll.window.capture="menu = null">

        @if ($canEdit)
            <form x-ref="cellForm" method="POST" action="{{ route('ward.ai-schedule.cell') }}" class="hidden">
                @csrf
                <input type="hidden" name="ward_id" value="{{ $ward->id }}">
                <input type="hidden" name="week" value="{{ $weekValue }}">
                <input type="hidden" name="nurse_id" x-ref="cellNurse">
                <input type="hidden" name="date" x-ref="cellDate">
                <input type="hidden" name="shift" x-ref="cellShift">
            </form>
        @endif

        <table class="min-w-full text-sm">
            <thead>
                <tr class="bg-gradient-to-r from-blue-50 to-cyan-50">
                    <th class="sticky left-0 z-10 bg-blue-50 px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Nurse</th>
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
                    <th class="px-3 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">Shifts</th>
                    <th class="px-3 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">Nights</th>
                    <th class="px-3 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">Hours</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($board['team'] as $nurse)
                    @php $stat = $board['stats'][$nurse->id]; @endphp
                    <tr class="hover:bg-blue-50/40">
                        <th class="sticky left-0 z-10 bg-white px-4 py-2 text-left font-normal whitespace-nowrap">
                            <div class="font-medium text-gray-800">{{ $nurse->name }}</div>
                            @if ($nurse->designation)
                                <div class="text-[11px] text-gray-400">{{ $nurse->designation }}</div>
                            @endif
                        </th>
                        @foreach ($board['dates'] as $date)
                            @php
                                $day = $date->toDateString();
                                $cell = $board['cells'][$nurse->id][$day];
                                $editable = $canEdit && !$cell['other_ward'];
                                $holiday = isset($board['holidays'][$day]);
                            @endphp
                            <td class="px-1 py-1.5 text-center {{ $date->isWeekend() ? 'bg-slate-50' : '' }} {{ $holiday ? 'bg-rose-50/60' : '' }}">
                                <button type="button" data-cell
                                    @if ($editable) @click="open($event, {{ $nurse->id }}, @js($nurse->name), '{{ $day }}', '{{ $date->format('D j M') }}')" @endif
                                    @disabled(!$editable)
                                    title="{{ $cell['conflict'] ?? ($cell['other_ward'] ? 'On ' . $cell['other_ward'] : ($cell['source'] === 'schedule' ? 'From the Ward Schedule page' : ($cell['leave'] ? $cell['leave']->label() : ''))) }}"
                                    class="relative inline-flex items-center justify-center gap-0.5 w-14 h-8 rounded-md text-xs font-bold transition-colors {{ $editable ? 'hover:ring-2 hover:ring-indigo-300 cursor-pointer' : 'cursor-default' }} {{ $cell['conflict'] ? 'ring-2 ring-red-500' : '' }}">
                                    @if ($cell['leave'] && !in_array($cell['shift'], ['AM', 'PM', 'ON'], true))
                                        <span class="inline-flex items-center justify-center w-full h-full rounded-md border {{ $leaveChip[$cell['leave']->type] ?? 'bg-emerald-100 text-emerald-800 border-emerald-200' }}">{{ $cell['leave']->code() }}</span>
                                    @elseif ($cell['shift'])
                                        <span class="inline-flex items-center justify-center gap-0.5 w-full h-full rounded-md border {{ $cell['other_ward'] ? 'bg-gray-50 text-gray-400 border-gray-200' : $chip[$cell['shift']] ?? $chip['OFF'] }} {{ $cell['source'] === 'schedule' ? 'border-dashed border-2' : '' }}">
                                            {{ $cell['shift'] }}
                                            @if ($cell['source'] === 'manual')
                                                <svg class="w-2.5 h-2.5 opacity-70" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" /></svg>
                                            @endif
                                        </span>
                                        @if ($cell['leave'])
                                            <span class="absolute -top-1.5 -right-1.5 rounded bg-rose-600 px-1 text-[9px] leading-4 text-white">{{ $cell['leave']->code() }}</span>
                                        @endif
                                    @else
                                        <span class="text-gray-300 font-normal">&ndash;</span>
                                    @endif
                                </button>
                            </td>
                        @endforeach
                        <td class="px-3 py-2 text-center whitespace-nowrap">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold
                                {{ $stat['shifts'] > $target ? 'bg-red-100 text-red-800' : ($stat['shifts'] + $stat['leave'] < $target ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800') }}"
                                title="{{ $stat['AM'] }} AM, {{ $stat['PM'] }} PM, {{ $stat['ON'] }} ON{{ $stat['leave'] ? ', ' . $stat['leave'] . ' leave days' : '' }}">
                                {{ $stat['shifts'] }}/{{ $target }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-center text-gray-700">{{ $stat['ON'] }}</td>
                        <td class="px-3 py-2 text-center text-gray-700">{{ rtrim(rtrim(number_format($stat['hours'], 1), '0'), '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $board['dates']->count() + 4 }}" class="px-6 py-10 text-center text-gray-500">
                            No active nurses yet. Add nurses under Admin Management.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot class="border-t-2 border-gray-200 bg-gray-50/70">
                @foreach ($shifts as $code => $info)
                    <tr>
                        <th class="sticky left-0 z-10 bg-gray-50 px-4 py-2 text-left font-normal whitespace-nowrap">
                            <span class="inline-flex items-center justify-center w-9 rounded border text-[11px] font-bold {{ $chip[$code] }}">{{ $code }}</span>
                            <span class="ml-1 text-xs text-gray-500">on duty</span>
                        </th>
                        @foreach ($board['dates'] as $date)
                            @php $staff = $board['staffing'][$date->toDateString()][$code]; @endphp
                            <td class="px-1 py-1.5 text-center">
                                <span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-semibold
                                    {{ $staff['count'] < $staff['min'] ? 'bg-red-100 text-red-700' : ($staff['count'] > $staff['min'] ? 'bg-sky-100 text-sky-700' : 'bg-emerald-100 text-emerald-700') }}"
                                    title="{{ $staff['count'] }} on duty, minimum {{ $staff['min'] }}">
                                    {{ $staff['count'] }}/{{ $staff['min'] }}
                                </span>
                            </td>
                        @endforeach
                        <td colspan="3"></td>
                    </tr>
                @endforeach
            </tfoot>
        </table>

        {{-- One shared menu for setting a day by hand --}}
        @if ($canEdit)
            <div x-show="menu" x-cloak
                @click.outside="if (!$event.target.closest('[data-cell]')) menu = null"
                :style="menu && { left: menu.x + 'px', top: menu.y + 'px' }"
                class="fixed z-50 w-52 rounded-xl border border-gray-200 bg-white p-2 shadow-2xl">
                <div class="px-1 pb-2 text-xs text-gray-500">
                    <span class="font-semibold text-gray-800" x-text="menu && menu.name"></span>
                    &middot; <span x-text="menu && menu.label"></span>
                </div>
                <div class="grid grid-cols-3 gap-1">
                    @foreach ($shifts as $code => $info)
                        <button type="button" @click="set('{{ $code }}')" title="{{ $info['time'] }}"
                            class="rounded-md border py-2 text-xs font-bold hover:opacity-80 {{ $chip[$code] }}">{{ $code }}</button>
                    @endforeach
                </div>
                <div class="mt-1 grid grid-cols-2 gap-1">
                    <button type="button" @click="set('OFF')" class="rounded-md border py-2 text-xs font-bold hover:opacity-80 {{ $chip['OFF'] }}">Day off</button>
                    <button type="button" @click="set('clear')" class="rounded-md border border-gray-200 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50">Clear</button>
                </div>
            </div>
        @endif
    </div>
</div>
