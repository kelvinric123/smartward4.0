{{--
    Read-only leave and holiday report (LeaveReport) for a month, a quarter
    or a year: the period in figures, leave by nurse and type, who worked
    each public holiday, an availability calendar, every leave, and the
    requests from the nurse app. Reads only $report and $options and links
    nowhere. Leave types always show (this report is about leave); notes only
    when the report was built with them.

    Needs: $report (LeaveReport::build).
    Optional $options: mono, calendar, entries, requests (sections).
--}}
@php
    $options = array_merge(['mono' => false, 'calendar' => true, 'entries' => true, 'requests' => true], $options ?? []);
    $totals = $report['totals'];
    $types = $report['types'];
    $usedTypes = array_filter($types, fn (array $type, string $key) => ($totals['by_type'][$key] ?? 0) > 0, ARRAY_FILTER_USE_BOTH);
    $codes = \App\Services\NurseScheduling\WardShifts::CODES;
    $leaveTone = fn (?string $type) => in_array($type, ['medical', 'emergency'], true)
        ? 'bg-rose-100 text-rose-800 border-rose-200'
        : 'bg-emerald-100 text-emerald-800 border-emerald-200';
    $requestTone = [
        'pending' => 'ro-staff-short bg-amber-100 text-amber-800',
        'awaiting_colleague' => 'ro-staff-over bg-sky-100 text-sky-800',
        'approved' => 'ro-staff-met bg-emerald-100 text-emerald-800',
        'declined' => 'ro-staff-met bg-rose-100 text-rose-800',
        'cancelled' => 'ro-staff-met bg-gray-100 text-gray-600',
    ];
    $plural = fn (int $count, string $word) => $count . ' ' . \Illuminate\Support\Str::plural($word, $count);
    $dateRange = fn ($start, $end) => $start->equalTo($end) ? $start->format('D j M Y') : $start->format('j M') . ' – ' . $end->format('j M Y');
@endphp

@once
    @include('wards.ai-schedule.readonly.report-styles')
@endonce

<div class="ro-leave-report space-y-4">
    {{-- The period in figures --}}
    <section class="rp-tiles">
        <div class="rp-tile">
            <div class="rp-tile-label">Leave days</div>
            <div class="rp-tile-value">{{ $totals['days'] }}</div>
            <div class="rp-tile-note">{{ $plural($totals['entries'], 'leave') }} in the period</div>
        </div>
        <div class="rp-tile">
            <div class="rp-tile-label">Nurses with leave</div>
            <div class="rp-tile-value">{{ $totals['nurses_with_leave'] }} <span class="text-xs font-semibold text-gray-600">of {{ $report['team'] }}</span></div>
            <div class="rp-tile-note">Average {{ $totals['average_days'] ?? 0 }} days per nurse</div>
        </div>
        <div class="rp-tile">
            <div class="rp-tile-label">Public holidays</div>
            <div class="rp-tile-value">{{ $totals['holidays'] }}</div>
            <div class="rp-tile-note">{{ $plural($totals['holiday_shifts'], 'shift') }} worked on them</div>
        </div>
        <div class="rp-tile {{ $totals['busy_days'] ? 'rp-tile-warn' : '' }}">
            <div class="rp-tile-label">Days with many away</div>
            <div class="rp-tile-value">{{ $totals['busy_days'] }}{{ $totals['busy_days'] ? ' !' : '' }}</div>
            <div class="rp-tile-note">{{ $report['busy_percent'] }}% or more of the team on leave</div>
        </div>
        @foreach ($usedTypes as $key => $type)
            <div class="rp-tile">
                <div class="rp-tile-label">{{ $type['code'] }} &middot; {{ $type['label'] }}</div>
                <div class="rp-tile-value">{{ $totals['by_type'][$key] }}</div>
                <div class="rp-tile-note">{{ \Illuminate\Support\Str::plural('day', $totals['by_type'][$key]) }}</div>
            </div>
        @endforeach
    </section>

    {{-- Each nurse's leave by type --}}
    <section>
        <h3 class="rp-section-title">Leave days per nurse</h3>
        <table class="ro-table text-xs">
            <thead>
                <tr>
                    <th class="text-left">Nurse</th>
                    @foreach ($usedTypes as $key => $type)
                        <th class="text-right" title="{{ $type['label'] }}">{{ $type['code'] }}</th>
                    @endforeach
                    <th class="text-right">Total days</th>
                    <th class="text-right">Leaves</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($report['nurses'] as $nurse)
                    <tr>
                        <td class="whitespace-nowrap">
                            <span class="font-semibold text-gray-900">{{ $nurse['name'] }}</span>
                            @if ($nurse['designation'])
                                <span class="ro-small ro-muted text-gray-500">{{ $nurse['designation'] }}</span>
                            @endif
                        </td>
                        @foreach ($usedTypes as $key => $type)
                            <td class="rp-num">{{ $nurse['by_type'][$key] ?: '' }}</td>
                        @endforeach
                        <td class="rp-num font-semibold">{{ $nurse['days'] ?: '–' }}</td>
                        <td class="rp-num">{{ $nurse['entries'] ?: '' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($usedTypes) + 3 }}" class="py-4 text-center text-gray-500">No nurses on this ward's team.</td>
                    </tr>
                @endforelse
            </tbody>
            <tbody>
                <tr class="rp-total">
                    <th class="text-left">All nurses</th>
                    @foreach ($usedTypes as $key => $type)
                        <td class="rp-num">{{ $totals['by_type'][$key] }}</td>
                    @endforeach
                    <td class="rp-num">{{ $totals['days'] }}</td>
                    <td class="rp-num">{{ $totals['entries'] }}</td>
                </tr>
            </tbody>
        </table>
        <p class="mt-1 text-xs text-gray-600">
            Days inside the period, weekends and holidays included, as leave is booked. A day under two leaves counts once, under the
            one that starts first.
            @foreach ($usedTypes as $type){{ $type['code'] }} {{ $type['label'] }}{{ $loop->last ? '.' : ', ' }}@endforeach
        </p>
    </section>

    {{-- Public holidays: who worked them and who was away --}}
    <section class="ro-avoid">
        <h3 class="rp-section-title">Public holidays</h3>
        @if ($report['holidays'])
            <table class="ro-table text-xs">
                <thead>
                    <tr>
                        <th class="text-left">Date</th>
                        <th class="text-left">Holiday</th>
                        <th class="text-left">Worked on this ward</th>
                        <th class="text-left">On leave</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report['holidays'] as $holiday)
                        <tr>
                            <td class="whitespace-nowrap font-semibold">{{ $holiday['date']->format('D j M Y') }}</td>
                            <td>{{ $holiday['name'] }}</td>
                            <td>
                                @forelse ($holiday['worked'] as $worked)
                                    <span class="whitespace-nowrap">{{ $worked['name'] }} <span class="ro-muted text-gray-500">({{ $worked['shift'] }})</span>{{ $loop->last ? '' : ',' }}</span>
                                @empty
                                    <span class="ro-muted text-gray-500">Nobody rostered</span>
                                @endforelse
                            </td>
                            <td>
                                @forelse ($holiday['on_leave'] as $away)
                                    <span class="whitespace-nowrap">{{ $away['name'] }} <span class="ro-muted text-gray-500">({{ $away['code'] }})</span>{{ $loop->last ? '' : ',' }}</span>
                                @empty
                                    <span class="ro-muted text-gray-500">–</span>
                                @endforelse
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="text-xs text-gray-500">No public holidays in this period.</p>
        @endif
    </section>

    @if ($options['calendar'])
        {{-- Who is free each day: on leave, and on duty by shift --}}
        <section class="page-break space-y-3">
            <h3 class="rp-section-title">Availability calendar</h3>
            @foreach ($report['calendar'] as $month)
                @php
                    $monthTypes = collect($month['days'])->flatMap(fn (array $day) => array_keys($day['codes']))->unique()->all();
                    $monthTypes = array_values(array_filter(array_keys($types), fn (string $key) => in_array($key, $monthTypes, true)));
                    $monthHolidays = collect($month['days'])->whereNotNull('holiday');
                @endphp
                <div class="ro-avoid" data-month="{{ $month['days'][0]['date']->format('Y-m') }}">
                    <h4 class="mb-1 text-xs font-bold text-gray-900">{{ $month['label'] }}</h4>
                    <table class="ro-table rp-grid">
                        <thead>
                            <tr>
                                <th class="rp-grid-label">Team of {{ $report['team'] }}</th>
                                @foreach ($month['days'] as $day)
                                    <th class="{{ $day['holiday'] ? 'bg-rose-50' : ($day['weekend'] ? 'bg-slate-50' : '') }}">
                                        <div class="ro-small normal-case tracking-normal">{{ mb_substr($day['date']->format('D'), 0, 2) }}</div>
                                        <div>{{ $day['date']->format('j') }}</div>
                                        @if ($day['holiday'])
                                            <div class="ro-small font-bold text-rose-700">PH</div>
                                        @endif
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <th class="rp-grid-label">On leave</th>
                                @foreach ($month['days'] as $day)
                                    <td class="{{ $day['busy'] ? 'font-bold text-red-800 bg-red-100' : '' }}">
                                        {{ $day['on_leave'] ?: '' }}{{ $day['busy'] ? '!' : '' }}
                                    </td>
                                @endforeach
                            </tr>
                            @foreach ($monthTypes as $key)
                                <tr>
                                    <th class="rp-grid-label font-normal">
                                        <span class="ro-chip ro-chip-leave {{ $leaveTone($key) }}">{{ $types[$key]['code'] }}</span>
                                    </th>
                                    @foreach ($month['days'] as $day)
                                        <td class="ro-muted text-gray-600">{{ $day['codes'][$key] ?? '' }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                            @foreach ($codes as $code)
                                <tr class="{{ $loop->first ? 'border-t-2 border-gray-400' : '' }}">
                                    <th class="rp-grid-label font-normal">On duty {{ $code }}</th>
                                    @foreach ($month['days'] as $day)
                                        <td>{{ $day['rostered'][$code] ?: '' }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if ($monthHolidays->isNotEmpty())
                        <p class="mt-0.5 text-xs text-gray-600">
                            PH:
                            @foreach ($monthHolidays as $day){{ $day['date']->format('j M') }} {{ $day['holiday'] }}{{ $loop->last ? '.' : '; ' }}@endforeach
                        </p>
                    @endif
                </div>
            @endforeach
            <p class="text-xs text-gray-600">
                On leave: nurses of the team away that day (red and ! when {{ $report['busy_percent'] }}% or more of the
                {{ $report['team'] }} are). On duty: nurses on the roster for this ward, by shift; empty where nobody is rostered yet.
            </p>
        </section>
    @endif

    @if ($options['entries'])
        <section class="page-break">
            <h3 class="rp-section-title">Every leave in the period</h3>
            <table class="ro-table text-xs">
                <thead>
                    <tr>
                        <th class="text-left">Nurse</th>
                        <th class="text-left">Type</th>
                        <th class="text-left">Dates</th>
                        <th class="text-right">Days in period</th>
                        <th class="text-left">Booked</th>
                        @if ($report['notes'])
                            <th class="text-left">Note</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['entries'] as $entry)
                        <tr>
                            <td class="whitespace-nowrap font-semibold text-gray-900">{{ $entry['nurse'] }}</td>
                            <td class="whitespace-nowrap">
                                <span class="ro-chip ro-chip-leave {{ $leaveTone($entry['type']) }}">{{ $entry['code'] }}</span>
                                {{ $entry['label'] }}
                            </td>
                            <td class="whitespace-nowrap">{{ $dateRange($entry['start'], $entry['end']) }}</td>
                            <td class="rp-num">
                                {{ $entry['days'] }}
                                @if ($entry['clipped'])
                                    <span class="ro-small ro-muted text-gray-500">of {{ $entry['total_days'] }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap">
                                {{ $entry['booked_by'] ?? '–' }}
                                @if ($entry['booked_at'])
                                    <span class="ro-small ro-muted text-gray-500">{{ $entry['booked_at']->format('j M Y') }}</span>
                                @endif
                            </td>
                            @if ($report['notes'])
                                <td>{{ $entry['note'] }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $report['notes'] ? 6 : 5 }}" class="py-4 text-center text-gray-500">No leave in this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            @unless ($report['notes'])
                <p class="mt-1 text-xs text-gray-600">Leave notes are left out: they can hold health details.</p>
            @endunless
        </section>
    @endif

    @if ($options['requests'])
        <section class="{{ $options['entries'] ? 'mt-6' : 'page-break' }}">
            <h3 class="rp-section-title">Requests from the nurse app</h3>
            <table class="ro-table text-xs">
                <thead>
                    <tr>
                        <th class="text-left">Nurse</th>
                        <th class="text-left">Request</th>
                        <th class="text-left">Asked</th>
                        <th class="text-left">Status</th>
                        <th class="text-left">Decided</th>
                        @if ($report['notes'])
                            <th class="text-left">Notes</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['requests'] as $ask)
                        <tr>
                            <td class="whitespace-nowrap font-semibold text-gray-900">{{ $ask['nurse'] }}</td>
                            <td><span class="font-semibold">{{ $ask['type'] }}:</span> {{ $ask['summary'] }}</td>
                            <td class="whitespace-nowrap">{{ $ask['asked_at']?->format('j M Y') ?? '–' }}</td>
                            <td class="whitespace-nowrap"><span class="ro-staff {{ $requestTone[$ask['status']] ?? 'ro-staff-met bg-gray-100 text-gray-600' }}">{{ $ask['status_label'] }}</span></td>
                            <td class="whitespace-nowrap">
                                @if ($ask['decided_at'])
                                    {{ $ask['decided_at']->format('j M Y') }}{{ $ask['decided_by'] ? ' by ' . $ask['decided_by'] : '' }}
                                @else
                                    <span class="ro-muted text-gray-500">–</span>
                                @endif
                            </td>
                            @if ($report['notes'])
                                <td>
                                    {{ $ask['note'] }}
                                    @if ($ask['decision_note'])
                                        <div class="ro-small ro-muted text-gray-600">Decision: {{ $ask['decision_note'] }}</div>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $report['notes'] ? 6 : 5 }}" class="py-4 text-center text-gray-500">No requests from the nurse app for this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    @endif
</div>
