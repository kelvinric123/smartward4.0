{{--
    AI Nurse Schedule printout, in the format and with the options chosen in
    the Print dialog (AiNurseSchedulePrintController). A page of its own, not
    the app layout, so nothing is clipped and it always prints on white: the
    read-only partials draw the content from the ScheduleSnapshot, and the
    reports from their own $report.
--}}
@php
    $wardName = $snapshot['ward']['name'];
    $hospital = $snapshot['hospital'];
    $assign = $snapshot['assign'];
    $from = $snapshot['from'];
    $to = $snapshot['to'];
    $weekRange = $from->format('j M') . ' – ' . $to->format('j M Y');
    $shiftLine = $assign ? $assign['shift'] . ' shift, ' . $assign['date']->format('D j M Y') . ', ' . $assign['time'] : null;
    $periodLine = fn () => match ($period) {
        'quarter' => 'Q' . $report['from']->quarter . ' ' . $report['from']->year . ' · ' . $report['from']->format('j M') . ' – ' . $report['to']->format('j M Y'),
        'year' => $report['from']->year . ' · ' . $report['from']->format('j M') . ' – ' . $report['to']->format('j M Y'),
        default => $report['from']->format('F Y'),
    };
    $subtitle = match ($format) {
        'assign' => $shiftLine,
        'acuity' => 'Patients on the ward ' . $snapshot['generated_at']->format('D j M Y, H:i') . ' · week of ' . $from->format('j M Y'),
        'pack' => 'Week ' . $weekRange . ' · beds for the ' . $shiftLine,
        'productivity' => $report['month']->format('F Y') . ' · ' . $report['days_in_month'] . ' days',
        'leave' => $periodLine(),
        'credentials' => 'As of ' . $report['today']->format('j M Y') . ' · expiring within ' . $report['window'] . ' days',
        default => (count($snapshot['weeks']) > 1 ? count($snapshot['weeks']) . ' weeks, ' : 'Week ') . $weekRange,
    };
    $nameModes = ['full' => 'full names', 'initials' => 'initials', 'hidden' => 'bed only'];
    $ana = 'American Nurses Association (2017), Defining staffing: workforce management, patient classification and acuity systems';

    // The sheet on screen is as wide as the paper, so the preview wraps as the printout will
    $paperMm = ['A4' => [210, 297], 'A3' => [297, 420], 'Letter' => [215.9, 279.4], 'Legal' => [215.9, 355.6]][$paper];
    $sheetWidth = ($orientation === 'landscape' ? $paperMm[1] : $paperMm[0]) . 'mm';

    $switch = fn (array $change) => route('ward.ai-schedule.print', array_merge($query, $change));
    $switchClass = fn (bool $current) => $current
        ? 'rounded px-1.5 py-0.5 font-semibold bg-gray-800 text-white'
        : 'rounded px-1.5 py-0.5 text-gray-700 hover:bg-gray-100';
    $packOptions = array_merge($options, ['references' => false]);
@endphp
<!DOCTYPE html>
<html lang="en" class="print-size-{{ $size }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $formatLabel }} &middot; {{ $wardName }}</title>
    @vite(['resources/css/app.css'])
    @include('wards.ai-schedule.readonly.styles')
    <style>
        @page {
            size: {{ $paper }} {{ $orientation }};
            margin: 10mm;

            @bottom-right {
                content: "Page " counter(page) " of " counter(pages);
                font-size: 8pt;
                color: #4b5563;
            }
        }

        /* Text size: everything is in rem, so the root size scales the whole sheet */
        html.print-size-compact {
            font-size: 12px;
        }

        html.print-size-normal {
            font-size: 14px;
        }

        html.print-size-large {
            font-size: 16px;
        }

        body {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        thead {
            display: table-header-group;
        }

        tr {
            break-inside: avoid;
        }

        .sheet {
            width: {{ $sheetWidth }};
            max-width: 100%;
            padding: 10mm;
        }

        .signature-line {
            border-bottom: 1px solid #6b7280;
            height: 1.6rem;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background: #fff !important;
            }

            .page-break {
                break-before: page;
            }

            .sheet {
                width: auto !important;
                max-width: none !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
            }
        }
    </style>
</head>

<body class="bg-gray-100 text-gray-900">
    {{-- Screen only: print, close, and switch the paper without going back to the dialog --}}
    <div class="no-print sticky top-0 z-10 border-b border-gray-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-2">
            <div class="text-sm text-gray-600">
                <span class="font-semibold text-gray-900">Print preview</span>
                &middot; {{ $formatLabel }} &middot; {{ $paper }} {{ $orientation }}
            </div>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                <div class="flex items-center gap-1">
                    <span class="font-semibold text-gray-500">Paper</span>
                    @foreach (\App\Http\Controllers\AiNurseSchedulePrintController::PAPERS as $option)
                        <a href="{{ $switch(['paper' => $option]) }}" class="{{ $switchClass($option === $paper) }}">{{ $option }}</a>
                    @endforeach
                </div>
                <div class="flex items-center gap-1">
                    <span class="font-semibold text-gray-500">Orientation</span>
                    @foreach (\App\Http\Controllers\AiNurseSchedulePrintController::ORIENTATIONS as $option)
                        <a href="{{ $switch(['orientation' => $option]) }}" class="{{ $switchClass($option === $orientationChoice) }}">{{ ucfirst($option) }}</a>
                    @endforeach
                </div>
            </div>
            <div class="flex gap-2">
                <button type="button" onclick="window.print()"
                    class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-green-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-green-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Print / Save PDF
                </button>
                <button type="button" onclick="history.length > 1 ? history.back() : window.close()"
                    class="px-4 py-1.5 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-md hover:bg-gray-50">
                    Close
                </button>
            </div>
        </div>
    </div>

    <main class="sheet mx-auto my-4 bg-white shadow {{ $mono ? 'ro-mono' : '' }}" data-format="{{ $format }}">
        <header class="mb-3 flex items-start justify-between gap-4 border-b-2 border-gray-800 pb-2">
            <div class="flex items-center gap-3">
                @if ($hospital['logo_url'])
                    <img src="{{ $hospital['logo_url'] }}" alt="" class="h-10 w-auto">
                @endif
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ $hospital['name'] }}</div>
                    <h1 class="text-lg font-bold leading-tight text-gray-900">{{ $formatLabel }}: {{ $wardName }}</h1>
                    <div class="text-sm text-gray-700">{{ $subtitle }}</div>
                </div>
            </div>
            <div class="shrink-0 text-right text-xs text-gray-600">
                <div>Printed {{ $snapshot['generated_at']->format('d M Y, H:i') }}</div>
                @if ($printedBy)
                    <div>by {{ $printedBy }}</div>
                @endif
                @if ($showsPatients)
                    <div>Patients: {{ $nameModes[$names] }}</div>
                    @if ($names !== 'hidden')
                        <div class="font-semibold text-gray-800">Confidential: for ward use only</div>
                    @endif
                @endif
                @if ($report)
                    <div class="font-semibold text-gray-800">Confidential: staff information</div>
                @endif
            </div>
        </header>

        @switch($format)
            @case('nurse')
                @include('wards.ai-schedule.readonly.nurse-list', ['snapshot' => $snapshot, 'options' => $options])
                @break

            @case('assign')
                @include('wards.ai-schedule.readonly.beds', ['snapshot' => $snapshot, 'options' => $options])
                @break

            @case('acuity')
                @include('wards.ai-schedule.readonly.acuity', ['snapshot' => $snapshot, 'options' => $options])
                @break

            @case('workload')
                @include('wards.ai-schedule.readonly.workload', ['snapshot' => $snapshot, 'options' => $options])
                @break

            @case('pack')
                <section>
                    <h2 class="mb-2 text-base font-bold text-gray-900">Weekly roster</h2>
                    @include('wards.ai-schedule.readonly.roster', ['snapshot' => $snapshot, 'options' => $packOptions])
                </section>
                <section class="page-break">
                    <h2 class="mb-2 text-base font-bold text-gray-900">Patient acuity &amp; staffing</h2>
                    @include('wards.ai-schedule.readonly.acuity', ['snapshot' => $snapshot, 'options' => $packOptions])
                </section>
                <section class="page-break">
                    <h2 class="mb-2 text-base font-bold text-gray-900">Bed assignment</h2>
                    @include('wards.ai-schedule.readonly.beds', ['snapshot' => $snapshot, 'options' => $packOptions])
                </section>
                <section class="page-break">
                    <h2 class="mb-2 text-base font-bold text-gray-900">Workload &amp; fairness</h2>
                    @include('wards.ai-schedule.readonly.workload', ['snapshot' => $snapshot, 'options' => $packOptions])
                </section>
                @if ($options['references'])
                    <section id="acuity-references" class="page-break space-y-3">
                        <h2 class="text-base font-bold text-gray-900">Method and references</h2>
                        @include('wards.ai-schedule.partials.acuity-method', ['acuitySettings' => $snapshot['acuity']['settings'], 'shifts' => $snapshot['shifts'], 'compact' => true])
                        @include('wards.ai-schedule.partials.acuity-references', ['compact' => true])
                    </section>
                @endif
                @break

            @case('productivity')
                @include('wards.ai-schedule.readonly.productivity', ['report' => $report, 'options' => $options])
                @break

            @case('leave')
                @include('wards.ai-schedule.readonly.leave-report', ['report' => $report, 'options' => $options])
                @break

            @case('credentials')
                @include('wards.ai-schedule.readonly.credentials', ['report' => $report, 'options' => $options])
                @break

            @default
                @include('wards.ai-schedule.readonly.roster', ['snapshot' => $snapshot, 'options' => $options])
        @endswitch

        @if (in_array('signature', $sections, true))
            <section class="ro-avoid mt-6 grid grid-cols-3 gap-6 text-xs text-gray-700">
                @foreach (['Prepared by', 'Checked by', 'Approved by'] as $role)
                    <div>
                        <div class="font-semibold uppercase tracking-wider text-gray-800">{{ $role }}</div>
                        @foreach (['Name', 'Signature', 'Date'] as $line)
                            <div class="mt-2 flex items-end gap-2">
                                <span class="w-16 shrink-0">{{ $line }}</span>
                                <span class="signature-line flex-1"></span>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </section>
        @endif

        <footer class="ro-avoid mt-5 border-t border-gray-300 pt-2 text-xs text-gray-500">
            <div class="flex flex-wrap justify-between gap-x-4">
                <span>SmartWard AI Nurse Schedule &middot; {{ $wardName }} &middot; {{ $hospital['name'] }}</span>
                <span>{{ $formatLabel }} &middot; printed {{ $snapshot['generated_at']->format('d M Y, H:i') }}</span>
            </div>
            {{-- The approach is cited to ANA; the numbers on the sheet are SmartWard's own and say so --}}
            @if ($citation === 'acuity')
                <p class="mt-1">
                    Patient acuity and acuity-based staffing after the {{ $ana }}. ANA describes the approach; the care levels'
                    hours, the shift shares and the nurse numbers are SmartWard's own, from this ward's settings. A planning aid:
                    the nurse's judgement decides.
                </p>
            @elseif ($citation === 'productivity')
                <p class="mt-1">
                    Measures chosen from the {{ $ana }}: improving by monitoring staffing (pp. 4, 8) and benchmarks such as hours
                    of nursing care per patient day, staffing mix and extra shifts (pp. 24-25). The figures are SmartWard's own, from
                    the roster, the admission log and this ward's acuity settings; hours are rostered, not clocked.
                </p>
            @elseif ($citation === 'credentials')
                <p class="mt-1">
                    Staffing matches nurses to patients on skill mix, education and competencies ({{ $ana }}, pp. 3-4). The
                    statuses are SmartWard's, from each nurse's credentialing file on {{ $report['today']->format('j M Y') }}.
                </p>
            @endif
        </footer>
    </main>

    @if ($autoprint)
        <script>
            window.addEventListener('load', () => setTimeout(() => window.print(), 400));
        </script>
    @endif
</body>

</html>
