@php
    /**
     * The whole infusions panel: source strip, counters, infusion cards, the
     * pumps the engine can see that nobody has bound yet, and what finished
     * recently.
     *
     * The page re-fetches this same partial on a timer (?fragment=1) and
     * swaps it in, so one file is both the first paint and every refresh.
     */
    $tones = [
        'alarming' => ['ring' => 'ring-red-300', 'bar' => 'bg-red-500', 'chip' => 'bg-red-500', 'fill' => 'bg-red-500', 'tint' => 'bg-red-50/60'],
        'warning' => ['ring' => 'ring-amber-300', 'bar' => 'bg-amber-500', 'chip' => 'bg-amber-500', 'fill' => 'bg-amber-500', 'tint' => 'bg-amber-50/60'],
        'running' => ['ring' => 'ring-emerald-200', 'bar' => 'bg-emerald-500', 'chip' => 'bg-emerald-500', 'fill' => 'bg-emerald-500', 'tint' => 'bg-white'],
        'paused' => ['ring' => 'ring-yellow-200', 'bar' => 'bg-yellow-400', 'chip' => 'bg-yellow-500', 'fill' => 'bg-yellow-400', 'tint' => 'bg-white'],
        'stopped' => ['ring' => 'ring-slate-200', 'bar' => 'bg-slate-400', 'chip' => 'bg-slate-500', 'fill' => 'bg-slate-400', 'tint' => 'bg-white'],
        'completed' => ['ring' => 'ring-sky-200', 'bar' => 'bg-sky-400', 'chip' => 'bg-sky-500', 'fill' => 'bg-sky-400', 'tint' => 'bg-white'],
        'pending' => ['ring' => 'ring-gray-200', 'bar' => 'bg-gray-300', 'chip' => 'bg-gray-400', 'fill' => 'bg-gray-300', 'tint' => 'bg-white'],
    ];

    $tiles = [
        ['key' => 'running', 'label' => 'Running', 'filter' => 'running', 'text' => 'text-emerald-600', 'active' => 'bg-emerald-50 ring-emerald-400'],
        ['key' => 'warnings', 'label' => 'Ending soon', 'filter' => 'warnings', 'text' => 'text-amber-600', 'active' => 'bg-amber-50 ring-amber-400'],
        ['key' => 'alarms', 'label' => 'Alarms', 'filter' => 'alarms', 'text' => 'text-red-600', 'active' => 'bg-red-50 ring-red-400'],
        ['key' => 'paused', 'label' => 'Paused', 'filter' => 'paused', 'text' => 'text-yellow-600', 'active' => 'bg-yellow-50 ring-yellow-400'],
        ['key' => 'completed', 'label' => 'Completed', 'filter' => 'completed', 'text' => 'text-sky-600', 'active' => 'bg-sky-50 ring-sky-400'],
    ];

    $query = fn (array $params) => '?' . http_build_query(array_merge(
        ['ward_id' => $wardId, 'tab' => 'infusions', 'filter' => $filter],
        $params
    ));
@endphp

<div id="infusionPanel" data-refreshed-at="{{ now()->toIso8601String() }}">

    {{-- Where the numbers come from, and how fresh they are. --}}
    @if (!empty($engineError))
        <div class="mb-3 flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
            <svg class="mt-px h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <span class="font-medium">{{ $engineError }}</span>
        </div>
    @elseif (($dataSource ?? 'local') === 'engine')
        @php
            $lastSeen = $engineMeta['last_seen_at'] ?? null;
            $silentFor = $lastSeen ? $lastSeen->diffInSeconds(now()) : null;
            $quiet = $silentFor !== null && $silentFor > 120;
        @endphp
        <div
            class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-1 rounded-lg border px-3 py-2 text-xs {{ $quiet ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-indigo-200 bg-indigo-50 text-indigo-700' }}">
            <span class="inline-flex items-center font-semibold">
                <span
                    class="mr-2 h-1.5 w-1.5 rounded-full {{ $quiet ? 'bg-amber-500' : 'bg-indigo-500 animate-pulse' }}"></span>
                Live from Qmed Infusion Engine
            </span>
            <span class="opacity-80">
                {{ $engineMeta['pump_count'] }} {{ Str::plural('pump', $engineMeta['pump_count']) }} seen ·
                {{ $engineMeta['linked_count'] }} linked to a patient
            </span>
            @if ($lastSeen)
                <span class="opacity-80" title="{{ $lastSeen->format('Y-m-d H:i:s') }}">
                    Last pump message {{ $lastSeen->diffForHumans() }}
                </span>
            @endif
        </div>
    @else
        <div
            class="mb-3 inline-flex items-center rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-medium text-gray-600">
            <span class="mr-2 h-1.5 w-1.5 rounded-full bg-gray-400"></span>
            SmartWard database (engine mode is off)
        </div>
    @endif

    {{-- Counters double as the filter. --}}
    @php $transfusing = $bloodTransfusions ?? collect(); @endphp
    <div class="mb-4 grid grid-cols-3 gap-2 {{ $transfusing->isNotEmpty() ? 'sm:grid-cols-6' : 'sm:grid-cols-5' }}">
        @foreach ($tiles as $tile)
            @php $count = $stats[$tile['key']] ?? 0; @endphp
            <a href="{{ $query(['filter' => $tile['filter']]) }}"
                class="rounded-xl px-3 py-2.5 text-center ring-1 transition-all {{ $filter === $tile['filter'] ? $tile['active'] . ' ring-2' : 'bg-white ring-gray-200 hover:ring-gray-300' }}">
                <div
                    class="text-xl font-bold leading-tight {{ $count > 0 ? $tile['text'] : 'text-gray-300' }} {{ $count > 0 && $tile['key'] === 'alarms' ? 'blink-alarm' : '' }} {{ $count > 0 && $tile['key'] === 'warnings' ? 'pulse-warning' : '' }}">
                    {{ $count }}
                </div>
                <div class="text-[11px] font-medium text-gray-500">{{ $tile['label'] }}</div>
            </a>
        @endforeach
        @if ($transfusing->isNotEmpty())
            <div class="rounded-xl bg-white px-3 py-2.5 text-center ring-1 ring-rose-200">
                <div class="text-xl font-bold leading-tight text-rose-600">{{ $transfusing->count() }}</div>
                <div class="text-[11px] font-medium text-gray-500">Transfusing</div>
            </div>
        @endif
    </div>

    @if ($filter !== 'active')
        <div class="mb-3 flex items-center gap-2 text-xs text-gray-500">
            <span>Showing <span class="font-semibold text-gray-700">{{ $filter }}</span></span>
            <a href="{{ $query(['filter' => 'active']) }}"
                class="rounded-md px-2 py-0.5 font-medium text-indigo-600 hover:bg-indigo-50">Back to active</a>
        </div>
    @endif

    {{-- Blood units running in this ward. Kept separate from the pump
         infusions below: blood has no pump, no device and no alarm feed. --}}
    @if ($transfusing->isNotEmpty())
        <div class="mb-6">
            <h3 class="mb-3 flex items-center text-lg font-bold text-gray-700">
                <span class="mr-2 h-3 w-3 rounded-full bg-rose-500"></span>
                Blood Transfusions in Progress
                <span class="ml-2 text-xs font-medium text-gray-500">({{ $transfusing->count() }})</span>
            </h3>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($transfusing as $transfusion)
                    <x-transfusion-card :transfusion="$transfusion" :show-patient="true" />
                @endforeach
            </div>
        </div>
    @endif

    {{-- ------------------------------------------------------ infusion cards --}}
    @if ($infusions->count() > 0)
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($infusions as $infusion)
                @php
                    $pump = $infusion->infusionPump;
                    $state = $pumpStates[$infusion->infusion_pump_id] ?? null;

                    $key = $infusion->status === 'running' && $infusion->is_warning ? 'warning' : $infusion->status;
                    $tone = $tones[$key] ?? $tones['pending'];

                    // Battery, power and last contact are live from the engine
                    // when it is the source; the registry row is the fallback.
                    $battery = $state['battery_percent'] ?? $pump?->battery_percent;
                    $power = $state['power_status'] ?? $pump?->power_status;
                    $seenAt = isset($state['last_seen_at'])
                        ? \Carbon\Carbon::parse($state['last_seen_at'])
                        : $pump?->last_seen_at;
                    $stale = $seenAt && $seenAt->lt(now()->subMinutes(2));

                    $rateUnit = $state['flow_rate_unit'] ?? 'mL/h';
                    $pumpName = $state['pump_label'] ?? $pump?->serial_no ?? $pump?->device_id;
                    $pumpModel = $state['pump_model'] ?? $pump?->pump_model;
                @endphp

                <div
                    class="overflow-hidden rounded-xl ring-1 shadow-sm {{ $tone['ring'] }} {{ $tone['tint'] }} {{ $infusion->status === 'alarming' ? 'ring-2' : '' }}">
                    <div class="h-1 {{ $tone['bar'] }} {{ $infusion->status === 'alarming' ? 'blink-alarm' : '' }}">
                    </div>

                    {{-- Who --}}
                    <div class="flex items-start justify-between gap-2 px-3 pt-2.5">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span
                                    class="rounded bg-gray-900 px-1.5 py-0.5 text-xs font-bold text-white">{{ $infusion->patient->bed_number ?? '—' }}</span>
                                <span
                                    class="truncate text-sm font-semibold text-gray-800">{{ $infusion->patient->name ?? 'Unknown patient' }}</span>
                            </div>
                            <div class="mt-0.5 text-[11px] text-gray-500">MRN
                                {{ $infusion->patient->mrn ?? 'N/A' }}</div>
                        </div>
                        <span
                            class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white {{ $tone['chip'] }}">
                            {{ $key === 'warning' ? 'Ending soon' : $infusion->status }}
                        </span>
                    </div>

                    {{-- Alarm first: it is the reason someone is looking at this card. --}}
                    @if ($infusion->status === 'alarming' && $infusion->alarm_message)
                        @php
                            $alarmTone = match ($infusion->alarm_priority) {
                                'high' => 'bg-red-100 text-red-800 border-red-300',
                                'medium' => 'bg-orange-100 text-orange-800 border-orange-300',
                                'low' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
                                'technical' => 'bg-sky-100 text-sky-800 border-sky-300',
                                default => 'bg-red-100 text-red-800 border-red-300',
                            };
                        @endphp
                        <div class="mx-3 mt-2 flex items-start gap-1.5 rounded-lg border px-2 py-1.5 text-xs {{ $alarmTone }}">
                            <svg class="mt-px h-3.5 w-3.5 flex-shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            <div class="min-w-0">
                                @if ($infusion->alarm_priority)
                                    <span
                                        class="font-bold uppercase">{{ $infusion->alarm_priority_display }}</span>
                                    ·
                                @endif
                                <span class="font-semibold">{{ $infusion->alarm_message }}</span>
                            </div>
                        </div>
                    @endif

                    {{-- What is running, and how fast --}}
                    <div class="flex items-end justify-between gap-2 px-3 pt-2">
                        <div class="min-w-0">
                            <div class="truncate text-sm font-bold text-gray-900"
                                title="{{ $infusion->medication_name }}">{{ $infusion->medication_name }}</div>
                            @if ($infusion->formatted_concentration)
                                <div class="text-[11px] text-gray-500">{{ $infusion->formatted_concentration }}</div>
                            @endif
                        </div>
                        <div class="shrink-0 text-right">
                            <div class="text-lg font-bold leading-none text-gray-900">
                                {{ $infusion->flow_rate !== null ? number_format($infusion->flow_rate, 1) : '--' }}
                            </div>
                            <div class="text-[10px] tracking-wide text-gray-400">{{ $rateUnit }}</div>
                        </div>
                    </div>

                    {{-- Progress --}}
                    <div class="px-3 pt-2">
                        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200">
                            <div class="h-2 rounded-full transition-all duration-700 {{ $tone['fill'] }}"
                                style="width: {{ max(1.5, (float) $infusion->progress_percent) }}%"></div>
                        </div>
                        <div class="mt-1 flex items-center justify-between text-[11px] text-gray-500">
                            <span>
                                {{ number_format((float) $infusion->infused_volume, 1) }} /
                                {{ $infusion->total_volume ? number_format((float) $infusion->total_volume, 1) : '--' }}
                                mL
                            </span>
                            <span class="font-semibold text-gray-700">{{ $infusion->progress_percent }}%</span>
                        </div>
                    </div>

                    {{-- When it ends --}}
                    <div class="mx-3 mt-2 grid grid-cols-2 gap-2">
                        <div
                            class="rounded-lg px-2 py-1.5 {{ $infusion->is_warning ? 'bg-amber-100' : 'bg-gray-50' }}">
                            <div
                                class="text-[10px] uppercase tracking-wide {{ $infusion->is_warning ? 'text-amber-700' : 'text-gray-400' }}">
                                Time left</div>
                            <div
                                class="text-sm font-bold {{ $infusion->is_warning ? 'text-amber-800' : 'text-gray-800' }}">
                                {{ $infusion->formatted_remaining_time }}</div>
                        </div>
                        <div class="rounded-lg bg-gray-50 px-2 py-1.5">
                            <div class="text-[10px] uppercase tracking-wide text-gray-400">Finishes</div>
                            <div class="text-sm font-bold text-gray-800">
                                {{ $infusion->estimated_completion ? $infusion->estimated_completion->format('H:i') : '--:--' }}
                            </div>
                        </div>
                    </div>

                    {{-- The pump itself --}}
                    <div
                        class="mt-2.5 flex items-center justify-between gap-2 border-t border-gray-100 bg-white/70 px-3 py-1.5 text-[11px]">
                        <span class="truncate text-gray-600" title="{{ $pumpModel ?? $pumpName }}">
                            <span class="font-semibold text-gray-700">{{ $pumpName ?? 'Pump' }}</span>
                            @if ($pumpModel)
                                · {{ Str::limit($pumpModel, 22) }}
                            @endif
                        </span>
                        <span class="flex shrink-0 items-center gap-2">
                            @if ($power === 'mains')
                                <span class="flex items-center text-emerald-600" title="On mains power">
                                    <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </span>
                            @endif
                            @if ($battery !== null)
                                @php
                                    $batteryTone = $battery >= 50 ? 'text-emerald-600' : ($battery >= 20 ? 'text-amber-600' : 'text-red-600');
                                @endphp
                                <span class="flex items-center {{ $batteryTone }}"
                                    title="Battery {{ (int) $battery }}%">
                                    <svg class="mr-0.5 h-3.5 w-3.5" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <rect x="2" y="7" width="16" height="10" rx="2"
                                            stroke-width="2" />
                                        <path stroke-linecap="round" stroke-width="2" d="M21 10v4" />
                                    </svg>
                                    {{ (int) $battery }}%
                                </span>
                            @endif
                            @if ($stale)
                                <span class="rounded bg-amber-100 px-1.5 py-0.5 font-semibold text-amber-700"
                                    title="Last message {{ $seenAt->format('Y-m-d H:i:s') }}">
                                    Quiet {{ $seenAt->diffForHumans(null, true) }}
                                </span>
                            @elseif ($seenAt)
                                <span class="text-gray-400"
                                    title="{{ $seenAt->format('Y-m-d H:i:s') }}">{{ $seenAt->diffForHumans(null, true) }}
                                    ago</span>
                            @endif
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="rounded-xl border border-gray-200 bg-white p-8 text-center">
            <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-gray-100">
                <svg class="h-7 w-7 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                </svg>
            </div>
            <h3 class="text-base font-semibold text-gray-700">
                @if ($filter === 'alarms')
                    No alarms
                @elseif ($filter === 'warnings')
                    Nothing ending soon
                @elseif ($filter === 'completed')
                    Nothing completed
                @else
                    No infusions to show
                @endif
            </h3>
            <p class="mt-1 text-sm text-gray-500">
                @if ($unlinkedPumps->count() > 0)
                    {{ $unlinkedPumps->count() }} {{ Str::plural('pump', $unlinkedPumps->count()) }} reporting below
                    still {{ $unlinkedPumps->count() === 1 ? 'needs' : 'need' }} a patient.
                @else
                    Infusions appear here once a pump is reporting and bound to a patient.
                @endif
            </p>
        </div>
    @endif

    {{-- ----------------------------------------------------- unlinked pumps --}}
    @if ($unlinkedPumps->count() > 0)
        <div class="mt-6">
            <div class="mb-2 flex items-center justify-between">
                <h3 class="flex items-center text-sm font-bold text-gray-700">
                    <span class="mr-2 h-2 w-2 rounded-full bg-amber-400"></span>
                    Reporting, not linked to a patient
                    <span
                        class="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700">{{ $unlinkedPumps->count() }}</span>
                </h3>
                <span class="text-[11px] text-gray-400">Bind on the ward dashboard: open the bed → Link Pump</span>
            </div>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($unlinkedPumps as $enginePump)
                    @php
                        $seen = isset($enginePump['last_seen_at']) ? \Carbon\Carbon::parse($enginePump['last_seen_at']) : null;
                        $alarming = !empty($enginePump['active_alarm']);
                    @endphp
                    <div
                        class="rounded-lg border bg-white px-3 py-2 {{ $alarming ? 'border-red-300 bg-red-50/50' : 'border-amber-200' }}">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-semibold text-gray-800">
                                    {{ $enginePump['pump_label'] ?: $enginePump['device_id'] }}
                                </div>
                                <div class="truncate font-mono text-[10px] text-gray-400"
                                    title="{{ $enginePump['device_id'] }}">{{ $enginePump['device_id'] }}</div>
                            </div>
                            <span
                                class="shrink-0 whitespace-nowrap rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase {{ $alarming ? 'bg-red-500 text-white' : 'bg-gray-100 text-gray-600' }}">
                                {{ $alarming ? 'Alarm' : ($enginePump['pump_status'] ?? 'unknown') }}
                            </span>
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[11px] text-gray-500">
                            @if (!empty($enginePump['drug_name']))
                                <span class="font-medium text-gray-700">{{ $enginePump['drug_name'] }}</span>
                            @endif
                            @if (isset($enginePump['flow_rate']))
                                <span>{{ number_format((float) $enginePump['flow_rate'], 1) }}
                                    {{ $enginePump['flow_rate_unit'] ?? 'mL/h' }}</span>
                            @endif
                            @if (!empty($enginePump['ward']))
                                <span>{{ $enginePump['ward'] }}</span>
                            @endif
                            @if ($seen)
                                <span title="{{ $seen->format('Y-m-d H:i:s') }}">{{ $seen->diffForHumans(null, true) }}
                                    ago</span>
                            @endif
                        </div>
                        @if ($alarming)
                            <div class="mt-1 text-[11px] font-semibold text-red-700">{{ $enginePump['active_alarm'] }}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- --------------------------------------------------- recently finished --}}
    @if ($filter === 'active' && $recentlyCompleted->count() > 0)
        <div class="mt-6">
            <h3 class="mb-2 flex items-center text-sm font-bold text-gray-700">
                <span class="mr-2 h-2 w-2 rounded-full bg-sky-400"></span>
                Completed in the last 24 hours
                <span
                    class="ml-2 rounded-full bg-sky-100 px-2 py-0.5 text-[11px] font-semibold text-sky-700">{{ $recentlyCompleted->count() }}</span>
            </h3>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($recentlyCompleted as $done)
                    <div class="rounded-lg border border-sky-200 bg-sky-50/50 px-3 py-2">
                        <div class="flex items-center justify-between gap-2">
                            <span class="truncate text-sm font-semibold text-gray-800">
                                <span
                                    class="mr-1 rounded bg-gray-900 px-1.5 py-0.5 text-[10px] font-bold text-white">{{ $done->patient->bed_number ?? '—' }}</span>
                                {{ $done->patient->name ?? 'Unknown' }}
                            </span>
                            <span class="shrink-0 text-[11px] text-gray-500">
                                {{ $done->completed_at ? $done->completed_at->format('H:i') : '' }}
                            </span>
                        </div>
                        <div class="mt-0.5 text-[11px] text-gray-600">
                            {{ $done->medication_name }} ·
                            {{ number_format((float) $done->total_volume, 1) }} mL
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
