<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ward Infusion Overview</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Inter', sans-serif; }
        @keyframes pulse-warning {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        .pulse-warning {
            animation: pulse-warning 2s ease-in-out infinite;
        }
        @keyframes blink-alarm {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0.3; }
        }
        .blink-alarm {
            animation: blink-alarm 1s steps(1) infinite;
        }
    </style>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="bg-gray-50">
    <div class="p-4" x-data="{ filter: '{{ $filter }}', wardId: '{{ $wardId }}', tab: '{{ $tab }}', showUnbindModal: false, unbindPumpId: null, unbindPumpName: '', unbindPumpHasActiveInfusion: false }">
        {{-- The infusions panel carries its own source strip, and refreshes it.
             The devices tab still needs to hear about an unreachable engine. --}}
        @if($tab === 'devices' && !empty($engineError))
            <div class="mb-3 px-3 py-2 bg-red-50 border border-red-200 rounded-lg text-xs text-red-700 font-medium">
                ⚠ {{ $engineError }}
            </div>
        @endif
        <!-- Header with Stats -->
        <div class="mb-4">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center">
                    <div class="p-2 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-lg mr-3">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-gray-800">Ward Infusion Overview</h2>
                        <p class="text-xs text-gray-500">Real-time infusion pump monitoring</p>
                    </div>
                </div>
                @if($tab === 'infusions')
                    <div class="flex items-center gap-2 text-xs text-gray-400">
                        <span id="refreshState">updated just now</span>
                        <button type="button" id="refreshNow"
                            class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-2 py-1 font-medium text-gray-600 transition-colors hover:bg-gray-50">
                            <svg id="refreshIcon" class="h-3.5 w-3.5" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Refresh
                        </button>
                    </div>
                @endif
            </div>
            
            <!-- Tab Navigation -->
            <div class="flex items-center space-x-2 mb-4 border-b border-gray-200">
                <a href="?ward_id={{ $wardId }}&tab=infusions&filter={{ $filter }}" 
                   class="px-4 py-2 text-sm font-semibold transition-all {{ $tab === 'infusions' ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-600 hover:text-gray-800' }}">
                    Infusions
                </a>
                <a href="?ward_id={{ $wardId }}&tab=devices" 
                   class="px-4 py-2 text-sm font-semibold transition-all {{ $tab === 'devices' ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-600 hover:text-gray-800' }}">
                    Devices
                </a>
            </div>

        </div>

        <!-- Infusions Tab Content -->
        <div x-show="tab === 'infusions'">
            @include('wards.partials.infusion-panel')
        </div>

        <!-- Devices Tab Content -->
        <div x-show="tab === 'devices'">
            <div class="bg-white rounded-xl shadow-md border border-gray-200">
                <div class="p-4 border-b border-gray-200 bg-gradient-to-r from-purple-50 to-indigo-50">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="p-2 bg-purple-500 rounded-lg mr-3">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-800">Registered Devices</h3>
                                <p class="text-xs text-gray-500">All infusion pumps and their bindings</p>
                            </div>
                        </div>
                        <div class="text-sm font-semibold text-gray-600">
                            {{ $pumps->count() }} {{ $pumps->count() === 1 ? 'Device' : 'Devices' }}
                        </div>
                    </div>
                </div>
                <div class="p-4">
                    @if($pumps->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Device</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Asset No</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Serial No</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Bed</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Patient</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">MRN</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Linked At</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($pumps as $pump)
                                        <tr class="hover:bg-gray-50 {{ $pump->patient_id ? 'bg-green-50/30' : '' }}">
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="p-1.5 {{ $pump->patient_id ? 'bg-green-100' : 'bg-gray-100' }} rounded mr-2">
                                                        <svg class="w-4 h-4 {{ $pump->patient_id ? 'text-green-600' : 'text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                                                        </svg>
                                                    </div>
                                                    <div>
                                                        @if($pump->device_name)
                                                            <div class="text-sm font-bold text-gray-900">{{ $pump->device_name }}</div>
                                                            <div class="text-xs text-gray-500">{{ $pump->device_id }}</div>
                                                        @else
                                                            <div class="text-sm font-bold text-gray-900">{{ $pump->device_id }}</div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                                {{ $pump->asset_no ?? '-' }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                                {{ $pump->serial_no ?? '-' }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                                @if($pump->patient && $pump->patient->bed_number)
                                                    <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-bold">
                                                        {{ $pump->patient->bed_number }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                                {{ $pump->patient->name ?? '-' }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                                {{ $pump->patient->mrn ?? '-' }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                                @if($pump->linked_at)
                                                    <span class="text-xs" title="{{ $pump->linked_at->format('Y-m-d H:i:s') }}">
                                                        {{ $pump->linked_at->diffForHumans() }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">Not linked</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                                @if($pump->patient_id)
                                                    @php
                                                        $hasActiveInfusion = \App\Models\Infusion::where('patient_id', $pump->patient_id)
                                                            ->where('infusion_pump_id', $pump->id)
                                                            ->active()
                                                            ->exists();
                                                    @endphp
                                                    <button 
                                                        @click="showUnbindModal = true; unbindPumpId = {{ $pump->id }}; unbindPumpName = '{{ $pump->device_id }}'; unbindPumpHasActiveInfusion = {{ $hasActiveInfusion ? 'true' : 'false' }}"
                                                        class="px-3 py-1.5 bg-red-100 hover:bg-red-200 text-red-700 rounded-lg text-xs font-medium transition-colors">
                                                        <svg class="w-3.5 h-3.5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                        </svg>
                                                        Unbind
                                                    </button>
                                                @else
                                                    <span class="text-gray-400 text-xs">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-8 text-gray-500">
                            <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                            </svg>
                            <p class="text-sm font-medium">No devices registered</p>
                            <p class="text-xs text-gray-400 mt-1">Devices will appear here when they are registered</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Unbind Confirmation Modal -->
        <div x-show="showUnbindModal" 
             x-cloak 
             class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
             @click.self="showUnbindModal = false">
            <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 p-6" @click.stop>
                <div class="flex items-center mb-4">
                    <div class="p-3 bg-red-100 rounded-full mr-4">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Confirm Unbind</h3>
                        <p class="text-sm text-gray-500">This action will remove the pump binding</p>
                    </div>
                </div>
                <!-- Warning for Active Infusion -->
                <div x-show="unbindPumpHasActiveInfusion" class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                    <p class="text-sm text-red-800 flex items-start">
                        <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <span>
                            <strong>CRITICAL WARNING:</strong> This pump has an <strong>ACTIVE INFUSION</strong>. Unbinding it will automatically mark the infusion as <strong>COMPLETED</strong>.
                        </span>
                    </p>
                </div>
                <!-- Standard Warning -->
                <div x-show="!unbindPumpHasActiveInfusion" class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4">
                    <p class="text-sm text-amber-800">
                        <strong>Warning:</strong> Unbinding this pump will remove the patient association and may affect ongoing infusion tracking.
                    </p>
                </div>
                <p class="text-sm text-gray-700 mb-6">
                    Are you sure you want to unbind pump <strong x-text="unbindPumpName"></strong> from the patient?
                </p>
                <div class="flex space-x-3">
                    <button 
                        @click="showUnbindModal = false"
                        class="flex-1 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-medium transition-colors">
                        Cancel
                    </button>
                    <button 
                        @click="unbindPump(unbindPumpId)"
                        class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition-colors">
                        Unbind
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        async function unbindPump(pumpId) {
            try {
                const response = await fetch(`{{ url('ward-dashboard/unlink-pump') }}/${pumpId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    }
                });

                const data = await response.json();
                
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Failed to unbind pump');
                }
            } catch (error) {
                alert('An error occurred. Please try again.');
            }
        }
    </script>

    @if($tab === 'infusions')
        <script>
            /**
             * Keep the panel current without reloading the iframe.
             *
             * The page used to carry <meta http-equiv="refresh">, which threw
             * away the chosen filter, the scroll position and the open modal
             * every 30 seconds. Instead we re-fetch this same route with
             * ?fragment=1 - the server renders only the infusions panel - and
             * swap the element in place.
             */
            (function () {
                const baseSeconds = {{ $refreshSec }};
                const target = new URL(window.location.href);
                target.searchParams.set('fragment', '1');

                const state = document.getElementById('refreshState');
                const button = document.getElementById('refreshNow');
                const icon = document.getElementById('refreshIcon');

                let refreshedAt = Date.now();
                let failures = 0;
                let timer = null;
                let inFlight = false;

                function describeAge() {
                    if (failures > 0) {
                        return 'reconnecting…';
                    }
                    const seconds = Math.round((Date.now() - refreshedAt) / 1000);
                    if (seconds < 5) return 'updated just now';
                    if (seconds < 60) return `updated ${seconds}s ago`;
                    return `updated ${Math.round(seconds / 60)}m ago`;
                }

                function paintAge() {
                    if (state) state.textContent = describeAge();
                }

                function schedule() {
                    clearTimeout(timer);
                    // Back off while the engine is unreachable rather than
                    // hammering it every few seconds.
                    const factor = Math.min(6, Math.pow(2, failures));
                    timer = setTimeout(refresh, baseSeconds * 1000 * factor);
                }

                // This page lives in an iframe inside a modal that stays in the
                // DOM after it is closed. A display:none iframe has no layout,
                // so a zero viewport means nobody is looking - stop calling the
                // engine until it is opened again. Both checks are re-tested on
                // the next tick, so nothing can stall permanently.
                function offScreen() {
                    return document.hidden || window.innerWidth === 0;
                }

                async function refresh() {
                    if (inFlight) return;
                    if (offScreen()) { schedule(); return; }

                    inFlight = true;
                    icon?.classList.add('animate-spin');

                    try {
                        const response = await fetch(target, {
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                            cache: 'no-store',
                        });
                        if (!response.ok) throw new Error('HTTP ' + response.status);

                        const holder = document.createElement('div');
                        holder.innerHTML = await response.text();
                        const fresh = holder.querySelector('#infusionPanel');
                        const current = document.getElementById('infusionPanel');

                        if (fresh && current) {
                            current.replaceWith(fresh);
                            refreshedAt = Date.now();
                            failures = 0;
                        }
                    } catch (error) {
                        failures++;
                    } finally {
                        inFlight = false;
                        icon?.classList.remove('animate-spin');
                        paintAge();
                        schedule();
                    }
                }

                button?.addEventListener('click', function () {
                    failures = 0;
                    refresh();
                });

                document.addEventListener('visibilitychange', function () {
                    if (!document.hidden) refresh();
                });

                setInterval(paintAge, 1000);
                schedule();
            })();
        </script>
    @endif
</body>
</html>
































