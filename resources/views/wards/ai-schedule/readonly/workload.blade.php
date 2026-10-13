{{--
    Read-only workload and fairness from a ScheduleSnapshot: how evenly each
    week's shifts, nights, weekends and holidays are shared, against the
    ward's weekly target, and over all the weeks shown when there are several.
    Reads only $snapshot and $options and links nowhere.

    Needs: $snapshot (ScheduleSnapshot::build).
    Optional $options: mono.
--}}
@php
    $options = array_merge(['mono' => false], $options ?? []);
    $target = $snapshot['rules']['target_shifts_per_week'];
    $hours = fn (float $value) => rtrim(rtrim(number_format($value, 1), '0'), '.');
    $spread = fn ($stats, string $key) => $stats->isEmpty() ? '-' : ($stats->min($key) === $stats->max($key) ? $stats->min($key) : $stats->min($key) . ' to ' . $stats->max($key));
    $status = fn (array $stat, int $goal) => $stat['shifts'] > $goal
        ? ['Over target', 'ro-staff-short bg-red-100 text-red-800']
        : ($stat['shifts'] + $stat['leave'] < $goal ? ['Under target', 'ro-staff-over bg-amber-100 text-amber-800'] : ['On target', 'ro-staff-met bg-emerald-100 text-emerald-800']);

    // Each nurse's totals over every week shown
    $totals = [];
    foreach ($snapshot['weeks'] as $week) {
        foreach ($week['nurses'] as $nurse) {
            $totals[$nurse['id']] ??= ['name' => $nurse['name'], 'shifts' => 0, 'AM' => 0, 'PM' => 0, 'ON' => 0, 'weekend' => 0, 'holiday' => 0, 'hours' => 0.0, 'leave' => 0];
            foreach (['shifts', 'AM', 'PM', 'ON', 'weekend', 'holiday', 'hours', 'leave'] as $key) {
                $totals[$nurse['id']][$key] += $nurse['stats'][$key];
            }
        }
    }
    $tables = collect($snapshot['weeks'])->map(fn (array $week) => [
        'title' => 'Week ' . $week['number'] . ' · ' . $week['start']->format('D j M') . ' – ' . $week['end']->format('D j M Y'),
        'rows' => collect($week['nurses'])->map(fn (array $nurse) => ['name' => $nurse['name']] + $nurse['stats']),
        'goal' => $target,
        'holidays' => !empty($week['holidays']),
    ]);
    if (count($snapshot['weeks']) > 1) {
        $tables->push([
            'title' => 'All ' . count($snapshot['weeks']) . ' weeks · ' . $snapshot['from']->format('j M') . ' – ' . $snapshot['to']->format('j M Y'),
            'rows' => collect($totals)->values(),
            'goal' => $target * count($snapshot['weeks']),
            'holidays' => collect($snapshot['weeks'])->contains(fn (array $week) => !empty($week['holidays'])),
        ]);
    }
@endphp

<div class="ro-workload space-y-4">
    @foreach ($tables as $table)
        @php
            $rows = $table['rows'];
            $averageNights = $rows->avg('ON') ?? 0;
        @endphp
        {{-- The table may run over pages (its rows stay whole and its headings repeat); only the
             title and chips are kept together, and with the table, so page 1 is never left empty --}}
        <section class="{{ $loop->first ? '' : 'page-break' }}">
            <div class="ro-avoid mb-1.5 flex flex-wrap items-baseline justify-between gap-2" style="break-after: avoid; page-break-after: avoid">
                <h3 class="text-sm font-bold text-gray-900">{{ $table['title'] }}</h3>
                <div class="flex flex-wrap gap-1.5 text-xs">
                    <span class="ro-staff ro-staff-met bg-sky-50 text-sky-800">Shifts per nurse: {{ $spread($rows, 'shifts') }}</span>
                    <span class="ro-staff ro-staff-met bg-indigo-50 text-indigo-800">Nights: {{ $spread($rows, 'ON') }}</span>
                    <span class="ro-staff ro-staff-met bg-slate-100 text-slate-700">Weekend shifts: {{ $spread($rows, 'weekend') }}</span>
                    @if ($table['holidays'])
                        <span class="ro-staff ro-staff-met bg-rose-50 text-rose-800">Holiday shifts: {{ $spread($rows, 'holiday') }}</span>
                    @endif
                </div>
            </div>
            <table class="ro-table text-xs">
                <thead>
                    <tr>
                        <th class="text-left">Nurse</th>
                        <th class="text-center">Shifts / {{ $table['goal'] }}</th>
                        <th class="text-center">AM</th>
                        <th class="text-center">PM</th>
                        <th class="text-center">ON</th>
                        <th class="text-center">Weekend</th>
                        <th class="text-center">Holiday</th>
                        <th class="text-center">Hours</th>
                        <th class="text-center">Leave days</th>
                        <th class="text-left">Against target</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php [$statusLabel, $statusTone] = $status($row, $table['goal']); @endphp
                        <tr>
                            <td class="whitespace-nowrap font-semibold text-gray-900">{{ $row['name'] }}</td>
                            <td class="text-center">{{ $row['shifts'] }}</td>
                            <td class="text-center">{{ $row['AM'] }}</td>
                            <td class="text-center">{{ $row['PM'] }}</td>
                            <td class="text-center {{ $row['ON'] > $averageNights + 1 ? 'font-bold text-amber-800' : '' }}">{{ $row['ON'] }}</td>
                            <td class="text-center">{{ $row['weekend'] }}</td>
                            <td class="text-center">{{ $row['holiday'] }}</td>
                            <td class="text-center">{{ $hours($row['hours']) }}</td>
                            <td class="text-center">{{ $row['leave'] ?: '-' }}</td>
                            <td><span class="ro-staff {{ $statusTone }}">{{ $statusLabel }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="py-4 text-center text-gray-500">No nurses on this roster yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    @endforeach
    <p class="text-xs text-gray-600">
        Target: {{ $target }} shifts a week per nurse; leave days count toward it. Nights in bold are more than one above the average.
    </p>
</div>
