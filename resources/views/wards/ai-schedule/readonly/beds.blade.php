{{--
    Read-only bed assignment for one shift from a ScheduleSnapshot: each bed
    with its patient (as the snapshot allows), care level, workload and nurse,
    then each nurse's share. On paper it is the handover sheet. Reads only
    $snapshot and $options and links nowhere.

    Needs: $snapshot with 'assign' built.
    Optional $options: mono, staffing (rostered against the minimum and the
    acuity recommendation), legend, show_reasons (each patient's care needs),
    write_in (an empty column for handover notes).
--}}
@php
    $options = array_merge(['mono' => false, 'staffing' => true, 'legend' => true, 'show_reasons' => true, 'write_in' => false], $options ?? []);
    $assign = $snapshot['assign'];
    $showPatients = $snapshot['names'] !== 'hidden';
    $showNeeds = $options['show_reasons'] && !$snapshot['public'];
    $levels = \App\Services\NurseScheduling\PatientAcuity::LEVELS;
    $levelTone = [
        1 => 'bg-green-100 text-green-800 border-green-300',
        2 => 'bg-blue-100 text-blue-800 border-blue-300',
        3 => 'bg-yellow-100 text-yellow-800 border-yellow-300',
        4 => 'bg-red-100 text-red-800 border-red-300',
    ];
    $balance = [
        'heavy' => ['Heavy', 'bg-red-100 text-red-800'],
        'light' => ['Light', 'bg-sky-100 text-sky-800'],
        'even' => ['Balanced', 'bg-emerald-100 text-emerald-800'],
    ];
    $score = fn (float $value) => number_format($value, 1);
    $shortOfMinimum = $assign['rostered'] < $assign['min'];
    $shortOfNeed = $assign['recommended'] !== null && $assign['rostered'] < $assign['recommended'];
@endphp

<div class="ro-beds space-y-3">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h3 class="text-sm font-bold text-gray-900">
            {{ $assign['shift'] }} &middot; {{ $assign['date']->format('l j M Y') }}
            <span class="font-normal text-gray-600">{{ $assign['time'] }}{{ $assign['is_now'] ? ' (on now)' : '' }}</span>
        </h3>
        <span class="text-xs text-gray-700">
            {{ $assign['patients'] }} {{ \Illuminate\Support\Str::plural('patient', $assign['patients']) }} in {{ $assign['beds'] }} {{ \Illuminate\Support\Str::plural('bed', $assign['beds']) }}
            @if ($assign['unassigned'])
                &middot; <span class="font-semibold text-amber-800">{{ $assign['unassigned'] }} without a nurse</span>
            @endif
        </span>
    </div>

    @if ($options['staffing'])
        <p class="text-xs text-gray-700">
            <span class="{{ $shortOfMinimum ? 'font-bold text-red-700' : '' }}">{{ $assign['rostered'] }} {{ \Illuminate\Support\Str::plural('nurse', $assign['rostered']) }} rostered
                (minimum {{ $assign['min'] }}{{ $shortOfMinimum ? ', short' : '' }})</span>
            @if ($assign['recommended'] !== null)
                &middot; <span class="{{ $shortOfNeed ? 'font-bold text-red-700' : '' }}">the patients now need {{ $assign['recommended'] }} registered {{ \Illuminate\Support\Str::plural('nurse', $assign['recommended']) }}</span>
                <span class="ro-muted text-gray-500">(from patient acuity and the ward's settings; a planning aid)</span>
            @endif
        </p>
    @endif

    <table class="ro-table text-xs">
        <thead>
            <tr>
                <th class="text-left">Bed</th>
                @if ($showPatients)
                    <th class="text-left">Patient</th>
                @endif
                <th class="text-left">Care level</th>
                <th class="text-center">Load</th>
                @if ($showNeeds)
                    <th class="text-left">Care needs</th>
                @endif
                <th class="text-left">Nurse</th>
                @if ($options['write_in'])
                    <th class="ro-write text-left">Handover notes</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse ($assign['rows'] as $row)
                <tr>
                    <td class="whitespace-nowrap font-semibold text-gray-900">{{ $row['bed'] }}</td>
                    @if ($showPatients)
                        <td>
                            @if ($row['occupied'])
                                <div class="font-medium text-gray-900">{{ $row['patient'] }}</div>
                                @if ($row['mrn'])
                                    <div class="ro-small ro-muted text-gray-500">{{ $row['mrn'] }}</div>
                                @endif
                            @else
                                <span class="ro-muted text-gray-400">Empty</span>
                            @endif
                        </td>
                    @endif
                    <td class="whitespace-nowrap">
                        @if ($row['level'])
                            <span class="ro-level ro-level-{{ $row['level'] }} {{ $levelTone[$row['level']] }}">Level {{ $row['level'] }} {{ $levels[$row['level']]['short'] }}</span>
                            @if (!$row['classified'])
                                <div class="ro-small ro-muted text-gray-500">suggested</div>
                            @endif
                        @elseif (!$showPatients && !$row['occupied'])
                            <span class="ro-muted text-gray-400">Empty</span>
                        @else
                            <span class="ro-muted text-gray-400">&ndash;</span>
                        @endif
                    </td>
                    <td class="text-center font-semibold">{{ $score($row['score']) }}</td>
                    @if ($showNeeds)
                        <td>{{ $row['needs'] ? implode(', ', $row['needs']) : '' }}</td>
                    @endif
                    <td class="whitespace-nowrap {{ $row['nurse'] ? 'font-semibold text-gray-900' : 'text-amber-800' }}">{{ $row['nurse'] ?? 'No nurse' }}</td>
                    @if ($options['write_in'])
                        <td></td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="py-4 text-center text-gray-500">This ward has no beds that need a nurse.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="ro-avoid">
        <h4 class="mb-1 text-xs font-bold uppercase tracking-wider text-gray-700">Each nurse's share</h4>
        @if ($assign['loads'])
            <table class="ro-table text-xs">
                <thead>
                    <tr>
                        <th class="text-left">Nurse</th>
                        <th class="text-left">Beds</th>
                        <th class="text-center">Patients</th>
                        <th class="text-center">Load</th>
                        <th class="text-left">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($assign['loads'] as $load)
                        <tr>
                            <td class="whitespace-nowrap">
                                <span class="font-semibold text-gray-900">{{ $load['name'] }}</span>
                                @if (!$load['rostered'])
                                    <span class="ro-small font-semibold text-amber-800">(not rostered on this shift)</span>
                                @endif
                            </td>
                            <td>{{ $load['beds'] ? implode(', ', $load['beds']) : 'None yet' }}</td>
                            <td class="text-center">{{ $load['patients'] }}</td>
                            <td class="text-center font-semibold">{{ $score($load['score']) }}</td>
                            <td><span class="ro-staff ro-staff-{{ $load['level'] === 'heavy' ? 'short' : 'met' }} {{ $balance[$load['level']][1] }}">{{ $balance[$load['level']][0] }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="text-xs text-gray-500">Nobody is rostered on this shift and no beds are assigned.</p>
        @endif
    </div>

    @if ($options['legend'])
        <div class="ro-avoid flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-gray-700">
            @foreach ($levels as $level => $info)
                <span class="inline-flex items-center gap-1.5">
                    <span class="ro-level ro-level-{{ $level }} {{ $levelTone[$level] }}">Level {{ $level }}</span>
                    {{ $info['name'] }}
                </span>
            @endforeach
            <span>Load: the bed's workload score, the sum of the ward's workload weights.</span>
            <span>Heavy or light: more than {{ \App\Services\NurseScheduling\WorkloadWeights::format($assign['band']) }}% above or below the shift average.</span>
        </div>
    @endif
</div>
