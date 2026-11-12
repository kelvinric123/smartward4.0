<!-- Screen Status: battery, signal, online state and last paint of each screen, as SEEKINK reports them -->
<div x-data="ekadScreenStatus()" x-init="load()"
    class="no-print bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-indigo-100">
    <div class="p-6 border-b border-indigo-100 bg-gradient-to-r from-indigo-50 to-violet-50">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center">
                <div class="p-3 bg-indigo-600 rounded-xl mr-4">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-800">Screen Status</h3>
                    <p class="text-sm text-gray-500">Battery, signal and last paint of each screen, as SEEKINK reports them</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span x-show="fetchedAt" class="text-xs text-gray-500">Updated <span x-text="fetchedAt"></span></span>
                <button @click="load()" :disabled="loading"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:bg-indigo-400 text-white font-medium rounded-lg transition-colors">
                    <svg class="w-4 h-4 mr-2" :class="loading && 'animate-spin'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span x-text="loading ? 'Reading SEEKINK...' : 'Refresh'"></span>
                </button>
            </div>
        </div>
    </div>

    <div class="p-6 space-y-4">
        <div x-show="error" x-cloak class="p-3 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700">
            <span class="font-semibold">Couldn't read SEEKINK:</span> <span x-text="error"></span>
        </div>

        <!-- Summary; each count filters the table -->
        <div x-show="summary" x-cloak class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <template x-for="tile in tiles()" :key="tile.key">
                <button type="button" @click="filter = filter === tile.key ? 'all' : tile.key"
                    class="text-left p-3 rounded-xl border transition-colors"
                    :class="filter === tile.key ? 'border-indigo-500 ring-2 ring-indigo-300 bg-white' : 'border-gray-200 bg-gray-50 hover:bg-white'">
                    <div class="text-2xl font-bold" :class="tile.count > 0 ? tile.color : 'text-gray-400'" x-text="tile.count"></div>
                    <div class="text-xs font-medium text-gray-600" x-text="tile.label"></div>
                </button>
            </template>
        </div>

        <div x-show="summary" x-cloak class="flex flex-wrap items-center gap-3">
            <select x-model="ward"
                class="rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="">All wards</option>
                <template x-for="name in wards()" :key="name">
                    <option :value="name" x-text="name"></option>
                </template>
            </select>
            <input type="search" x-model="search" placeholder="Bed, screen name or MAC"
                class="rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-56">
            <span class="text-xs text-gray-500">
                <span x-text="visibleScreens().length"></span> of <span x-text="screens.length"></span> mapped screens
                <template x-if="filter !== 'all'">
                    <button type="button" @click="filter = 'all'" class="ml-1 text-indigo-600 hover:text-indigo-800 font-medium">Show all</button>
                </template>
            </span>
        </div>

        <!-- Mapped screens -->
        <div x-show="summary" x-cloak class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ward / Bed</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Screen</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Battery</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Signal</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last report</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last paint</th>
                        <th class="px-3 py-3"></th>
                    </tr>
                </thead>
                <template x-for="screen in visibleScreens()" :key="screen.mapping_id">
                    <tbody class="bg-white divide-y divide-gray-100 border-b border-gray-200">
                        <tr :class="needsAttention(screen) && 'bg-amber-50/40'">
                            <td class="px-3 py-3 text-sm">
                                <div class="font-semibold text-gray-900" x-text="screen.bed || '-'"></div>
                                <div class="text-xs text-gray-500" x-text="screen.ward || '-'"></div>
                            </td>
                            <td class="px-3 py-3 text-sm">
                                <div class="font-medium text-gray-900" x-text="screen.nickname || '-'"></div>
                                <div class="text-xs font-mono text-gray-500" x-text="screen.mac"></div>
                                <div x-show="screen.firmware" class="text-[11px] text-gray-400" x-text="[screen.model, screen.firmware].filter(Boolean).join(' · ')"></div>
                            </td>
                            <td class="px-3 py-3 text-sm whitespace-nowrap">
                                <template x-if="!screen.found">
                                    <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800"
                                        title="SEEKINK has no screen with this MAC address. Check the MAC in Bed Mapping.">Not in SEEKINK</span>
                                </template>
                                <template x-if="screen.found">
                                    <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full"
                                        :class="screen.online ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700'">
                                        <span class="w-1.5 h-1.5 rounded-full mr-1.5" :class="screen.online ? 'bg-green-500' : 'bg-gray-500'"></span>
                                        <span x-text="screen.online ? 'Online' : 'Offline'"></span>
                                    </span>
                                </template>
                                <div x-show="!screen.mapping_active" class="mt-1 text-[11px] text-gray-500">Mapping inactive</div>
                            </td>
                            <td class="px-3 py-3 text-sm whitespace-nowrap">
                                <template x-if="screen.battery !== null">
                                    <div class="flex items-center gap-2" :title="screen.low_battery ? 'Low battery: charge or replace' : ''">
                                        <div class="w-10 h-3 rounded-sm border border-gray-400 p-0.5">
                                            <div class="h-full rounded-sm" :class="batteryColor(screen)" :style="`width: ${Math.max(4, Math.min(100, screen.battery))}%`"></div>
                                        </div>
                                        <span class="font-medium" :class="screen.low_battery ? 'text-red-600' : 'text-gray-700'" x-text="screen.battery + '%'"></span>
                                    </div>
                                </template>
                                <span x-show="screen.battery === null" class="text-gray-400">-</span>
                            </td>
                            <td class="px-3 py-3 text-sm whitespace-nowrap">
                                <template x-if="screen.signal_level">
                                    <div class="flex items-end gap-1.5" :title="`Bluetooth ${screen.signal} dBm`">
                                        <div class="flex items-end gap-px h-3.5">
                                            <template x-for="bar in [1, 2, 3]" :key="bar">
                                                <div class="w-1 rounded-sm" :style="`height: ${bar * 33}%`"
                                                    :class="bar <= signalBars(screen) ? signalColor(screen) : 'bg-gray-200'"></div>
                                            </template>
                                        </div>
                                        <span class="text-xs text-gray-600" x-text="screen.signal + ' dBm'"></span>
                                    </div>
                                </template>
                                <span x-show="!screen.signal_level" class="text-gray-400">-</span>
                            </td>
                            <td class="px-3 py-3 text-sm whitespace-nowrap">
                                <div class="text-gray-700" x-text="screen.last_report_ago || '-'"></div>
                                <div class="text-[11px] text-gray-400" x-text="screen.last_report || ''"></div>
                            </td>
                            <td class="px-3 py-3 text-sm">
                                <template x-if="screen.last_paint">
                                    <div>
                                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full" :class="paintColor(screen.last_paint)"
                                            x-text="screen.last_paint.status_label"></span>
                                        <span class="text-xs text-gray-500 ml-1" x-text="screen.last_paint.ago"></span>
                                        <div class="text-[11px] text-gray-500" x-text="screen.last_paint.code_label"></div>
                                    </div>
                                </template>
                                <span x-show="!screen.last_paint" class="text-xs text-gray-400" title="No paint in SEEKINK's latest jobs; History reads this screen's own">Not recent</span>
                            </td>
                            <td class="px-3 py-3 text-right whitespace-nowrap">
                                <button x-show="screen.found" @click="toggleHistory(screen.mac)"
                                    class="text-indigo-600 hover:text-indigo-800 text-sm font-medium"
                                    x-text="history[screen.mac]?.open ? 'Hide' : 'History'"></button>
                            </td>
                        </tr>
                        <tr x-show="history[screen.mac]?.open" x-cloak class="bg-gray-50">
                            <td colspan="8" class="px-6 py-3">
                                <div x-show="history[screen.mac]?.loading" class="text-sm text-gray-500">Reading paint jobs...</div>
                                <div x-show="history[screen.mac]?.error" class="text-sm text-red-600" x-text="history[screen.mac]?.error"></div>
                                <template x-if="history[screen.mac]?.paints">
                                    <div>
                                        <div class="text-xs font-semibold text-gray-600 mb-2">Last <span x-text="history[screen.mac].paints.length"></span> paint jobs</div>
                                        <table class="text-xs">
                                            <tbody class="divide-y divide-gray-200">
                                                <template x-for="(paint, i) in history[screen.mac].paints" :key="i">
                                                    <tr>
                                                        <td class="py-1.5 pr-4 text-gray-600 whitespace-nowrap" x-text="paint.sent_at"></td>
                                                        <td class="py-1.5 pr-4"><span class="inline-flex px-2 py-0.5 font-medium rounded-full" :class="paintColor(paint)" x-text="paint.status_label"></span></td>
                                                        <td class="py-1.5 pr-4 text-gray-700" x-text="paint.code_label || '-'"></td>
                                                        <td class="py-1.5 text-gray-400 whitespace-nowrap" x-text="paint.at && paint.at !== paint.sent_at ? 'reported ' + paint.at : ''"></td>
                                                    </tr>
                                                </template>
                                                <tr x-show="history[screen.mac].paints.length === 0">
                                                    <td class="py-1.5 text-gray-500">SEEKINK has no paint jobs for this screen.</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </template>
                <tbody x-show="visibleScreens().length === 0">
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-gray-500"
                            x-text="screens.length === 0 ? 'No bed mappings configured yet.' : 'No screens match.'"></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Screens in SEEKINK that no bed is mapped to -->
        <div x-show="unmapped.length > 0" x-cloak class="rounded-xl border border-gray-200">
            <button type="button" @click="showUnmapped = !showUnmapped"
                class="w-full flex items-center justify-between px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 rounded-xl">
                <span><span x-text="unmapped.length"></span> screens in SEEKINK aren't mapped to a bed</span>
                <svg class="w-4 h-4 transition-transform" :class="showUnmapped && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="showUnmapped" x-cloak class="overflow-x-auto border-t border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Screen</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">MAC</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Battery</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Last report</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="screen in unmapped" :key="screen.mac">
                            <tr>
                                <td class="px-3 py-2 font-medium text-gray-900" x-text="screen.nickname || '-'"></td>
                                <td class="px-3 py-2 font-mono text-gray-600" x-text="screen.mac"></td>
                                <td class="px-3 py-2" :class="screen.online ? 'text-green-700' : 'text-gray-500'" x-text="screen.online ? 'Online' : 'Offline'"></td>
                                <td class="px-3 py-2" :class="screen.low_battery ? 'text-red-600 font-medium' : 'text-gray-700'"
                                    x-text="screen.battery === null ? '-' : screen.battery + '%'"></td>
                                <td class="px-3 py-2 text-gray-600" x-text="screen.last_report_ago || '-'"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    function ekadScreenStatus() {
        return {
            loading: false,
            error: '',
            fetchedAt: '',
            summary: null,
            screens: [],
            unmapped: [],
            lowBatteryLevel: 20,
            filter: 'all',
            ward: '',
            search: '',
            showUnmapped: false,
            history: {},

            async load() {
                this.loading = true;
                this.error = '';
                try {
                    const response = await fetch('{{ route('ekad.screen-status') }}', { headers: { 'Accept': 'application/json' } });
                    const result = await response.json();
                    if (!result.success) {
                        this.error = result.message || 'Unknown error';
                        return;
                    }
                    this.summary = result.summary;
                    this.screens = result.screens;
                    this.unmapped = result.unmapped;
                    this.lowBatteryLevel = result.low_battery_level;
                    this.fetchedAt = result.fetched_at;
                    this.history = {};
                } catch (e) {
                    this.error = e.message;
                } finally {
                    this.loading = false;
                }
            },

            tiles() {
                const s = this.summary || {};
                return [
                    { key: 'all', label: 'Mapped screens', count: s.mapped, color: 'text-gray-900' },
                    { key: 'online', label: 'Online', count: s.online, color: 'text-green-600' },
                    { key: 'offline', label: 'Offline', count: s.offline, color: 'text-gray-700' },
                    { key: 'low_battery', label: `Battery ≤ ${this.lowBatteryLevel}%`, count: s.low_battery, color: 'text-red-600' },
                    { key: 'paint_failed', label: 'Last paint failed', count: s.paint_failed, color: 'text-red-600' },
                    { key: 'missing', label: 'Not in SEEKINK', count: s.missing, color: 'text-amber-600' },
                ];
            },

            wards() {
                return [...new Set(this.screens.map(s => s.ward).filter(Boolean))].sort();
            },

            visibleScreens() {
                const term = this.search.trim().toLowerCase();
                return this.screens.filter(s => {
                    if (this.ward && s.ward !== this.ward) return false;
                    if (term && ![s.bed, s.nickname, s.mac].some(v => (v || '').toLowerCase().includes(term))) return false;
                    switch (this.filter) {
                        case 'online': return s.found && s.online;
                        case 'offline': return s.found && !s.online;
                        case 'low_battery': return s.low_battery;
                        case 'paint_failed': return s.last_paint?.failed;
                        case 'missing': return !s.found;
                        default: return true;
                    }
                });
            },

            needsAttention(screen) {
                return !screen.found || !screen.online || screen.low_battery || screen.last_paint?.failed;
            },

            batteryColor(screen) {
                if (screen.low_battery) return 'bg-red-500';
                return screen.battery <= 40 ? 'bg-amber-400' : 'bg-green-500';
            },

            signalBars(screen) {
                return { good: 3, fair: 2, weak: 1 }[screen.signal_level] || 0;
            },

            signalColor(screen) {
                return { good: 'bg-green-500', fair: 'bg-amber-400', weak: 'bg-red-500' }[screen.signal_level] || 'bg-gray-300';
            },

            paintColor(paint) {
                if (paint.failed) return 'bg-red-100 text-red-800';
                return paint.status === 1 ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800';
            },

            async toggleHistory(mac) {
                const entry = this.history[mac];
                if (entry?.open) {
                    entry.open = false;
                    return;
                }
                if (entry?.paints) {
                    entry.open = true;
                    return;
                }
                this.history[mac] = { open: true, loading: true, error: '', paints: null };
                try {
                    const response = await fetch(`{{ url('ekad/screen-status') }}/${encodeURIComponent(mac)}/paints`, { headers: { 'Accept': 'application/json' } });
                    const result = await response.json();
                    if (result.success) {
                        this.history[mac].paints = result.paints;
                    } else {
                        this.history[mac].error = result.message || 'Unknown error';
                    }
                } catch (e) {
                    this.history[mac].error = e.message;
                } finally {
                    this.history[mac].loading = false;
                }
            },
        };
    }
</script>
