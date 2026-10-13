{{--
    Read-only credentialing and privileging report (CredentialReport): the
    ward's figures as tiles, its skill mix, each nurse's APC, registration,
    certificates and privileges, the list of what to act on, and optionally
    every privilege. Staff data: names, designations, credential titles,
    dates and statuses only, never contact details, ID or document numbers or
    notes. Reads only $report and $options and links nowhere.

    Needs: $report (CredentialReport::build).
    Optional $options: mono, matrix, actions, privileges (sections).
--}}
@php
    $options = array_merge(['mono' => false, 'matrix' => true, 'actions' => true, 'privileges' => false], $options ?? []);
    $summary = $report['summary'];
    $window = $report['window'];
    $actionCounts = $summary['actions'];
    $red = 'ro-staff-short bg-red-100 text-red-800';
    $amber = 'ro-staff-over bg-amber-100 text-amber-800';
    $green = 'ro-staff-met bg-emerald-100 text-emerald-800';
    $grey = 'ro-staff-met bg-gray-100 text-gray-700';
    // Where a credential stands, as words and colour; a certificate nobody needs and the nurse lacks is just not held
    $standing = fn (array $status, bool $needed = true) => match ($status['status']) {
        'expired' => ['Expired', $red],
        'expiring' => ['Expiring', $amber],
        'valid' => ['Valid', $green],
        default => $needed ? ['Missing', $red] : [null, null],
    };
    $when = fn (array $status) => match (true) {
        $status['expires'] === null => $status['status'] === 'valid' ? 'no expiry' : null,
        $status['status'] === 'expired' => 'since ' . $status['expires']->format('j M Y'),
        $status['status'] === 'expiring' => 'in ' . $status['days'] . ' d, ' . $status['expires']->format('j M Y'),
        default => 'to ' . $status['expires']->format('j M Y'),
    };
    $actionTone = [
        'expired' => $red,
        'apc_missing' => $red,
        'missing' => $red,
        'at_risk' => $red,
        'review_overdue' => $red,
        'expiring' => $amber,
        'review_due' => $amber,
        'unverified' => $grey,
    ];
    $days = fn (?int $days, string $type) => match (true) {
        $days === null => '–',
        $days < 0 => abs($days) . ' ' . \Illuminate\Support\Str::plural('day', abs($days)) . ($type === 'review_overdue' ? ' overdue' : ' ago'),
        $days === 0 => 'today',
        default => 'in ' . $days . ' ' . \Illuminate\Support\Str::plural('day', $days),
    };
    $reviewTone = ['overdue' => $red, 'due' => $amber, 'ok' => $grey];
    $privilegeTone = ['granted' => $green, 'supervised' => $amber, 'suspended' => $red, 'withdrawn' => $grey];
@endphp

@once
    @include('wards.ai-schedule.readonly.report-styles')
@endonce

<div class="ro-credentials space-y-4">
    <p class="text-xs text-gray-600">
        Staffing matches nurses to patients on their skill mix, education and competencies (ANA, 2017, pp. 3-4): this is the ward's
        picture of them on {{ $report['today']->format('j M Y') }}. "Expiring" looks {{ $window }} days ahead.
    </p>

    {{-- The ward in figures --}}
    <section class="rp-tiles">
        <div class="rp-tile">
            <div class="rp-tile-label">Nurses</div>
            <div class="rp-tile-value">{{ $summary['nurses'] }}</div>
            <div class="rp-tile-note">{{ $summary['registered'] }} registered{{ $summary['tagging'] ? ' · ' . $summary['tagging'] . ' in orientation' : '' }}</div>
        </div>
        <div class="rp-tile {{ $summary['apc_valid_percent'] !== null && $summary['apc_valid_percent'] < 100 ? 'rp-tile-alert' : '' }}">
            <div class="rp-tile-label">APC in date</div>
            <div class="rp-tile-value">{{ $summary['apc_valid_percent'] !== null ? $summary['apc_valid_percent'] . '%' : '–' }}{{ $summary['apc_valid_percent'] !== null && $summary['apc_valid_percent'] < 100 ? ' !' : '' }}</div>
            <div class="rp-tile-note">{{ $summary['apc_valid'] }} of {{ $summary['registered'] }} registered nurses</div>
        </div>
        <div class="rp-tile {{ $summary['bls_valid_percent'] !== null && $summary['bls_valid_percent'] < 100 ? 'rp-tile-warn' : '' }}">
            <div class="rp-tile-label">BLS in date</div>
            <div class="rp-tile-value">{{ $summary['bls_valid_percent'] !== null ? $summary['bls_valid_percent'] . '%' : '–' }}</div>
            <div class="rp-tile-note">{{ $summary['bls_valid'] }} of {{ $summary['nurses'] }} nurses{{ $actionCounts['missing'] ? ' · ' . $actionCounts['missing'] . ' not on file' : '' }}</div>
        </div>
        <div class="rp-tile">
            <div class="rp-tile-label">Critical care post-basic</div>
            <div class="rp-tile-value">{{ $summary['critical_care'] }}</div>
            <div class="rp-tile-note">Nurses with one on file</div>
        </div>
        <div class="rp-tile {{ $actionCounts['expired'] + $actionCounts['apc_missing'] ? 'rp-tile-alert' : '' }}">
            <div class="rp-tile-label">Expired / no APC</div>
            <div class="rp-tile-value">{{ $actionCounts['expired'] }} / {{ $actionCounts['apc_missing'] }}</div>
            <div class="rp-tile-note">Credentials run out; registered nurses without an APC</div>
        </div>
        <div class="rp-tile {{ $actionCounts['expiring'] ? 'rp-tile-warn' : '' }}">
            <div class="rp-tile-label">Expiring</div>
            <div class="rp-tile-value">{{ $actionCounts['expiring'] }}</div>
            <div class="rp-tile-note">Within {{ $window }} days</div>
        </div>
        <div class="rp-tile {{ $actionCounts['at_risk'] ? 'rp-tile-alert' : '' }}">
            <div class="rp-tile-label">Privileges at risk</div>
            <div class="rp-tile-value">{{ $actionCounts['at_risk'] }}</div>
            <div class="rp-tile-note">Held without the certificate they need, or with one run out</div>
        </div>
        <div class="rp-tile {{ $actionCounts['review_overdue'] ? 'rp-tile-alert' : ($actionCounts['review_due'] ? 'rp-tile-warn' : '') }}">
            <div class="rp-tile-label">Reviews overdue / due</div>
            <div class="rp-tile-value">{{ $actionCounts['review_overdue'] }} / {{ $actionCounts['review_due'] }}</div>
            <div class="rp-tile-note">Privilege reviews, due within {{ $window }} days</div>
        </div>
        <div class="rp-tile">
            <div class="rp-tile-label">Not verified</div>
            <div class="rp-tile-value">{{ $actionCounts['unverified'] }}</div>
            <div class="rp-tile-note">Credentials whose original was not sighted</div>
        </div>
    </section>

    <section class="ro-avoid flex flex-wrap items-center gap-1.5 text-xs">
        <span class="font-bold uppercase tracking-wider text-gray-700">Skill mix</span>
        @forelse ($summary['skill_mix'] as $designation => $count)
            <span class="ro-staff ro-staff-met bg-gray-100 text-gray-800">{{ $designation }}: {{ $count }}</span>
        @empty
            <span class="ro-muted text-gray-500">No nurses on this ward's team.</span>
        @endforelse
    </section>

    @if ($options['matrix'])
        <section>
            <h3 class="rp-section-title">Compliance per nurse</h3>
            <p class="mb-1 text-xs text-gray-600">
                Valid, expiring (within {{ $window }} days), expired, or missing: BLS for everyone on the list, another certificate where a
                privilege the nurse holds needs it; "–" is not held and not needed. A certificate column shows when a nurse here holds or
                needs one; BLS always shows. ! against a name: something has expired, is missing, is at risk or is overdue. Students and care
                assistants hold no APC (n/a).
            </p>
            <table class="ro-table text-xs">
                <thead>
                    <tr>
                        <th class="text-left">Nurse</th>
                        <th class="text-left">APC</th>
                        <th class="text-center">Registration</th>
                        @foreach ($report['certificates'] as $key => $label)
                            <th class="text-left">{{ $label }}</th>
                        @endforeach
                        <th class="text-center">Not verified</th>
                        <th class="text-center">Privileges</th>
                        <th class="text-center">Reviews overdue / due</th>
                        <th class="text-center">At risk</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['nurses'] as $nurse)
                        @php
                            [$apcLabel, $apcTone] = $nurse['registered'] ? $standing($nurse['apc']) : $standing($nurse['apc'], false);
                            $counts = $nurse['privilege_counts'];
                        @endphp
                        <tr data-nurse="{{ $nurse['id'] }}">
                            <td class="whitespace-nowrap">
                                <div class="font-semibold text-gray-900">
                                    {{ $nurse['name'] }}
                                    @if ($nurse['attention'] === 'now')
                                        <span class="font-bold text-red-700">!</span>
                                    @endif
                                </div>
                                <div class="ro-small ro-muted text-gray-500">
                                    {{ $nurse['designation'] ?: 'No designation' }}{{ $nurse['registered'] ? '' : ' · not a registered nurse' }}{{ $nurse['tagging'] ? ' · orientation (tagging)' : '' }}
                                </div>
                            </td>
                            <td class="whitespace-nowrap">
                                @if ($apcLabel)
                                    <span class="ro-staff {{ $apcTone }}">{{ $apcLabel }}</span>
                                    @if ($when($nurse['apc']))
                                        <div class="ro-small ro-muted text-gray-500">{{ $when($nurse['apc']) }}</div>
                                    @endif
                                @else
                                    <span class="ro-muted text-gray-400">n/a</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if ($nurse['registration'])
                                    On file
                                @elseif ($nurse['registered'])
                                    <span class="ro-staff {{ $amber }}">None</span>
                                @else
                                    <span class="ro-muted text-gray-400">–</span>
                                @endif
                            </td>
                            @foreach ($report['certificates'] as $key => $label)
                                @php
                                    $certificate = $nurse['certificates'][$key];
                                    [$certificateLabel, $certificateTone] = $standing($certificate, $certificate['needed']);
                                @endphp
                                <td class="whitespace-nowrap">
                                    @if ($certificateLabel)
                                        <span class="ro-staff {{ $certificateTone }}">{{ $certificateLabel }}</span>
                                        @if ($certificate['status'] === 'missing')
                                            <div class="ro-small ro-muted text-gray-500">{{ $certificate['for_privilege'] ? 'a privilege needs it' : 'everyone needs it' }}</div>
                                        @elseif ($when($certificate))
                                            <div class="ro-small ro-muted text-gray-500">{{ $when($certificate) }}</div>
                                        @endif
                                    @else
                                        <span class="ro-muted text-gray-400">–</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="text-center">{{ $nurse['unverified'] ?: '' }}</td>
                            <td class="whitespace-nowrap text-center">
                                {{ $counts['granted'] }} granted{{ $counts['supervised'] ? ', ' . $counts['supervised'] . ' supervised' : '' }}
                                @if ($counts['suspended'] || $counts['withdrawn'])
                                    <div class="ro-small ro-muted text-gray-500">
                                        {{ collect(['suspended' => $counts['suspended'], 'withdrawn' => $counts['withdrawn']])->filter()->map(fn ($count, $status) => $count . ' ' . $status)->implode(', ') }}
                                    </div>
                                @endif
                            </td>
                            <td class="text-center">
                                @if ($nurse['review_overdue'] || $nurse['review_due'])
                                    <span class="ro-staff {{ $nurse['review_overdue'] ? $red : $amber }}">{{ $nurse['review_overdue'] }} / {{ $nurse['review_due'] }}</span>
                                @else
                                    <span class="ro-muted text-gray-400">–</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if ($nurse['at_risk'])
                                    <span class="ro-staff {{ $red }}">{{ $nurse['at_risk'] }} at risk</span>
                                @else
                                    <span class="ro-muted text-gray-400">–</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($report['certificates']) + 7 }}" class="py-4 text-center text-gray-500">No nurses on this ward's team.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    @endif

    @if ($options['actions'])
        <section class="{{ $options['matrix'] ? 'mt-6' : '' }}">
            <h3 class="rp-section-title">Action list, most urgent first</h3>
            <table class="ro-table text-xs">
                <thead>
                    <tr>
                        <th class="text-left">What</th>
                        <th class="text-left">Nurse</th>
                        <th class="text-left">Item</th>
                        <th class="text-left">Date</th>
                        <th class="text-left">When</th>
                        <th class="text-left">To do</th>
                        <th class="text-left ro-write">Done (initials, date)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['actions'] as $action)
                        <tr data-action="{{ $action['type'] }}">
                            <td class="whitespace-nowrap"><span class="ro-staff {{ $actionTone[$action['type']] }}">{{ $report['actions_meta'][$action['type']]['label'] }}</span></td>
                            <td class="whitespace-nowrap font-semibold text-gray-900">{{ $action['nurse'] }}</td>
                            <td>
                                {{ $action['item'] }}
                                @if ($action['detail'])
                                    <div class="ro-small ro-muted text-gray-500">{{ $action['detail'] }}</div>
                                @endif
                            </td>
                            <td class="whitespace-nowrap">{{ $action['date']?->format('j M Y') ?? '–' }}</td>
                            <td class="whitespace-nowrap">{{ $days($action['days'], $action['type']) }}</td>
                            <td>{{ $action['todo'] }}</td>
                            <td></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-4 text-center text-gray-500">Nothing to act on: every credential and review is in date for the next {{ $window }} days.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    @endif

    @if ($options['privileges'])
        <section class="{{ $options['matrix'] || $options['actions'] ? 'page-break' : '' }} space-y-3">
            <h3 class="rp-section-title">Privileges per nurse</h3>
            @foreach ($report['nurses'] as $nurse)
                <div class="ro-avoid">
                    <h4 class="mb-1 text-xs font-bold text-gray-900">
                        {{ $nurse['name'] }}
                        <span class="font-normal ro-muted text-gray-500">{{ $nurse['designation'] }}</span>
                    </h4>
                    @if ($nurse['privileges'])
                        <table class="ro-table text-xs">
                            <thead>
                                <tr>
                                    <th class="text-left">Privilege</th>
                                    <th class="text-left">Category</th>
                                    <th class="text-left">Status</th>
                                    <th class="text-left">Review</th>
                                    <th class="text-left">Certificate</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($nurse['privileges'] as $privilege)
                                    <tr>
                                        <td>{{ $privilege['name'] }}</td>
                                        <td class="whitespace-nowrap">{{ $privilege['category'] }}</td>
                                        <td class="whitespace-nowrap"><span class="ro-staff {{ $privilegeTone[$privilege['status']] ?? $grey }}">{{ $privilege['status_label'] }}</span></td>
                                        <td class="whitespace-nowrap">
                                            @if ($privilege['review_on'])
                                                @if (in_array($privilege['review'], ['overdue', 'due'], true))
                                                    <span class="ro-staff {{ $reviewTone[$privilege['review']] }}">{{ $privilege['review'] === 'overdue' ? 'Overdue' : 'Due' }}</span>
                                                @endif
                                                {{ $privilege['review_on']->format('j M Y') }}
                                            @else
                                                <span class="ro-muted text-gray-400">–</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($privilege['at_risk'])
                                                <span class="ro-staff {{ $red }}">At risk</span>
                                            @endif
                                            {{ $privilege['certificate'] }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="text-xs text-gray-500">No privileges recorded.</p>
                    @endif
                </div>
            @endforeach
        </section>
    @endif
</div>
