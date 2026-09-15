{{-- Registered Pump Users: grouped by ward, searchable, with a tile view and
     a table view - the same shape as the Qmed Gateways section on the vital
     sign integration page. --}}
@php
    // Pumps report every few seconds while they are on, so "quiet" here means
    // minutes, not the hours a gateway heartbeat allows.
    $freshness = function ($pump) {
        if (!$pump->last_seen_at) {
            return ['key' => 'never', 'label' => 'Never seen', 'dot' => '#9ca3af', 'bg' => '#f3f4f6', 'text' => '#4b5563'];
        }
        if ($pump->last_seen_at->gt(now()->subMinutes(5))) {
            return ['key' => 'live', 'label' => 'Reporting', 'dot' => '#22c55e', 'bg' => '#dcfce7', 'text' => '#15803d'];
        }
        if ($pump->last_seen_at->gt(now()->subDay())) {
            return ['key' => 'quiet', 'label' => 'Quiet', 'dot' => '#eab308', 'bg' => '#fef9c3', 'text' => '#854d0e'];
        }

        return ['key' => 'stale', 'label' => 'Not reporting', 'dot' => '#ef4444', 'bg' => '#fee2e2', 'text' => '#b91c1c'];
    };

    // Must stay in step with pumpHaystacks in infusionIntegration(), or the
    // "no matches" count and the rows on screen disagree.
    $haystack = fn ($p) => Str::lower(implode(' ', array_filter([
        $p->device_name, $p->serial_no, $p->asset_no, $p->device_id,
        $p->device_type, $p->location, $p->pump_model,
        $p->ward->ward_name ?? 'Unassigned',
        $p->patient->name ?? null,
    ])));

    $grouped = $pumps
        ->groupBy(fn ($p) => $p->ward->ward_name ?? 'Unassigned')
        ->sortKeys();

    // Unassigned is a to-do list, not a ward - keep it last.
    if ($grouped->has('Unassigned')) {
        $unassigned = $grouped->get('Unassigned');
        $grouped = $grouped->forget('Unassigned')->put('Unassigned', $unassigned);
    }

    $totalPumps = $pumps->count();
    $reporting = $pumps->filter(fn ($p) => $freshness($p)['key'] === 'live')->count();
@endphp

<div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-purple-100">
    <div class="p-6 border-b border-purple-100 bg-gradient-to-r from-purple-50 to-pink-50">
        <div class="flex flex-wrap justify-between items-center gap-4">
            <div class="flex items-center">
                <div class="p-3 bg-purple-600 rounded-xl mr-4">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-800">Registered Pump Users</h3>
                    <p class="text-sm text-gray-500">
                        @if($totalPumps > 0)
                            <span class="font-medium text-purple-700">{{ $reporting }}</span> of {{ $totalPumps }}
                            reporting &middot; {{ $grouped->count() }}
                            {{ Str::plural('ward', $grouped->count()) }}
                        @else
                            Pumps are auto-registered when they first send HL7 data
                        @endif
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="relative">
                    <input type="text" x-model="pumpSearch"
                        placeholder="Search name, serial, ward, patient…"
                        class="w-64 max-w-full pl-9 pr-8 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <button type="button" x-show="pumpSearch" x-cloak @click="pumpSearch = ''"
                        class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="inline-flex rounded-lg bg-gray-100 p-1 shadow-inner">
                    <button type="button" @click="setPumpView('panel')"
                        :class="pumpView === 'panel' ? 'bg-white text-purple-700 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                        class="inline-flex items-center px-3 py-1.5 rounded-md text-sm font-medium transition-all">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM14 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zM14 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z" />
                        </svg>
                        Control Panel
                    </button>
                    <button type="button" @click="setPumpView('details')"
                        :class="pumpView === 'details' ? 'bg-white text-purple-700 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                        class="inline-flex items-center px-3 py-1.5 rounded-md text-sm font-medium transition-all">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        Details
                    </button>
                </div>

                <button @click="showAddPumpModal = true"
                    class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Pump
                </button>
            </div>
        </div>
    </div>

    <div class="p-6">
        @if($totalPumps > 0)
            <div class="flex flex-wrap items-center gap-x-5 gap-y-1 mb-5 text-xs text-gray-500">
                <span class="font-medium text-gray-600">Status:</span>
                <span class="flex items-center"><span class="w-2.5 h-2.5 rounded-full mr-1.5"
                        style="background-color:#22c55e"></span>Reporting (seen &lt; 5 min)</span>
                <span class="flex items-center"><span class="w-2.5 h-2.5 rounded-full mr-1.5"
                        style="background-color:#eab308"></span>Quiet (seen today)</span>
                <span class="flex items-center"><span class="w-2.5 h-2.5 rounded-full mr-1.5"
                        style="background-color:#ef4444"></span>Not reporting</span>
                <span class="flex items-center"><span class="w-2.5 h-2.5 rounded-full mr-1.5"
                        style="background-color:#9ca3af"></span>Never seen</span>
                <span x-show="pumpSearch" x-cloak class="ml-auto font-medium text-purple-700">
                    <span x-text="matchingPumps(pumpSearch)"></span> of {{ $totalPumps }} match
                </span>
            </div>

            <div x-show="matchingPumps(pumpSearch) === 0" x-cloak class="py-10 text-center">
                <p class="text-gray-500 font-medium">No pump matches “<span x-text="pumpSearch"></span>”</p>
                <button type="button" @click="pumpSearch = ''"
                    class="mt-2 text-sm font-medium text-purple-600 hover:underline">Clear search</button>
            </div>

            {{-- ==================== CONTROL PANEL VIEW ==================== --}}
            <div x-show="pumpView === 'panel'" class="space-y-8">
                @foreach($grouped as $wardName => $group)
                    <div data-search="{{ $group->map($haystack)->implode(' | ') }}" x-show="pumpSearch.trim() === '' || $el.dataset.search.includes(pumpSearch.trim().toLowerCase())">
                        <div class="flex items-center gap-3 mb-3 pb-2 border-b border-gray-100">
                            <svg class="w-5 h-5 {{ $wardName === 'Unassigned' ? 'text-gray-400' : 'text-purple-600' }}"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            <h4 class="text-base font-bold text-gray-800">{{ $wardName }}</h4>
                            <span
                                class="px-2 py-0.5 text-xs font-medium rounded-full border {{ $wardName === 'Unassigned' ? 'bg-gray-50 text-gray-600 border-gray-200' : 'bg-purple-50 text-purple-700 border-purple-100' }}">
                                {{ $group->filter(fn($p) => $freshness($p)['key'] === 'live')->count() }}/{{ $group->count() }}
                                reporting
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                            @foreach($group as $pump)
                                @php $fresh = $freshness($pump); @endphp
                                <div data-search="{{ $haystack($pump) }}" x-show="pumpSearch.trim() === '' || $el.dataset.search.includes(pumpSearch.trim().toLowerCase())"
                                    class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm hover:shadow-md transition-shadow">
                                    {{-- Name first: it is what a person calls the pump. --}}
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-1.5">
                                                <span class="w-2.5 h-2.5 rounded-full flex-shrink-0"
                                                    style="background-color:{{ $fresh['dot'] }}"
                                                    title="{{ $fresh['label'] }}"></span>
                                                <span class="font-bold text-gray-800 truncate"
                                                    title="{{ $pump->device_name ?? $pump->device_id }}">
                                                    {{ $pump->device_name ?: ($pump->serial_no ?: $pump->device_id) }}
                                                </span>
                                            </div>
                                            <div class="mt-0.5 text-xs text-gray-500 truncate">
                                                {{ $pump->ward->ward_name ?? 'No ward assigned' }}
                                                @if($pump->location)
                                                    &middot; {{ $pump->location }}
                                                @endif
                                            </div>
                                        </div>
                                        <span class="shrink-0 text-[10px] font-semibold px-1.5 py-0.5 rounded"
                                            style="background-color:{{ $fresh['bg'] }};color:{{ $fresh['text'] }}">
                                            {{ $fresh['label'] }}
                                        </span>
                                    </div>

                                    <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-1.5 text-xs">
                                        <div class="col-span-2">
                                            <dt class="text-[10px] uppercase tracking-wide text-gray-400">Device ID</dt>
                                            <dd class="font-mono text-gray-600 truncate"
                                                title="{{ $pump->device_id }}">{{ $pump->device_id }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-[10px] uppercase tracking-wide text-gray-400">Serial</dt>
                                            <dd class="text-gray-700">{{ $pump->serial_no ?: '—' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-[10px] uppercase tracking-wide text-gray-400">Asset</dt>
                                            <dd class="text-gray-700">{{ $pump->asset_no ?: '—' }}</dd>
                                        </div>
                                    </dl>

                                    @if($pump->patient)
                                        <div
                                            class="mt-2.5 flex items-center gap-1.5 rounded-lg bg-emerald-50 border border-emerald-100 px-2 py-1 text-xs text-emerald-800">
                                            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                            </svg>
                                            <span class="truncate">
                                                @if($pump->patient->bed_number)
                                                    <span class="font-semibold">{{ $pump->patient->bed_number }}</span> ·
                                                @endif
                                                {{ $pump->patient->name }}
                                            </span>
                                        </div>
                                    @endif

                                    <div
                                        class="mt-3 flex items-center justify-between gap-2 border-t border-gray-100 pt-2.5">
                                        <div class="flex items-center gap-2 text-xs text-gray-500 min-w-0">
                                            @if($pump->battery_percent !== null)
                                                <span
                                                    class="{{ $pump->battery_percent >= 50 ? 'text-emerald-600' : ($pump->battery_percent >= 20 ? 'text-amber-600' : 'text-red-600') }}">
                                                    {{ (int) $pump->battery_percent }}%
                                                </span>
                                            @endif
                                            <span class="truncate"
                                                title="{{ $pump->last_seen_at?->format('Y-m-d H:i:s') }}">
                                                {{ $pump->last_seen_at ? $pump->last_seen_at->diffForHumans() : 'Never seen' }}
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-1 shrink-0">
                                            @include('integration.infusion.partials.pump-actions', ['pump' => $pump])
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- ======================= DETAILS VIEW ======================= --}}
            <div x-show="pumpView === 'details'" x-cloak class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase">Name</th>
                            <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase">Ward</th>
                            <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase">Serial / Asset</th>
                            <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase">Device ID</th>
                            <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase">Type / Location</th>
                            <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase">Status</th>
                            <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase">Last Seen</th>
                            <th class="px-3 py-3 text-right text-xs font-bold text-gray-700 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        @foreach($pumps as $pump)
                            @php $fresh = $freshness($pump); @endphp
                            <tr data-search="{{ $haystack($pump) }}" x-show="pumpSearch.trim() === '' || $el.dataset.search.includes(pumpSearch.trim().toLowerCase())"
                                class="hover:bg-gray-50 transition-colors">
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2.5 h-2.5 rounded-full flex-shrink-0"
                                            style="background-color:{{ $fresh['dot'] }}"
                                            title="{{ $fresh['label'] }}"></span>
                                        <div class="min-w-0">
                                            <div class="text-sm font-bold text-gray-800 truncate">
                                                {{ $pump->device_name ?: ($pump->serial_no ?: $pump->device_id) }}
                                            </div>
                                            @if($pump->patient)
                                                <div class="text-xs text-emerald-700 truncate">
                                                    Linked ·
                                                    {{ $pump->patient->bed_number ? $pump->patient->bed_number . ' ' : '' }}{{ $pump->patient->name }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-sm text-gray-700">{{ $pump->ward->ward_name ?? '—' }}</td>
                                <td class="px-3 py-3 text-sm text-gray-700">
                                    <div>{{ $pump->serial_no ?: '—' }}</div>
                                    @if($pump->asset_no)
                                        <div class="text-xs text-gray-400">{{ $pump->asset_no }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-sm font-mono text-gray-600">
                                    <span class="block max-w-[7.5rem] truncate"
                                        title="{{ $pump->device_id }}">{{ $pump->device_id }}</span>
                                </td>
                                <td class="px-3 py-3 text-sm text-gray-600">
                                    <div>{{ $pump->device_type ?: 'Unknown' }}</div>
                                    @if($pump->location)
                                        <div class="text-xs text-gray-400">{{ $pump->location }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <span
                                        class="text-xs font-medium px-2 py-0.5 rounded {{ $pump->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                        {{ $pump->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 text-sm text-gray-600"
                                    title="{{ $pump->last_seen_at?->format('Y-m-d H:i:s') }}">
                                    {{ $pump->last_seen_at ? $pump->last_seen_at->diffForHumans() : 'Never' }}
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        @include('integration.infusion.partials.pump-actions', ['pump' => $pump])
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-8">
                <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                </svg>
                <p class="text-gray-500">No pumps registered yet.</p>
                <p class="text-sm text-gray-400 mt-1">Pumps will appear here when they send their first HL7 message,
                    or you can add them manually.</p>
            </div>
        @endif
    </div>
</div>
