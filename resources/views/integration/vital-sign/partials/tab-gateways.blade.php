{{-- Qmed Gateways tab: control-panel (default) and details views, grouped by ward. --}}
@php
    // Traffic-light colours (inline so they render regardless of the Tailwind build).
    $connColors = [
        'green'  => ['dot' => '#22c55e', 'bg' => '#dcfce7', 'text' => '#15803d'],
        'yellow' => ['dot' => '#eab308', 'bg' => '#fef9c3', 'text' => '#854d0e'],
        'red'    => ['dot' => '#ef4444', 'bg' => '#fee2e2', 'text' => '#b91c1c'],
    ];
    $connLabels = fn($conn, $lastSeen) => [
        'green'  => 'Online',
        'yellow' => 'No heartbeat > 1h',
        'red'    => ($lastSeen ? 'No heartbeat > 2h' : 'Never seen'),
    ][$conn];

    // Gateway-kind badge: vital-sign carts (emerald) vs ECG forwarders (rose).
    $typeBadge = fn($g) => $g->gateway_type === \App\Models\QmedGateway::TYPE_ECG
        ? ['label' => 'ECG', 'bg' => '#ffe4e6', 'text' => '#be123c', 'border' => '#fecdd3']
        : ['label' => 'Vital Sign', 'bg' => '#d1fae5', 'text' => '#047857', 'border' => '#a7f3d0'];

    // Group carts by ward so each ward reads like its own control panel.
    $groupedGateways = $gateways->groupBy(fn($g) => $g->ward?->ward_name ?? 'Unassigned')->sortKeys();
    $totalGateways = $gateways->count();
    $onlineGateways = $gateways->filter(fn($g) => $g->connection_status === 'green')->count();
@endphp

<div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-emerald-100">
    <div class="p-6 border-b border-emerald-100 bg-gradient-to-r from-emerald-50 to-teal-50">
        <div class="flex flex-wrap justify-between items-center gap-4">
            <div class="flex items-center">
                <div class="p-3 bg-emerald-600 rounded-xl mr-4">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-800">Qmed Gateways</h3>
                    <p class="text-sm text-gray-500">
                        @if($totalGateways > 0)
                            <span class="font-medium text-emerald-700">{{ $onlineGateways }}</span> of {{ $totalGateways }} online
                            &middot; {{ $groupedGateways->count() }} {{ \Illuminate\Support\Str::plural('ward', $groupedGateways->count()) }}
                        @else
                            Manage Qmed Gateways and their status
                        @endif
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <!-- View mode toggle -->
                <div class="inline-flex rounded-lg bg-gray-100 p-1 shadow-inner">
                    <button type="button" @click="setGwView('panel')"
                        :class="gwView === 'panel' ? 'bg-white text-emerald-700 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                        class="inline-flex items-center px-3 py-1.5 rounded-md text-sm font-medium transition-all">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM14 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zM14 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z" />
                        </svg>
                        Control Panel
                    </button>
                    <button type="button" @click="setGwView('details')"
                        :class="gwView === 'details' ? 'bg-white text-emerald-700 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                        class="inline-flex items-center px-3 py-1.5 rounded-md text-sm font-medium transition-all">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        Details
                    </button>
                </div>
                <button @click="openAddGatewayModal()"
                    class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Gateway
                </button>
            </div>
        </div>
    </div>

    <div class="p-6">
        @if($totalGateways > 0)
            <!-- Status legend -->
            <div class="flex flex-wrap items-center gap-x-5 gap-y-1 mb-5 text-xs text-gray-500">
                <span class="font-medium text-gray-600">Status:</span>
                <span class="flex items-center"><span class="w-2.5 h-2.5 rounded-full mr-1.5" style="background-color:#22c55e"></span>Online (heartbeat &lt; 1h)</span>
                <span class="flex items-center"><span class="w-2.5 h-2.5 rounded-full mr-1.5" style="background-color:#eab308"></span>No heartbeat &ge; 1h</span>
                <span class="flex items-center"><span class="w-2.5 h-2.5 rounded-full mr-1.5" style="background-color:#ef4444"></span>No heartbeat &ge; 2h</span>
            </div>

            {{-- ============ CONTROL PANEL VIEW (default) ============ --}}
            <div x-show="gwView === 'panel'" class="space-y-8">
                @foreach($groupedGateways as $wardName => $group)
                    @php
                        $wardCount = $group->count();
                        $wardOnline = $group->filter(fn($g) => $g->connection_status === 'green')->count();
                    @endphp
                    <div>
                        <!-- Ward header -->
                        <div class="flex items-center gap-3 mb-3 pb-2 border-b border-gray-100">
                            <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            <h4 class="text-base font-bold text-gray-800">{{ $wardName }}</h4>
                            <span class="px-2 py-0.5 text-xs font-medium bg-emerald-50 text-emerald-700 rounded-full border border-emerald-100">
                                {{ $wardOnline }}/{{ $wardCount }} online
                            </span>
                        </div>

                        <!-- Gateway tiles -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                            @foreach($group as $gateway)
                                @php
                                    $hb = $gateway->last_heartbeat ?? [];
                                    $usingHeartbeat = (bool) $gateway->last_heartbeat_at;
                                    $lastSeen = $gateway->last_heartbeat_at ?? $gateway->last_ping_at;
                                    $conn = $gateway->connection_status;
                                    $cc = $connColors[$conn];
                                    $connLabel = $connLabels($conn, $lastSeen);
                                    $mConn = data_get($hb, 'monitor.connected');
                                    $mCount = data_get($hb, 'monitor.count');
                                    $qPending = data_get($hb, 'queue.pending');
                                    $qDead = data_get($hb, 'queue.dead', 0);
                                    $isEcg = $gateway->gateway_type === \App\Models\QmedGateway::TYPE_ECG;
                                    $ecgSent = data_get($hb, 'ecg.sent_total');
                                    $ecgFailed = data_get($hb, 'ecg.failed_total', 0);
                                    $tb = $typeBadge($gateway);
                                @endphp
                                <div x-data="{ open: false }"
                                    class="relative rounded-xl border border-gray-200 bg-white p-4 hover:shadow-md transition-all"
                                    style="border-top:4px solid {{ $cc['dot'] }}">
                                    <!-- Tile header -->
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="relative flex h-3 w-3 flex-shrink-0">
                                                @if($conn === 'green')
                                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-60" style="background-color:{{ $cc['dot'] }}"></span>
                                                @endif
                                                <span class="relative inline-flex rounded-full h-3 w-3" style="background-color:{{ $cc['dot'] }}"></span>
                                            </span>
                                            <div class="min-w-0">
                                                <div class="font-bold text-gray-800 truncate" title="{{ $gateway->name }}">{{ $gateway->name }}</div>
                                                <div class="flex items-center gap-1.5">
                                                    @if($gateway->gateway_id)
                                                        <code class="text-[11px] text-emerald-700">{{ $gateway->gateway_id }}</code>
                                                    @endif
                                                    <span class="inline-flex px-1.5 py-px text-[10px] font-semibold rounded border"
                                                        style="background-color:{{ $tb['bg'] }};color:{{ $tb['text'] }};border-color:{{ $tb['border'] }}">{{ $tb['label'] }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="button" @click="open = true" title="View details"
                                            class="flex-shrink-0 w-7 h-7 inline-flex items-center justify-center rounded-full bg-gray-100 hover:bg-emerald-100 text-gray-500 hover:text-emerald-700 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </button>
                                    </div>

                                    <!-- Status pill -->
                                    <div class="mt-3">
                                        <span class="inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-full"
                                            style="background-color:{{ $cc['bg'] }};color:{{ $cc['text'] }}">
                                            {{ $connLabel }}
                                        </span>
                                        @unless($gateway->is_active)
                                            <span class="ml-1 inline-flex items-center px-2 py-0.5 text-[11px] font-medium bg-gray-100 text-gray-600 rounded-full">Inactive</span>
                                        @endunless
                                    </div>

                                    <!-- Micro stats -->
                                    <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                                        @if($isEcg)
                                            <div class="rounded-lg bg-gray-50 py-1.5" title="ECG recordings delivered to the server{{ $ecgFailed > 0 ? ' / failed (dead-lettered)' : '' }}">
                                                <div class="text-[10px] uppercase tracking-wide text-gray-400">ECG Sent</div>
                                                <div class="text-sm font-semibold {{ $ecgFailed > 0 ? 'text-red-600' : ($ecgSent !== null ? 'text-green-600' : 'text-gray-400') }}">
                                                    {{ $ecgSent !== null ? $ecgSent . ($ecgFailed > 0 ? '/' . $ecgFailed . '✗' : '') : '—' }}
                                                </div>
                                            </div>
                                        @else
                                            <div class="rounded-lg bg-gray-50 py-1.5">
                                                <div class="text-[10px] uppercase tracking-wide text-gray-400">Monitor</div>
                                                <div class="text-sm font-semibold {{ $mConn !== null ? ($mConn > 0 ? 'text-green-600' : 'text-red-600') : 'text-gray-400' }}">
                                                    {{ $mConn !== null ? $mConn.'/'.$mCount : '—' }}
                                                </div>
                                            </div>
                                        @endif
                                        <div class="rounded-lg bg-gray-50 py-1.5">
                                            <div class="text-[10px] uppercase tracking-wide text-gray-400">Queue</div>
                                            <div class="text-sm font-semibold {{ $qDead > 0 ? 'text-red-600' : 'text-gray-700' }}">
                                                {{ $qPending !== null ? $qPending : '—' }}
                                            </div>
                                        </div>
                                        <div class="rounded-lg bg-gray-50 py-1.5">
                                            <div class="text-[10px] uppercase tracking-wide text-gray-400">Seen</div>
                                            <div class="text-sm font-semibold text-gray-700 truncate px-1" title="{{ $lastSeen ? $lastSeen->diffForHumans() : 'Never' }}">
                                                {{ $lastSeen ? $lastSeen->shortRelativeDiffForHumans() : 'Never' }}
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Info / detail modal (rendered only when opened) -->
                                    <template x-if="open">
                                        <div class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="open = false">
                                            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                                                <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity" @click="open = false"></div>
                                                <div class="relative inline-block w-full max-w-2xl p-6 my-8 text-left align-middle bg-white shadow-xl rounded-2xl max-h-[90vh] overflow-hidden flex flex-col"
                                                    style="border-top:5px solid {{ $cc['dot'] }}">
                                                    <div class="flex justify-between items-start mb-4 flex-shrink-0">
                                                        <div class="flex items-center flex-wrap gap-2">
                                                            <h3 class="text-xl font-bold text-gray-800">{{ $gateway->name }}</h3>
                                                            @if($gateway->gateway_id)
                                                                <code class="px-2 py-0.5 text-xs bg-emerald-50 text-emerald-700 border border-emerald-100 rounded">{{ $gateway->gateway_id }}</code>
                                                            @endif
                                                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full border"
                                                                style="background-color:{{ $tb['bg'] }};color:{{ $tb['text'] }};border-color:{{ $tb['border'] }}">{{ $tb['label'] }}</span>
                                                            <span class="px-2 py-0.5 text-xs font-medium {{ $gateway->is_active ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700' }} rounded-full">
                                                                {{ $gateway->is_active ? 'Active' : 'Inactive' }}
                                                            </span>
                                                            <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full flex items-center"
                                                                style="background-color:{{ $cc['bg'] }};color:{{ $cc['text'] }}">
                                                                <span class="w-2.5 h-2.5 rounded-full mr-1.5" style="background-color:{{ $cc['dot'] }}"></span>
                                                                {{ $connLabel }}
                                                            </span>
                                                        </div>
                                                        <button @click="open = false" class="text-gray-400 hover:text-gray-600 flex-shrink-0">
                                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                            </svg>
                                                        </button>
                                                    </div>

                                                    <div class="overflow-y-auto flex-1 pr-1">
                                                        @include('integration.vital-sign.partials.gateway-detail-body', ['gateway' => $gateway])
                                                    </div>

                                                    <div class="mt-4 pt-4 border-t border-gray-200 flex justify-end items-center gap-2 flex-shrink-0">
                                                        @include('integration.vital-sign.partials.gateway-actions', ['gateway' => $gateway])
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- ============ DETAILS VIEW ============ --}}
            <div x-show="gwView === 'details'" x-cloak class="space-y-8">
                @foreach($groupedGateways as $wardName => $group)
                    <div>
                        <div class="flex items-center gap-3 mb-3 pb-2 border-b border-gray-100">
                            <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            <h4 class="text-base font-bold text-gray-800">{{ $wardName }}</h4>
                            <span class="px-2 py-0.5 text-xs font-medium bg-emerald-50 text-emerald-700 rounded-full border border-emerald-100">
                                {{ $group->count() }} {{ \Illuminate\Support\Str::plural('gateway', $group->count()) }}
                            </span>
                        </div>

                        <div class="grid gap-4">
                            @foreach($group as $gateway)
                                @php
                                    $lastSeen = $gateway->last_heartbeat_at ?? $gateway->last_ping_at;
                                    $conn = $gateway->connection_status;
                                    $cc = $connColors[$conn];
                                    $connLabel = $connLabels($conn, $lastSeen);
                                    $tb = $typeBadge($gateway);
                                @endphp
                                <div class="border border-gray-200 rounded-xl p-5 hover:border-emerald-300 hover:shadow-md transition-all bg-white"
                                    style="border-left:5px solid {{ $cc['dot'] }}">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <div class="flex items-center flex-wrap gap-2 mb-2">
                                                <h4 class="text-lg font-bold text-gray-800">{{ $gateway->name }}</h4>
                                                @if($gateway->gateway_id)
                                                    <code class="px-2 py-0.5 text-xs bg-emerald-50 text-emerald-700 border border-emerald-100 rounded">{{ $gateway->gateway_id }}</code>
                                                @endif
                                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full border"
                                                    style="background-color:{{ $tb['bg'] }};color:{{ $tb['text'] }};border-color:{{ $tb['border'] }}">{{ $tb['label'] }}</span>
                                                <span class="px-2 py-0.5 text-xs font-medium {{ $gateway->is_active ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700' }} rounded-full">
                                                    {{ $gateway->is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                                <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full flex items-center"
                                                    style="background-color:{{ $cc['bg'] }};color:{{ $cc['text'] }}"
                                                    title="Last heartbeat: {{ $lastSeen ? $lastSeen->diffForHumans() : 'never' }}">
                                                    <span class="w-2.5 h-2.5 rounded-full mr-1.5" style="background-color:{{ $cc['dot'] }}"></span>
                                                    {{ $connLabel }}
                                                </span>
                                            </div>

                                            @include('integration.vital-sign.partials.gateway-detail-body', ['gateway' => $gateway])
                                        </div>
                                        <div class="flex items-center space-x-2 ml-4">
                                            @include('integration.vital-sign.partials.gateway-actions', ['gateway' => $gateway])
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- MP5SC Listener Configuration Help -->
            <div class="mt-8 bg-slate-900 rounded-xl p-5">
                <div class="flex justify-between items-center mb-3">
                    <h4 class="font-bold text-white flex items-center">
                        <svg class="w-5 h-5 mr-2 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                        </svg>
                        MP5SC Listener Configuration
                    </h4>
                </div>
                <p class="text-sm text-gray-400 mb-3">The mp5sc_listener container will automatically fetch active devices from the API. Make sure the following environment variables are set:</p>
                <pre class="text-sm text-gray-300 font-mono overflow-x-auto"><span class="text-cyan-400">API_BASE_URL</span>=<span class="text-green-400">"http://{{ $gatewayConfig['server_ip'] }}:{{ $gatewayConfig['server_port'] }}/api/v1"</span>
<span class="text-cyan-400">API_PASSPHRASE</span>=<span class="text-green-400">"{{ $gatewayConfig['passphrase'] }}"</span>
<span class="text-cyan-400">API_USERNAME</span>=<span class="text-green-400">"your_api_user"</span>
<span class="text-cyan-400">API_PASSWORD</span>=<span class="text-green-400">"your_password"</span></pre>
                <p class="text-xs text-gray-500 mt-3">Devices configured here will be fetched via <code class="text-cyan-300">GET /api/v1/monitor-devices</code></p>
            </div>

        @else
            <!-- Sample data: shows how a registered MP5SC cart will appear. -->
            <div class="mb-6">
                <div class="flex items-center gap-2 mb-2">
                    <span class="px-2 py-0.5 text-xs font-semibold bg-amber-100 text-amber-800 rounded">SAMPLE</span>
                    <span class="text-xs text-gray-500">Example only — replaced automatically once your first Pi runs
                        <code class="bg-gray-100 px-1 rounded">setup.sh</code> and registers.</span>
                </div>
                <div class="border border-dashed border-emerald-300 rounded-xl p-5 bg-emerald-50/30" style="border-left:5px solid #22c55e">
                    <div class="flex items-center flex-wrap gap-2 mb-2">
                        <h4 class="text-lg font-bold text-gray-800">GW-0007</h4>
                        <code class="px-2 py-0.5 text-xs bg-emerald-50 text-emerald-700 border border-emerald-100 rounded">GW-0007</code>
                        <span class="px-2 py-0.5 text-xs font-medium bg-blue-100 text-blue-700 rounded-full">Active</span>
                        <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full flex items-center" style="background-color:#dcfce7;color:#15803d">
                            <span class="w-2.5 h-2.5 rounded-full mr-1.5" style="background-color:#22c55e"></span>Online
                        </span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-y-2 gap-x-6 text-sm text-gray-600">
                        <div><span class="font-medium text-gray-500">Ward:</span> Ward A (General Medical)</div>
                        <div><span class="font-medium text-gray-500">Hostname:</span> gw-0007</div>
                        <div><span class="font-medium text-gray-500">Location:</span> Cart 7</div>
                        <div><span class="font-medium text-gray-500">Last Heartbeat:</span> a few seconds ago</div>
                        <div><span class="font-medium text-gray-500">Last IP:</span> <code class="bg-gray-100 px-1.5 py-0.5 rounded">10.20.0.51</code></div>
                        <div><span class="font-medium text-gray-500">MAC Address:</span> <code class="bg-gray-100 px-1.5 py-0.5 rounded">b8:27:eb:12:34:56</code></div>
                        <div><span class="font-medium text-gray-500">Monitor:</span> <span class="text-green-600">1/1 connected</span></div>
                        <div><span class="font-medium text-gray-500">Queue:</span> 2 pending</div>
                        <div><span class="font-medium text-gray-500">Power:</span> <span class="text-green-600">OK</span></div>
                        <div><span class="font-medium text-gray-500">App / Disk:</span> v2.1.0 &middot; 74% free</div>
                        <div><span class="font-medium text-gray-500">Listener:</span> <span class="text-green-600">active (running)</span> &middot; 0 restart(s)</div>
                        <div><span class="font-medium text-gray-500">Net recovery:</span> <span class="text-green-600">1 Wi-Fi reconnect(s)</span></div>
                    </div>
                    <div class="mt-3 rounded-lg bg-gray-50 border border-gray-100 p-3 text-xs text-gray-600">
                        <div class="font-semibold text-gray-500 uppercase tracking-wider mb-2">Details
                            <span class="normal-case font-normal text-gray-400">· updated 12 minutes ago</span></div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-y-1 gap-x-6">
                            <div><span class="text-gray-500">DB records:</span> 1,284 (sent 1,201, dead 0)</div>
                            <div><span class="text-gray-500">DB size:</span> 3.4 MB</div>
                            <div class="md:col-span-2"><span class="text-gray-500">Record range:</span> 2026-06-24 08:12:03 &rarr; 2026-07-02 10:41:55 (UTC)</div>
                            <div><span class="text-gray-500">Last sent:</span> 2026-07-02 10:41:57</div>
                            <div><span class="text-gray-500">Wi-Fi:</span> WARD-ENTERPRISE (74%)</div>
                            <div><span class="text-gray-500">Uplink:</span> wlan0</div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">SSH Access</span>
                        <div class="flex flex-wrap items-center gap-2 mt-1">
                            <code class="text-xs bg-gray-900 text-emerald-300 px-2 py-1 rounded font-mono">ssh pi@10.20.0.51</code>
                            <button type="button" class="px-2 py-1 text-xs bg-gray-100 text-gray-700 rounded" disabled>Copy</button>
                            <button type="button" class="px-2 py-1 text-xs bg-emerald-100 text-emerald-700 rounded" disabled>Ping</button>
                        </div>
                    </div>
                    <div class="mt-3">
                        <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">Linked API Users</span>
                        <div class="flex flex-wrap gap-2 mt-1">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-50 text-purple-700 border border-purple-100">api@qmed.asia</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center py-8 border-t border-gray-100">
                <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                </svg>
                <h4 class="text-lg font-medium text-gray-600 mb-2">No Qmed Gateways yet</h4>
                <p class="text-gray-500 mb-4">Run <code class="bg-gray-100 px-1 rounded">setup.sh</code> on a Pi to auto-register one, or add it manually.</p>
                <button @click="openAddGatewayModal()"
                    class="inline-flex items-center px-4 py-2 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Gateway
                </button>
            </div>
        @endif
    </div>
</div>
