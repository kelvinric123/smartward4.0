{{--
    Read-only duty list: each nurse's weeks from a ScheduleSnapshot as a block
    of their own, with dashed edges, so the sheet can be cut up and handed out
    or pinned up. Reads only $snapshot and $options and links nowhere.

    Needs: $snapshot (ScheduleSnapshot::build).
    Optional $options: mono, legend, leave_detail (the legend lists each leave code).
--}}
@php
    $options = array_merge(['mono' => false, 'legend' => true, 'leave_detail' => $snapshot['leave_detail']], $options ?? []);
    $chip = [
        'AM' => 'bg-sky-100 text-sky-800 border-sky-200',
        'PM' => 'bg-amber-100 text-amber-800 border-amber-200',
        'ON' => 'bg-indigo-600 text-white border-indigo-700',
        'OFF' => 'bg-gray-100 text-gray-500 border-gray-200',
    ];
    $leaveTone = fn (?array $leave) => in_array($leave['type'] ?? null, ['medical', 'emergency'], true)
        ? 'bg-rose-100 text-rose-800 border-rose-200'
        : 'bg-emerald-100 text-emerald-800 border-emerald-200';
    $hours = fn (float $value) => rtrim(rtrim(number_format($value, 1), '0'), '.');
    $codes = \App\Services\NurseScheduling\WardShifts::CODES;

    // One entry per nurse across every week shown: the team can change from week to week
    $people = [];
    foreach ($snapshot['weeks'] as $index => $week) {
        foreach ($week['nurses'] as $nurse) {
            $people[$nurse['id']] ??= ['name' => $nurse['name'], 'designation' => $nurse['designation'], 'weeks' => [], 'shifts' => 0, 'nights' => 0, 'hours' => 0.0];
            $people[$nurse['id']]['weeks'][$index] = $nurse['days'];
            $people[$nurse['id']]['shifts'] += $nurse['stats']['shifts'];
            $people[$nurse['id']]['nights'] += $nurse['stats']['ON'];
            $people[$nurse['id']]['hours'] += $nurse['stats']['hours'];
        }
    }
    $people = collect($people)->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
    $dayNames = collect($snapshot['weeks'][0]['dates'])->map(fn ($date) => $date->format('D'));
@endphp

<div class="ro-nurse-list space-y-3">
    @if ($people->isEmpty())
        <p class="text-sm text-gray-500">No nurses on this roster yet.</p>
    @else
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 print:grid-cols-2">
            @foreach ($people as $person)
                <div class="ro-cut ro-avoid rounded-lg p-2.5">
                    <div class="flex items-baseline justify-between gap-2">
                        <div class="min-w-0">
                            <div class="truncate text-sm font-bold text-gray-900">{{ $person['name'] }}</div>
                            @if ($person['designation'])
                                <div class="ro-small ro-muted text-gray-500">{{ $person['designation'] }}</div>
                            @endif
                        </div>
                        <div class="shrink-0 text-right text-xs text-gray-700">
                            {{ $person['shifts'] }} {{ \Illuminate\Support\Str::plural('shift', $person['shifts']) }}
                            &middot; {{ $hours($person['hours']) }} h
                            @if ($person['nights'])
                                &middot; {{ $person['nights'] }} {{ \Illuminate\Support\Str::plural('night', $person['nights']) }}
                            @endif
                        </div>
                    </div>
                    <table class="ro-table mt-1.5 text-xs">
                        <thead>
                            <tr>
                                @if (count($snapshot['weeks']) > 1)
                                    <th class="text-left">Week of</th>
                                @endif
                                @foreach ($dayNames as $name)
                                    <th class="text-center">{{ $name }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($snapshot['weeks'] as $index => $week)
                                <tr>
                                    @if (count($snapshot['weeks']) > 1)
                                        <td class="whitespace-nowrap font-semibold">{{ $week['start']->format('j M') }}</td>
                                    @endif
                                    @foreach ($week['dates'] as $date)
                                        @php
                                            $day = $date->toDateString();
                                            $cell = $person['weeks'][$index][$day] ?? null;
                                            $working = $cell && in_array($cell['shift'], $codes, true);
                                        @endphp
                                        <td class="text-center {{ isset($week['holidays'][$day]) ? 'bg-rose-50' : '' }}">
                                            <div class="ro-small ro-muted text-gray-500">{{ $date->format('j') }}{{ isset($week['holidays'][$day]) ? ' hol' : '' }}</div>
                                            @if ($cell && $cell['leave'] && !$working)
                                                <span class="ro-chip ro-chip-leave {{ $leaveTone($cell['leave']) }}">{{ $cell['leave']['code'] }}</span>
                                            @elseif ($working && $cell['other_ward'])
                                                <span class="ro-chip ro-chip-other bg-gray-50 text-gray-400 border-gray-200" title="On {{ $cell['other_ward'] }}">{{ $cell['shift'] }}</span>
                                                <div class="ro-small ro-muted text-gray-500">{{ $cell['other_ward'] }}</div>
                                            @elseif ($cell && $cell['shift'])
                                                <span class="ro-chip ro-chip-{{ $cell['shift'] }} {{ $chip[$cell['shift']] ?? $chip['OFF'] }}">{{ $cell['shift'] }}</span>
                                                @if ($cell['leave'])
                                                    <div class="ro-small font-semibold text-rose-700" title="{{ $cell['conflict'] }}">{{ $cell['leave']['code'] }} !</div>
                                                @endif
                                            @else
                                                <span class="ro-muted text-gray-300">&ndash;</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>
    @endif

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
            <span>hol: public holiday</span>
        </div>
    @endif
</div>
