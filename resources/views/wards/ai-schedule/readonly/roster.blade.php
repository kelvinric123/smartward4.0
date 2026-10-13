{{--
    Read-only roster grid: each week of a ScheduleSnapshot, nurse by day, with
    the staffing each shift reaches. Used by the printouts and the view-only
    link, so it reads only $snapshot and $options and links nowhere.

    Needs: $snapshot (ScheduleSnapshot::build).
    Optional $options: mono, staffing (count/minimum rows, plus registered
    nurses against the acuity recommendation when the snapshot has it),
    legend, leave_detail (the legend lists each leave code).
--}}
@php
    $options = array_merge(['mono' => false, 'staffing' => true, 'legend' => true, 'leave_detail' => $snapshot['leave_detail']], $options ?? []);
    $chip = [
        'AM' => 'bg-sky-100 text-sky-800 border-sky-200',
        'PM' => 'bg-amber-100 text-amber-800 border-amber-200',
        'ON' => 'bg-indigo-600 text-white border-indigo-700',
        'OFF' => 'bg-gray-100 text-gray-500 border-gray-200',
    ];
    $leaveTone = fn (?array $leave) => in_array($leave['type'] ?? null, ['medical', 'emergency'], true)
        ? 'bg-rose-100 text-rose-800 border-rose-200'
        : 'bg-emerald-100 text-emerald-800 border-emerald-200';
    $staffTone = [
        'short' => 'ro-staff-short bg-red-100 text-red-800',
        'met' => 'ro-staff-met bg-emerald-100 text-emerald-800',
        'over' => 'ro-staff-over bg-sky-100 text-sky-800',
    ];
    $hours = fn (float $value) => rtrim(rtrim(number_format($value, 1), '0'), '.');
    $codes = \App\Services\NurseScheduling\WardShifts::CODES;
    $hasAcuity = $options['staffing'] && collect($snapshot['weeks'][0]['staffing'])->flatten(1)->contains(fn ($staff) => $staff['recommended'] !== null);
@endphp

<div class="ro-roster space-y-4">
    @foreach ($snapshot['weeks'] as $week)
        @php $clashes = []; @endphp
        <section class="{{ $loop->first ? '' : 'page-break' }}" data-week="{{ $week['start']->toDateString() }}">
            <div class="mb-1.5 flex flex-wrap items-baseline justify-between gap-2">
                <h3 class="text-sm font-bold text-gray-900">
                    Week {{ $week['number'] }} &middot; {{ $week['start']->format('D j M') }} &ndash; {{ $week['end']->format('D j M Y') }}
                </h3>
                <span class="ro-muted text-xs text-gray-500">{{ count($week['nurses']) }} {{ \Illuminate\Support\Str::plural('nurse', count($week['nurses'])) }}</span>
            </div>

            <table class="ro-table text-xs">
                <thead>
                    <tr>
                        <th class="text-left">Nurse</th>
                        @foreach ($week['dates'] as $date)
                            @php
                                $day = $date->toDateString();
                                $holiday = $week['holidays'][$day] ?? null;
                            @endphp
                            <th class="text-center {{ $day === $snapshot['today'] ? 'text-indigo-800' : '' }}">
                                <div>{{ $date->format('D') }}</div>
                                <div class="text-sm normal-case tracking-normal">{{ $date->format('j') }}</div>
                                @if ($day === $snapshot['today'])
                                    <div class="ro-small normal-case tracking-normal">Today</div>
                                @endif
                                @if ($holiday)
                                    <div class="ro-small font-semibold normal-case tracking-normal text-rose-700">{{ $holiday }}</div>
                                @endif
                            </th>
                        @endforeach
                        <th class="text-center">Shifts</th>
                        <th class="text-center">Hours</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($week['nurses'] as $nurse)
                        <tr>
                            <td class="whitespace-nowrap">
                                <div class="font-semibold text-gray-900">{{ $nurse['name'] }}</div>
                                @if ($nurse['designation'])
                                    <div class="ro-small ro-muted text-gray-500">{{ $nurse['designation'] }}</div>
                                @endif
                            </td>
                            @foreach ($week['dates'] as $date)
                                @php
                                    $day = $date->toDateString();
                                    $cell = $nurse['days'][$day] ?? null;
                                    $working = $cell && in_array($cell['shift'], $codes, true);
                                    $tint = isset($week['holidays'][$day]) ? 'bg-rose-50' : ($date->isWeekend() ? 'bg-slate-50' : '');
                                    if ($cell && $cell['conflict']) {
                                        $clashes[] = $nurse['name'] . ', ' . $date->format('D j M') . ': ' . $cell['conflict'];
                                    }
                                @endphp
                                <td class="text-center {{ $tint }}">
                                    @if ($cell && $cell['leave'] && !$working)
                                        <span class="ro-chip ro-chip-leave {{ $leaveTone($cell['leave']) }}">{{ $cell['leave']['code'] }}</span>
                                    @elseif ($working && $cell['other_ward'])
                                        <span class="ro-chip ro-chip-other bg-gray-50 text-gray-400 border-gray-200">{{ $cell['shift'] }}</span>
                                        <div class="ro-small ro-muted text-gray-500">{{ $cell['other_ward'] }}</div>
                                    @elseif ($cell && $cell['shift'])
                                        <span class="ro-chip ro-chip-{{ $cell['shift'] }} {{ $chip[$cell['shift']] ?? $chip['OFF'] }}">{{ $cell['shift'] }}</span>
                                        @if ($cell['leave'])
                                            <div class="ro-small font-semibold text-rose-700">{{ $cell['leave']['code'] }}</div>
                                        @endif
                                    @else
                                        <span class="ro-muted text-gray-300">&ndash;</span>
                                    @endif
                                    @if ($cell && $cell['conflict'])
                                        <span class="font-bold text-red-700" title="{{ $cell['conflict'] }}">!</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="text-center">{{ $nurse['stats']['shifts'] }}</td>
                            <td class="text-center">{{ $hours($nurse['stats']['hours']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($week['dates']) + 3 }}" class="py-4 text-center text-gray-500">No nurses on this roster yet.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($options['staffing'])
                    {{-- A second body, not a tfoot: a printed tfoot repeats at the foot of every page --}}
                    <tbody>
                        @foreach ($codes as $code)
                            <tr class="{{ $loop->first ? 'border-t-2 border-gray-400' : '' }}">
                                <th class="text-left font-normal whitespace-nowrap">
                                    <span class="ro-chip ro-chip-{{ $code }} {{ $chip[$code] }}">{{ $code }}</span>
                                    <span class="ro-muted text-gray-500">on duty</span>
                                </th>
                                @foreach ($week['dates'] as $date)
                                    @php
                                        $staff = $week['staffing'][$date->toDateString()][$code];
                                        $minTone = $staff['count'] < $staff['min'] ? 'short' : ($staff['count'] > $staff['min'] ? 'over' : 'met');
                                    @endphp
                                    <td class="text-center">
                                        <span class="ro-staff {{ $staffTone[$minTone] }}">{{ $staff['count'] }}/{{ $staff['min'] }}{{ $minTone === 'short' ? ' !' : '' }}</span>
                                        @if ($staff['recommended'] !== null)
                                            <div class="ro-small mt-0.5 whitespace-nowrap {{ $staff['status'] === 'short' ? 'font-bold text-red-700' : 'ro-muted text-gray-600' }}">
                                                RN {{ $staff['registered'] }}/{{ $staff['recommended'] }}
                                            </div>
                                        @endif
                                    </td>
                                @endforeach
                                <td colspan="2"></td>
                            </tr>
                        @endforeach
                    </tbody>
                @endif
            </table>

            @if ($clashes)
                <div class="mt-1.5 text-xs text-red-800">
                    <span class="font-semibold">! To check:</span>
                    {{ implode('; ', $clashes) }}.
                </div>
            @endif
        </section>
    @endforeach

    @if ($options['legend'])
        <div class="ro-avoid flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-gray-700">
            @foreach ($snapshot['shifts'] as $code => $info)
                <span class="inline-flex items-center gap-1.5">
                    <span class="ro-chip ro-chip-{{ $code }} {{ $chip[$code] }}">{{ $code }}</span>
                    {{ $info['name'] }} {{ $info['time'] }}
                </span>
            @endforeach
            <span class="inline-flex items-center gap-1.5"><span class="ro-chip ro-chip-OFF {{ $chip['OFF'] }}">OFF</span> day off</span>
            @if ($options['leave_detail'])
                @foreach (\App\Models\NurseLeave::TYPES as $type => $leave)
                    <span class="inline-flex items-center gap-1.5">
                        <span class="ro-chip ro-chip-leave {{ $leaveTone(['type' => $type]) }}">{{ $leave['code'] }}</span>
                        {{ $leave['label'] }}
                    </span>
                @endforeach
            @else
                <span class="inline-flex items-center gap-1.5"><span class="ro-chip ro-chip-leave {{ $leaveTone(null) }}">Leave</span> on leave</span>
            @endif
            <span class="inline-flex items-center gap-1.5"><span class="ro-muted text-gray-400">&ndash;</span> not rostered</span>
            <span class="inline-flex items-center gap-1.5"><span class="font-bold text-red-700">!</span> to check</span>
            @if ($options['staffing'])
                <span class="inline-flex items-center gap-1.5">
                    <span class="ro-staff {{ $staffTone['met'] }}">4/4</span> on duty / minimum (red and ! below it)
                </span>
                @if ($hasAcuity)
                    <span class="inline-flex items-center gap-1.5">
                        <span class="ro-small font-semibold">RN 3/4</span> registered nurses / needed for the patients now (from patient acuity and the ward's settings; a planning aid)
                    </span>
                @endif
            @endif
        </div>
    @endif
</div>
