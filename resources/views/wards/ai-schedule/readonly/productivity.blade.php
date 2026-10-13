{{--
    Read-only monthly productivity report (ProductivityReport): the month's
    figures as tiles, then each day's patients, care hours needed and nurse
    hours rostered, each nurse's month, and how every figure is worked out.
    Reads only $report and $options and links nowhere.

    Needs: $report (ProductivityReport::build).
    Optional $options: mono, daily (the day-by-day table), nurses (per nurse),
    definitions (how the figures are worked out, with the ANA sources).
--}}
@php
    $options = array_merge(['mono' => false, 'daily' => true, 'nurses' => true, 'definitions' => true], $options ?? []);
    $totals = $report['totals'];
    $codes = \App\Services\NurseScheduling\WardShifts::CODES;
    $num = fn ($value) => $value === null ? '–' : rtrim(rtrim(number_format((float) $value, 1), '0'), '.');
    $shortTone = 'ro-staff-short bg-red-100 text-red-800';
    $okTone = 'ro-staff-met bg-emerald-100 text-emerald-800';
    $variance = fn (float $value) => match (true) {
        $value > 0 => ['+' . $num($value) . ' extra', 'ro-staff-short bg-amber-100 text-amber-800'],
        $value < 0 => [$num($value) . ' under', 'ro-staff-over bg-sky-100 text-sky-800'],
        default => ['On target', $okTone],
    };
    $sources = \App\Support\AcuityReferences::SOURCES;
    // The month's figures run to today; the days after it are projected and added up apart
    $projected = $totals['projected'];
    $toDate = $projected['days'] ? 'to ' . \Illuminate\Support\Carbon::parse($report['today'])->format('j M') : null;
    $sourceLabel = fn (array $day) => match ($day['source']) {
        'rated' => 'rated',
        'partly' => $day['rated_patients'] . ' of ' . $day['census'] . ' rated',
        default => 'est.',
    };
@endphp

@once
    @include('wards.ai-schedule.readonly.report-styles')
@endonce

<div class="ro-productivity space-y-4">
    {{-- The month in figures --}}
    <section class="rp-tiles">
        <div class="rp-tile">
            <div class="rp-tile-label">Patient days</div>
            <div class="rp-tile-value">{{ number_format($totals['patient_days']) }}</div>
            <div class="rp-tile-note">Average {{ $num($totals['average_census']) }} a day{{ $toDate ? ' · ' . $totals['days'] . ' days ' . $toDate : '' }}</div>
        </div>
        <div class="rp-tile">
            <div class="rp-tile-label">Admissions / discharges</div>
            <div class="rp-tile-value">{{ $totals['admissions'] }} / {{ $totals['discharges'] }}</div>
            <div class="rp-tile-note">Transfers in {{ $totals['transfers_in'] }} / out {{ $totals['transfers_out'] }} &middot; from the admission log</div>
        </div>
        <div class="rp-tile">
            <div class="rp-tile-label">Care hours needed</div>
            <div class="rp-tile-value">{{ number_format($totals['required_hours'], 1) }} h</div>
            <div class="rp-tile-note">
                Rated {{ $totals['days_rated'] }} {{ \Illuminate\Support\Str::plural('day', $totals['days_rated']) }}{{ $totals['days_partly_rated'] ? ', partly ' . $totals['days_partly_rated'] : '' }}, estimated {{ $totals['days_estimated'] }}
            </div>
        </div>
        <div class="rp-tile">
            <div class="rp-tile-label">RN hours rostered</div>
            <div class="rp-tile-value">{{ number_format($totals['rn_hours'], 1) }} h</div>
            <div class="rp-tile-note">All nursing staff {{ number_format($totals['all_hours'], 1) }} h</div>
        </div>
        <div class="rp-tile">
            <div class="rp-tile-label">Skill mix</div>
            <div class="rp-tile-value">{{ $totals['rn_share'] !== null ? $totals['rn_share'] . '%' : '–' }}</div>
            <div class="rp-tile-note">Of the hours rostered are registered nurses'</div>
        </div>
        <div class="rp-tile">
            <div class="rp-tile-label">Hours per patient day</div>
            <div class="rp-tile-value">{{ $num($totals['actual_hppd']) }} <span class="text-xs font-semibold text-gray-600">/ {{ $num($totals['target_hppd']) }} needed</span></div>
            <div class="rp-tile-note">Registered nurse hours</div>
        </div>
        <div class="rp-tile {{ ($totals['productivity'] ?? 0) > 100 ? 'rp-tile-alert' : '' }}">
            <div class="rp-tile-label">Productivity</div>
            <div class="rp-tile-value">{{ $totals['productivity'] !== null ? $totals['productivity'] . '%' : '–' }}{{ ($totals['productivity'] ?? 0) > 100 ? ' !' : '' }}</div>
            <div class="rp-tile-note">Needed / rostered RN hours{{ ($totals['productivity'] ?? 0) > 100 ? ': rostered below the need' : '' }}</div>
        </div>
        <div class="rp-tile {{ $totals['shifts_below_min'] ? 'rp-tile-alert' : '' }}">
            <div class="rp-tile-label">Below the minimum</div>
            <div class="rp-tile-value">{{ $totals['shifts_below_min'] }} <span class="text-xs font-semibold text-gray-600">of {{ $totals['shifts'] }} shifts</span></div>
            <div class="rp-tile-note">Fewer on duty than the staffing rules</div>
        </div>
        <div class="rp-tile {{ $totals['shifts_short'] ? 'rp-tile-warn' : '' }}">
            <div class="rp-tile-label">Short of acuity</div>
            <div class="rp-tile-value">{{ $totals['shifts_short'] }} <span class="text-xs font-semibold text-gray-600">of {{ $totals['shifts'] }} shifts</span></div>
            <div class="rp-tile-note">Fewer RNs than the patients needed</div>
        </div>
        <div class="rp-tile">
            <div class="rp-tile-label">Public holidays</div>
            <div class="rp-tile-value">{{ $totals['holidays'] }}</div>
            <div class="rp-tile-note">
                @forelse ($report['holidays'] as $holiday)
                    {{ $holiday['date']->format('j M') }} {{ $holiday['name'] }}{{ $loop->last ? '' : ';' }}
                @empty
                    None this month
                @endforelse
            </div>
        </div>
        @if ($projected['days'])
            <div class="rp-tile">
                <div class="rp-tile-label">Rest of the month</div>
                <div class="rp-tile-value">{{ $projected['days'] }} <span class="text-xs font-semibold text-gray-600">{{ \Illuminate\Support\Str::plural('day', $projected['days']) }} projected</span></div>
                <div class="rp-tile-note">
                    Not in the figures here. At today's patients {{ $num($projected['required_hours']) }} care h needed, {{ $num($projected['rn_hours']) }} RN h
                    rostered; roster built for {{ $projected['days_rostered'] }} of them
                </div>
            </div>
        @endif
    </section>

    @if ($options['daily'])
        <section>
            <h3 class="rp-section-title">Day by day</h3>
            <p class="mb-1 text-xs text-gray-600">
                Census: patients at midnight; "projected" days carry today's count and are not in the month's figures. In / Out:
                admissions and transfers from other wards / discharges and transfers to them. Care h per patient: "rated" from the
                nurses' acuity ratings that day, "n of N rated" with today's care levels for the patients not rated, "est." from today's
                care levels ({{ $num($report['estimate']['hours_per_patient']) }} h). RN / needed: registered nurses rostered against
                those the care hours call for (red and ! when short); below it, everyone on duty against the minimum in the staffing
                rules. Productivity: care hours needed / RN hours rostered; above 100% (red and !) the rostered registered nurses had
                fewer hours than the patients needed.
            </p>
            <table class="ro-table rp-dense text-xs">
                <thead>
                    <tr>
                        <th class="text-left">Date</th>
                        <th class="text-right">Census</th>
                        <th class="text-right">In</th>
                        <th class="text-right">Out</th>
                        <th class="text-right">Care h per patient</th>
                        <th class="text-right">Care h needed</th>
                        <th class="text-right">RN h</th>
                        <th class="text-right">All h</th>
                        <th class="text-right">RN h per patient</th>
                        <th class="text-right">Productivity</th>
                        @foreach ($codes as $code)
                            <th class="text-center">{{ $code }} RN / needed</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report['days'] as $day)
                        <tr class="{{ $day['holiday'] ? 'bg-rose-50' : ($day['weekend'] ? 'bg-slate-50' : '') }}" data-day="{{ $day['day'] }}">
                            <td class="whitespace-nowrap">
                                <span class="font-semibold">{{ $day['date']->format('D j') }}</span>
                                @if ($day['is_today'])
                                    <span class="ro-small font-semibold text-indigo-800">today</span>
                                @elseif ($day['projected'])
                                    <span class="ro-small ro-muted text-gray-500">projected</span>
                                @endif
                                @if ($day['holiday'])
                                    <div class="ro-small font-semibold text-rose-700">{{ $day['holiday'] }}</div>
                                @endif
                            </td>
                            <td class="rp-num font-semibold">{{ $day['census'] }}</td>
                            <td class="rp-num">{{ ($day['admissions'] + $day['transfers_in']) ?: '' }}</td>
                            <td class="rp-num">{{ ($day['discharges'] + $day['transfers_out']) ?: '' }}</td>
                            <td class="rp-num">
                                {{ $num($day['hours_per_patient']) }}
                                <span class="ro-small ro-muted text-gray-500">{{ $sourceLabel($day) }}</span>
                            </td>
                            <td class="rp-num">{{ $num($day['required_hours']) }}</td>
                            <td class="rp-num">{{ $num($day['rn_hours']) }}</td>
                            <td class="rp-num">{{ $num($day['all_hours']) }}</td>
                            <td class="rp-num">{{ $num($day['actual_hppd']) }}</td>
                            <td class="rp-num">
                                @if ($day['productivity'] === null)
                                    <span class="ro-muted text-gray-400">–</span>
                                @else
                                    <span class="ro-staff {{ $day['productivity'] > 100 ? $shortTone : 'ro-staff-met' }}">{{ $day['productivity'] }}%{{ $day['productivity'] > 100 ? ' !' : '' }}</span>
                                @endif
                            </td>
                            @if ($day['projected'] && !$day['rostered'])
                                {{-- Not rostered yet: nothing to set against the patients, so no shortfall either --}}
                                <td colspan="{{ count($codes) }}" class="text-center ro-muted text-gray-500">No roster yet</td>
                            @else
                                @foreach ($codes as $code)
                                    @php $shift = $day['shifts'][$code]; @endphp
                                    <td class="text-center">
                                        <span class="ro-staff {{ $shift['short'] ? $shortTone : 'ro-staff-met' }}">RN {{ $shift['registered'] }}/{{ $shift['recommended'] }}{{ $shift['short'] ? ' !' : '' }}</span>
                                        <div class="ro-small whitespace-nowrap {{ $shift['below_min'] ? 'font-bold text-red-700' : 'ro-muted text-gray-500' }}">
                                            {{ $shift['count'] }} on, min {{ $shift['min'] }}{{ $shift['below_min'] ? ': below' : '' }}
                                        </div>
                                    </td>
                                @endforeach
                            @endif
                        </tr>
                    @endforeach
                </tbody>
                {{-- A second body, not a tfoot: a printed tfoot repeats at the foot of every page --}}
                <tbody>
                    <tr class="rp-total">
                        <th class="text-left">{{ $toDate ? 'Month ' . $toDate : 'Month' }}</th>
                        <td class="rp-num">{{ number_format($totals['patient_days']) }}</td>
                        <td class="rp-num">{{ $totals['admissions'] + $totals['transfers_in'] }}</td>
                        <td class="rp-num">{{ $totals['discharges'] + $totals['transfers_out'] }}</td>
                        <td class="rp-num">{{ $num($totals['target_hppd']) }}</td>
                        <td class="rp-num">{{ $num($totals['required_hours']) }}</td>
                        <td class="rp-num">{{ $num($totals['rn_hours']) }}</td>
                        <td class="rp-num">{{ $num($totals['all_hours']) }}</td>
                        <td class="rp-num">{{ $num($totals['actual_hppd']) }}</td>
                        <td class="rp-num">{{ $totals['productivity'] !== null ? $totals['productivity'] . '%' : '–' }}</td>
                        <td colspan="{{ count($codes) }}" class="text-center">
                            {{ $totals['shifts_short'] }} short of acuity &middot; {{ $totals['shifts_below_min'] }} below the minimum
                        </td>
                    </tr>
                    @if ($projected['days'])
                        <tr>
                            <th class="text-left">Rest of the month <span class="ro-small ro-muted text-gray-500">projected</span></th>
                            <td class="rp-num">{{ number_format($projected['patient_days']) }}</td>
                            <td colspan="3"></td>
                            <td class="rp-num">{{ $num($projected['required_hours']) }}</td>
                            <td class="rp-num">{{ $num($projected['rn_hours']) }}</td>
                            <td class="rp-num">{{ $num($projected['all_hours']) }}</td>
                            <td colspan="{{ 2 + count($codes) }}" class="ro-muted text-gray-500">
                                At today's patients; roster built for {{ $projected['days_rostered'] }} of {{ $projected['days'] }} days. Not in the month's figures.
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </section>
    @endif

    @if ($options['nurses'])
        <section class="{{ $options['daily'] ? 'page-break' : '' }}">
            <h3 class="rp-section-title">Per nurse</h3>
            <table class="ro-table text-xs">
                <thead>
                    <tr>
                        <th class="text-left">Nurse</th>
                        <th class="text-right">Shifts</th>
                        <th class="text-right">AM</th>
                        <th class="text-right">PM</th>
                        <th class="text-right">ON (nights)</th>
                        <th class="text-right">Hours</th>
                        <th class="text-right">Weekend</th>
                        <th class="text-right">Holiday</th>
                        <th class="text-right">Leave days</th>
                        <th class="text-right">Target</th>
                        <th class="text-left">Against target</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['nurses'] as $nurse)
                        @php [$varianceLabel, $varianceTone] = $variance($nurse['variance']); @endphp
                        <tr>
                            <td class="whitespace-nowrap">
                                <div class="font-semibold text-gray-900">{{ $nurse['name'] }}</div>
                                <div class="ro-small ro-muted text-gray-500">
                                    {{ $nurse['designation'] ?: 'No designation' }}{{ $nurse['registered'] ? '' : ' · not counted as RN' }}
                                </div>
                            </td>
                            <td class="rp-num font-semibold">
                                {{ $nurse['shifts'] }}
                                @if ($nurse['other_ward'])
                                    <div class="ro-small ro-muted text-gray-500">{{ $nurse['other_ward'] }} on other wards</div>
                                @endif
                            </td>
                            <td class="rp-num">{{ $nurse['AM'] }}</td>
                            <td class="rp-num">{{ $nurse['PM'] }}</td>
                            <td class="rp-num">{{ $nurse['ON'] }}</td>
                            <td class="rp-num">{{ $num($nurse['hours']) }}</td>
                            <td class="rp-num">{{ $nurse['weekend'] }}</td>
                            <td class="rp-num">{{ $nurse['holiday'] }}</td>
                            <td class="rp-num">{{ $nurse['leave'] ?: '–' }}</td>
                            <td class="rp-num">{{ $num($nurse['expected']) }}</td>
                            <td><span class="ro-staff {{ $varianceTone }}">{{ $varianceLabel }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="py-4 text-center text-gray-500">No nurses on this month's roster yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            @php $targetRules = $report['rules']; @endphp
            <p class="mt-1 text-xs text-gray-600">
                Target: {{ $targetRules['target_shifts_per_week'] }} shifts a week over
                @if ($targetRules['target_days'] === $report['days_in_month'])
                    the month's {{ $report['days_in_month'] }} days
                @elseif ($targetRules['target_days'])
                    the {{ $targetRules['target_days'] }} days to {{ $targetRules['target_through']->format('j M') }} (to today, or as far as the roster is built)
                @else
                    no days yet (the month has not started and has no roster)
                @endif
                is {{ $num($targetRules['target_shifts']) }} shifts; each leave day takes off its share ({{ $targetRules['target_shifts_per_week'] }}/7 of a
                shift), as leave counts weekends too. Shifts and leave are counted over the same days. Shifts above the target are extra shifts
                (the nearest SmartWard has to overtime); a shift on another ward counts.
            </p>
        </section>
    @endif

    @if ($options['definitions'])
        <section class="{{ $options['daily'] || $options['nurses'] ? 'page-break' : '' }} space-y-2 text-xs text-gray-700">
            <h3 class="rp-section-title">How the figures are worked out</h3>
            <dl class="grid grid-cols-1 gap-x-6 gap-y-1.5 md:grid-cols-2">
                <div class="ro-avoid">
                    <dt class="font-semibold text-gray-900">Census and patient days</dt>
                    <dd>The midnight census, worked back from the patients in the ward's beds now through the admission log: each midnight is the
                        next day's count less that day's admissions and transfers in, plus its discharges and transfers out (a move between beds
                        on this ward is neither). Days after today are projected at today's count and kept out of the month's figures, which run
                        to today. Patient days add the census.</dd>
                </div>
                <div class="ro-avoid">
                    <dt class="font-semibold text-gray-900">Care hours needed</dt>
                    <dd>Each patient's care hours, added up: for a patient the nurses rated that day, the hours of their last rating that day;
                        for the others, today's mix of care levels. When at least as many were rated as the census (patients come and go in a
                        day), the rated patients' average &times; the census. Hours per level in 24 h, this ward's settings:
                        @foreach ($report['settings']['hours'] as $level => $hours)Level {{ $level }} {{ $num($hours) }} h{{ $loop->last ? '.' : ', ' }}@endforeach
                        ANA gives no hours per level: they are the ward's own.</dd>
                </div>
                <div class="ro-avoid">
                    <dt class="font-semibold text-gray-900">RN hours and all hours</dt>
                    <dd>ROSTERED hours: each nurse on the roster for a shift on this ward &times; the shift's length
                        (@foreach ($report['shifts'] as $code => $shift){{ $code }} {{ $num($shift['hours']) }} h{{ $loop->last ? '' : ', ' }}@endforeach).
                        SmartWard has no time-and-attendance clock, so these are not hours worked. Registered nurses only for RN hours; students
                        and care assistants count in all hours.</dd>
                </div>
                <div class="ro-avoid">
                    <dt class="font-semibold text-gray-900">Hours per patient day</dt>
                    <dd>RN hours / patient days (actual), against care hours needed / patient days (needed). Total hours of nursing care per
                        patient per day is one of the benchmarks a hospital may track (ANA, 2017, p. 24).</dd>
                </div>
                <div class="ro-avoid">
                    <dt class="font-semibold text-gray-900">Productivity</dt>
                    <dd>Care hours needed / RN hours rostered &times; 100. Above 100% the rostered registered nurses had fewer hours than the
                        patients needed; well below 100% they had more. A comparison to watch over months, not a target.</dd>
                </div>
                <div class="ro-avoid">
                    <dt class="font-semibold text-gray-900">Skill mix</dt>
                    <dd>RN hours as a share of all nursing hours rostered. The staffing mix (registered nurses and others) is a benchmark too
                        (ANA, 2017, p. 24).</dd>
                </div>
                <div class="ro-avoid">
                    <dt class="font-semibold text-gray-900">Below the minimum, short of acuity</dt>
                    <dd>Below the minimum: fewer on duty than the staffing rules ask. Short of acuity: fewer registered nurses than the day's
                        care hours call for on that shift (shift shares
                        @foreach ($report['settings']['shares'] as $code => $share){{ $code }} {{ $num($share) }}%{{ $loop->last ? '' : ', ' }}@endforeach,
                        rounded up as on the Patient acuity tab). Counted up to today: a later day may not be rostered yet.</dd>
                </div>
                <div class="ro-avoid">
                    <dt class="font-semibold text-gray-900">Extra shifts</dt>
                    <dd>Shifts above a nurse's target (the weekly target spread over the days counted, less each leave day's share). Extra and
                        overtime hours are a staffing benchmark (ANA, 2017, p. 25); without clock-in data, extra shifts stand in for them.</dd>
                </div>
            </dl>
            <p class="ro-avoid">
                Why measure it: workforce management runs from forecasting through scheduling and staffing to improving, which means
                monitoring, analysing and evaluating staffing to refine the next schedules (ANA, 2017, pp. 4, 8). These figures are
                SmartWard's own, worked out from what it records; they are a planning aid for that review, not a validated measure.
            </p>
            <p class="ro-avoid ro-muted text-gray-600">{{ $sources['ana2017']['citation'] }}</p>
        </section>
    @endif
</div>
