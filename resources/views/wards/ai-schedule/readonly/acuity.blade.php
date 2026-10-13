{{--
    Read-only patient acuity from a ScheduleSnapshot: patients by care level,
    the nurses each shift needs for them, the week's registered nurses against
    that, and each patient's level. The indicators behind a level show only
    when allowed (never on a public copy). Reads only $snapshot and $options
    and links nowhere but to its own references.

    Needs: $snapshot with 'acuity' built.
    Optional $options: mono, staffing (the shift and week tables), legend (the
    level descriptions), show_reasons (indicators per patient), references (the
    method and the sources, from a new page), compact (for paper).
--}}
@php
    $options = array_merge(['mono' => false, 'staffing' => true, 'legend' => true, 'show_reasons' => true, 'references' => true, 'compact' => false], $options ?? []);
    $acuity = $snapshot['acuity'];
    $summary = $acuity['summary'];
    $staffing = $acuity['staffing'];
    $levels = \App\Services\NurseScheduling\PatientAcuity::LEVELS;
    $codes = \App\Services\NurseScheduling\WardShifts::CODES;
    $showPatients = $snapshot['names'] !== 'hidden';
    $showReasons = $options['show_reasons'] && !$snapshot['public'];
    $levelTone = [
        1 => 'bg-green-100 text-green-800 border-green-300',
        2 => 'bg-blue-100 text-blue-800 border-blue-300',
        3 => 'bg-yellow-100 text-yellow-800 border-yellow-300',
        4 => 'bg-red-100 text-red-800 border-red-300',
    ];
    $statusBadge = fn (array $row) => match ($row['status']) {
        'agrees' => ['Agrees', 'ro-staff-met bg-emerald-100 text-emerald-800'],
        'review_up' => ['Review: indicators suggest Level ' . $row['suggested'], 'ro-staff-short bg-amber-100 text-amber-800'],
        'review_down' => ['Rated above the indicators', 'ro-staff-over bg-sky-100 text-sky-800'],
        'unclassified' => ['Not classified', 'ro-staff-met bg-gray-100 text-gray-700'],
        default => ['Empty', 'ro-staff-met bg-gray-50 text-gray-500'],
    };
    $weekTone = [
        'short' => 'ro-staff-short bg-red-100 text-red-800',
        'met' => 'ro-staff-met bg-emerald-100 text-emerald-800',
        'over' => 'ro-staff-over bg-sky-100 text-sky-800',
    ];
    $fmt = fn ($value) => \App\Services\NurseScheduling\AcuitySettings::format((float) $value);
    $ratedShare = $summary['patients'] ? round($summary['rated_recently'] / $summary['patients'] * 100) : 0;
    $firstWeek = $snapshot['weeks'][0];
@endphp

<div class="ro-acuity space-y-4">
    <p class="text-xs text-gray-600">
        Method: patient classification and acuity-based staffing (ANA, 2017).
        @if ($options['references'])
            <a href="#acuity-references" class="font-semibold text-indigo-700">See Method and references.</a>
        @else
            {{ \App\Support\AcuityReferences::DISCLAIMER }}
        @endif
    </p>

    {{-- The ward picture --}}
    <div class="ro-avoid flex flex-wrap items-center gap-2 text-xs">
        <span class="ro-staff ro-staff-met bg-gray-100 text-gray-800">{{ $summary['patients'] }} {{ \Illuminate\Support\Str::plural('patient', $summary['patients']) }}</span>
        @foreach ($levels as $level => $info)
            <span class="ro-level ro-level-{{ $level }} {{ $levelTone[$level] }}">Level {{ $level }} {{ $info['short'] }}: {{ $summary['by_level'][$level] }}</span>
        @endforeach
        <span class="ro-staff ro-staff-met bg-gray-100 text-gray-700">Not classified: {{ $summary['unclassified'] }}</span>
        <span class="ro-staff {{ $summary['review_up'] ? 'ro-staff-short bg-amber-100 text-amber-800' : 'ro-staff-met bg-gray-100 text-gray-700' }}">Needs review: {{ $summary['review_up'] }}</span>
        <span class="ro-staff ro-staff-met bg-gray-100 text-gray-700">Rated in the last 24 h: {{ $summary['rated_recently'] }} of {{ $summary['patients'] }} ({{ $ratedShare }}%)</span>
    </div>

    @if ($options['staffing'])
        {{-- Nurses per shift for the patients on the ward now --}}
        <section class="ro-avoid">
            <h4 class="mb-1 text-xs font-bold uppercase tracking-wider text-gray-700">Staffing by patient acuity</h4>
            <table class="ro-table text-xs">
                <thead>
                    <tr>
                        <th class="text-left">Shift</th>
                        <th class="text-right">Care hours</th>
                        <th class="text-right">Nurses (exact)</th>
                        <th class="text-center">Registered nurses needed</th>
                        <th class="text-right">Patients per nurse</th>
                        <th class="text-center">Minimum (staffing rules)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($codes as $code)
                        @php $shift = $staffing['shifts'][$code]; @endphp
                        <tr>
                            <td class="whitespace-nowrap">
                                <span class="font-semibold">{{ $code }}</span>
                                <span class="ro-muted text-gray-500">{{ $snapshot['shifts'][$code]['time'] }}</span>
                            </td>
                            <td class="text-right">{{ $fmt($shift['care_hours']) }} h over {{ $fmt($shift['shift_hours']) }} h</td>
                            <td class="text-right">{{ $fmt($shift['exact']) }}</td>
                            <td class="text-center text-sm font-bold">{{ $shift['recommended'] }}</td>
                            <td class="text-right">{{ $shift['patients_per_nurse'] !== null ? $fmt($shift['patients_per_nurse']) : '-' }}</td>
                            <td class="text-center">{{ $snapshot['rules']['min_staff'][$code] ?? 0 }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="mt-1 text-xs text-gray-600">
                {{ $fmt($staffing['care_hours']) }} h of nursing care in 24 h for {{ $staffing['patients'] }} {{ \Illuminate\Support\Str::plural('patient', $staffing['patients']) }}
                ({{ $fmt($staffing['hours_per_patient']) }} h per patient), shared
                @foreach ($codes as $code){{ $code }} {{ $fmt($acuity['settings']->share($code)) }}%{{ $loop->last ? '' : ', ' }}@endforeach.
                A patient not yet classified counts at the suggested level.
            </p>
        </section>

        {{-- The week on the roster against it --}}
        <section class="ro-avoid">
            <h4 class="mb-1 text-xs font-bold uppercase tracking-wider text-gray-700">
                Week of {{ $firstWeek['start']->format('j M') }}: registered nurses rostered / needed
            </h4>
            <table class="ro-table text-xs">
                <thead>
                    <tr>
                        <th class="text-left">Shift</th>
                        @foreach ($firstWeek['dates'] as $date)
                            @php $holiday = $firstWeek['holidays'][$date->toDateString()] ?? null; @endphp
                            <th class="text-center {{ $date->toDateString() === $snapshot['today'] ? 'text-indigo-800' : '' }}">
                                {{ $date->format('D j') }}
                                @if ($holiday)
                                    <div class="ro-small font-semibold normal-case tracking-normal text-rose-700">{{ $holiday }}</div>
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($codes as $code)
                        <tr>
                            <td class="font-semibold">{{ $code }}</td>
                            @foreach ($firstWeek['dates'] as $date)
                                @php $cell = $acuity['week'][$date->toDateString()][$code] ?? null; @endphp
                                <td class="text-center">
                                    @if ($cell && $cell['recommended'] !== null)
                                        <span class="ro-staff {{ $weekTone[$cell['status']] }}">{{ $cell['registered'] }}/{{ $cell['recommended'] }}{{ $cell['status'] === 'short' ? ' !' : '' }}</span>
                                        <div class="ro-small ro-muted text-gray-500">
                                            min {{ $cell['min'] }}{{ $cell['rostered'] !== $cell['registered'] ? ' · ' . $cell['rostered'] . ' on duty' : '' }}
                                        </div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="mt-1 text-xs text-gray-600">
                <span class="font-semibold {{ $acuity['short_shifts'] ? 'text-red-700' : '' }}">{{ $acuity['short_shifts'] }} {{ \Illuminate\Support\Str::plural('shift', $acuity['short_shifts']) }} short</span>
                of the registered nurses the patients need (red and !). Projected from the patients on the ward now. Students and
                care assistants are on duty but not counted. The minimum in the staffing rules stays the floor: a fixed minimum does
                not follow the patients' changing needs ({{ \App\Support\AcuityReferences::RATIOS_CITE }}).
            </p>
        </section>
    @endif

    {{-- Each patient --}}
    <section>
        <h4 class="mb-1 text-xs font-bold uppercase tracking-wider text-gray-700">Patients</h4>
        <table class="ro-table text-xs">
            <thead>
                <tr>
                    <th class="text-left">Bed</th>
                    @if ($showPatients)
                        <th class="text-left">Patient</th>
                    @endif
                    <th class="text-left">Suggested</th>
                    <th class="text-left">Rating</th>
                    <th class="text-left">Status</th>
                    @if ($showReasons)
                        <th class="text-left">Indicators</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($acuity['rows'] as $row)
                    @php [$statusLabel, $statusTone] = $statusBadge($row); @endphp
                    <tr class="{{ $row['occupied'] ? '' : 'ro-muted text-gray-400' }}">
                        <td class="whitespace-nowrap font-semibold">{{ $row['bed'] }}</td>
                        @if ($showPatients)
                            <td>
                                @if ($row['occupied'])
                                    <div class="font-medium text-gray-900">{{ $row['patient'] }}</div>
                                    @if ($row['mrn'])
                                        <div class="ro-small ro-muted text-gray-500">{{ $row['mrn'] }}</div>
                                    @endif
                                @else
                                    Empty
                                @endif
                            </td>
                        @endif
                        @if ($row['occupied'])
                            <td class="whitespace-nowrap">
                                <span class="ro-level ro-level-{{ $row['suggested'] }} {{ $levelTone[$row['suggested']] }}">Level {{ $row['suggested'] }}</span>
                            </td>
                            <td class="whitespace-nowrap">
                                @if ($row['classified'])
                                    <span class="ro-level ro-level-{{ $row['classified'] }} {{ $levelTone[$row['classified']] }}">Level {{ $row['classified'] }} {{ $levels[$row['classified']]['short'] }}</span>
                                @else
                                    <span class="ro-muted text-gray-500">Not classified</span>
                                @endif
                                @if ($row['rated_at'])
                                    <div class="ro-small ro-muted text-gray-500">
                                        {{ $row['rated_at']->format('j M H:i') }}{{ $row['rated_by'] ? ' by ' . $row['rated_by'] : '' }}
                                    </div>
                                @endif
                            </td>
                            <td><span class="ro-staff {{ $statusTone }}">{{ $statusLabel }}</span></td>
                            @if ($showReasons)
                                <td>
                                    @forelse ($row['reasons'] as $reason)
                                        <span class="whitespace-nowrap">{{ $reason['label'] }} <span class="ro-muted text-gray-500">(L{{ $reason['level'] }})</span>{{ $loop->last ? '' : ',' }}</span>
                                    @empty
                                        <span class="ro-muted text-gray-500">No indicators</span>
                                    @endforelse
                                    @foreach ($row['notes'] as $note)
                                        <div class="ro-small font-semibold text-gray-700">{{ $note }}</div>
                                    @endforeach
                                </td>
                            @endif
                        @else
                            <td colspan="{{ $showReasons ? 4 : 3 }}">{{ $showPatients ? '' : 'Empty' }}</td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-4 text-center text-gray-500">This ward has no beds that need a nurse.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    @if ($options['legend'] && !$options['references'])
        <div class="ro-avoid space-y-1 text-xs text-gray-700">
            @foreach ($levels as $level => $info)
                <div>
                    <span class="ro-level ro-level-{{ $level }} {{ $levelTone[$level] }}">Level {{ $level }} {{ $info['short'] }}</span>
                    {{ $info['description'] }}
                    <span class="ro-muted text-gray-500">({{ $fmt($acuity['settings']->hours($level)) }} h of care a day)</span>
                </div>
            @endforeach
            <div class="ro-muted text-gray-600">
                Suggested: from the indicators the system records. Rating: the nurse's level, which stands. A rating that differs is
                flagged for review.
            </div>
        </div>
    @endif

    @if ($options['references'])
        <section id="acuity-references" class="page-break space-y-3">
            <h4 class="text-sm font-bold text-gray-900">Method and references</h4>
            @include('wards.ai-schedule.partials.acuity-method', ['acuitySettings' => $acuity['settings'], 'shifts' => $snapshot['shifts'], 'compact' => $options['compact']])
            @include('wards.ai-schedule.partials.acuity-references', ['compact' => $options['compact']])
        </section>
    @endif
</div>
