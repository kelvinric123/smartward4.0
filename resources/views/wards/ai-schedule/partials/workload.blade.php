{{-- Workload tab: the load on each nurse on duty now, and how evenly the week is shared --}}

@php
    $liveInfo = $shifts[$liveCode];
    $liveMax = max(1, collect($liveLoads)->max('score') ?? 1);
    $liveTotal = collect($liveLoads)->sum('score');
    $levelStyle = [
        'heavy' => ['bar' => 'bg-red-500', 'badge' => 'bg-red-100 text-red-800', 'label' => 'Heavy'],
        'light' => ['bar' => 'bg-sky-400', 'badge' => 'bg-sky-100 text-sky-800', 'label' => 'Light'],
        'even' => ['bar' => 'bg-emerald-500', 'badge' => 'bg-emerald-100 text-emerald-800', 'label' => 'Balanced'],
    ];

    $target = $rules->targetShiftsPerWeek;
    $stats = collect($board['stats']);
    $spread = fn (string $key) => $stats->isEmpty() ? '-' : ($stats->min($key) === $stats->max($key) ? $stats->min($key) : $stats->min($key) . ' to ' . $stats->max($key));
    $averageNights = $stats->avg('ON') ?? 0;
@endphp

<div x-show="tab === 'workload'" x-cloak class="space-y-4">
    {{-- On duty now --}}
    <div class="bg-white rounded-2xl shadow-lg border border-blue-100 p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">On duty now</h3>
                <p class="text-xs text-gray-500 mt-0.5">
                    {{ $liveCode }} shift, {{ $liveInfo['time'] }}, as the ward dashboard shows it. Heavy or light means
                    more than {{ \App\Services\NurseScheduling\WorkloadWeights::format($weights->get('balance_band')) }}% above or below
                    the shift average (set under Workload weights on the Bed assignment tab).
                </p>
            </div>
            @if ($liveLoads)
                <div class="flex gap-2 text-xs">
                    <span class="rounded-full bg-gray-100 px-3 py-1 font-semibold text-gray-700">Ward total {{ number_format($liveTotal, 1) }}</span>
                    <span class="rounded-full bg-gray-100 px-3 py-1 font-semibold text-gray-700">Average {{ number_format($liveTotal / count($liveLoads), 1) }} per nurse</span>
                </div>
            @endif
        </div>

        @if ($liveLoads)
            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($liveLoads as $nurseId => $load)
                    @php $style = $levelStyle[$load['level']]; @endphp
                    <div class="rounded-xl border border-gray-200 p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="truncate font-semibold text-gray-800">{{ $liveNurses->get($nurseId)->name ?? 'Nurse #' . $nurseId }}</div>
                                <div class="text-xs text-gray-500">{{ $load['beds'] }} {{ \Illuminate\Support\Str::plural('bed', $load['beds']) }} &middot; {{ $load['patients'] }} {{ \Illuminate\Support\Str::plural('patient', $load['patients']) }}</div>
                            </div>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $style['badge'] }}">{{ $style['label'] }}</span>
                        </div>
                        <div class="mt-3 flex items-center gap-2">
                            <div class="h-2 flex-1 rounded-full bg-gray-100">
                                <div class="h-2 rounded-full {{ $style['bar'] }}" style="width: {{ min(100, round($load['score'] / $liveMax * 100)) }}%"></div>
                            </div>
                            <span class="w-10 text-right text-sm font-bold text-gray-800">{{ number_format($load['score'], 1) }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="mt-4 rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500">
                No beds are assigned for the shift on now. Use the Bed assignment tab to share them out.
            </div>
        @endif
    </div>

    {{-- The week --}}
    <div class="bg-white rounded-2xl shadow-lg border border-blue-100">
        <div class="p-5 border-b border-gray-100 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">This week</h3>
                <p class="text-xs text-gray-500 mt-0.5">How evenly shifts, nights, weekends and holidays are shared, against {{ $target }} shifts a week each.</p>
            </div>
            <div class="flex flex-wrap gap-2 text-xs">
                <span class="rounded-full bg-sky-50 px-3 py-1 font-semibold text-sky-800">Shifts per nurse: {{ $spread('shifts') }}</span>
                <span class="rounded-full bg-indigo-50 px-3 py-1 font-semibold text-indigo-800">Nights: {{ $spread('ON') }}</span>
                <span class="rounded-full bg-slate-100 px-3 py-1 font-semibold text-slate-700">Weekend shifts: {{ $spread('weekend') }}</span>
                @if (!empty($board['holidays']))
                    <span class="rounded-full bg-rose-50 px-3 py-1 font-semibold text-rose-800">Holiday shifts: {{ $spread('holiday') }}</span>
                @endif
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gradient-to-r from-blue-50 to-cyan-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Nurse</th>
                        <th class="px-3 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">Shifts</th>
                        <th class="px-3 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">AM</th>
                        <th class="px-3 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">PM</th>
                        <th class="px-3 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">ON</th>
                        <th class="px-3 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">Weekend</th>
                        <th class="px-3 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">Holiday</th>
                        <th class="px-3 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">Hours</th>
                        <th class="px-3 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">Leave days</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($board['team'] as $nurse)
                        @php
                            $stat = $board['stats'][$nurse->id];
                            $status = $stat['shifts'] > $target
                                ? ['bg-red-100 text-red-800', 'Over target']
                                : ($stat['shifts'] + $stat['leave'] < $target ? ['bg-amber-100 text-amber-800', 'Under target'] : ['bg-emerald-100 text-emerald-800', 'On target']);
                        @endphp
                        <tr class="hover:bg-blue-50/40">
                            <td class="px-4 py-2 font-medium text-gray-800 whitespace-nowrap">{{ $nurse->name }}</td>
                            <td class="px-3 py-2 text-center">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $status[0] }}" title="{{ $status[1] }}">{{ $stat['shifts'] }}/{{ $target }}</span>
                            </td>
                            <td class="px-3 py-2 text-center text-gray-700">{{ $stat['AM'] }}</td>
                            <td class="px-3 py-2 text-center text-gray-700">{{ $stat['PM'] }}</td>
                            <td class="px-3 py-2 text-center {{ $stat['ON'] > $averageNights + 1 ? 'font-semibold text-amber-700' : 'text-gray-700' }}">{{ $stat['ON'] }}</td>
                            <td class="px-3 py-2 text-center text-gray-700">{{ $stat['weekend'] }}</td>
                            <td class="px-3 py-2 text-center text-gray-700">{{ $stat['holiday'] }}</td>
                            <td class="px-3 py-2 text-center text-gray-700">{{ rtrim(rtrim(number_format($stat['hours'], 1), '0'), '.') }}</td>
                            <td class="px-3 py-2 text-center text-gray-700">{{ $stat['leave'] ?: '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
