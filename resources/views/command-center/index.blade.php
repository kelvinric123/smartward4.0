<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-bold text-2xl text-slate-800 leading-tight">{{ __('Command Center') }}</h2>
                <p class="text-sm text-slate-500 mt-1">Ward, bed, admission &amp; vital sign analytics</p>
            </div>
            <div class="text-sm text-slate-500">{{ date('l, F j, Y') }}</div>
        </div>
    </x-slot>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <div class="py-6" x-data="{ tab: '{{ request('tab', 'ward') }}' }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

            {{-- ------ Filter bar ------ --}}
            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <form method="GET" action="{{ route('command-center.index') }}"
                    class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
                    <input type="hidden" name="tab" x-bind:value="tab">

                    <div class="lg:col-span-2">
                        <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Ward</label>
                        <select name="ward_id"
                            class="w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
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
                            class="w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">To</label>
                        <input type="date" name="end_date" value="{{ $endDate->format('Y-m-d') }}"
                            class="w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Trend Year</label>
                        <select name="year"
                            class="w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            @foreach($availableYears as $y)
                                <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Compare</label>
                        <select name="compare_year"
                            class="w-full rounded-lg border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
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
                                class="text-xs px-2.5 py-1 rounded-md border border-slate-200 text-slate-600 hover:bg-slate-50 hover:border-slate-300 transition-colors">
                                {{ $label }}
                            </a>
                        @endforeach

                        <div class="ml-auto flex items-center gap-2">
                            <a href="{{ route('command-center.index') }}"
                                class="px-3 py-1.5 text-sm text-slate-600 hover:text-slate-900 transition-colors">
                                Reset
                            </a>
                            <button type="submit"
                                class="px-4 py-1.5 bg-slate-900 text-white text-sm rounded-lg font-medium hover:bg-slate-700 transition-colors">
                                Apply
                            </button>
                        </div>
                    </div>
                </form>
                <div class="mt-3 text-xs text-slate-500">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 font-medium">
                        {{ $selectedWard ? $selectedWard->ward_name : 'All Wards' }}
                    </span>
                    <span class="mx-2">·</span>
                    <span>{{ $startDate->format('M j, Y') }} → {{ $endDate->format('M j, Y') }}</span>
                </div>
            </div>

            {{-- ------ Tabs ------ --}}
            <div class="bg-white rounded-xl border border-slate-200">
                <div class="border-b border-slate-200 px-2">
                    <nav class="flex gap-1 overflow-x-auto">
                        @php
                            $tabs = [
                                'ward'    => ['label' => 'Ward Statistics', 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
                                'bed'     => ['label' => 'Bed Statistics', 'icon' => 'M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z'],
                                'patient' => ['label' => 'Patient Statistics', 'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
                            ];
                        @endphp
                        @foreach($tabs as $key => $info)
                            <button type="button" @click="tab = '{{ $key }}'"
                                :class="tab === '{{ $key }}'
                                    ? 'text-slate-900 border-slate-900'
                                    : 'text-slate-500 border-transparent hover:text-slate-800'"
                                class="flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $info['icon'] }}" />
                                </svg>
                                {{ $info['label'] }}
                            </button>
                        @endforeach
                    </nav>
                </div>

                {{-- ===================== WARD TAB ===================== --}}
                <div x-show="tab === 'ward'" x-cloak class="p-5 space-y-5">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <x-cc-stat label="Total Wards" :value="$stats['total_wards']" />
                        <x-cc-stat label="Total Beds" :value="$stats['total_beds']" />
                        <x-cc-stat label="Occupied" :value="$stats['occupied']" hint="{{ $stats['occupancy_rate'] }}% occupancy" />
                        <x-cc-stat label="Admissions" :value="$stats['admissions_period']" hint="in selected period" />
                    </div>

                    <div class="bg-slate-50/60 rounded-xl border border-slate-200 p-5">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">Bed Occupancy by Ward</h3>
                            <span class="text-xs text-slate-400">Current state</span>
                        </div>
                        <div class="relative" style="height: 320px;">
                            <canvas id="wardOccupancyChart"></canvas>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
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
                                        <tr class="hover:bg-slate-50/60">
                                            <td class="px-5 py-3">
                                                <div class="font-medium text-slate-800">{{ $w['ward_name'] }}</div>
                                                <div class="text-xs text-slate-400">{{ $w['ward_code'] }}</div>
                                            </td>
                                            <td class="px-3 py-3 text-right text-slate-700">{{ $w['total'] }}</td>
                                            <td class="px-3 py-3 text-right text-slate-700">{{ $w['occupied'] }}</td>
                                            <td class="px-3 py-3 text-right text-slate-700">{{ $w['available'] }}</td>
                                            <td class="px-3 py-3 text-right text-slate-700">{{ $w['admissions'] }}</td>
                                            <td class="px-3 py-3 text-right text-slate-700">{{ $w['discharges'] }}</td>
                                            <td class="px-5 py-3">
                                                <div class="flex items-center gap-3">
                                                    <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                                        <div class="h-full bg-slate-700"
                                                            style="width: {{ $w['occupancy_rate'] }}%"></div>
                                                    </div>
                                                    <span class="text-xs font-semibold text-slate-700 w-12 text-right">{{ $w['occupancy_rate'] }}%</span>
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
                <div x-show="tab === 'bed'" x-cloak class="p-5 space-y-5">
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                        <x-cc-stat label="Total" :value="$stats['total_beds']" />
                        <x-cc-stat label="Occupied" :value="$stats['occupied']" />
                        <x-cc-stat label="Available" :value="$stats['available']" />
                        <x-cc-stat label="Reserved" :value="$stats['reserved']" />
                        <x-cc-stat label="Maintenance" :value="$stats['maintenance']" />
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                        <div class="bg-slate-50/60 rounded-xl border border-slate-200 p-5">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">Bed Status</h3>
                                <span class="text-xs text-slate-400">Now</span>
                            </div>
                            <div class="relative" style="height: 240px;">
                                <canvas id="bedStatusChart"></canvas>
                            </div>
                            <div class="mt-4 space-y-1.5 text-xs">
                                <div class="flex items-center justify-between">
                                    <span class="flex items-center text-slate-600"><span class="w-2.5 h-2.5 rounded-full bg-slate-800 mr-2"></span>Occupied</span>
                                    <span class="font-medium text-slate-700">{{ $stats['occupied'] }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="flex items-center text-slate-600"><span class="w-2.5 h-2.5 rounded-full bg-slate-400 mr-2"></span>Available</span>
                                    <span class="font-medium text-slate-700">{{ $stats['available'] }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="flex items-center text-slate-600"><span class="w-2.5 h-2.5 rounded-full bg-slate-300 mr-2"></span>Reserved</span>
                                    <span class="font-medium text-slate-700">{{ $stats['reserved'] }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="flex items-center text-slate-600"><span class="w-2.5 h-2.5 rounded-full bg-slate-200 mr-2"></span>Maintenance</span>
                                    <span class="font-medium text-slate-700">{{ $stats['maintenance'] }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="lg:col-span-2 bg-slate-50/60 rounded-xl border border-slate-200 p-5">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">Occupancy Rate</h3>
                                <span class="text-xs text-slate-400">Per ward · current</span>
                            </div>
                            <div class="relative" style="height: 280px;">
                                <canvas id="occupancyRateChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ===================== PATIENT TAB ===================== --}}
                <div x-show="tab === 'patient'" x-cloak class="p-5 space-y-5">
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                        <x-cc-stat label="Admitted" :value="$stats['admitted_patients']" />
                        <x-cc-stat label="Pending Discharge" :value="$stats['pending_discharge']" />
                        <x-cc-stat label="Prebooked" :value="$stats['prebooked']" />
                        <x-cc-stat label="Admissions" :value="$stats['admissions_period']" hint="period" />
                        <x-cc-stat label="Discharges" :value="$stats['discharges_period']" hint="period" />
                        <x-cc-stat label="Vital Signs" :value="number_format($stats['vital_signs_period'])" hint="period" />
                    </div>

                    <div class="bg-slate-50/60 rounded-xl border border-slate-200 p-5">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">Monthly Trend</h3>
                                <p class="text-xs text-slate-400 mt-0.5">
                                    {{ $year }}{{ $compareYear ? " vs $compareYear" : '' }} — month by month
                                </p>
                            </div>
                            <div class="flex items-center gap-3 text-xs">
                                <span class="flex items-center text-slate-600"><span class="w-3 h-0.5 bg-slate-800 mr-1.5"></span>Admissions</span>
                                <span class="flex items-center text-slate-600"><span class="w-3 h-0.5 bg-slate-400 mr-1.5"></span>Discharges</span>
                                <span class="flex items-center text-slate-600"><span class="w-3 h-0.5 bg-slate-300 mr-1.5 border border-dashed border-slate-500"></span>Vital Signs</span>
                            </div>
                        </div>
                        <div class="relative" style="height: 360px;">
                            <canvas id="monthlyTrendChart"></canvas>
                        </div>
                    </div>

                    <div class="bg-slate-50/60 rounded-xl border border-slate-200 p-5">
                        <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wider mb-3">Admissions vs Discharges</h3>
                        <p class="text-xs text-slate-400 mb-3">{{ $startDate->format('M j') }} – {{ $endDate->format('M j, Y') }}</p>
                        <div class="relative" style="height: 220px;">
                            <canvas id="admDischargeChart"></canvas>
                        </div>
                    </div>

                    {{-- ---------- Vital Signs Volume — two normalized rate graphs ---------- --}}
                    <div class="space-y-5">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">Vital Signs Volume</h3>
                            <p class="text-xs text-slate-400 mt-0.5">Normalized rates · {{ $startDate->format('M j') }} – {{ $endDate->format('M j, Y') }}</p>
                        </div>

                        {{-- Graph 1: Per Patient / Per Day --}}
                        <div class="bg-slate-50/60 rounded-xl border border-slate-200 p-5">
                            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                                <div>
                                    <div class="text-xs font-semibold text-slate-700 uppercase tracking-wider">Per Patient / Per Day</div>
                                    <div class="text-xs text-slate-400 mt-0.5">
                                        Avg vital signs each patient received per day
                                        <span class="text-slate-400">·</span>
                                        <span class="font-mono">vitals ÷ distinct patients</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-5 text-xs">
                                    <div>
                                        <div class="text-slate-400 uppercase tracking-wider text-[10px]">Period avg</div>
                                        <div class="text-sm font-semibold text-slate-800 tabular-nums">{{ $vitalsRates['avg_per_patient'] }}</div>
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
                        <div class="bg-slate-50/60 rounded-xl border border-slate-200 p-5">
                            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                                <div>
                                    <div class="text-xs font-semibold text-slate-700 uppercase tracking-wider">Per Patient / Per Admission / Per Day</div>
                                    <div class="text-xs text-slate-400 mt-0.5">
                                        Avg vital signs per admission episode per day
                                        <span class="text-slate-400">·</span>
                                        <span class="font-mono">vitals ÷ distinct (patient, admission)</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-5 text-xs">
                                    <div>
                                        <div class="text-slate-400 uppercase tracking-wider text-[10px]">Period avg</div>
                                        <div class="text-sm font-semibold text-slate-800 tabular-nums">{{ $vitalsRates['avg_per_admission'] }}</div>
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
            </div>
        </div>
    </div>

    <style>[x-cloak] { display: none !important; }</style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            Chart.defaults.font.family = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
            Chart.defaults.color = '#64748b';
            Chart.defaults.borderColor = '#e2e8f0';

            const ink = '#0f172a';
            const ink70 = '#334155';
            const ink50 = '#64748b';
            const ink30 = '#94a3b8';
            const ink20 = '#cbd5e1';
            const ink10 = '#e2e8f0';

            // ----- Ward occupancy stacked bar -----
            const wardData = @json($wardBreakdown);
            if (document.getElementById('wardOccupancyChart')) {
                new Chart(document.getElementById('wardOccupancyChart').getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: wardData.map(w => w.ward_code || w.ward_name),
                        datasets: [
                            { label: 'Occupied',    data: wardData.map(w => w.occupied),    backgroundColor: ink,    borderRadius: 4, stack: 'beds' },
                            { label: 'Available',   data: wardData.map(w => w.available),   backgroundColor: ink30,  borderRadius: 4, stack: 'beds' },
                            { label: 'Reserved',    data: wardData.map(w => w.reserved),    backgroundColor: ink20,  borderRadius: 4, stack: 'beds' },
                            { label: 'Maintenance', data: wardData.map(w => w.maintenance), backgroundColor: ink10,  borderRadius: 4, stack: 'beds' },
                        ]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10 } } },
                        scales: {
                            x: { stacked: true, grid: { display: false } },
                            y: { stacked: true, beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' } }
                        }
                    }
                });
            }

            // ----- Bed status donut -----
            if (document.getElementById('bedStatusChart')) {
                new Chart(document.getElementById('bedStatusChart').getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Occupied', 'Available', 'Reserved', 'Maintenance'],
                        datasets: [{
                            data: [{{ $stats['occupied'] }}, {{ $stats['available'] }}, {{ $stats['reserved'] }}, {{ $stats['maintenance'] }}],
                            backgroundColor: [ink, ink30, ink20, ink10],
                            borderWidth: 2, borderColor: '#fff', cutout: '70%',
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

            // ----- Per-ward occupancy rate (horizontal) -----
            if (document.getElementById('occupancyRateChart')) {
                new Chart(document.getElementById('occupancyRateChart').getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: wardData.map(w => w.ward_code || w.ward_name),
                        datasets: [{
                            label: 'Occupancy %',
                            data: wardData.map(w => w.occupancy_rate),
                            backgroundColor: ink70,
                            borderRadius: 4,
                            barThickness: 16,
                        }]
                    },
                    options: {
                        indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' }, grid: { color: '#f1f5f9' } },
                            y: { grid: { display: false } }
                        }
                    }
                });
            }

            // ----- Monthly line chart (with compare) -----
            const monthly = @json($monthly);
            const compareMonthly = @json($compareMonthly);
            if (document.getElementById('monthlyTrendChart')) {
                const datasets = [
                    {
                        label: `Admissions ${monthly.year}`,
                        data: monthly.admissions,
                        borderColor: ink, backgroundColor: ink + '14',
                        tension: 0.35, fill: true, pointRadius: 3, borderWidth: 2,
                    },
                    {
                        label: `Discharges ${monthly.year}`,
                        data: monthly.discharges,
                        borderColor: ink50, backgroundColor: 'transparent',
                        tension: 0.35, fill: false, pointRadius: 3, borderWidth: 2,
                    },
                    {
                        label: `Vital Signs ${monthly.year}`,
                        data: monthly.vital_signs,
                        borderColor: ink30, backgroundColor: 'transparent', borderDash: [4, 4],
                        tension: 0.35, fill: false, pointRadius: 3, borderWidth: 2,
                        yAxisID: 'y1',
                    },
                ];
                if (compareMonthly) {
                    datasets.push(
                        { label: `Admissions ${compareMonthly.year}`, data: compareMonthly.admissions,
                          borderColor: ink, borderDash: [2, 4], backgroundColor: 'transparent',
                          tension: 0.35, fill: false, pointRadius: 2, borderWidth: 1.5 },
                        { label: `Discharges ${compareMonthly.year}`, data: compareMonthly.discharges,
                          borderColor: ink50, borderDash: [2, 4], backgroundColor: 'transparent',
                          tension: 0.35, fill: false, pointRadius: 2, borderWidth: 1.5 },
                    );
                }
                new Chart(document.getElementById('monthlyTrendChart').getContext('2d'), {
                    type: 'line',
                    data: { labels: monthly.labels, datasets },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, boxHeight: 2 } } },
                        scales: {
                            y:  { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' },
                                  title: { display: true, text: 'Admissions / Discharges', color: ink50 } },
                            y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false },
                                  title: { display: true, text: 'Vital Signs', color: ink50 } }
                        }
                    }
                });
            }

            // ----- Admissions vs Discharges (period totals) -----
            if (document.getElementById('admDischargeChart')) {
                new Chart(document.getElementById('admDischargeChart').getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: ['Admissions', 'Discharges'],
                        datasets: [{
                            data: [{{ $stats['admissions_period'] }}, {{ $stats['discharges_period'] }}],
                            backgroundColor: [ink, ink30],
                            borderRadius: 6, barThickness: 60,
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { grid: { display: false } },
                            y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' } }
                        }
                    }
                });
            }

            // ----- Vital Signs PER PATIENT / PER DAY -----
            const vitalsRates = @json($vitalsRates);
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
                         title: { display: true, text: labelText, color: ink50, font: { size: 11 } } }
                }
            });

            if (document.getElementById('vitalPerPatientDayChart') && vitalsRates.labels.length > 0) {
                new Chart(document.getElementById('vitalPerPatientDayChart').getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: vitalsRates.labels,
                        datasets: [{
                            label: 'Vitals per patient',
                            data: vitalsRates.per_patient,
                            borderColor: ink, backgroundColor: ink + '14',
                            tension: 0.35, fill: true, pointRadius: 2, borderWidth: 2,
                        }]
                    },
                    options: rateChartOpts(
                        'Vitals per patient',
                        vitalsRates.raw_vitals, vitalsRates.raw_patients,
                        'Total vitals', 'Patients'
                    )
                });
            }

            // ----- Vital Signs PER PATIENT / PER ADMISSION / PER DAY -----
            if (document.getElementById('vitalPerAdmissionDayChart') && vitalsRates.labels.length > 0) {
                new Chart(document.getElementById('vitalPerAdmissionDayChart').getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: vitalsRates.labels,
                        datasets: [{
                            label: 'Vitals per admission',
                            data: vitalsRates.per_admission,
                            borderColor: ink70, backgroundColor: ink70 + '14',
                            tension: 0.35, fill: true, pointRadius: 2, borderWidth: 2,
                        }]
                    },
                    options: rateChartOpts(
                        'Vitals per admission',
                        vitalsRates.raw_vitals, vitalsRates.raw_admissions,
                        'Total vitals', 'Admissions'
                    )
                });
            }
        });
    </script>
</x-app-layout>
