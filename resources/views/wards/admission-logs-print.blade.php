<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admission Log Report</title>
    @vite(['resources/css/app.css'])
    <style>
        @page {
            size: A4 {{ $orientation }};
            margin: 10mm;
        }

        body {
            font-family: 'Inter', sans-serif;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        .report-table th,
        .report-table td {
            border: 1px solid #d1d5db;
            padding: 4px 6px;
            text-align: left;
            vertical-align: top;
        }

        .report-table thead th {
            background: #f3f4f6;
            color: #4b5563;
            font-size: 10px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        /* The column headings repeat on every printed page; a row never splits across two */
        .report-table thead {
            display: table-header-group;
        }

        .report-table tr {
            break-inside: avoid;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background: #fff !important;
            }

            .sheet {
                max-width: none !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
            }
        }
    </style>
</head>

@php
    use App\Models\AdmissionLog;
    use Illuminate\Support\Str;

    $logoPath = $hospital?->logo_path ?: $hospital?->navbar_logo_path;
    $logoUrl = $logoPath ? \Illuminate\Support\Facades\Storage::url($logoPath) : null;
@endphp

<body class="bg-gray-100">
    {{-- Screen-only toolbar --}}
    <div class="no-print sticky top-0 z-10 border-b border-gray-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2 px-4 py-2">
            <div class="text-sm text-gray-600">
                <span class="font-semibold text-gray-900">Print preview</span>
                &middot; {{ number_format($printed) }} {{ Str::plural('record', $printed) }}
                &middot; A4 {{ $orientation }}
            </div>
            <div class="flex gap-2">
                <button type="button" onclick="window.print()"
                    class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-green-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-green-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Print
                </button>
                <button type="button" onclick="history.length > 1 ? history.back() : window.close()"
                    class="px-4 py-1.5 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-md hover:bg-gray-50">
                    Close
                </button>
            </div>
        </div>
    </div>

    <main class="sheet mx-auto my-4 max-w-6xl bg-white p-8 shadow">
        <header class="mb-4 flex items-start justify-between gap-4 border-b-2 border-gray-800 pb-3">
            <div class="flex items-center gap-3">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="" class="h-10 w-auto">
                @endif
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">
                        {{ $hospital?->name ?? config('app.name') }}
                    </div>
                    <h1 class="text-xl font-bold text-gray-900">Admission Log Report</h1>
                </div>
            </div>
            <div class="text-right text-xs text-gray-600">
                <div>Generated {{ now()->format('d M Y, H:i') }}</div>
                @if ($generatedBy)
                    <div>by {{ $generatedBy }}</div>
                @endif
            </div>
        </header>

        {{-- What the report covers --}}
        <dl class="mb-4 grid grid-cols-2 gap-x-6 gap-y-2 text-xs sm:grid-cols-4">
            @foreach ($filterSummary as $label => $value)
                <div>
                    <dt class="text-[10px] font-semibold uppercase tracking-wider text-gray-500">{{ $label }}</dt>
                    <dd class="text-gray-900">{{ $value }}</dd>
                </div>
            @endforeach
            <div>
                <dt class="text-[10px] font-semibold uppercase tracking-wider text-gray-500">Records</dt>
                <dd class="font-semibold text-gray-900">{{ number_format($total) }}</dd>
            </div>
        </dl>

        @if ($printed < $total)
            <div class="mb-4 rounded border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                Only the first {{ number_format($printed) }} of {{ number_format($total) }} records are included.
                Narrow the dates to print the rest.
            </div>
        @endif

        @if ($showSummary && $statusTotals->isNotEmpty())
            <div class="mb-4 flex flex-wrap gap-2">
                @foreach ($statusTotals as $status => $count)
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ AdmissionLog::actionBadgeClass($status) }}">
                        {{ AdmissionLog::actionLabel($status) }}
                        <span class="font-bold">{{ $count }}</span>
                    </span>
                @endforeach
            </div>
        @endif

        @if ($total === 0)
            <p class="py-10 text-center text-sm text-gray-500">No admission logs match these filters.</p>
        @else
            @foreach ($groups as $heading => $rows)
                <section class="{{ $loop->first ? '' : 'mt-5' }}">
                    @if ($group !== 'none')
                        <h2 class="mb-1.5 flex items-baseline justify-between border-b border-gray-300 pb-1 text-sm font-bold text-gray-900">
                            <span>{{ $heading }}</span>
                            <span class="text-xs font-normal text-gray-500">{{ $rows->count() }} {{ Str::plural('record', $rows->count()) }}</span>
                        </h2>
                    @endif
                    <table class="report-table">
                        <thead>
                            <tr>
                                @foreach ($columns as $label)
                                    <th>{{ $label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $log)
                                @include('wards.partials.admission-log-row', [
                                    'log' => $log,
                                    'columns' => $columns->keys()->all(),
                                    'cellClass' => '',
                                    'clampNotes' => false,
                                ])
                            @endforeach
                        </tbody>
                    </table>
                </section>
            @endforeach
        @endif

        <footer class="mt-6 border-t border-gray-200 pt-2 text-center text-[10px] text-gray-400">
            End of report &middot; {{ number_format($printed) }} {{ Str::plural('record', $printed) }}
            &middot; {{ $sort === 'asc' ? 'oldest first' : 'newest first' }}
        </footer>
    </main>

    @if ($autoprint && $total > 0)
        <script>
            window.addEventListener('load', () => setTimeout(() => window.print(), 400));
        </script>
    @endif
</body>

</html>
