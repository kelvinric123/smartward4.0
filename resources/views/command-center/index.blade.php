<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-bold text-2xl text-slate-800 leading-tight">{{ __('Command Center') }}</h2>
                <p class="text-sm text-slate-500 mt-1">Live clinical watch, with ward, bed, patient, vitals, risk &amp; nutrition analytics</p>
            </div>
            <div class="text-sm text-slate-500">{{ date('l, F j, Y') }}</div>
        </div>
    </x-slot>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // The live board's auto-refresh comes back quietly: no entry animations or count-up on a wall screen
        try {
            if (sessionStorage.getItem('ccQuietReload') === '1') document.documentElement.classList.add('cc-quiet');
        } catch (e) {}
    </script>

    @php
        $activeTab = in_array(request('tab'), ['live', 'ward', 'bed', 'patient', 'vitals', 'risk', 'diet']) ? request('tab') : 'live';

        $icons = [
            'building'  => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
            'bed'       => 'M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z',
            'users'     => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
            'user'      => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
            'check'     => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
            'login'     => 'M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1',
            'logout'    => 'M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1',
            'clock'     => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
            'heart'     => 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z',
            'warning'   => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
            'shield'    => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
            'clipboard' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
            'ban'       => 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636',
            'beaker'    => 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z',
            'lightning' => 'M13 10V3L4 14h7v7l9-11h-7z',
            'chart'     => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
            'eye'       => 'M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z',
        ];
    @endphp

    {{-- The open tab is kept in the address, so a refresh or a bookmark comes back to it --}}
    <div class="py-6" x-data="{ tab: '{{ $activeTab }}' }"
        x-init="$watch('tab', v => {
            window.dispatchEvent(new CustomEvent('cc-tab', { detail: v }));
            try {
                const url = new URL(window.location.href);
                url.searchParams.set('tab', v);
                window.history.replaceState(null, '', url);
            } catch (e) {}
        })">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

            {{-- ------ Filter bar ------ --}}
            <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                <form method="GET" action="{{ route('command-center.index') }}"
                    class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
                    <input type="hidden" name="tab" x-bind:value="tab">

                    <div class="lg:col-span-2">
                        <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Ward</label>
                        <select name="ward_id"
                            class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All Wards</option>
                            @foreach($wards as $ward)
                                <option value="{{ $ward->id }}" {{ $selectedWardId == $ward->id ? 'selected' : '' }}>
                                    {{ $ward->ward_name }} ({{ $ward->ward_code }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">From</label>
                        <input type="date" name="start_date" value="{{ $startDate->format('Y-m-d') }}"
                            class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">To</label>
                        <input type="date" name="end_date" value="{{ $endDate->format('Y-m-d') }}"
                            class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Trend Year</label>
                        <select name="year"
                            class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach($availableYears as $y)
                                <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Compare</label>
                        <select name="compare_year"
                            class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">— none —</option>
                            @foreach($availableYears as $y)
                                <option value="{{ $y }}" {{ $compareYear == $y ? 'selected' : '' }}>{{ $y }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-2 lg:col-span-6 flex flex-wrap items-center gap-2 pt-1 border-t border-slate-100">
                        <span class="text-xs text-slate-500 mr-2">Quick range:</span>
                        @php
                            $quick = [
                                'Today' => [now()->format('Y-m-d'), now()->format('Y-m-d')],
                                'This week' => [now()->startOfWeek()->format('Y-m-d'), now()->endOfWeek()->format('Y-m-d')],
                                'This month' => [now()->startOfMonth()->format('Y-m-d'), now()->endOfMonth()->format('Y-m-d')],
                                'Last 30 days' => [now()->subDays(29)->format('Y-m-d'), now()->format('Y-m-d')],
                                'Last 90 days' => [now()->subDays(89)->format('Y-m-d'), now()->format('Y-m-d')],
                                'This year' => [now()->startOfYear()->format('Y-m-d'), now()->endOfYear()->format('Y-m-d')],
                            ];
                        @endphp
                        @foreach($quick as $label => [$s, $e])
                            <a href="{{ route('command-center.index', array_merge(request()->except(['start_date', 'end_date']), ['start_date' => $s, 'end_date' => $e])) }}"
                                class="text-xs px-2.5 py-1 rounded-md border border-slate-200 text-slate-600 hover:bg-indigo-50 hover:border-indigo-300 hover:text-indigo-700 transition-all duration-200">
                                {{ $label }}
                            </a>
                        @endforeach

                        <div class="ml-auto flex items-center gap-2">
                            <a href="{{ route('command-center.index') }}"
                                class="px-3 py-1.5 text-sm text-slate-600 hover:text-slate-900 transition-colors">
                                Reset
                            </a>
                            <button type="submit"
                                class="px-4 py-1.5 bg-gradient-to-r from-indigo-600 to-violet-600 text-white text-sm rounded-lg font-medium hover:from-indigo-500 hover:to-violet-500 shadow-sm hover:shadow transition-all duration-200">
                                Apply
                            </button>
                        </div>
                    </div>
                </form>
                <div class="mt-3 text-xs text-slate-500">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700 font-medium">
                        {{ $selectedWard ? $selectedWard->ward_name : 'All Wards' }}
                    </span>
                    <span class="mx-2">·</span>
                    <span>{{ $startDate->format('M j, Y') }} → {{ $endDate->format('M j, Y') }}</span>
                </div>
            </div>

            {{-- ------ Tabs ------ --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
                <div class="border-b border-slate-200 px-2">
                    <nav class="flex gap-1 overflow-x-auto">
                        @php
                            $tabs = [
                                'live'    => ['label' => 'Live',        'icon' => $icons['lightning'], 'active' => 'text-red-600 border-red-500 bg-red-50/40', 'badge' => $live['attention_total']],
                                'ward'    => ['label' => 'Ward',        'icon' => $icons['building'],  'active' => 'text-indigo-600 border-indigo-500 bg-indigo-50/40'],
                                'bed'     => ['label' => 'Beds',        'icon' => $icons['bed'],       'active' => 'text-sky-600 border-sky-500 bg-sky-50/40'],
                                'patient' => ['label' => 'Patients',    'icon' => $icons['users'],     'active' => 'text-emerald-600 border-emerald-500 bg-emerald-50/40'],
                                'vitals'  => ['label' => 'Vital Signs', 'icon' => $icons['heart'],     'active' => 'text-rose-600 border-rose-500 bg-rose-50/40'],
                                'risk'    => ['label' => 'Risk & Safety','icon' => $icons['warning'],  'active' => 'text-amber-600 border-amber-500 bg-amber-50/40'],
                                'diet'    => ['label' => 'Diet & Nutrition', 'icon' => $icons['clipboard'], 'active' => 'text-teal-600 border-teal-500 bg-teal-50/40'],
                            ];
                        @endphp
                        @foreach($tabs as $key => $info)
                            <button type="button" @click="tab = '{{ $key }}'"
                                :class="tab === '{{ $key }}'
                                    ? '{{ $info['active'] }}'
                                    : 'text-slate-500 border-transparent hover:text-slate-800 hover:bg-slate-50'"
                                class="flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 rounded-t-lg transition-all duration-200 whitespace-nowrap">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $info['icon'] }}" />
                                </svg>
                                {{ $info['label'] }}
                                @if(!empty($info['badge']))
                                    <span class="min-w-[1.25rem] px-1.5 py-0.5 rounded-full bg-red-600 text-white text-[10px] font-bold leading-none text-center"
                                        title="{{ $info['badge'] }} {{ \Illuminate\Support\Str::plural('patient', $info['badge']) }} needing attention">{{ $info['badge'] }}</span>
                                @endif
                            </button>
                        @endforeach
                    </nav>
                </div>

                {{-- ===================== LIVE TAB ===================== --}}
                {{-- What needs attention across the wards right now (CommandCenterLive); the date range does not apply --}}
                @php
                    $ls = $live['stats'];
                    $when = fn ($at) => $at->isToday() ? $at->format('H:i') : $at->format('d M H:i');
                    $details = fn (array $row, ?string $openTab = null) => route('ward.patient-details', array_filter(['patient_id' => $row['id'], 'open_tab' => $openTab]));
                    $ewsChips = ['urgent' => 'bg-red-600 text-white', 'warning' => 'bg-amber-400 text-white', 'normal' => 'bg-emerald-500 text-white', 'old' => 'bg-slate-200 text-slate-600'];
                    $ewsHours = \App\Services\CommandCenterLive::EWS_CURRENT_HOURS;
                    $reasonChip = 'inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs transition-colors';
                    // A ward board count: blank when none, a coloured chip when some
                    $count = fn (int $n, string $tone) => $n > 0
                        ? '<span class="inline-flex min-w-[1.75rem] justify-center rounded px-1.5 py-0.5 text-xs font-semibold tabular-nums ' . $tone . '">' . $n . '</span>'
                        : '<span class="text-slate-300">&ndash;</span>';
                    $recentHours = \App\Support\ClinicalIndicatorReadings::RECENT_HOURS;
                @endphp
                <div x-show="tab === 'live'" x-cloak
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="p-5 space-y-5">

                    {{-- Right now, and keeping it current --}}
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-slate-600">
                            <span class="relative flex h-2.5 w-2.5">
                                <span class="absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75 animate-ping"></span>
                                <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                            </span>
                            <span class="font-semibold text-slate-800">Right now</span>
                            <span class="text-slate-300">&middot;</span>
                            <span>{{ $selectedWard ? $selectedWard->ward_name : 'All wards' }}</span>
                            <span class="text-slate-300">&middot;</span>
                            <span>Updated {{ $live['generated_at']->format('H:i:s') }}</span>
                            <span class="text-xs text-slate-400">The date range applies to the other tabs.</span>
                        </div>
                        {{-- Reloads while this tab is open and the screen is visible; the choice is remembered on this browser --}}
                        <div x-data="{
                                auto: true,
                                init() {
                                    try { this.auto = localStorage.getItem('ccLiveRefresh') !== 'off' } catch (e) {}
                                    const panel = this.$el;
                                    setInterval(() => {
                                        if (!this.auto || panel.offsetParent === null || document.visibilityState !== 'visible') return;
                                        try { sessionStorage.setItem('ccQuietReload', '1') } catch (e) {}
                                        window.location.reload();
                                    }, 60000);
                                },
                                toggle() {
                                    this.auto = !this.auto;
                                    try { localStorage.setItem('ccLiveRefresh', this.auto ? 'on' : 'off') } catch (e) {}
                                },
                            }">
                            <button type="button" @click="toggle()"
                                :class="auto ? 'border-emerald-300 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50'"
                                class="inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 text-xs font-medium transition-colors">
                                <span class="h-2 w-2 rounded-full" :class="auto ? 'bg-emerald-500' : 'bg-slate-300'"></span>
                                <span x-text="auto ? 'Auto-refresh every minute' : 'Auto-refresh off'">Auto-refresh every minute</span>
                            </button>
                        </div>
                    </div>

                    {{-- Short labels: the cards are narrow at six across --}}
                    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 cc-stagger">
                        <x-cc-stat label="Inpatients" :value="$ls['inpatients']"
                            :hint="$ls['pending_discharge'] . ' to discharge'" color="indigo" :icon="$icons['users']" />
                        <x-cc-stat label="Crit. care" :value="$ls['cc_wards'] ? $ls['cc_occupied'] . ' / ' . $ls['cc_beds'] : '–'"
                            :hint="$ls['cc_wards'] ? $ls['ventilated'] . ' ventilated' : 'No CC ward'" color="cyan" :icon="$icons['bed']" />
                        <x-cc-stat label="EWS 5+" :value="$ls['ews_urgent']"
                            :hint="$ls['ews_warning'] . ' at EWS 3–4'" color="red" :icon="$icons['warning']" />
                        <x-cc-stat label="Escalations" :value="$ls['escalations']"
                            :hint="'Monitors, ' . $recentHours . ' h'" color="rose" :icon="$icons['heart']" />
                        <x-cc-stat label="Overdue" :value="$ls['overdue_care']"
                            hint="Scales & doses" color="amber" :icon="$icons['clock']" />
                        <x-cc-stat label="Alerts" :value="$ls['alerts']"
                            :hint="$ls['alerts_urgent'] . ' urgent'" color="violet" :icon="$icons['lightning']" />
                    </div>

                    {{-- The patients to look at first --}}
                    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                        <div class="px-5 py-3 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2">
                            <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-700 uppercase tracking-wider">
                                <span class="w-2 h-2 rounded-full bg-gradient-to-r from-red-500 to-amber-500"></span>
                                Needs attention now
                            </h3>
                            <span class="text-xs text-slate-400">
                                {{ $live['attention_total'] }} {{ \Illuminate\Support\Str::plural('patient', $live['attention_total']) }} &middot; most urgent first
                            </span>
                        </div>
                        @if(empty($live['attention']))
                            <div class="px-5 py-10 text-center">
                                <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons['check'] }}" />
                                    </svg>
                                </div>
                                <p class="text-sm font-medium text-slate-700">Nobody needs attention right now</p>
                                <p class="mt-1 text-xs text-slate-400">No raised EWS, escalating monitor readings, overdue assessments or doses, fluid balance alerts or urgent alerts.</p>
                            </div>
                        @else
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-slate-50 text-slate-500 uppercase text-[11px] tracking-wider">
                                        <tr>
                                            <th class="px-5 py-2.5 text-left font-semibold">Patient</th>
                                            <th class="px-3 py-2.5 text-left font-semibold">Ward &middot; Bed</th>
                                            <th class="px-3 py-2.5 text-left font-semibold">EWS</th>
                                            <th class="px-3 py-2.5 text-left font-semibold">Why</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach($live['attention'] as $row)
                                            <tr class="align-top hover:bg-slate-50/70 transition-colors">
                                                <td class="px-5 py-3">
                                                    <a href="{{ $details($row) }}" target="_blank" rel="noopener"
                                                        class="font-medium text-slate-800 hover:text-indigo-600">{{ $row['name'] }}</a>
                                                    <div class="text-xs text-slate-400">{{ $row['mrn'] }}</div>
                                                    @if($row['pending_discharge'] || $row['ventilated'] || $row['transfusing'])
                                                        <div class="mt-1 flex flex-wrap gap-1">
                                                            @if($row['ventilated'])
                                                                <span class="rounded bg-cyan-50 px-1.5 py-0.5 text-[10px] font-semibold text-cyan-700">Ventilated</span>
                                                            @endif
                                                            @if($row['transfusing'])
                                                                <span class="rounded bg-rose-50 px-1.5 py-0.5 text-[10px] font-semibold text-rose-700">Transfusing</span>
                                                            @endif
                                                            @if($row['pending_discharge'])
                                                                <span class="rounded bg-yellow-50 px-1.5 py-0.5 text-[10px] font-semibold text-yellow-700">Pending discharge</span>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-3 whitespace-nowrap">
                                                    <span class="text-slate-700">{{ $row['ward'] }}</span>
                                                    @if($row['critical_care'])
                                                        <span class="ml-1 rounded bg-cyan-50 px-1 py-0.5 text-[10px] font-bold text-cyan-700" title="Critical care ward">CC</span>
                                                    @endif
                                                    <div class="text-xs text-slate-400">Bed {{ $row['bed'] ?: '–' }}</div>
                                                </td>
                                                <td class="px-3 py-3 whitespace-nowrap">
                                                    @if($row['ews'])
                                                        <span class="rounded px-1.5 py-0.5 text-xs font-bold {{ $ewsChips[$row['ews']['severity']] }}"
                                                            @if($row['ews']['severity'] === 'old') title="From vital signs over {{ $ewsHours }} h old, so not counted" @endif>EWS {{ $row['ews']['score'] }}</span>
                                                        <div class="mt-1 text-[11px] text-slate-400">
                                                            {{ $row['ews']['severity'] === 'old' ? 'Old · ' : '' }}{{ $when($row['ews']['at']) }}
                                                        </div>
                                                    @else
                                                        <span class="text-xs text-slate-400">No vitals</span>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-3">
                                                    <div class="flex flex-wrap gap-1.5">
                                                        @foreach($row['escalations'] as $escalation)
                                                            <a href="{{ $details($row, 'indicator-' . $escalation['indicator_id']) }}" target="_blank" rel="noopener"
                                                                class="{{ $reasonChip }} border-red-200 bg-red-50 text-red-700 hover:bg-red-100"
                                                                title="At an escalation level: inform the ICU doctor">
                                                                <span class="font-semibold">{{ $escalation['code'] }}</span> {{ $escalation['readings'] }}
                                                            </a>
                                                        @endforeach
                                                        @foreach($row['assessments_overdue'] as $overdue)
                                                            <a href="{{ $details($row, 'indicator-' . $overdue['indicator_id']) }}" target="_blank" rel="noopener"
                                                                class="{{ $reasonChip }} border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100">
                                                                <span class="font-semibold">{{ $overdue['code'] }}</span> overdue &middot; {{ $overdue['history'] }}
                                                            </a>
                                                        @endforeach
                                                        @if($row['doses_overdue'])
                                                            <a href="{{ $details($row, 'medications') }}" target="_blank" rel="noopener"
                                                                class="{{ $reasonChip }} border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100"
                                                                title="{{ implode(', ', $row['doses_overdue']) }}">
                                                                <span class="font-semibold">{{ count($row['doses_overdue']) }} {{ \Illuminate\Support\Str::plural('dose', count($row['doses_overdue'])) }} overdue</span>
                                                                {{ \Illuminate\Support\Str::limit(implode(', ', $row['doses_overdue']), 40) }}
                                                            </a>
                                                        @endif
                                                        @if($row['fluid'])
                                                            <a href="{{ $details($row, 'io') }}" target="_blank" rel="noopener"
                                                                class="{{ $reasonChip }} {{ $row['fluid']['level'] === 'critical' ? 'border-red-200 bg-red-50 text-red-700 hover:bg-red-100' : 'border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100' }}">
                                                                <span class="font-semibold">I/O</span> {{ $row['fluid']['title'] }}
                                                            </a>
                                                        @endif
                                                        @if($row['alerts_urgent'])
                                                            <span class="{{ $reasonChip }} border-violet-200 bg-violet-50 text-violet-700">
                                                                <span class="font-semibold">{{ $row['alerts_urgent'] }}</span> urgent {{ \Illuminate\Support\Str::plural('alert', $row['alerts_urgent']) }} pending
                                                            </span>
                                                        @endif
                                                        @if(!$row['escalations'] && !$row['assessments_overdue'] && !$row['doses_overdue'] && !$row['fluid'] && !$row['alerts_urgent'])
                                                            <span class="text-xs text-slate-500">Raised EWS</span>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if($live['attention_total'] > count($live['attention']))
                                <div class="border-t border-slate-100 px-5 py-2.5 text-xs text-slate-500">
                                    {{ $live['attention_total'] - count($live['attention']) }} more &middot; choose a ward above to see its whole list.
                                </div>
                            @endif
                        @endif
                    </div>

                    {{-- Every ward at a glance --}}
                    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                        <div class="px-5 py-3 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2">
                            <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-700 uppercase tracking-wider">
                                <span class="w-2 h-2 rounded-full bg-gradient-to-r from-indigo-500 to-cyan-500"></span>
                                Wards right now
                            </h3>
                            <span class="text-xs text-slate-400">Counts are patients &middot; a ward's name opens its dashboard</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50 text-slate-500 uppercase text-[11px] tracking-wider">
                                    <tr>
                                        <th class="px-5 py-2.5 text-left font-semibold">Ward</th>
                                        <th class="px-3 py-2.5 text-left font-semibold w-44">Beds</th>
                                        <th class="px-3 py-2.5 text-right font-semibold whitespace-nowrap" title="Pending discharge">To discharge</th>
                                        <th class="px-3 py-2.5 text-right font-semibold whitespace-nowrap" title="Prebooked, waiting for a bed">Incoming</th>
                                        <th class="px-3 py-2.5 text-right font-semibold whitespace-nowrap">EWS 5+</th>
                                        <th class="px-3 py-2.5 text-right font-semibold whitespace-nowrap">EWS 3–4</th>
                                        <th class="px-3 py-2.5 text-right font-semibold whitespace-nowrap" title="Hemodynamic or ventilator readings at an escalation level, last {{ $recentHours }} h">Escalating</th>
                                        <th class="px-3 py-2.5 text-right font-semibold whitespace-nowrap">Ventilated</th>
                                        <th class="px-3 py-2.5 text-right font-semibold whitespace-nowrap" title="Monitored assessments or medication doses overdue">Overdue</th>
                                        <th class="px-3 py-2.5 text-right font-semibold whitespace-nowrap" title="Fluid balance (I/O chart) alerts">I/O</th>
                                        <th class="px-5 py-2.5 text-right font-semibold whitespace-nowrap" title="Pending ward notifications">Alerts</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse($live['wards'] as $w)
                                        @php
                                            $wardBar = $w['occupancy'] >= 90 ? 'from-rose-500 to-red-500' : ($w['occupancy'] >= 75 ? 'from-amber-400 to-orange-500' : 'from-emerald-400 to-teal-500');
                                        @endphp
                                        <tr class="hover:bg-slate-50/70 transition-colors">
                                            <td class="px-5 py-3">
                                                <span class="whitespace-nowrap">
                                                    <a href="{{ $w['dashboard_url'] }}" class="font-medium text-slate-800 hover:text-indigo-600">{{ $w['name'] }}</a>
                                                    @if($w['critical_care'])
                                                        <span class="ml-1 rounded bg-cyan-50 px-1 py-0.5 text-[10px] font-bold text-cyan-700" title="Critical care ward: opens the Critical Care Ward Dashboard">CC</span>
                                                    @endif
                                                </span>
                                                <div class="text-xs text-slate-400">
                                                    {{ $w['code'] }}{{ $w['type'] ? ' · ' . $w['type'] : '' }}{{ $w['transfusing'] ? ' · ' . $w['transfusing'] . ' transfusing' : '' }}
                                                </div>
                                            </td>
                                            <td class="px-3 py-3">
                                                <div class="flex items-center gap-2">
                                                    <div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden">
                                                        <div class="cc-bar h-full rounded-full bg-gradient-to-r {{ $wardBar }}" style="width: {{ $w['occupancy'] }}%"></div>
                                                    </div>
                                                    <span class="text-xs tabular-nums text-slate-600 whitespace-nowrap">{{ $w['occupied'] }}/{{ $w['beds'] }}</span>
                                                </div>
                                            </td>
                                            <td class="px-3 py-3 text-right">{!! $count($w['pending_discharge'], 'bg-yellow-50 text-yellow-700') !!}</td>
                                            <td class="px-3 py-3 text-right">{!! $count($w['incoming'], 'bg-sky-50 text-sky-700') !!}</td>
                                            <td class="px-3 py-3 text-right">{!! $count($w['ews_urgent'], 'bg-red-100 text-red-700') !!}</td>
                                            <td class="px-3 py-3 text-right">{!! $count($w['ews_warning'], 'bg-amber-100 text-amber-800') !!}</td>
                                            <td class="px-3 py-3 text-right">{!! $count($w['escalations'], 'bg-red-100 text-red-700') !!}</td>
                                            <td class="px-3 py-3 text-right">{!! $count($w['ventilated'], 'bg-cyan-50 text-cyan-700') !!}</td>
                                            <td class="px-3 py-3 text-right">{!! $count($w['overdue_care'], 'bg-amber-100 text-amber-800') !!}</td>
                                            <td class="px-3 py-3 text-right">{!! $count($w['fluid_alerts'], 'bg-sky-50 text-sky-700') !!}</td>
                                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                                {!! $count($w['alerts'], $w['alerts_urgent'] ? 'bg-violet-600 text-white' : 'bg-violet-50 text-violet-700') !!}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="11" class="px-5 py-8 text-center text-slate-400">No wards configured</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- ===================== WARD TAB ===================== --}}
                <div x-show="tab === 'ward'" x-cloak
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="p-5 space-y-5">

                    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 cc-stagger">
                        <x-cc-stat label="Total Wards" :value="$stats['total_wards']" color="indigo" :icon="$icons['building']" />
                        <x-cc-stat label="Total Beds" :value="$stats['total_beds']" color="sky" :icon="$icons['bed']" />
                        <x-cc-stat label="Occupied" :value="$stats['occupied']" hint="{{ $stats['occupancy_rate'] }}% occupancy" color="rose" :icon="$icons['user']" />
                        <x-cc-stat label="Available" :value="$stats['available']" color="emerald" :icon="$icons['check']" />
                        <x-cc-stat label="Admissions" :value="$stats['admissions_period']" hint="in period" color="violet" :icon="$icons['login']" />
                        <x-cc-stat label="Discharges" :value="$stats['discharges_period']" hint="in period" color="amber" :icon="$icons['logout']" />
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-5 gap-5">
                        <div class="lg:col-span-3 bg-white rounded-xl border border-slate-200 p-5 hover:shadow-md transition-shadow duration-300">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-700 uppercase tracking-wider">
                                    <span class="w-2 h-2 rounded-full bg-gradient-to-r from-indigo-500 to-violet-500"></span>
                                    Bed Occupancy by Ward
                                </h3>
                                <span class="text-xs text-slate-400">Current state</span>
                            </div>
                            <div class="relative" style="height: 320px;">
                                <canvas id="wardOccupancyChart"></canvas>
                            </div>
                        </div>

                        <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 p-5 hover:shadow-md transition-shadow duration-300">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-700 uppercase tracking-wider">
                                    <span class="w-2 h-2 rounded-full bg-gradient-to-r from-violet-500 to-teal-500"></span>
                                    Patient Flow by Ward
                                </h3>
                                <span class="text-xs text-slate-400">{{ $startDate->format('M j') }} – {{ $endDate->format('M j') }}</span>
                            </div>
                            <div class="relative" style="height: 320px;">
                                <canvas id="wardFlowChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden hover:shadow-md transition-shadow duration-300">
                        <div class="px-5 py-3 border-b border-slate-200 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">Ward Breakdown</h3>
                            <span class="text-xs text-slate-400">{{ $startDate->format('M j') }} – {{ $endDate->format('M j, Y') }}</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50 text-slate-500 uppercase text-[11px] tracking-wider">
                                    <tr>
                                        <th class="px-5 py-2.5 text-left font-semibold">Ward</th>
                                        <th class="px-3 py-2.5 text-right font-semibold">Beds</th>
                                        <th class="px-3 py-2.5 text-right font-semibold">Occupied</th>
                                        <th class="px-3 py-2.5 text-right font-semibold">Available</th>
                                        <th class="px-3 py-2.5 text-right font-semibold">Admissions</th>
                                        <th class="px-3 py-2.5 text-right font-semibold">Discharges</th>
                                        <th class="px-5 py-2.5 text-left font-semibold w-64">Occupancy</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse($wardBreakdown as $w)
                                        @php
                                            $rate = $w['occupancy_rate'];
                                            $barColor = $rate >= 90 ? 'from-rose-500 to-red-500' : ($rate >= 75 ? 'from-amber-400 to-orange-500' : 'from-emerald-400 to-teal-500');
                                            $rateText = $rate >= 90 ? 'text-rose-600' : ($rate >= 75 ? 'text-amber-600' : 'text-emerald-600');
                                        @endphp
                                        <tr class="hover:bg-indigo-50/30 transition-colors duration-150">
                                            <td class="px-5 py-3">
                                                <div class="font-medium text-slate-800">{{ $w['ward_name'] }}</div>
                                                <div class="text-xs text-slate-400">{{ $w['ward_code'] }}</div>
                                            </td>
                                            <td class="px-3 py-3 text-right text-slate-700">{{ $w['total'] }}</td>
                                            <td class="px-3 py-3 text-right">
                                                <span class="inline-flex px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 font-medium">{{ $w['occupied'] }}</span>
                                            </td>
                                            <td class="px-3 py-3 text-right">
                                                <span class="inline-flex px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 font-medium">{{ $w['available'] }}</span>
                                            </td>
                                            <td class="px-3 py-3 text-right text-slate-700">{{ $w['admissions'] }}</td>
                                            <td class="px-3 py-3 text-right text-slate-700">{{ $w['discharges'] }}</td>
                                            <td class="px-5 py-3">
                                                <div class="flex items-center gap-3">
                                                    <div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden">
                                                        <div class="cc-bar h-full rounded-full bg-gradient-to-r {{ $barColor }}"
                                                            style="width: {{ $rate }}%"></div>
                                                    </div>
                                                    <span class="text-xs font-semibold {{ $rateText }} w-12 text-right">{{ $rate }}%</span>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7" class="px-5 py-8 text-center text-slate-400">No wards configured</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- ===================== BED TAB ===================== --}}
                <div x-show="tab === 'bed'" x-cloak
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="p-5 space-y-5">

                    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 cc-stagger">
                        <x-cc-stat label="Total Beds" :value="$stats['total_beds']" color="sky" :icon="$icons['bed']" />
                        <x-cc-stat label="Occupied" :value="$stats['occupied']" color="rose" :icon="$icons['user']" />
                        <x-cc-stat label="Available" :value="$stats['available']" color="emerald" :icon="$icons['check']" />
                        <x-cc-stat label="Reserved" :value="$stats['reserved']" color="amber" :icon="$icons['clock']" />
                        <x-cc-stat label="Maintenance" :value="$stats['maintenance']" color="slate" :icon="$icons['ban']" />
                        <x-cc-stat label="Occupancy" value="{{ $stats['occupancy_rate'] }}%" hint="of active beds" color="indigo" :icon="$icons['chart']" />
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                        <div class="bg-white rounded-xl border border-slate-200 p-5 hover:shadow-md transition-shadow duration-300">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-700 uppercase tracking-wider">
                                    <span class="w-2 h-2 rounded-full bg-gradient-to-r from-sky-500 to-cyan-500"></span>
                                    Bed Status
                                </h3>
                                <span class="text-xs text-slate-400">Now</span>
                            </div>
                            <div class="relative" style="height: 240px;">
                                <canvas id="bedStatusChart"></canvas>
                            </div>
                            <div class="mt-4 space-y-1.5 text-xs">
                                @foreach([
                                    ['Occupied', $stats['occupied'], 'bg-indigo-500'],
                                    ['Available', $stats['available'], 'bg-emerald-500'],
                                    ['Reserved', $stats['reserved'], 'bg-amber-500'],
                                    ['Maintenance', $stats['maintenance'], 'bg-slate-400'],
                                ] as [$lbl, $val, $dot])
                                    <div class="flex items-center justify-between">
                                        <span class="flex items-center text-slate-600"><span class="w-2.5 h-2.5 rounded-full {{ $dot }} mr-2"></span>{{ $lbl }}</span>
                                        <span class="font-medium text-slate-700">{{ $val }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 p-5 hover:shadow-md transition-shadow duration-300">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-700 uppercase tracking-wider">
                                    <span class="w-2 h-2 rounded-full bg-gradient-to-r from-emerald-500 to-rose-500"></span>
                                    Occupancy Rate
                                </h3>
                                <span class="text-xs text-slate-400">Per ward · current · color-coded by load</span>
                            </div>
                            <div class="relative" style="height: 280px;">
                                <canvas id="occupancyRateChart"></canvas>
                            </div>
                            <div class="mt-3 flex items-center gap-4 text-[11px] text-slate-500">
                                <span class="flex items-center"><span class="w-2.5 h-2.5 rounded-sm bg-emerald-500 mr-1.5"></span>&lt; 75%</span>
                                <span class="flex items-center"><span class="w-2.5 h-2.5 rounded-sm bg-amber-500 mr-1.5"></span>75–89%</span>
                                <span class="flex items-center"><span class="w-2.5 h-2.5 rounded-sm bg-rose-500 mr-1.5"></span>≥ 90%</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl border border-slate-200 p-5 hover:shadow-md transition-shadow duration-300">
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                            <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-700 uppercase tracking-wider">
                                <span class="w-2 h-2 rounded-full bg-gradient-to-r from-sky-500 to-indigo-500"></span>
                                Daily Patient Census
                            </h3>
                            <div class="flex items-center gap-4 text-xs">
                                <span class="px-2 py-0.5 rounded-md bg-sky-50 text-sky-700 font-medium">Avg {{ $censusTrend['avg'] }}</span>
                                <span class="px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700 font-medium">Peak {{ $censusTrend['peak'] }}</span>
                                <span class="text-slate-400">{{ $startDate->format('M j') }} – {{ $endDate->format('M j, Y') }}</span>
                            </div>
                        </div>
                        <p class="text-xs text-slate-400 mb-3">Patients in house each day (admitted on or before, not yet discharged)</p>
                        <div class="relative" style="height: 260px;">
                            <canvas id="censusTrendChart"></canvas>
                        </div>
                    </div>
                </div>

                {{-- ===================== PATIENT TAB ===================== --}}
                <div x-show="tab === 'patient'" x-cloak
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="p-5 space-y-5">

                    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 cc-stagger">
                        <x-cc-stat label="Admitted" :value="$stats['admitted_patients']" color="emerald" :icon="$icons['users']" />
                        <x-cc-stat label="Pending Discharge" :value="$stats['pending_discharge']" color="amber" :icon="$icons['clock']" />
                        <x-cc-stat label="Prebooked" :value="$stats['prebooked']" color="sky" :icon="$icons['clipboard']" />
                        <x-cc-stat label="Admissions" :value="$stats['admissions_period']" hint="period" color="violet" :icon="$icons['login']" />
                        <x-cc-stat label="Discharges" :value="$stats['discharges_period']" hint="period" color="rose" :icon="$icons['logout']" />
                        <x-cc-stat label="Avg Stay" :value="$demographics['avg_los_current']" hint="days · current inpatients" color="indigo" :icon="$icons['clock']" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div class="bg-white rounded-xl border border-slate-200 p-5 hover:shadow-md transition-shadow duration-300">
                            <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-700 uppercase tracking-wider mb-3">
                                <span class="w-2 h-2 rounded-full bg-gradient-to-r from-sky-500 to-pink-500"></span>
                                Gender Mix
                            </h3>
                            <p class="text-xs text-slate-400 mb-3">Current inpatients{{ $demographics['avg_age'] !== null ? ' · avg age ' . $demographics['avg_age'] : '' }}</p>
                            <div class="relative" style="height: 220px;">
                                <canvas id="genderChart"></canvas>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl border border-slate-200 p-5 hover:shadow-md transition-shadow duration-300">
                            <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-700 uppercase tracking-wider mb-3">
                                <span class="w-2 h-2 rounded-full bg-gradient-to-r from-violet-500 to-purple-500"></span>
                                Age Distribution
                            </h3>
                            <p class="text-xs text-slate-400 mb-3">Current inpatients by age band</p>
                            <div class="relative" style="height: 220px;">
                                <canvas id="ageBandChart"></canvas>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl border border-slate-200 p-5 hover:shadow-md transition-shadow duration-300">
                            <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-700 uppercase tracking-wider mb-3">
                                <span class="w-2 h-2 rounded-full bg-gradient-to-r from-teal-500 to-emerald-500"></span>
                                Patient Class
                            </h3>
                            <p class="text-xs text-slate-400 mb-3">Current inpatients (ADT class)</p>
                            <div class="relative" style="height: 220px;">
                                <canvas id="patientClassChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl border border-slate-200 p-5 hover:shadow-md transition-shadow duration-300">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-700 uppercase tracking-wider">
                                    <span class="w-2 h-2 rounded-full bg-gradient-to-r from-indigo-500 to-amber-500"></span>
                                    Monthly Trend
                                </h3>
                                <p class="text-xs text-slate-400 mt-0.5">
                                    {{ $year }}{{ $compareYear ? " vs $compareYear" : '' }} — month by month
                                </p>
                            </div>
                            <div class="flex items-center gap-3 text-xs">
                                <span class="flex items-center text-slate-600"><span class="w-3 h-1 rounded-full bg-indigo-500 mr-1.5"></span>Admissions</span>
                                <span class="flex items-center text-slate-600"><span class="w-3 h-1 rounded-full bg-emerald-500 mr-1.5"></span>Discharges</span>
                                <span class="flex items-center text-slate-600"><span class="w-3 h-1 rounded-full bg-amber-500 mr-1.5"></span>Vital Signs</span>
                            </div>
                        </div>
                        <div class="relative" style="height: 360px;">
                            <canvas id="monthlyTrendChart"></canvas>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                        <div class="bg-white rounded-xl border border-slate-200 p-5 hover:shadow-md transition-shadow duration-300">
                            <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-700 uppercase tracking-wider mb-1">
                                <span class="w-2 h-2 rounded-full bg-gradient-to-r from-indigo-500 to-emerald-500"></span>
                                Admissions vs Discharges
                            </h3>
                            <p class="text-xs text-slate-400 mb-3">{{ $startDate->format('M j') }} – {{ $endDate->format('M j, Y') }}</p>
                            <div class="relative" style="height: 220px;">
                                <canvas id="admDischargeChart"></canvas>
                            </div>
                        </div>
                        <div class="lg:col-span-2 grid grid-cols-2 gap-4 content-start">
                            @foreach([
                                ['Avg Length of Stay', $demographics['avg_los_current'], 'days · current inpatients', 'from-indigo-500 to-violet-500'],
                                ['Longest Current Stay', $demographics['longest_stay'], 'days', 'from-rose-500 to-pink-500'],
                                ['Avg LOS (discharged)', $demographics['avg_los_discharged'] ?? '—', 'days · discharged in period', 'from-emerald-500 to-teal-500'],
                                ['Average Age', $demographics['avg_age'] ?? '—', 'years · current inpatients', 'from-amber-400 to-orange-500'],
                            ] as [$lbl, $val, $hint, $gradient])
                                <div class="relative overflow-hidden bg-white rounded-xl border border-slate-200 p-4 hover:shadow-md hover:-translate-y-0.5 transition-all duration-300">
                                    <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r {{ $gradient }}"></div>
                                    <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">{{ $lbl }}</div>
                                    <div class="mt-1.5 text-2xl font-bold text-slate-900 tabular-nums" data-countup>{{ $val }}</div>
                                    <div class="mt-0.5 text-xs text-slate-400">{{ $hint }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- ===================== VITAL SIGNS TAB ===================== --}}
                <div x-show="tab === 'vitals'" x-cloak
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="p-5 space-y-5">

                    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3 cc-stagger">
                        <x-cc-stat label="Total Readings" :value="number_format($vitalStats['total'])" hint="in period" color="rose" :icon="$icons['heart']" />
                        <x-cc-stat label="Patients Covered" :value="$vitalStats['patients_covered']" hint="distinct patients" color="indigo" :icon="$icons['users']" />
                        <x-cc-stat label="Full Readings" :value="$vitalStats['full_readings']" hint="{{ $vitalStats['full_pct'] }}% of readings" color="emerald" :icon="$icons['check']" />
                        <x-cc-stat label="Abnormal Flags" :value="$vitalStats['abnormal_total']" hint="{{ $vitalStats['abnormal_pct'] }}% flagged" color="amber" :icon="$icons['warning']" />
                        <x-cc-stat label="Last 24 Hours" :value="$vitalStats['last_24h']" hint="readings" color="sky" :icon="$icons['lightning']" />
                    </div>

                    {{-- Averages strip --}}
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 cc-stagger">
                        @foreach([
                            ['Blood Pressure', $vitalStats['averages']['bp'] ?? '—', 'mmHg avg', 'from-rose-500 to-red-500', 'bg-rose-50 text-rose-600'],
                            ['Pulse Rate', $vitalStats['averages']['pulse'] ?? '—', 'bpm avg', 'from-red-500 to-orange-500', 'bg-red-50 text-red-600'],
                            ['Temperature', $vitalStats['averages']['temp'] ?? '—', '°C avg', 'from-orange-400 to-amber-500', 'bg-orange-50 text-orange-600'],
                            ['SpO₂', $vitalStats['averages']['spo2'] ?? '—', '% avg', 'from-sky-500 to-cyan-500', 'bg-sky-50 text-sky-600'],
                            ['Respiratory Rate', $vitalStats['averages']['rr'] ?? '—', '/min avg', 'from-teal-500 to-emerald-500', 'bg-teal-50 text-teal-600'],
                        ] as [$lbl, $val, $unit, $gradient, $chip])
                            <div class="relative overflow-hidden bg-white rounded-xl border border-slate-200 p-4 hover:shadow-md hover:-translate-y-0.5 transition-all duration-300">
                                <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r {{ $gradient }}"></div>
                                <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">{{ $lbl }}</div>
                                <div class="mt-1.5 flex items-baseline gap-1.5">
                                    <span class="text-2xl font-bold text-slate-900 tabular-nums">{{ $val }}</span>
                                    <span class="text-xs {{ $chip }} px-1.5 py-0.5 rounded font-medium">{{ $unit }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if($vitalStats['total'] > 0)
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                            <div class="bg-white rounded-xl border border-slate-200 p-5 hover:shadow-md transition-shadow duration-300">
                                <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-700 uppercase tracking-wider mb-1">
                                    <span class="w-2 h-2 rounded-full bg-gradient-to-r from-amber-500 to-rose-500"></span>
                                    Abnormal Readings
                                </h3>
                                <p class="text-xs text-slate-400 mb-3">Readings outside normal thresholds in the period</p>
                                <div class="relative" style="height: 280px;">
                                    <canvas id="abnormalChart"></canvas>
                                </div>
                            </div>
                            <div class="bg-white rounded-xl border border-slate-200 p-5 hover:shadow-md transition-shadow duration-300">
                                <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-700 uppercase tracking-wider mb-1">
                                    <span class="w-2 h-2 rounded-full bg-gradient-to-r from-violet-500 to-fuchsia-500"></span>
                                    Recording Activity by Hour
                                </h3>
                                <p class="text-xs text-slate-400 mb-3">When vitals get taken across the day (rounds pattern)</p>
                                <div class="relative" style="height: 280px;">
                                    <canvas id="hourlyChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-xl border border-slate-200 p-5 hover:shadow-md transition-shadow duration-300">
                            <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-700 uppercase tracking-wider mb-1">
                                <span class="w-2 h-2 rounded-full bg-gradient-to-r from-rose-500 to-pink-500"></span>
                                Daily Recording Volume
                            </h3>
                            <p class="text-xs text-slate-400 mb-3">{{ $startDate->format('M j') }} – {{ $endDate->format('M j, Y') }}</p>
                            <div class="relative" style="height: 240px;">
                                <canvas id="dailyVolumeChart"></canvas>
                            </div>
                        </div>
                    @else
                        <div class="bg-white rounded-xl border border-dashed border-slate-300 py-12 text-center">
                            <div class="text-slate-400 text-sm">No vital signs recorded in this period — adjust the date range above.</div>
                        </div>
                    @endif

                    {{-- ---------- Vital Signs Volume — two normalized rate graphs ---------- --}}
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                        {{-- Graph 1: Per Patient / Per Day --}}
                        <div class="bg-white rounded-xl border border-slate-200 p-5 hover:shadow-md transition-shadow duration-300">
                            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                                <div>
                                    <div class="text-xs font-semibold text-slate-700 uppercase tracking-wider">Per Patient / Per Day</div>
                                    <div class="text-xs text-slate-400 mt-0.5">
                                        Avg vitals each patient received per day
                                    </div>
                                </div>
                                <div class="flex items-center gap-4 text-xs">
                                    <div>
                                        <div class="text-slate-400 uppercase tracking-wider text-[10px]">Period avg</div>
                                        <div class="text-sm font-semibold text-indigo-600 tabular-nums">{{ $vitalsRates['avg_per_patient'] }}</div>
                                    </div>
                                    <div>
                                        <div class="text-slate-400 uppercase tracking-wider text-[10px]">Total vitals</div>
                                        <div class="text-sm font-semibold text-slate-800 tabular-nums">{{ number_format($vitalsRates['total_vitals']) }}</div>
                                    </div>
                                </div>
                            </div>
                            @if($vitalsRates['days_with_data_patient'] > 0)
                                <div class="relative" style="height: 240px;">
                                    <canvas id="vitalPerPatientDayChart"></canvas>
                                </div>
                            @else
                                <div class="py-10 text-center text-sm text-slate-400">No vital signs recorded in this period.</div>
                            @endif
                        </div>

                        {{-- Graph 2: Per Patient / Per Admission / Per Day --}}
                        <div class="bg-white rounded-xl border border-slate-200 p-5 hover:shadow-md transition-shadow duration-300">
                            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                                <div>
                                    <div class="text-xs font-semibold text-slate-700 uppercase tracking-wider">Per Admission / Per Day</div>
                                    <div class="text-xs text-slate-400 mt-0.5">
                                        Avg vitals per admission episode per day
                                    </div>
                                </div>
                                <div class="flex items-center gap-4 text-xs">
                                    <div>
                                        <div class="text-slate-400 uppercase tracking-wider text-[10px]">Period avg</div>
                                        <div class="text-sm font-semibold text-teal-600 tabular-nums">{{ $vitalsRates['avg_per_admission'] }}</div>
                                    </div>
                                    <div>
                                        <div class="text-slate-400 uppercase tracking-wider text-[10px]">Days w/ data</div>
                                        <div class="text-sm font-semibold text-slate-800 tabular-nums">{{ $vitalsRates['days_with_data_admission'] }}</div>
                                    </div>
                                </div>
                            </div>
                            @if($vitalsRates['days_with_data_admission'] > 0)
                                <div class="relative" style="height: 240px;">
                                    <canvas id="vitalPerAdmissionDayChart"></canvas>
                                </div>
                            @else
                                <div class="py-10 text-center text-sm text-slate-400">No admission-linked vital signs in this period.</div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- ===================== RISK & SAFETY TAB ===================== --}}
                <div x-show="tab === 'risk'" x-cloak
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="p-5 space-y-5">

                    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 cc-stagger">
                        <x-cc-stat label="Inpatients" :value="$riskStats['total_inpatients']" hint="{{ $selectedWard ? $selectedWard->ward_code : 'all wards' }}" color="indigo" :icon="$icons['users']" />
                        <x-cc-stat label="High Fall Risk" :value="$riskStats['high_fall']" hint="high + FR alert" color="orange" :icon="$icons['warning']" />
                        <x-cc-stat label="On Isolation" :value="$riskStats['isolated']" hint="any precaution" color="violet" :icon="$icons['shield']" />
                        <x-cc-stat label="High Acuity" :value="$riskStats['high_acuity']" hint="nursing level 3–4" color="red" :icon="$icons['heart']" />
                        <x-cc-stat label="With Allergies" :value="$riskStats['with_allergies']" hint="documented" color="amber" :icon="$icons['clipboard']" />
                        <x-cc-stat label="HGT Monitored" :value="$riskStats['hgt_monitored']" hint="glucose monitoring" color="teal" :icon="$icons['beaker']" />
                    </div>

                    @if($riskStats['total_inpatients'] > 0)
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                            <div class="bg-white rounded-xl border border-slate-200 p-5 hover:shadow-md transition-shadow duration-300">
                                <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-700 uppercase tracking-wider mb-1">
                                    <span class="w-2 h-2 rounded-full bg-gradient-to-r from-amber-400 to-red-500"></span>
                                    Fall Risk
                                </h3>
                                <p class="text-xs text-slate-400 mb-3">Current inpatients</p>
                                <div class="relative" style="height: 230px;">
                                    <canvas id="fallRiskChart"></canvas>
                                </div>
                            </div>
                            <div class="bg-white rounded-xl border border-slate-200 p-5 hover:shadow-md transition-shadow duration-300">
                                <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-700 uppercase tracking-wider mb-1">
                                    <span class="w-2 h-2 rounded-full bg-gradient-to-r from-green-500 to-red-500"></span>
                                    Nursing Acuity
                                </h3>
                                <p class="text-xs text-slate-400 mb-3">Care level distribution</p>
                                <div class="relative" style="height: 230px;">
                                    <canvas id="nursingChart"></canvas>
                                </div>
                            </div>
                            <div class="bg-white rounded-xl border border-slate-200 p-5 hover:shadow-md transition-shadow duration-300">
                                <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-700 uppercase tracking-wider mb-1">
                                    <span class="w-2 h-2 rounded-full bg-gradient-to-r from-violet-500 to-blue-500"></span>
                                    Isolation Precautions
                                </h3>
                                <p class="text-xs text-slate-400 mb-3">Current inpatients</p>
                                <div class="relative" style="height: 230px;">
                                    <canvas id="isolationChart"></canvas>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="bg-white rounded-xl border border-dashed border-slate-300 py-12 text-center">
                            <div class="text-slate-400 text-sm">No inpatients currently admitted{{ $selectedWard ? ' in ' . $selectedWard->ward_name : '' }}.</div>
                        </div>
                    @endif

                    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden hover:shadow-md transition-shadow duration-300">
                        <div class="px-5 py-3 border-b border-slate-200 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">Ward Safety Matrix</h3>
                            <span class="text-xs text-slate-400">All wards · current inpatients</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50 text-slate-500 uppercase text-[11px] tracking-wider">
                                    <tr>
                                        <th class="px-5 py-2.5 text-left font-semibold">Ward</th>
                                        <th class="px-3 py-2.5 text-right font-semibold">Inpatients</th>
                                        <th class="px-3 py-2.5 text-right font-semibold">High Fall Risk</th>
                                        <th class="px-3 py-2.5 text-right font-semibold">Isolation</th>
                                        <th class="px-3 py-2.5 text-right font-semibold">High Acuity</th>
                                        <th class="px-3 py-2.5 text-right font-semibold">Allergies</th>
                                        <th class="px-3 py-2.5 text-right font-semibold">NBM</th>
                                        <th class="px-3 py-2.5 text-right font-semibold">HGT</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse($wardSafety as $w)
                                        <tr class="hover:bg-amber-50/30 transition-colors duration-150">
                                            <td class="px-5 py-3">
                                                <div class="font-medium text-slate-800">{{ $w['ward_name'] }}</div>
                                                <div class="text-xs text-slate-400">{{ $w['ward_code'] }}</div>
                                            </td>
                                            <td class="px-3 py-3 text-right font-medium text-slate-700">{{ $w['inpatients'] }}</td>
                                            @foreach([
                                                ['high_fall', 'bg-orange-100 text-orange-700'],
                                                ['isolated', 'bg-violet-100 text-violet-700'],
                                                ['high_acuity', 'bg-red-100 text-red-700'],
                                                ['allergies', 'bg-amber-100 text-amber-700'],
                                                ['nbm', 'bg-rose-100 text-rose-700'],
                                                ['hgt', 'bg-teal-100 text-teal-700'],
                                            ] as [$field, $badge])
                                                <td class="px-3 py-3 text-right">
                                                    @if($w[$field] > 0)
                                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold {{ $badge }}">{{ $w[$field] }}</span>
                                                    @else
                                                        <span class="text-slate-300">0</span>
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @empty
                                        <tr><td colspan="8" class="px-5 py-8 text-center text-slate-400">No wards configured</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- ===================== DIET & NUTRITION TAB ===================== --}}
                <div x-show="tab === 'diet'" x-cloak
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="p-5 space-y-5">

                    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 cc-stagger">
                        <x-cc-stat label="With Diet Orders" :value="$dietStats['with_diet']" hint="of {{ $dietStats['total_inpatients'] }} inpatients" color="teal" :icon="$icons['clipboard']" />
                        <x-cc-stat label="No Diet Recorded" :value="$dietStats['no_diet']" color="slate" :icon="$icons['eye']" />
                        <x-cc-stat label="Nil By Mouth" :value="$dietStats['nbm']" hint="NBM / NPO" color="red" :icon="$icons['ban']" />
                        <x-cc-stat label="Diabetic Diet" :value="$dietStats['diabetic']" color="violet" :icon="$icons['beaker']" />
                        <x-cc-stat label="Therapeutic Diets" :value="$dietStats['therapeutic']" hint="any restriction" color="emerald" :icon="$icons['shield']" />
                        <x-cc-stat label="Sugar Readings" :value="$dietStats['sugar']['total']" hint="in period" color="amber" :icon="$icons['lightning']" />
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-5 gap-5">
                        <div class="lg:col-span-3 bg-white rounded-xl border border-slate-200 p-5 hover:shadow-md transition-shadow duration-300">
                            <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-700 uppercase tracking-wider mb-1">
                                <span class="w-2 h-2 rounded-full bg-gradient-to-r from-teal-500 to-lime-500"></span>
                                Diet Order Distribution
                            </h3>
                            <p class="text-xs text-slate-400 mb-3">Current inpatients · a patient can have multiple diet orders</p>
                            @if(count($dietStats['distribution']) > 0)
                                <div class="relative" style="height: {{ max(240, min(15, count($dietStats['distribution'])) * 34) }}px;">
                                    <canvas id="dietDistChart"></canvas>
                                </div>
                            @else
                                <div class="py-10 text-center text-sm text-slate-400">
                                    No diet orders recorded for current inpatients yet.<br>
                                    <span class="text-xs">Diet orders arrive via ADT or the ward patient-details form.</span>
                                </div>
                            @endif
                        </div>

                        <div class="lg:col-span-2 space-y-5">
                            <div class="bg-white rounded-xl border border-slate-200 p-5 hover:shadow-md transition-shadow duration-300">
                                <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-700 uppercase tracking-wider mb-1">
                                    <span class="w-2 h-2 rounded-full bg-gradient-to-r from-amber-400 to-red-500"></span>
                                    Glucose Readings Status
                                </h3>
                                <p class="text-xs text-slate-400 mb-3">
                                    HGT readings in period
                                    @if($dietStats['sugar']['avg'] !== null)
                                        · avg {{ $dietStats['sugar']['avg'] }} mmol/L
                                        · range {{ $dietStats['sugar']['min'] }}–{{ $dietStats['sugar']['max'] }}
                                    @endif
                                </p>
                                @if($dietStats['sugar']['total'] > 0)
                                    <div class="relative" style="height: 200px;">
                                        <canvas id="sugarStatusChart"></canvas>
                                    </div>
                                @else
                                    <div class="py-8 text-center text-sm text-slate-400">No glucose readings in this period.</div>
                                @endif
                            </div>

                            <div class="bg-white rounded-xl border border-slate-200 p-5 hover:shadow-md transition-shadow duration-300">
                                <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-700 uppercase tracking-wider mb-1">
                                    <span class="w-2 h-2 rounded-full bg-gradient-to-r from-teal-500 to-cyan-500"></span>
                                    HGT Monitoring Frequency
                                </h3>
                                <p class="text-xs text-slate-400 mb-3">{{ $dietStats['hgt_enabled'] }} patient(s) on glucose monitoring</p>
                                @if($dietStats['hgt_enabled'] > 0)
                                    <div class="relative" style="height: 200px;">
                                        <canvas id="hgtFreqChart"></canvas>
                                    </div>
                                @else
                                    <div class="py-8 text-center text-sm text-slate-400">No patients currently on HGT monitoring.</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        [x-cloak] { display: none !important; }

        @keyframes ccUp {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: none; }
        }
        .cc-stagger > * { animation: ccUp .5s cubic-bezier(.22, 1, .36, 1) both; }
        .cc-stagger > *:nth-child(1) { animation-delay: .03s; }
        .cc-stagger > *:nth-child(2) { animation-delay: .08s; }
        .cc-stagger > *:nth-child(3) { animation-delay: .13s; }
        .cc-stagger > *:nth-child(4) { animation-delay: .18s; }
        .cc-stagger > *:nth-child(5) { animation-delay: .23s; }
        .cc-stagger > *:nth-child(6) { animation-delay: .28s; }

        @keyframes ccBarGrow {
            from { transform: scaleX(0); }
            to   { transform: scaleX(1); }
        }
        .cc-bar { transform-origin: left; animation: ccBarGrow 1.1s cubic-bezier(.22, 1, .36, 1) both .3s; }

        .cc-quiet .cc-stagger > *, .cc-quiet .cc-bar { animation: none !important; }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Set by the live board's auto-refresh just before it reloads
            const quiet = document.documentElement.classList.contains('cc-quiet');
            try { sessionStorage.removeItem('ccQuietReload'); } catch (e) {}

            // ---------- Global chart styling ----------
            Chart.defaults.font.family = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
            Chart.defaults.color = '#64748b';
            Chart.defaults.borderColor = '#e2e8f0';
            Chart.defaults.animation.duration = quiet ? 0 : 950;
            Chart.defaults.animation.easing = 'easeOutQuart';
            Chart.defaults.plugins.legend.labels.usePointStyle = true;
            Chart.defaults.plugins.legend.labels.boxWidth = 8;
            Chart.defaults.plugins.legend.labels.boxHeight = 8;
            Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(15, 23, 42, .92)';
            Chart.defaults.plugins.tooltip.padding = 10;
            Chart.defaults.plugins.tooltip.cornerRadius = 10;
            Chart.defaults.plugins.tooltip.boxPadding = 4;
            Chart.defaults.plugins.tooltip.usePointStyle = true;

            // ---------- Palette ----------
            const P = {
                indigo: '#6366f1', violet: '#8b5cf6', sky: '#0ea5e9', cyan: '#06b6d4',
                teal: '#14b8a6', emerald: '#10b981', green: '#22c55e', lime: '#84cc16',
                yellow: '#eab308', amber: '#f59e0b', orange: '#f97316', rose: '#f43f5e',
                red: '#ef4444', fuchsia: '#d946ef', pink: '#ec4899', blue: '#3b82f6',
                slate: '#94a3b8', gray: '#cbd5e1',
            };
            const wheel = [P.indigo, P.emerald, P.amber, P.rose, P.sky, P.violet, P.teal, P.orange, P.fuchsia, P.lime, P.cyan, P.pink, P.blue, P.yellow];
            const riskColors = {
                none: P.gray, low: P.green, moderate: P.yellow, high: P.orange, alert_active: P.red,
                level_1: P.green, level_2: P.sky, level_3: P.amber, level_4: P.red,
                contact: P.blue, droplet: P.cyan, airborne: P.violet, protective: P.green,
                mrsa: P.orange, vre: P.pink, cdiff: P.amber, covid: P.red, tb: P.rose,
            };

            const grad = (canvas, hex, top = '4D', bottom = '05') => {
                const ctx = canvas.getContext('2d');
                const g = ctx.createLinearGradient(0, 0, 0, (canvas.parentNode && canvas.parentNode.clientHeight) || 300);
                g.addColorStop(0, hex + top);
                g.addColorStop(1, hex + bottom);
                return g;
            };
            const stagger = { delay: (c) => (c.type === 'data' && c.mode === 'default') ? c.dataIndex * 50 : 0 };
            const el = (id) => document.getElementById(id);

            // ---------- Server data ----------
            const DATA = {
                wardBreakdown: @json($wardBreakdown),
                monthly: @json($monthly),
                compareMonthly: @json($compareMonthly),
                vitalsRates: @json($vitalsRates),
                demographics: @json($demographics),
                censusTrend: @json($censusTrend),
                vitalStats: @json($vitalStats),
                riskStats: @json($riskStats),
                dietStats: @json($dietStats),
            };

            // ---------- Count-up animation for stat values ----------
            (quiet ? [] : document.querySelectorAll('[data-countup]')).forEach((node) => {
                const raw = node.textContent.trim();
                if (!/^[\d.,]+\s*[%°A-Za-z]*$/.test(raw)) return;
                const num = parseFloat(raw.replace(/,/g, ''));
                if (isNaN(num)) return;
                const suffix = raw.replace(/^[\d.,]+/, '');
                const hasComma = raw.includes(',');
                const decimals = (raw.split('.')[1] || '').replace(/\D/g, '').length;
                const dur = 900;
                const t0 = performance.now();
                const fmt = (v) => {
                    let s;
                    if (hasComma) {
                        s = Number(v.toFixed(decimals)).toLocaleString(undefined, { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
                    } else {
                        s = v.toFixed(decimals);
                    }
                    return s + suffix;
                };
                const step = (t) => {
                    const p = Math.min((t - t0) / dur, 1);
                    const e = 1 - Math.pow(1 - p, 3);
                    node.textContent = fmt(num * e);
                    if (p < 1) requestAnimationFrame(step);
                };
                requestAnimationFrame(step);
            });

            // ============================================================
            // Per-tab chart initializers (lazy — animate on first open)
            // ============================================================

            function initWard() {
                const wd = DATA.wardBreakdown;
                if (el('wardOccupancyChart')) {
                    new Chart(el('wardOccupancyChart'), {
                        type: 'bar',
                        data: {
                            labels: wd.map(w => w.ward_code || w.ward_name),
                            datasets: [
                                { label: 'Occupied',    data: wd.map(w => w.occupied),    backgroundColor: P.indigo,  borderRadius: 5, stack: 'beds' },
                                { label: 'Available',   data: wd.map(w => w.available),   backgroundColor: P.emerald, borderRadius: 5, stack: 'beds' },
                                { label: 'Reserved',    data: wd.map(w => w.reserved),    backgroundColor: P.amber,   borderRadius: 5, stack: 'beds' },
                                { label: 'Maintenance', data: wd.map(w => w.maintenance), backgroundColor: P.slate,   borderRadius: 5, stack: 'beds' },
                            ]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            animation: stagger,
                            plugins: { legend: { position: 'bottom' } },
                            scales: {
                                x: { stacked: true, grid: { display: false } },
                                y: { stacked: true, beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' } }
                            }
                        }
                    });
                }
                if (el('wardFlowChart')) {
                    new Chart(el('wardFlowChart'), {
                        type: 'bar',
                        data: {
                            labels: wd.map(w => w.ward_code || w.ward_name),
                            datasets: [
                                { label: 'Admissions', data: wd.map(w => w.admissions), backgroundColor: P.violet, borderRadius: 5 },
                                { label: 'Discharges', data: wd.map(w => w.discharges), backgroundColor: P.teal,   borderRadius: 5 },
                            ]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            animation: stagger,
                            plugins: { legend: { position: 'bottom' } },
                            scales: {
                                x: { grid: { display: false } },
                                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' } }
                            }
                        }
                    });
                }
            }

            function initBed() {
                const wd = DATA.wardBreakdown;
                if (el('bedStatusChart')) {
                    new Chart(el('bedStatusChart'), {
                        type: 'doughnut',
                        data: {
                            labels: ['Occupied', 'Available', 'Reserved', 'Maintenance'],
                            datasets: [{
                                data: [{{ $stats['occupied'] }}, {{ $stats['available'] }}, {{ $stats['reserved'] }}, {{ $stats['maintenance'] }}],
                                backgroundColor: [P.indigo, P.emerald, P.amber, P.slate],
                                hoverOffset: 8,
                                borderWidth: 2, borderColor: '#fff', cutout: '68%',
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: (ctx) => {
                                            const total = ctx.dataset.data.reduce((a, b) => a + b, 0) || 1;
                                            const pct = (ctx.parsed / total * 100).toFixed(1);
                                            return ` ${ctx.label}: ${ctx.parsed} (${pct}%)`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
                if (el('occupancyRateChart')) {
                    const rates = wd.map(w => w.occupancy_rate);
                    new Chart(el('occupancyRateChart'), {
                        type: 'bar',
                        data: {
                            labels: wd.map(w => w.ward_code || w.ward_name),
                            datasets: [{
                                label: 'Occupancy %',
                                data: rates,
                                backgroundColor: rates.map(r => r >= 90 ? P.rose : (r >= 75 ? P.amber : P.emerald)),
                                borderRadius: 5,
                                barThickness: 16,
                            }]
                        },
                        options: {
                            indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                            animation: stagger,
                            plugins: { legend: { display: false } },
                            scales: {
                                x: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' }, grid: { color: '#f1f5f9' } },
                                y: { grid: { display: false } }
                            }
                        }
                    });
                }
                if (el('censusTrendChart')) {
                    const c = el('censusTrendChart');
                    new Chart(c, {
                        type: 'line',
                        data: {
                            labels: DATA.censusTrend.labels,
                            datasets: [{
                                label: 'Patients in house',
                                data: DATA.censusTrend.values,
                                borderColor: P.sky,
                                backgroundColor: grad(c, P.sky),
                                tension: 0.35, fill: true, pointRadius: 2, pointHoverRadius: 5,
                                pointBackgroundColor: P.sky, borderWidth: 2.5,
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            plugins: { legend: { display: false } },
                            scales: {
                                x: { grid: { display: false }, ticks: { maxTicksLimit: 12, autoSkip: true } },
                                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' } }
                            }
                        }
                    });
                }
            }

            function initPatient() {
                const demo = DATA.demographics;
                if (el('genderChart')) {
                    const labels = Object.keys(demo.gender);
                    const genderColor = (l) => l === 'Male' ? P.sky : (l === 'Female' ? P.pink : P.slate);
                    new Chart(el('genderChart'), {
                        type: 'doughnut',
                        data: {
                            labels,
                            datasets: [{
                                data: Object.values(demo.gender),
                                backgroundColor: labels.map(genderColor),
                                hoverOffset: 8,
                                borderWidth: 2, borderColor: '#fff', cutout: '62%',
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { legend: { position: 'bottom' } }
                        }
                    });
                }
                if (el('ageBandChart')) {
                    new Chart(el('ageBandChart'), {
                        type: 'bar',
                        data: {
                            labels: Object.keys(demo.age_bands),
                            datasets: [{
                                label: 'Patients',
                                data: Object.values(demo.age_bands),
                                backgroundColor: [P.sky, P.cyan, P.teal, P.violet, P.amber, P.rose, P.slate],
                                borderRadius: 5,
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            animation: stagger,
                            plugins: { legend: { display: false } },
                            scales: {
                                x: { grid: { display: false } },
                                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' } }
                            }
                        }
                    });
                }
                if (el('patientClassChart')) {
                    const labels = Object.keys(demo.patient_class);
                    new Chart(el('patientClassChart'), {
                        type: 'doughnut',
                        data: {
                            labels,
                            datasets: [{
                                data: Object.values(demo.patient_class),
                                backgroundColor: labels.map((_, i) => wheel[i % wheel.length]),
                                hoverOffset: 8,
                                borderWidth: 2, borderColor: '#fff', cutout: '62%',
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { legend: { position: 'bottom' } }
                        }
                    });
                }

                const monthly = DATA.monthly;
                const compareMonthly = DATA.compareMonthly;
                if (el('monthlyTrendChart')) {
                    const c = el('monthlyTrendChart');
                    const datasets = [
                        {
                            label: `Admissions ${monthly.year}`,
                            data: monthly.admissions,
                            borderColor: P.indigo, backgroundColor: grad(c, P.indigo, '33'),
                            tension: 0.35, fill: true, pointRadius: 3, pointHoverRadius: 6,
                            pointBackgroundColor: P.indigo, borderWidth: 2.5,
                        },
                        {
                            label: `Discharges ${monthly.year}`,
                            data: monthly.discharges,
                            borderColor: P.emerald, backgroundColor: 'transparent',
                            tension: 0.35, fill: false, pointRadius: 3, pointHoverRadius: 6,
                            pointBackgroundColor: P.emerald, borderWidth: 2.5,
                        },
                        {
                            label: `Vital Signs ${monthly.year}`,
                            data: monthly.vital_signs,
                            borderColor: P.amber, backgroundColor: 'transparent', borderDash: [5, 5],
                            tension: 0.35, fill: false, pointRadius: 3, pointHoverRadius: 6,
                            pointBackgroundColor: P.amber, borderWidth: 2,
                            yAxisID: 'y1',
                        },
                    ];
                    if (compareMonthly) {
                        datasets.push(
                            { label: `Admissions ${compareMonthly.year}`, data: compareMonthly.admissions,
                              borderColor: P.indigo + '80', borderDash: [3, 4], backgroundColor: 'transparent',
                              tension: 0.35, fill: false, pointRadius: 2, borderWidth: 1.5 },
                            { label: `Discharges ${compareMonthly.year}`, data: compareMonthly.discharges,
                              borderColor: P.emerald + '80', borderDash: [3, 4], backgroundColor: 'transparent',
                              tension: 0.35, fill: false, pointRadius: 2, borderWidth: 1.5 },
                        );
                    }
                    new Chart(c, {
                        type: 'line',
                        data: { labels: monthly.labels, datasets },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            plugins: { legend: { position: 'bottom' } },
                            scales: {
                                y:  { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' },
                                      title: { display: true, text: 'Admissions / Discharges', color: '#64748b' } },
                                y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false },
                                      title: { display: true, text: 'Vital Signs', color: '#64748b' } }
                            }
                        }
                    });
                }

                if (el('admDischargeChart')) {
                    new Chart(el('admDischargeChart'), {
                        type: 'bar',
                        data: {
                            labels: ['Admissions', 'Discharges'],
                            datasets: [{
                                data: [{{ $stats['admissions_period'] }}, {{ $stats['discharges_period'] }}],
                                backgroundColor: [P.indigo, P.emerald],
                                borderRadius: 8, barThickness: 60,
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            animation: stagger,
                            plugins: { legend: { display: false } },
                            scales: {
                                x: { grid: { display: false } },
                                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' } }
                            }
                        }
                    });
                }
            }

            function initVitals() {
                const vs = DATA.vitalStats;
                const vr = DATA.vitalsRates;

                if (el('abnormalChart')) {
                    const entries = Object.entries(vs.abnormal); // [label, {count, color}]
                    new Chart(el('abnormalChart'), {
                        type: 'bar',
                        data: {
                            labels: entries.map(e => e[0]),
                            datasets: [{
                                label: 'Readings',
                                data: entries.map(e => e[1].count),
                                backgroundColor: entries.map(e => e[1].color),
                                borderRadius: 5,
                                barThickness: 18,
                            }]
                        },
                        options: {
                            indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                            animation: stagger,
                            plugins: { legend: { display: false } },
                            scales: {
                                x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' } },
                                y: { grid: { display: false } }
                            }
                        }
                    });
                }

                if (el('hourlyChart')) {
                    const c = el('hourlyChart');
                    new Chart(c, {
                        type: 'bar',
                        data: {
                            labels: Array.from({ length: 24 }, (_, h) => `${String(h).padStart(2, '0')}:00`),
                            datasets: [{
                                label: 'Readings',
                                data: vs.hourly,
                                backgroundColor: P.violet + 'CC',
                                hoverBackgroundColor: P.violet,
                                borderRadius: 4,
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            animation: { delay: (ctx) => (ctx.type === 'data' && ctx.mode === 'default') ? ctx.dataIndex * 25 : 0 },
                            plugins: { legend: { display: false } },
                            scales: {
                                x: { grid: { display: false }, ticks: { maxTicksLimit: 12 } },
                                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' } }
                            }
                        }
                    });
                }

                if (el('dailyVolumeChart')) {
                    new Chart(el('dailyVolumeChart'), {
                        type: 'bar',
                        data: {
                            labels: vr.labels,
                            datasets: [{
                                label: 'Vitals recorded',
                                data: vr.raw_vitals,
                                backgroundColor: P.rose + 'B3',
                                hoverBackgroundColor: P.rose,
                                borderRadius: 4,
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                x: { grid: { display: false }, ticks: { maxTicksLimit: 15, autoSkip: true } },
                                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' } }
                            }
                        }
                    });
                }

                const rateChartOpts = (labelText, raw1, raw2, l1, l2) => ({
                    responsive: true, maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                afterLabel: (ctx) => {
                                    const i = ctx.dataIndex;
                                    return [`${l1}: ${raw1[i]}`, `${l2}: ${raw2[i]}`];
                                }
                            }
                        }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { maxTicksLimit: 12, autoSkip: true } },
                        y: { beginAtZero: true, grid: { color: '#f1f5f9' },
                             title: { display: true, text: labelText, color: '#64748b', font: { size: 11 } } }
                    }
                });

                if (el('vitalPerPatientDayChart') && vr.labels.length > 0) {
                    const c = el('vitalPerPatientDayChart');
                    new Chart(c, {
                        type: 'line',
                        data: {
                            labels: vr.labels,
                            datasets: [{
                                label: 'Vitals per patient',
                                data: vr.per_patient,
                                borderColor: P.indigo, backgroundColor: grad(c, P.indigo),
                                tension: 0.35, fill: true, pointRadius: 2, pointHoverRadius: 5,
                                pointBackgroundColor: P.indigo, borderWidth: 2.5,
                            }]
                        },
                        options: rateChartOpts(
                            'Vitals per patient',
                            vr.raw_vitals, vr.raw_patients,
                            'Total vitals', 'Patients'
                        )
                    });
                }

                if (el('vitalPerAdmissionDayChart') && vr.labels.length > 0) {
                    const c = el('vitalPerAdmissionDayChart');
                    new Chart(c, {
                        type: 'line',
                        data: {
                            labels: vr.labels,
                            datasets: [{
                                label: 'Vitals per admission',
                                data: vr.per_admission,
                                borderColor: P.teal, backgroundColor: grad(c, P.teal),
                                tension: 0.35, fill: true, pointRadius: 2, pointHoverRadius: 5,
                                pointBackgroundColor: P.teal, borderWidth: 2.5,
                            }]
                        },
                        options: rateChartOpts(
                            'Vitals per admission',
                            vr.raw_vitals, vr.raw_admissions,
                            'Total vitals', 'Admissions'
                        )
                    });
                }
            }

            function initRisk() {
                const rs = DATA.riskStats;
                const donut = (id, rows, cutout = '62%') => {
                    if (!el(id)) return;
                    const nonZero = rows.filter(r => r.count > 0);
                    const list = nonZero.length > 0 ? nonZero : rows;
                    new Chart(el(id), {
                        type: 'doughnut',
                        data: {
                            labels: list.map(r => r.label),
                            datasets: [{
                                data: list.map(r => r.count),
                                backgroundColor: list.map((r, i) => riskColors[r.key] || wheel[i % wheel.length]),
                                hoverOffset: 8,
                                borderWidth: 2, borderColor: '#fff', cutout,
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { legend: { position: 'bottom' } }
                        }
                    });
                };

                donut('fallRiskChart', rs.fall);
                donut('isolationChart', rs.isolation);

                if (el('nursingChart')) {
                    new Chart(el('nursingChart'), {
                        type: 'bar',
                        data: {
                            labels: rs.nursing.map(r => r.label),
                            datasets: [{
                                label: 'Patients',
                                data: rs.nursing.map(r => r.count),
                                backgroundColor: rs.nursing.map((r, i) => riskColors[r.key] || wheel[i % wheel.length]),
                                borderRadius: 5,
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            animation: stagger,
                            plugins: { legend: { display: false } },
                            scales: {
                                x: { grid: { display: false } },
                                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' } }
                            }
                        }
                    });
                }
            }

            function initDiet() {
                const ds = DATA.dietStats;

                if (el('dietDistChart')) {
                    const rows = ds.distribution.slice(0, 15);
                    new Chart(el('dietDistChart'), {
                        type: 'bar',
                        data: {
                            labels: rows.map(r => r.label),
                            datasets: [{
                                label: 'Patients',
                                data: rows.map(r => r.count),
                                backgroundColor: rows.map((_, i) => wheel[i % wheel.length]),
                                borderRadius: 5,
                                barThickness: 18,
                            }]
                        },
                        options: {
                            indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                            animation: stagger,
                            plugins: {
                                legend: { display: false },
                                tooltip: { callbacks: { title: (items) => rows[items[0].dataIndex].label + ' (' + rows[items[0].dataIndex].code + ')' } }
                            },
                            scales: {
                                x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' } },
                                y: { grid: { display: false } }
                            }
                        }
                    });
                }

                if (el('sugarStatusChart')) {
                    new Chart(el('sugarStatusChart'), {
                        type: 'doughnut',
                        data: {
                            labels: ['Low <4.0', 'Normal 4–7', 'Elevated 7–11', 'High >11'],
                            datasets: [{
                                data: [ds.sugar.low, ds.sugar.normal, ds.sugar.elevated, ds.sugar.high],
                                backgroundColor: [P.red, P.green, P.amber, P.orange],
                                hoverOffset: 8,
                                borderWidth: 2, borderColor: '#fff', cutout: '62%',
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { legend: { position: 'bottom' } }
                        }
                    });
                }

                if (el('hgtFreqChart')) {
                    const labels = Object.keys(ds.hgt_frequency);
                    new Chart(el('hgtFreqChart'), {
                        type: 'doughnut',
                        data: {
                            labels,
                            datasets: [{
                                data: Object.values(ds.hgt_frequency),
                                backgroundColor: labels.map((_, i) => wheel[i % wheel.length]),
                                hoverOffset: 8,
                                borderWidth: 2, borderColor: '#fff', cutout: '62%',
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { legend: { position: 'bottom' } }
                        }
                    });
                }
            }

            // ---------- Lazy mount: charts animate when their tab first opens ----------
            const inits = { ward: initWard, bed: initBed, patient: initPatient, vitals: initVitals, risk: initRisk, diet: initDiet };
            const mounted = {};
            function mount(tab) {
                if (mounted[tab] || !inits[tab]) return;
                mounted[tab] = true;
                requestAnimationFrame(() => setTimeout(() => inits[tab](), 40));
            }
            window.addEventListener('cc-tab', (e) => mount(e.detail));
            mount(@json($activeTab));
        });
    </script>
</x-app-layout>
