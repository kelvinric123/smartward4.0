<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admission Logs</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        [x-cloak] {
            display: none !important;
        }

        /* Columns the viewer has hidden */
        @foreach (array_keys($columns) as $key)
            .hide-{{ $key }} [data-col="{{ $key }}"] { display: none; }
        @endforeach
    </style>
    @include('components.autofill-guard')
</head>

@php
    use App\Models\AdmissionLog;
    use Illuminate\Support\Str;

    $selected = $filters['statuses'];
    $statusUrl = fn (array $statuses) => request()->fullUrlWithQuery([
        'statuses' => $statuses ?: null,
        'action' => null,
        'page' => null,
    ]);

    // Hidden-by-default columns start hidden, so nothing flashes before the page script runs
    $defaultHidden = collect($columns)->reject(fn (array $column) => $column[1])->keys()
        ->map(fn (string $key) => 'hide-' . $key)->implode(' ');

    $control = 'px-2.5 py-1.5 bg-white border border-gray-300 rounded-lg shadow-sm text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500';

    $pageConfig = [
        'columns' => collect($columns)->map(fn (array $column, string $key) => [
            'key' => $key,
            'label' => $column[0],
            'default' => $column[1],
        ])->values(),
        'statuses' => collect(array_keys($statusCounts))->map(fn (string $status) => [
            'key' => $status,
            'label' => AdmissionLog::actionLabel($status),
        ])->values(),
        'filters' => [
            'ward_id' => (string) ($filters['ward_id'] ?? ''),
            'statuses' => $selected,
            'source' => $filters['source'] ?? '',
            'bed_number' => $filters['bed_number'],
            'search' => $filters['search'],
            'from_date' => $filters['from_date'] ?? '',
            'to_date' => $filters['to_date'] ?? '',
        ],
        'urls' => [
            'summary' => route('ward.admission-logs.summary'),
            'print' => route('ward.admission-logs.print'),
        ],
    ];
@endphp

<body class="bg-gray-50">
    <div x-data="admissionLogsPage(@js($pageConfig))" class="h-screen flex flex-col gap-2 p-3">
        {{-- Header --}}
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="min-w-0">
                <h2 class="text-lg font-bold leading-tight text-gray-800">Admission Logs</h2>
                <p class="text-xs text-gray-500">
                    {{ number_format($logs->total()) }} {{ Str::plural('record', $logs->total()) }}
                    &middot; bed status and admission activity
                </p>
            </div>

            <div class="flex items-center gap-2">
                {{-- Show / hide columns --}}
                <div class="relative" x-data="{ open: false }" @click.outside="open = false"
                    @keydown.escape.window="open = false">
                    <button type="button" @click="open = !open"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-300 text-gray-700 rounded-lg text-sm font-medium shadow-sm hover:bg-gray-50">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
                        </svg>
                        Columns
                        <span class="text-xs text-gray-400" x-text="visible.length + '/' + columns.length"></span>
                    </button>
                    <div x-show="open" x-cloak
                        class="absolute right-0 z-30 mt-1 w-60 rounded-lg border border-gray-200 bg-white p-2 shadow-lg">
                        <div class="px-1 pb-1 text-[11px] font-semibold uppercase tracking-wider text-gray-500">Show columns</div>
                        <div class="max-h-72 overflow-y-auto">
                            <template x-for="column in columns" :key="column.key">
                                <label class="flex items-center gap-2 px-1 py-1 text-sm text-gray-700 rounded hover:bg-gray-50 cursor-pointer">
                                    <input type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        :checked="isVisible(column.key)" @change="toggleColumn(column.key)"
                                        :disabled="isVisible(column.key) && visible.length === 1">
                                    <span x-text="column.label"></span>
                                </label>
                            </template>
                        </div>
                        <div class="mt-1 pt-1.5 border-t border-gray-100 flex items-center justify-between px-1">
                            <button type="button" @click="resetColumns()" class="text-xs font-semibold text-blue-700 hover:underline">Default</button>
                            <button type="button" @click="showAllColumns()" class="text-xs font-semibold text-blue-700 hover:underline">Show all</button>
                        </div>
                    </div>
                </div>

                <button type="button" @click="openPrint()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-600 text-white rounded-lg text-sm font-medium shadow-sm hover:bg-green-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Print
                </button>
            </div>
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('ward.admission-logs') }}" class="flex flex-wrap items-center gap-2">
            @foreach ($selected as $status)
                <input type="hidden" name="statuses[]" value="{{ $status }}">
            @endforeach
            <input type="hidden" name="per_page" value="{{ $perPage }}">

            <select name="ward_id" class="{{ $control }}" aria-label="Ward">
                <option value="">All wards</option>
                @foreach ($wards as $ward)
                    <option value="{{ $ward->id }}" @selected($filters['ward_id'] == $ward->id)>{{ $ward->ward_name }}</option>
                @endforeach
            </select>

            <select name="source" class="{{ $control }}" aria-label="Source">
                <option value="">All sources</option>
                <option value="manual" @selected($filters['source'] === 'manual')>Manual</option>
                <option value="adt" @selected($filters['source'] === 'adt')>ADT/HIS</option>
            </select>

            <input type="text" name="bed_number" value="{{ $filters['bed_number'] }}" placeholder="Bed" autocomplete="off"
                class="{{ $control }} w-24" aria-label="Bed">
            <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="MRN or patient" autocomplete="off"
                class="{{ $control }} w-40" aria-label="MRN or patient">

            <div class="flex items-center gap-1">
                <input type="date" name="from_date" value="{{ $filters['from_date'] }}" class="{{ $control }}" aria-label="From date">
                <span class="text-xs text-gray-400">to</span>
                <input type="date" name="to_date" value="{{ $filters['to_date'] }}" class="{{ $control }}" aria-label="To date">
            </div>

            <button type="submit"
                class="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-sm font-medium shadow-sm hover:bg-blue-700">
                Apply
            </button>
            <a href="{{ route('ward.admission-logs', array_filter(['ward_id' => $filters['ward_id']])) }}"
                class="px-3 py-1.5 bg-white text-gray-700 rounded-lg text-sm border border-gray-300 hover:bg-gray-50"
                title="Clear every filter except the ward">
                Clear
            </a>
        </form>

        {{-- Status chips: click to add or remove a status from the filter --}}
        <div class="flex flex-wrap items-center gap-1.5">
            <span class="mr-0.5 text-[11px] font-semibold uppercase tracking-wider text-gray-500">Status</span>
            <a href="{{ $statusUrl([]) }}"
                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold border {{ $selected ? 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' : 'bg-gray-800 text-white border-gray-800' }}">
                All
                <span class="{{ $selected ? 'text-gray-400' : 'text-gray-300' }}">{{ array_sum($statusCounts) }}</span>
            </a>
            @foreach ($statusCounts as $status => $count)
                @continue($count === 0 && !in_array($status, $selected, true))
                @php
                    $isOn = in_array($status, $selected, true);
                    $next = $isOn ? array_values(array_diff($selected, [$status])) : [...$selected, $status];
                @endphp
                <a href="{{ $statusUrl($next) }}"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold border {{ AdmissionLog::actionBadgeClass($status) }} {{ $isOn ? 'border-blue-600 ring-2 ring-blue-500/40' : 'border-transparent opacity-80 hover:opacity-100' }}"
                    title="{{ $isOn ? 'Remove from the filter' : 'Add to the filter' }}">
                    @if ($isOn)
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                        </svg>
                    @endif
                    {{ AdmissionLog::actionLabel($status) }}
                    <span class="font-normal opacity-70">{{ $count }}</span>
                </a>
            @endforeach
        </div>

        {{-- The log: scrolls inside the page with the header row kept in view --}}
        <div class="flex-1 min-h-0 overflow-auto rounded-lg border border-gray-200 bg-white shadow-sm {{ $defaultHidden }}"
            :class="hiddenMap">
            <table class="w-full text-sm">
                <thead class="sticky top-0 z-10 bg-gray-50">
                    <tr>
                        @foreach ($columns as $key => $column)
                            <th scope="col" data-col="{{ $key }}"
                                class="px-2.5 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 whitespace-nowrap shadow-[inset_0_-1px_0_#e5e7eb]">
                                {{ $column[0] }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($logs as $log)
                        @include('wards.partials.admission-log-row', [
                            'log' => $log,
                            'columns' => array_keys($columns),
                            'rowClass' => 'hover:bg-blue-50/40',
                            'cellClass' => 'px-2.5 py-2 text-sm text-gray-700',
                        ])
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) }}" class="px-4 py-10 text-center text-sm text-gray-500">
                                No admission logs match these filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Rows per page and pages --}}
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-1 text-xs text-gray-600">
                <span class="mr-1">Rows per page</span>
                @foreach (\App\Http\Controllers\AdmissionLogController::PER_PAGE_OPTIONS as $option)
                    <a href="{{ request()->fullUrlWithQuery(['per_page' => $option, 'page' => null]) }}"
                        class="px-2 py-0.5 rounded {{ $option === $perPage ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-200' }}">
                        {{ $option }}
                    </a>
                @endforeach
            </div>
            <div class="flex-1 min-w-0 text-sm">
                @if ($logs->hasPages())
                    {{ $logs->onEachSide(1)->links() }}
                @elseif ($logs->total() > 0)
                    <p class="text-right text-xs text-gray-500">Showing all {{ number_format($logs->total()) }}</p>
                @endif
            </div>
        </div>

        {{-- Print dialog --}}
        <div x-show="print.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-3"
            @keydown.escape.window="print.open = false">
            <div class="absolute inset-0 bg-gray-900/50" @click="print.open = false"></div>

            <div class="relative flex max-h-full w-full max-w-3xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-3">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Print admission logs</h3>
                        <p class="text-xs text-gray-500">Prints every matching record, not just this page.</p>
                    </div>
                    <button type="button" @click="print.open = false" class="p-1 text-gray-400 hover:text-gray-600" aria-label="Close">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="grid flex-1 grid-cols-1 gap-5 overflow-y-auto px-5 py-4 md:grid-cols-2">
                    {{-- Which records --}}
                    <div class="space-y-4">
                        <section>
                            <h4 class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Dates</h4>
                            <div class="mb-2 flex flex-wrap gap-1.5">
                                <template x-for="preset in presets" :key="preset.key">
                                    <button type="button" @click="setRange(preset.key)" x-text="preset.label"
                                        class="px-2.5 py-1 rounded-full border text-xs font-medium"
                                        :class="print.range === preset.key ? 'bg-blue-600 border-blue-600 text-white' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'"></button>
                                </template>
                            </div>
                            <div class="flex items-center gap-2">
                                <input type="date" x-model="print.from_date" @change="print.range = 'custom'; refreshCount()"
                                    class="{{ $control }} flex-1 min-w-0" aria-label="Print from date">
                                <span class="text-xs text-gray-400">to</span>
                                <input type="date" x-model="print.to_date" @change="print.range = 'custom'; refreshCount()"
                                    class="{{ $control }} flex-1 min-w-0" aria-label="Print to date">
                            </div>
                        </section>

                        <section>
                            <div class="mb-1.5 flex items-center justify-between">
                                <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500">Status</h4>
                                <button type="button" @click="print.statuses = []; refreshCount()"
                                    class="text-xs font-semibold text-blue-700 hover:underline"
                                    x-text="print.statuses.length ? 'All statuses' : 'All statuses included'"></button>
                            </div>
                            <div class="grid grid-cols-1 gap-0.5 sm:grid-cols-2">
                                <template x-for="status in statuses" :key="status.key">
                                    <label class="flex items-center gap-2 rounded px-1 py-1 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
                                        <input type="checkbox" :value="status.key" x-model="print.statuses" @change="refreshCount()"
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="truncate" x-text="status.label" :title="status.label"></span>
                                        <span class="ml-auto text-xs text-gray-400" x-text="counts.byStatus[status.key] ?? 0"></span>
                                    </label>
                                </template>
                            </div>
                            <p class="mt-1 text-[11px] text-gray-500" x-show="!print.statuses.length">
                                None ticked prints every status.
                            </p>
                        </section>

                        <section class="grid grid-cols-2 gap-2">
                            <div>
                                <h4 class="mb-1 text-xs font-semibold uppercase tracking-wider text-gray-500">Ward</h4>
                                <select x-model="print.ward_id" @change="refreshCount()" class="{{ $control }} w-full">
                                    <option value="">All wards</option>
                                    @foreach ($wards as $ward)
                                        <option value="{{ $ward->id }}">{{ $ward->ward_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <h4 class="mb-1 text-xs font-semibold uppercase tracking-wider text-gray-500">Source</h4>
                                <select x-model="print.source" @change="refreshCount()" class="{{ $control }} w-full">
                                    <option value="">All sources</option>
                                    <option value="manual">Manual</option>
                                    <option value="adt">ADT/HIS</option>
                                </select>
                            </div>
                            <div>
                                <h4 class="mb-1 text-xs font-semibold uppercase tracking-wider text-gray-500">Bed</h4>
                                <input type="text" x-model="print.bed_number" @input.debounce.400ms="refreshCount()" autocomplete="off"
                                    placeholder="Any" class="{{ $control }} w-full">
                            </div>
                            <div>
                                <h4 class="mb-1 text-xs font-semibold uppercase tracking-wider text-gray-500">MRN or patient</h4>
                                <input type="text" x-model="print.search" @input.debounce.400ms="refreshCount()" autocomplete="off"
                                    placeholder="Anyone" class="{{ $control }} w-full">
                            </div>
                        </section>
                    </div>

                    {{-- How the printout looks --}}
                    <div class="space-y-4">
                        <section>
                            <div class="mb-1.5 flex items-center justify-between">
                                <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500">Columns</h4>
                                <button type="button" @click="print.columns = [...visible]"
                                    class="text-xs font-semibold text-blue-700 hover:underline">Same as on screen</button>
                            </div>
                            <div class="grid grid-cols-2 gap-0.5">
                                <template x-for="column in columns" :key="column.key">
                                    <label class="flex items-center gap-2 rounded px-1 py-1 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
                                        <input type="checkbox" :value="column.key" x-model="print.columns"
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span x-text="column.label"></span>
                                    </label>
                                </template>
                            </div>
                        </section>

                        <section class="grid grid-cols-2 gap-3 text-sm text-gray-700">
                            <div>
                                <h4 class="mb-1 text-xs font-semibold uppercase tracking-wider text-gray-500">Order</h4>
                                <label class="flex items-center gap-2 py-0.5"><input type="radio" value="asc" x-model="print.sort" class="text-blue-600 focus:ring-blue-500"> Oldest first</label>
                                <label class="flex items-center gap-2 py-0.5"><input type="radio" value="desc" x-model="print.sort" class="text-blue-600 focus:ring-blue-500"> Newest first</label>
                            </div>
                            <div>
                                <h4 class="mb-1 text-xs font-semibold uppercase tracking-wider text-gray-500">Paper</h4>
                                <label class="flex items-center gap-2 py-0.5"><input type="radio" value="landscape" x-model="print.orientation" class="text-blue-600 focus:ring-blue-500"> Landscape</label>
                                <label class="flex items-center gap-2 py-0.5"><input type="radio" value="portrait" x-model="print.orientation" class="text-blue-600 focus:ring-blue-500"> Portrait</label>
                            </div>
                            <div class="col-span-2">
                                <h4 class="mb-1 text-xs font-semibold uppercase tracking-wider text-gray-500">Group rows</h4>
                                <div class="flex flex-wrap gap-x-4">
                                    <label class="flex items-center gap-2 py-0.5"><input type="radio" value="none" x-model="print.group" class="text-blue-600 focus:ring-blue-500"> One list</label>
                                    <label class="flex items-center gap-2 py-0.5"><input type="radio" value="status" x-model="print.group" class="text-blue-600 focus:ring-blue-500"> By status</label>
                                    <label class="flex items-center gap-2 py-0.5"><input type="radio" value="date" x-model="print.group" class="text-blue-600 focus:ring-blue-500"> By day</label>
                                </div>
                            </div>
                            <label class="col-span-2 flex items-center gap-2">
                                <input type="checkbox" x-model="print.summary" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                Show the count per status at the top
                            </label>
                        </section>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 bg-gray-50 px-5 py-3">
                    <div class="text-sm">
                        <span x-show="counts.loading" class="text-gray-500">Counting…</span>
                        <span x-show="!counts.loading && counts.total !== null" :class="counts.total ? 'text-gray-800' : 'text-red-600'">
                            <span class="font-semibold" x-text="(counts.total ?? 0).toLocaleString()"></span>
                            <span x-text="counts.total === 1 ? 'record' : 'records'"></span> to print
                            <span x-show="counts.total > counts.max" class="text-amber-700">
                                (the first <span x-text="counts.max.toLocaleString()"></span> only; narrow the dates)
                            </span>
                        </span>
                        <span x-show="!print.columns.length" class="ml-2 text-red-600">Pick at least one column.</span>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" @click="print.open = false"
                            class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-md hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="button" @click="openReport()" :disabled="counts.total === 0 || !print.columns.length"
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-green-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-green-700 disabled:bg-gray-300 disabled:cursor-not-allowed">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            Open print view
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function admissionLogsPage(config) {
            const STORAGE_KEY = 'smartward.admissionLogs.columns';
            const pad = n => String(n).padStart(2, '0');
            const ymd = d => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());

            return {
                columns: config.columns,
                statuses: config.statuses,
                visible: [],
                presets: [
                    { key: 'today', label: 'Today' },
                    { key: 'yesterday', label: 'Yesterday' },
                    { key: 'week', label: 'Last 7 days' },
                    { key: 'month', label: 'This month' },
                    { key: 'lastMonth', label: 'Last month' },
                    { key: 'all', label: 'All dates' },
                ],
                print: {
                    open: false, range: 'all',
                    ward_id: '', statuses: [], source: '', bed_number: '', search: '', from_date: '', to_date: '',
                    columns: [], sort: 'asc', orientation: 'landscape', group: 'none', summary: true,
                },
                counts: { loading: false, total: null, byStatus: {}, max: 0 },
                countTimer: null,
                countRequest: 0,

                init() {
                    const keys = this.columns.map(c => c.key);
                    let saved = null;
                    try {
                        saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
                    } catch (e) {
                        saved = null;
                    }
                    this.visible = Array.isArray(saved) ? saved.filter(k => keys.includes(k)) : [];
                    if (!this.visible.length) this.visible = this.defaults();
                },

                // Columns on screen
                defaults() {
                    return this.columns.filter(c => c.default).map(c => c.key);
                },
                isVisible(key) {
                    return this.visible.includes(key);
                },
                toggleColumn(key) {
                    if (this.isVisible(key)) {
                        if (this.visible.length > 1) this.visible = this.visible.filter(k => k !== key);
                    } else {
                        this.visible = this.columns.map(c => c.key).filter(k => k === key || this.visible.includes(k));
                    }
                    this.saveColumns();
                },
                resetColumns() {
                    this.visible = this.defaults();
                    this.saveColumns();
                },
                showAllColumns() {
                    this.visible = this.columns.map(c => c.key);
                    this.saveColumns();
                },
                saveColumns() {
                    try {
                        localStorage.setItem(STORAGE_KEY, JSON.stringify(this.visible));
                    } catch (e) {
                        // Private windows and blocked storage: the choice just is not remembered
                    }
                },
                get hiddenMap() {
                    const map = {};
                    this.columns.forEach(c => map['hide-' + c.key] = !this.visible.includes(c.key));
                    return map;
                },

                // Print dialog, starting from what is on screen
                openPrint() {
                    const f = config.filters;
                    Object.assign(this.print, {
                        open: true,
                        ward_id: f.ward_id, statuses: [...f.statuses], source: f.source,
                        bed_number: f.bed_number, search: f.search, from_date: f.from_date, to_date: f.to_date,
                        range: f.from_date || f.to_date ? 'custom' : 'all',
                        columns: [...this.visible],
                    });
                    this.refreshCount();
                },
                setRange(key) {
                    const today = new Date();
                    const start = new Date(today);
                    let from = '', to = '';
                    if (key === 'today') {
                        from = to = ymd(today);
                    } else if (key === 'yesterday') {
                        start.setDate(start.getDate() - 1);
                        from = to = ymd(start);
                    } else if (key === 'week') {
                        start.setDate(start.getDate() - 6);
                        from = ymd(start);
                        to = ymd(today);
                    } else if (key === 'month') {
                        from = ymd(new Date(today.getFullYear(), today.getMonth(), 1));
                        to = ymd(today);
                    } else if (key === 'lastMonth') {
                        from = ymd(new Date(today.getFullYear(), today.getMonth() - 1, 1));
                        to = ymd(new Date(today.getFullYear(), today.getMonth(), 0));
                    }
                    this.print.range = key;
                    this.print.from_date = from;
                    this.print.to_date = to;
                    this.refreshCount();
                },
                params(forReport) {
                    const p = new URLSearchParams();
                    const f = this.print;
                    if (f.ward_id) p.append('ward_id', f.ward_id);
                    f.statuses.forEach(s => p.append('statuses[]', s));
                    if (f.source) p.append('source', f.source);
                    if (f.bed_number.trim()) p.append('bed_number', f.bed_number.trim());
                    if (f.search.trim()) p.append('search', f.search.trim());
                    if (f.from_date) p.append('from_date', f.from_date);
                    if (f.to_date) p.append('to_date', f.to_date);
                    if (forReport) {
                        this.columns.filter(c => f.columns.includes(c.key)).forEach(c => p.append('columns[]', c.key));
                        p.append('sort', f.sort);
                        p.append('orientation', f.orientation);
                        p.append('group', f.group);
                        p.append('summary', f.summary ? '1' : '0');
                        p.append('autoprint', '1');
                    }
                    return p;
                },
                refreshCount() {
                    clearTimeout(this.countTimer);
                    this.counts.loading = true;
                    this.countTimer = setTimeout(() => this.fetchCount(), 200);
                },
                async fetchCount() {
                    const request = ++this.countRequest;
                    try {
                        const response = await fetch(config.urls.summary + '?' + this.params(false), {
                            headers: { Accept: 'application/json' },
                        });
                        const data = await response.json();
                        if (request !== this.countRequest) return;
                        this.counts.total = data.total;
                        this.counts.byStatus = data.by_status || {};
                        this.counts.max = data.max;
                    } catch (e) {
                        if (request === this.countRequest) this.counts.total = null;
                    } finally {
                        if (request === this.countRequest) this.counts.loading = false;
                    }
                },
                openReport() {
                    const url = config.urls.print + '?' + this.params(true);
                    // A new tab keeps this list open; fall back to this frame if pop-ups are blocked
                    if (!window.open(url, '_blank')) window.location.href = url;
                    this.print.open = false;
                },
            };
        }
    </script>
</body>

</html>
