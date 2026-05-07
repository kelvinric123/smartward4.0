<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-bold text-2xl text-gray-800 leading-tight flex items-center">
                    <svg class="w-7 h-7 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                    </svg>
                    {{ $commandCentre->name }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">Patient Flow Command Centre Dashboard</p>
            </div>
            <div class="flex items-center gap-3">
                <div id="live-indicator" class="flex items-center px-3 py-1.5 bg-green-100 border border-green-300 rounded-full">
                    <span class="relative flex h-2.5 w-2.5 mr-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-500 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-green-600"></span>
                    </span>
                    <span class="text-xs font-semibold text-green-800">LIVE</span>
                </div>
                <a href="{{ route('patient-flow-command-centres.index') }}"
                    class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6" x-data="commandCentreDashboard()" x-init="startAutoRefresh()">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Top Summary Cards --}}
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-6">
                {{-- Total Wards --}}
                <div class="bg-white/90 backdrop-blur-sm rounded-xl border border-blue-100 shadow-md p-4 hover:shadow-lg transition-shadow">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Wards</span>
                        <div class="p-1.5 bg-indigo-100 rounded-lg">
                            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                        </div>
                    </div>
                    <p class="text-3xl font-black text-gray-800" x-text="summary.totalWards">{{ count($wardData) }}</p>
                </div>

                {{-- Total Beds --}}
                <div class="bg-white/90 backdrop-blur-sm rounded-xl border border-blue-100 shadow-md p-4 hover:shadow-lg transition-shadow">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Total Beds</span>
                        <div class="p-1.5 bg-blue-100 rounded-lg">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                        </div>
                    </div>
                    <p class="text-3xl font-black text-gray-800" x-text="summary.totalBeds">{{ collect($wardData)->sum('total_beds') }}</p>
                </div>

                {{-- Occupied --}}
                <div class="bg-white/90 backdrop-blur-sm rounded-xl border border-orange-100 shadow-md p-4 hover:shadow-lg transition-shadow">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Occupied</span>
                        <div class="p-1.5 bg-orange-100 rounded-lg">
                            <svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                    </div>
                    <p class="text-3xl font-black text-orange-600" x-text="summary.totalOccupied">{{ collect($wardData)->sum('occupied_beds') }}</p>
                </div>

                {{-- Available --}}
                <div class="bg-white/90 backdrop-blur-sm rounded-xl border border-green-100 shadow-md p-4 hover:shadow-lg transition-shadow">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Available</span>
                        <div class="p-1.5 bg-green-100 rounded-lg">
                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                    <p class="text-3xl font-black text-green-600" x-text="summary.totalAvailable">{{ collect($wardData)->sum('available_beds') }}</p>
                </div>

                {{-- Pending Discharge --}}
                <div class="bg-white/90 backdrop-blur-sm rounded-xl border border-amber-100 shadow-md p-4 hover:shadow-lg transition-shadow">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Pend. Discharge</span>
                        <div class="p-1.5 bg-amber-100 rounded-lg">
                            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                    <p class="text-3xl font-black text-amber-600" x-text="summary.totalPendingDischarge">{{ collect($wardData)->sum('pending_discharge') }}</p>
                </div>

                {{-- Discharged Today --}}
                <div class="bg-white/90 backdrop-blur-sm rounded-xl border border-teal-100 shadow-md p-4 hover:shadow-lg transition-shadow">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Discharged Today</span>
                        <div class="p-1.5 bg-teal-100 rounded-lg">
                            <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                        </div>
                    </div>
                    <p class="text-3xl font-black text-teal-600" x-text="summary.totalDischargedToday">{{ collect($wardData)->sum('discharged_today') }}</p>
                </div>
            </div>

            {{-- Airport-Style Board --}}
            <div class="bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 rounded-2xl shadow-2xl overflow-hidden border border-slate-700">

                {{-- Board Header --}}
                <div class="bg-gradient-to-r from-blue-600 via-cyan-600 to-blue-700 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 text-white mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                            </svg>
                            <h3 class="text-lg font-bold text-white tracking-wide">PATIENT FLOW BOARD</h3>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="text-sm text-blue-100" id="board-clock" x-text="currentTime"></span>
                            <span class="text-xs text-blue-200 bg-blue-800/40 px-3 py-1 rounded-full">
                                Auto-refresh: <span class="font-semibold">30s</span>
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Board Table --}}
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="bg-slate-800/80 border-b border-slate-600">
                                <th class="px-6 py-3 text-left text-xs font-bold text-cyan-400 uppercase tracking-widest">Ward</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-cyan-400 uppercase tracking-widest">Total Beds</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-cyan-400 uppercase tracking-widest">Occupied</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-cyan-400 uppercase tracking-widest">Available</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-cyan-400 uppercase tracking-widest">Admitted Today</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-cyan-400 uppercase tracking-widest">Pending Discharge</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-cyan-400 uppercase tracking-widest">Discharged Today</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-cyan-400 uppercase tracking-widest">Prebooked</th>
                                <th class="px-6 py-3 text-center text-xs font-bold text-cyan-400 uppercase tracking-widest">Occupancy</th>
                            </tr>
                        </thead>
                        <tbody id="ward-rows">
                            <template x-for="(ward, index) in wards" :key="ward.id">
                                <tr class="border-b border-slate-700/50 hover:bg-slate-700/30 transition-colors duration-200"
                                    :class="index % 2 === 0 ? 'bg-slate-800/30' : 'bg-slate-800/10'">
                                    {{-- Ward Name --}}
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="w-2 h-8 rounded-full mr-3"
                                                :class="ward.occupancy_percent >= 90 ? 'bg-red-500' : (ward.occupancy_percent >= 70 ? 'bg-amber-500' : 'bg-green-500')"></div>
                                            <div>
                                                <p class="text-sm font-bold text-white" x-text="ward.ward_name"></p>
                                                <p class="text-xs text-slate-400" x-text="ward.ward_code"></p>
                                            </div>
                                        </div>
                                    </td>
                                    {{-- Total Beds --}}
                                    <td class="px-4 py-4 text-center">
                                        <span class="text-lg font-bold text-slate-200" x-text="ward.total_beds"></span>
                                    </td>
                                    {{-- Occupied --}}
                                    <td class="px-4 py-4 text-center">
                                        <span class="inline-flex items-center justify-center min-w-[2.5rem] px-2 py-1 rounded-lg text-sm font-bold"
                                            :class="ward.occupied_beds > 0 ? 'bg-orange-500/20 text-orange-400 border border-orange-500/30' : 'text-slate-500'"
                                            x-text="ward.occupied_beds"></span>
                                    </td>
                                    {{-- Available --}}
                                    <td class="px-4 py-4 text-center">
                                        <span class="inline-flex items-center justify-center min-w-[2.5rem] px-2 py-1 rounded-lg text-sm font-bold"
                                            :class="ward.available_beds > 0 ? 'bg-green-500/20 text-green-400 border border-green-500/30' : 'bg-red-500/20 text-red-400 border border-red-500/30'"
                                            x-text="ward.available_beds"></span>
                                    </td>
                                    {{-- Admissions Today --}}
                                    <td class="px-4 py-4 text-center">
                                        <span class="inline-flex items-center justify-center min-w-[2.5rem] px-2 py-1 rounded-lg text-sm font-bold"
                                            :class="ward.admissions_today > 0 ? 'bg-blue-500/20 text-blue-400 border border-blue-500/30' : 'text-slate-500'"
                                            x-text="ward.admissions_today"></span>
                                    </td>
                                    {{-- Pending Discharge --}}
                                    <td class="px-4 py-4 text-center">
                                        <span class="inline-flex items-center justify-center min-w-[2.5rem] px-2 py-1 rounded-lg text-sm font-bold"
                                            :class="ward.pending_discharge > 0 ? 'bg-amber-500/20 text-amber-400 border border-amber-500/30 animate-pulse' : 'text-slate-500'"
                                            x-text="ward.pending_discharge"></span>
                                    </td>
                                    {{-- Discharged Today --}}
                                    <td class="px-4 py-4 text-center">
                                        <span class="inline-flex items-center justify-center min-w-[2.5rem] px-2 py-1 rounded-lg text-sm font-bold"
                                            :class="ward.discharged_today > 0 ? 'bg-teal-500/20 text-teal-400 border border-teal-500/30' : 'text-slate-500'"
                                            x-text="ward.discharged_today"></span>
                                    </td>
                                    {{-- Prebooked --}}
                                    <td class="px-4 py-4 text-center">
                                        <span class="inline-flex items-center justify-center min-w-[2.5rem] px-2 py-1 rounded-lg text-sm font-bold"
                                            :class="ward.prebooked > 0 ? 'bg-purple-500/20 text-purple-400 border border-purple-500/30' : 'text-slate-500'"
                                            x-text="ward.prebooked"></span>
                                    </td>
                                    {{-- Occupancy Bar --}}
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="flex-1 bg-slate-700 rounded-full h-3 min-w-[80px] overflow-hidden">
                                                <div class="h-full rounded-full transition-all duration-700 ease-out"
                                                    :class="ward.occupancy_percent >= 90 ? 'bg-gradient-to-r from-red-500 to-red-400' : (ward.occupancy_percent >= 70 ? 'bg-gradient-to-r from-amber-500 to-amber-400' : 'bg-gradient-to-r from-green-500 to-emerald-400')"
                                                    :style="'width: ' + Math.min(ward.occupancy_percent, 100) + '%'">
                                                </div>
                                            </div>
                                            <span class="text-sm font-bold min-w-[3rem] text-right"
                                                :class="ward.occupancy_percent >= 90 ? 'text-red-400' : (ward.occupancy_percent >= 70 ? 'text-amber-400' : 'text-green-400')"
                                                x-text="ward.occupancy_percent + '%'"></span>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>

                        {{-- Summary Footer --}}
                        <tfoot>
                            <tr class="bg-gradient-to-r from-slate-700 via-slate-700 to-slate-700 border-t-2 border-cyan-500/50">
                                <td class="px-6 py-4">
                                    <span class="text-sm font-black text-cyan-400 uppercase tracking-wider">TOTAL</span>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span class="text-lg font-black text-white" x-text="summary.totalBeds"></span>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span class="text-lg font-black text-orange-400" x-text="summary.totalOccupied"></span>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span class="text-lg font-black text-green-400" x-text="summary.totalAvailable"></span>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span class="text-lg font-black text-blue-400" x-text="summary.totalAdmittedToday"></span>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span class="text-lg font-black text-amber-400" x-text="summary.totalPendingDischarge"></span>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span class="text-lg font-black text-teal-400" x-text="summary.totalDischargedToday"></span>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span class="text-lg font-black text-purple-400" x-text="summary.totalPrebooked"></span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex-1 bg-slate-600 rounded-full h-3 min-w-[80px] overflow-hidden">
                                            <div class="h-full rounded-full transition-all duration-700 ease-out"
                                                :class="summary.overallOccupancy >= 90 ? 'bg-gradient-to-r from-red-500 to-red-400' : (summary.overallOccupancy >= 70 ? 'bg-gradient-to-r from-amber-500 to-amber-400' : 'bg-gradient-to-r from-green-500 to-emerald-400')"
                                                :style="'width: ' + Math.min(summary.overallOccupancy, 100) + '%'">
                                            </div>
                                        </div>
                                        <span class="text-sm font-bold min-w-[3rem] text-right"
                                            :class="summary.overallOccupancy >= 90 ? 'text-red-400' : (summary.overallOccupancy >= 70 ? 'text-amber-400' : 'text-green-400')"
                                            x-text="summary.overallOccupancy + '%'"></span>
                                    </div>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                {{-- Board Footer --}}
                <div class="bg-slate-800/80 px-6 py-3 border-t border-slate-700 flex items-center justify-between">
                    <div class="flex items-center gap-6">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-full bg-green-500"></div>
                            <span class="text-xs text-slate-400">&lt; 70% Occupancy</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-full bg-amber-500"></div>
                            <span class="text-xs text-slate-400">70-89% Occupancy</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-full bg-red-500"></div>
                            <span class="text-xs text-slate-400">≥ 90% Occupancy</span>
                        </div>
                    </div>
                    <div class="text-xs text-slate-500">
                        Last updated: <span x-text="lastUpdated" class="text-slate-400"></span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        function commandCentreDashboard() {
            return {
                wards: @json($wardData),
                currentTime: '',
                lastUpdated: '',
                refreshInterval: null,
                clockInterval: null,

                get summary() {
                    const totalWards = this.wards.length;
                    const totalBeds = this.wards.reduce((s, w) => s + w.total_beds, 0);
                    const totalOccupied = this.wards.reduce((s, w) => s + w.occupied_beds, 0);
                    const totalAvailable = this.wards.reduce((s, w) => s + w.available_beds, 0);
                    const totalAdmittedToday = this.wards.reduce((s, w) => s + w.admissions_today, 0);
                    const totalPendingDischarge = this.wards.reduce((s, w) => s + w.pending_discharge, 0);
                    const totalDischargedToday = this.wards.reduce((s, w) => s + w.discharged_today, 0);
                    const totalPrebooked = this.wards.reduce((s, w) => s + w.prebooked, 0);
                    const overallOccupancy = totalBeds > 0 ? Math.round((totalOccupied / totalBeds) * 100) : 0;

                    return {
                        totalWards,
                        totalBeds,
                        totalOccupied,
                        totalAvailable,
                        totalAdmittedToday,
                        totalPendingDischarge,
                        totalDischargedToday,
                        totalPrebooked,
                        overallOccupancy
                    };
                },

                startAutoRefresh() {
                    this.updateClock();
                    this.lastUpdated = this.formatNow();

                    // Update clock every second
                    this.clockInterval = setInterval(() => this.updateClock(), 1000);

                    // Refresh data every 30 seconds
                    this.refreshInterval = setInterval(() => this.fetchData(), 30000);
                },

                updateClock() {
                    const now = new Date();
                    this.currentTime = now.toLocaleDateString('en-GB', {
                        day: '2-digit', month: 'short', year: 'numeric'
                    }) + ', ' + now.toLocaleTimeString('en-US', {
                        hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
                    });
                },

                formatNow() {
                    const now = new Date();
                    return now.toLocaleDateString('en-GB', {
                        day: '2-digit', month: 'short', year: 'numeric'
                    }) + ', ' + now.toLocaleTimeString('en-US', {
                        hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
                    });
                },

                async fetchData() {
                    try {
                        const response = await fetch('{{ route("patient-flow-command-centres.dashboard-data", $commandCentre) }}');
                        const data = await response.json();
                        this.wards = data.wardData;
                        this.lastUpdated = data.timestamp;
                    } catch (err) {
                        console.error('Failed to refresh dashboard data:', err);
                    }
                }
            }
        }
    </script>
</x-app-layout>
