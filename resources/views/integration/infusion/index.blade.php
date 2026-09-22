<x-app-layout>
    <style>
        @media print {
            body { background: white !important; }
            nav, header, footer, .no-print, #hl7InfoModal, [x-cloak] { display: none !important; }
            .print-only-show { display: block !important; }
            .bg-white\/90 { background-color: white !important; backdrop-filter: none !important; box-shadow: none !important; border: none !important; }
            .shadow-lg, .shadow-md, .shadow { box-shadow: none !important; }
            .rounded-2xl, .rounded-xl { border-radius: 0 !important; }
            .max-w-7xl { max-width: 100% !important; padding: 0 !important; margin: 0 !important; }
            .space-y-6 > * { margin-bottom: 2rem !important; }
            
            /* Hide other sections in print */
            .grid-cols-2.md\:grid-cols-4, /* stats */
            .border-purple-100, /* pump list */
            .border-cyan-100 /* config */
            { display: none !important; }

            /* Ensure table fits */
            table { width: 100% !important; font-size: 10pt !important; }
            th, td { padding: 4px !important; }
            
            /* Show title clearly */
            .print-title { display: block !important; margin-bottom: 20px; text-align: center; }
        }
        .print-only-show { display: none; }
        .print-title { display: none; }
    </style>
    
    <div class="print-title">
        <h1 class="text-2xl font-bold">Infusion Integration - HL7 Logs</h1>
        <p class="text-sm text-gray-500">Generated: {{ now()->format('Y-m-d H:i') }}</p>
    </div>

    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-bold text-2xl text-gray-800 leading-tight">
                    {{ __('Infusion Integration') }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">B.Braun HL7 Integration via MLLP Protocol</p>
            </div>
            <button onclick="document.getElementById('hl7InfoModal').classList.remove('hidden')" 
                    class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                HL7 Protocol Info
            </button>
        </div>
    </x-slot>

    <!-- HL7 Info Modal -->
    <div id="hl7InfoModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity" onclick="document.getElementById('hl7InfoModal').classList.add('hidden')"></div>

            <div class="relative inline-block w-full max-w-5xl p-8 my-8 text-left align-middle transition-all transform bg-gradient-to-br from-slate-900 to-slate-800 shadow-2xl rounded-3xl border border-slate-700">
                <div class="flex justify-between items-center mb-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl mr-4">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-white">B.Braun HL7 Integration</h3>
                    </div>
                    <button onclick="document.getElementById('hl7InfoModal').classList.add('hidden')" class="text-gray-400 hover:text-white transition-colors">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="space-y-6 max-h-[70vh] overflow-y-auto pr-2">
                    <!-- Protocol Info -->
                    <div class="bg-slate-700/50 rounded-xl p-4 border border-slate-600">
                        <h4 class="text-lg font-bold text-white mb-3 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            MLLP Protocol
                        </h4>
                        <p class="text-gray-300 text-sm mb-3">
                            The B.Braun infusion pumps communicate using HL7 v2.x messages over MLLP (Minimal Lower Layer Protocol).
                            This is a standard healthcare integration protocol for real-time device communication.
                        </p>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <span class="text-xs font-semibold text-gray-400 uppercase">Host</span>
                                <p class="text-cyan-300 font-mono">{{ $mllpConfig['host'] }}</p>
                            </div>
                            <div>
                                <span class="text-xs font-semibold text-gray-400 uppercase">Port</span>
                                <p class="text-cyan-300 font-mono">{{ $mllpConfig['port'] }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Supported Message Types -->
                    <div class="bg-slate-700/50 rounded-xl p-4 border border-slate-600">
                        <h4 class="text-lg font-bold text-white mb-3 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                            Supported HL7 Message Types
                        </h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-sm">
                            <div class="bg-slate-800/70 rounded px-3 py-2">
                                <span class="text-purple-300 font-semibold">ORU</span>
                                <p class="text-gray-400 text-xs">Observation Result (Pump Status)</p>
                            </div>
                            <div class="bg-slate-800/70 rounded px-3 py-2">
                                <span class="text-purple-300 font-semibold">ORM</span>
                                <p class="text-gray-400 text-xs">Order Message (New Infusion)</p>
                            </div>
                            <div class="bg-slate-800/70 rounded px-3 py-2">
                                <span class="text-purple-300 font-semibold">ADT</span>
                                <p class="text-gray-400 text-xs">Patient Information Update</p>
                            </div>
                            <div class="bg-slate-800/70 rounded px-3 py-2">
                                <span class="text-purple-300 font-semibold">RAS</span>
                                <p class="text-gray-400 text-xs">Pharmacy/Treatment Admin</p>
                            </div>
                            <div class="bg-slate-800/70 rounded px-3 py-2">
                                <span class="text-purple-300 font-semibold">RDE</span>
                                <p class="text-gray-400 text-xs">Pharmacy Encoded Order</p>
                            </div>
                            <div class="bg-slate-800/70 rounded px-3 py-2">
                                <span class="text-purple-300 font-semibold">RGV</span>
                                <p class="text-gray-400 text-xs">Pharmacy/Treatment Give</p>
                            </div>
                        </div>
                    </div>

                    <!-- Pump Status Values -->
                    <div class="bg-slate-700/50 rounded-xl p-4 border border-slate-600">
                        <h4 class="text-lg font-bold text-white mb-3 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            Pump Status Values
                        </h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-sm">
                            <div class="bg-green-700/50 rounded px-3 py-2">
                                <span class="inline-block w-2 h-2 rounded-full bg-green-400 mr-2"></span>
                                <code class="text-green-300">running</code>
                            </div>
                            <div class="bg-yellow-700/50 rounded px-3 py-2">
                                <span class="inline-block w-2 h-2 rounded-full bg-yellow-400 mr-2"></span>
                                <code class="text-yellow-300">paused</code>
                            </div>
                            <div class="bg-gray-700/50 rounded px-3 py-2">
                                <span class="inline-block w-2 h-2 rounded-full bg-gray-400 mr-2"></span>
                                <code class="text-gray-300">stopped</code>
                            </div>
                            <div class="bg-blue-700/50 rounded px-3 py-2">
                                <span class="inline-block w-2 h-2 rounded-full bg-blue-400 mr-2"></span>
                                <code class="text-blue-300">completed</code>
                            </div>
                            <div class="bg-red-700/50 rounded px-3 py-2">
                                <span class="inline-block w-2 h-2 rounded-full bg-red-400 mr-2"></span>
                                <code class="text-red-300">alarming</code>
                            </div>
                            <div class="bg-gray-700/50 rounded px-3 py-2">
                                <span class="inline-block w-2 h-2 rounded-full bg-gray-500 mr-2"></span>
                                <code class="text-gray-300">pending</code>
                            </div>
                        </div>
                    </div>

                    <!-- Data Fields -->
                    <div class="bg-slate-700/50 rounded-xl p-4 border border-slate-600">
                        <h4 class="text-lg font-bold text-white mb-3 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                            </svg>
                            Infusion Data Fields
                        </h4>
                        <div class="grid grid-cols-2 gap-2 text-sm text-gray-300">
                            <div><code class="text-blue-300">flow_rate</code> - ml/hr</div>
                            <div><code class="text-blue-300">total_volume</code> - ml (VTBI)</div>
                            <div><code class="text-blue-300">infused_volume</code> - ml</div>
                            <div><code class="text-blue-300">remaining_volume</code> - ml</div>
                            <div><code class="text-blue-300">remaining_minutes</code> - Time to end</div>
                            <div><code class="text-blue-300">medication_name</code> - Drug name</div>
                            <div><code class="text-blue-300">device_id</code> - Pump ID</div>
                            <div><code class="text-blue-300">patient_mrn</code> - Patient MRN</div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button onclick="document.getElementById('hl7InfoModal').classList.add('hidden')" 
                            class="px-6 py-2.5 bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 text-white font-semibold rounded-lg transition-all">
                        Got it!
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="py-8" x-data="infusionIntegration()">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            @if (session('success'))
                <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-l-4 border-green-500 text-green-800 px-6 py-4 rounded-lg shadow-md" role="alert">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="font-medium">{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if (session('error'))
                <div class="bg-gradient-to-r from-red-50 to-pink-50 border-l-4 border-red-500 text-red-800 px-6 py-4 rounded-lg shadow-md" role="alert">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="font-medium">{{ session('error') }}</span>
                    </div>
                </div>
            @endif

            <!-- Integration Mode Selection -->
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-indigo-100"
                 x-data="integrationModeCard()">
                <div class="p-6 border-b border-indigo-100 bg-gradient-to-r from-indigo-50 to-purple-50">
                    <div class="flex items-center justify-between flex-wrap gap-3">
                        <div class="flex items-center">
                            <div class="p-3 bg-indigo-600 rounded-xl mr-4">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800">Integration Mode</h3>
                                <p class="text-sm text-gray-500">Choose how SmartWard connects to the infusion pumps</p>
                            </div>
                        </div>
                        @if ($integrationMode === 'engine')
                            @if ($engineStatus && ($engineStatus['ok'] ?? false))
                                <span class="px-3 py-1.5 rounded-full text-sm font-medium bg-green-100 text-green-700">
                                    <span class="inline-block w-2 h-2 rounded-full bg-green-500 animate-pulse mr-2"></span>
                                    Qmed Engine Connected — v{{ $engineStatus['health']['version'] ?? '?' }},
                                    {{ $engineStatus['health']['messages'] ?? 0 }} messages,
                                    {{ $engineStatus['health']['pumps'] ?? 0 }} pumps
                                </span>
                            @else
                                <span class="px-3 py-1.5 rounded-full text-sm font-medium bg-red-100 text-red-700">
                                    <span class="inline-block w-2 h-2 rounded-full bg-red-500 mr-2"></span>
                                    Qmed Engine Unreachable{{ $engineStatus ? ' — ' . ($engineStatus['error'] ?? '') : '' }}
                                </span>
                            @endif
                        @else
                            <span class="px-3 py-1.5 rounded-full text-sm font-medium bg-cyan-100 text-cyan-700">
                                Built-in listener active
                            </span>
                        @endif
                    </div>
                </div>

                <form action="{{ route('infusion-integration.settings.save') }}" method="POST" class="p-6">
                    @csrf
                    <input type="hidden" name="mode" :value="mode">

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Option 1: In the same project -->
                        <div class="rounded-2xl border-2 transition-all cursor-pointer"
                             :class="mode === 'local' ? 'border-cyan-500 bg-cyan-50/50 shadow-md' : 'border-gray-200 bg-white hover:border-cyan-300'"
                             @click="mode = 'local'">
                            <div class="p-5">
                                <div class="flex items-start">
                                    <div class="mt-0.5 mr-3 flex-shrink-0">
                                        <span class="w-5 h-5 rounded-full border-2 inline-flex items-center justify-center"
                                              :class="mode === 'local' ? 'border-cyan-600' : 'border-gray-300'">
                                            <span class="w-2.5 h-2.5 rounded-full bg-cyan-600" x-show="mode === 'local'"></span>
                                        </span>
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-bold text-gray-800">1) In the same project <span class="text-xs font-medium text-gray-500">(current / built-in)</span></div>
                                        <p class="text-sm text-gray-600 mt-1">
                                            The B.Braun MLLP listener runs alongside SmartWard and writes HL7 data
                                            directly into the SmartWard database.
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-4 pt-4 border-t border-cyan-100 space-y-3" x-show="mode === 'local'" x-cloak>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Listener Host</label>
                                            <input type="text" name="local_host" value="{{ $localConfig['host'] ?? '0.0.0.0' }}"
                                                   class="w-full rounded-lg border-gray-300 text-sm font-mono focus:border-cyan-500 focus:ring-cyan-500" @click.stop>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Listener Port</label>
                                            <input type="number" name="local_port" value="{{ $localConfig['port'] ?? '5001' }}"
                                                   class="w-full rounded-lg border-gray-300 text-sm font-mono focus:border-cyan-500 focus:ring-cyan-500" @click.stop>
                                        </div>
                                    </div>
                                    <p class="text-xs text-gray-500">
                                        Pumps send HL7/MLLP to this address. The values must match the running
                                        <code class="bg-gray-100 px-1 rounded">bbraun</code> listener service
                                        (container env <code class="bg-gray-100 px-1 rounded">BBRAUN_PORT</code>) —
                                        changing them here updates what SmartWard displays and expects.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Option 2: Qmed Infusion Engine -->
                        <div class="rounded-2xl border-2 transition-all cursor-pointer"
                             :class="mode === 'engine' ? 'border-indigo-500 bg-indigo-50/50 shadow-md' : 'border-gray-200 bg-white hover:border-indigo-300'"
                             @click="mode = 'engine'">
                            <div class="p-5">
                                <div class="flex items-start">
                                    <div class="mt-0.5 mr-3 flex-shrink-0">
                                        <span class="w-5 h-5 rounded-full border-2 inline-flex items-center justify-center"
                                              :class="mode === 'engine' ? 'border-indigo-600' : 'border-gray-300'">
                                            <span class="w-2.5 h-2.5 rounded-full bg-indigo-600" x-show="mode === 'engine'"></span>
                                        </span>
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-bold text-gray-800">2) Qmed Infusion Engine <span class="text-xs font-medium text-gray-500">(via API)</span></div>
                                        <p class="text-sm text-gray-600 mt-1">
                                            A standalone engine receives, stores and parses all pump HL7 separately.
                                            SmartWard reads the parsed data over a configurable REST API.
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-4 pt-4 border-t border-indigo-100 space-y-3" x-show="mode === 'engine'" x-cloak>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Engine API URL</label>
                                        <input type="url" name="engine_url" x-model="engineUrl" placeholder="http://192.168.0.88:6001"
                                               class="w-full rounded-lg border-gray-300 text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500" @click.stop>
                                    </div>
                                    <div class="grid grid-cols-3 gap-3">
                                        <div class="col-span-1">
                                            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">API Key <span class="normal-case">(optional)</span></label>
                                            <input type="password" name="engine_api_key" x-model="engineApiKey" placeholder="X-API-Key" autocomplete="new-password"
                                                   class="w-full rounded-lg border-gray-300 text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500" @click.stop>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Timeout (s)</label>
                                            <input type="number" name="engine_timeout" x-model="engineTimeout" min="1" max="60"
                                                   class="w-full rounded-lg border-gray-300 text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500" @click.stop>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Refresh (s)</label>
                                            <input type="number" name="engine_refresh_sec" x-model="engineRefreshSec" min="2" max="300"
                                                   class="w-full rounded-lg border-gray-300 text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500" @click.stop>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 flex-wrap">
                                        <button type="button" @click.stop="testEngine()"
                                                class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg transition-colors"
                                                :disabled="testing">
                                            <svg class="w-4 h-4 mr-2" x-show="!testing" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                            </svg>
                                            <svg class="w-4 h-4 mr-2 animate-spin" x-show="testing" x-cloak fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                                            </svg>
                                            <span x-text="testing ? 'Testing…' : 'Test Connection'"></span>
                                        </button>
                                        <a :href="engineUrl" target="_blank" @click.stop
                                           class="text-sm text-indigo-600 hover:text-indigo-800 font-medium underline">
                                            Open engine management page ↗
                                        </a>
                                    </div>
                                    <div x-show="testResult" x-cloak class="text-sm rounded-lg px-4 py-3"
                                         :class="testResult && testResult.ok ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200'">
                                        <template x-if="testResult && testResult.ok">
                                            <span>✓ Connected — engine v<span x-text="testResult.health.version"></span>,
                                                <span x-text="testResult.health.messages"></span> messages,
                                                <span x-text="testResult.health.pumps"></span> pumps,
                                                <span x-text="testResult.health.alarms"></span> alarms stored.</span>
                                        </template>
                                        <template x-if="testResult && !testResult.ok">
                                            <span>✗ <span x-text="testResult.error"></span></span>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-between flex-wrap gap-3">
                        <p class="text-xs text-gray-500">
                            The selected mode controls where SmartWard reads infusion data from.
                            Both configurations are kept — you can switch back at any time.
                        </p>
                        <button type="submit"
                                class="px-6 py-2.5 bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 text-white font-semibold rounded-lg shadow transition-all">
                            Save Integration Settings
                        </button>
                    </div>
                </form>
            </div>

            <!-- MLLP Configuration Section -->
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-cyan-100">
                <div class="p-6 border-b border-cyan-100 bg-gradient-to-r from-cyan-50 to-blue-50">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="p-3 bg-cyan-600 rounded-xl mr-4">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800">MLLP Listener Configuration</h3>
                                <p class="text-sm text-gray-500">B.Braun HL7 message listener settings</p>
                            </div>
                        </div>
                        @if($stats['active_pumps'] > 0)
                            <div class="flex items-center">
                                <span class="px-3 py-1.5 rounded-full text-sm font-medium bg-green-100 text-green-700">
                                    <span class="inline-block w-2 h-2 rounded-full bg-green-500 animate-pulse mr-2"></span>
                                    {{ $stats['active_pumps'] }} Pumps Online
                                </span>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-4 border border-gray-200">
                            <div class="text-xs font-semibold text-gray-500 uppercase mb-1">Host Address</div>
                            <div class="text-lg font-mono font-bold text-gray-800">{{ $mllpConfig['host'] }}</div>
                        </div>
                        <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-4 border border-gray-200">
                            <div class="text-xs font-semibold text-gray-500 uppercase mb-1">Port</div>
                            <div class="text-lg font-mono font-bold text-cyan-600">{{ $mllpConfig['port'] }}</div>
                        </div>
                        <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-4 border border-gray-200">
                            <div class="text-xs font-semibold text-gray-500 uppercase mb-1">Protocol</div>
                            <div class="text-sm font-semibold text-gray-800">{{ $mllpConfig['protocol'] }}</div>
                        </div>
                        <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-4 border border-gray-200">
                            <div class="text-xs font-semibold text-gray-500 uppercase mb-1">HL7 Version</div>
                            <div class="text-lg font-mono font-bold text-purple-600">{{ $mllpConfig['hl7_version'] }}</div>
                        </div>
                    </div>
                    
                    <div class="mt-6 pt-6 border-t border-gray-100">
                        <div class="flex items-center mb-4">
                            <div class="p-2 bg-orange-100 rounded-lg mr-3">
                                <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>
                                </svg>
                            </div>
                            <h4 class="text-sm font-bold text-gray-700 uppercase tracking-wider">Database Connection</h4>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                            <div class="bg-gradient-to-br from-orange-50 to-orange-100/50 rounded-xl p-4 border border-orange-100">
                                <div class="text-xs font-semibold text-gray-500 uppercase mb-1">DB Host</div>
                                <div class="text-lg font-mono font-bold text-gray-800">{{ $dbConfig['host'] }}</div>
                            </div>
                            <div class="bg-gradient-to-br from-orange-50 to-orange-100/50 rounded-xl p-4 border border-orange-100">
                                <div class="text-xs font-semibold text-gray-500 uppercase mb-1">DB Port</div>
                                <div class="text-lg font-mono font-bold text-orange-600">{{ $dbConfig['port'] }}</div>
                            </div>
                            <div class="bg-gradient-to-br from-orange-50 to-orange-100/50 rounded-xl p-4 border border-orange-100">
                                <div class="text-xs font-semibold text-gray-500 uppercase mb-1">Database</div>
                                <div class="text-sm font-bold text-gray-800">{{ $dbConfig['database'] }}</div>
                            </div>
                            <div class="bg-gradient-to-br from-orange-50 to-orange-100/50 rounded-xl p-4 border border-orange-100">
                                <div class="text-xs font-semibold text-gray-500 uppercase mb-1">Username</div>
                                <div class="text-lg font-mono font-bold text-gray-800">{{ $dbConfig['username'] }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-xl">
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-blue-500 mt-0.5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <div class="text-sm text-blue-700">
                                <strong>Listener Service:</strong> The B.Braun HL7 listener runs as a separate Python service. 
                                Configure settings in <code class="bg-blue-100 px-1 rounded">bbraun/.env</code> and start with 
                                <code class="bg-blue-100 px-1 rounded">python bbraun/bbraun_hl7_listener.py</code>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4">
                <div class="bg-white rounded-xl shadow-md p-4 border border-gray-100">
                    <div class="flex items-center">
                        <div class="p-2 bg-indigo-100 rounded-lg mr-3">
                            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-xl font-bold text-gray-800">{{ $stats['total_pumps'] }}</div>
                            <div class="text-xs text-gray-500">Total Pumps</div>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 border border-gray-100">
                    <div class="flex items-center">
                        <div class="p-2 bg-green-100 rounded-lg mr-3">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-xl font-bold text-gray-800">{{ $stats['active_pumps'] }}</div>
                            <div class="text-xs text-gray-500">Active Pumps</div>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 border border-gray-100">
                    <div class="flex items-center">
                        <div class="p-2 bg-blue-100 rounded-lg mr-3">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-xl font-bold text-gray-800">{{ $stats['active_infusions'] }}</div>
                            <div class="text-xs text-gray-500">Active Infusions</div>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 border border-gray-100">
                    <div class="flex items-center">
                        <div class="p-2 bg-amber-100 rounded-lg mr-3">
                            <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-xl font-bold text-amber-600">{{ $stats['warnings'] }}</div>
                            <div class="text-xs text-gray-500">Warnings</div>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 border border-gray-100">
                    <div class="flex items-center">
                        <div class="p-2 bg-red-100 rounded-lg mr-3">
                            <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-xl font-bold text-red-600">{{ $stats['alarms'] }}</div>
                            <div class="text-xs text-gray-500">Alarms</div>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 border border-gray-100">
                    <div class="flex items-center">
                        <div class="p-2 bg-purple-100 rounded-lg mr-3">
                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-xl font-bold text-gray-800">{{ $stats['total_hl7_messages'] }}</div>
                            <div class="text-xs text-gray-500">Total HL7 Msgs</div>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 border border-gray-100">
                    <div class="flex items-center">
                        <div class="p-2 bg-cyan-100 rounded-lg mr-3">
                            <svg class="w-5 h-5 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-xl font-bold text-gray-800">{{ $stats['messages_today'] }}</div>
                            <div class="text-xs text-gray-500">Today</div>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 border border-gray-100">
                    <div class="flex items-center">
                        <div class="p-2 bg-rose-100 rounded-lg mr-3">
                            <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-xl font-bold text-rose-600">{{ $stats['error_messages'] }}</div>
                            <div class="text-xs text-gray-500">Errors</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Registered Pumps Section -->
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-purple-100">
                <div class="p-6 border-b border-purple-100 bg-gradient-to-r from-purple-50 to-pink-50">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center">
                            <div class="p-3 bg-purple-600 rounded-xl mr-4">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800">Registered Pump Users</h3>
                                <p class="text-sm text-gray-500">Pumps are auto-registered when they first send HL7 data</p>
                            </div>
                        </div>
                        <button @click="showAddPumpModal = true" class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Add Pump
                        </button>
                    </div>
                </div>

                <div class="p-6">
                    @if($pumps->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Serial No</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Device ID</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Asset No</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Name</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Type</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Location</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Ward</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Status</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Last Seen</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    @foreach($pumps as $pump)
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-4 py-3 text-sm font-bold text-gray-800">{{ $pump->serial_no ?? '-' }}</td>
                                            <td class="px-4 py-3 text-sm font-mono text-gray-600">{{ $pump->device_id }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-700">{{ $pump->asset_no ?? '-' }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-700">{{ $pump->device_name ?? '-' }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-600">{{ $pump->device_type ?? 'Unknown' }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-600">{{ $pump->location ?? '-' }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-600">{{ $pump->ward->ward_name ?? '-' }}</td>
                                            <td class="px-4 py-3">
                                                <span class="text-xs font-medium px-2 py-0.5 rounded {{ $pump->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                                    {{ $pump->is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-600">{{ $pump->last_seen_at ? $pump->last_seen_at->diffForHumans() : 'Never' }}</td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center space-x-2">
                                                    <button @click="viewPumpStatus({{ json_encode($pump) }})" title="View live status (Infusion Engine)" class="text-emerald-600 hover:text-emerald-800 transition-colors">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                        </svg>
                                                    </button>
                                                    <button @click="viewPumpHl7({{ json_encode($pump) }})" title="View raw HL7 messages (debug)" class="text-indigo-600 hover:text-indigo-800 transition-colors">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                                                        </svg>
                                                    </button>
                                                    <button @click="editPump({{ json_encode($pump) }})" class="text-blue-600 hover:text-blue-800 transition-colors">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                        </svg>
                                                    </button>
                                                    <form action="{{ route('infusion-integration.pump.destroy', $pump) }}" method="POST" class="inline" >
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="button" onclick="confirmDelete(event, 'Are you sure you want to delete this item?')" class="text-red-600 hover:text-red-800 transition-colors">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-8">
                            <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                            </svg>
                            <p class="text-gray-500">No pumps registered yet.</p>
                            <p class="text-sm text-gray-400 mt-1">Pumps will appear here when they send their first HL7 message, or you can add them manually.</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Recent HL7 Logs Section -->
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-blue-100">
                <div class="p-6 border-b border-blue-100 bg-gradient-to-r from-blue-50 to-cyan-50">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center">
                            <div class="p-3 bg-blue-600 rounded-xl mr-4">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800">Recent HL7 Messages</h3>
                                <p class="text-sm text-gray-500">HL7 messages received from B.Braun pumps</p>
                            </div>
                        </div>
                    <div class="flex items-center space-x-2">
                        <!-- Duration Filter -->
                        <form action="{{ route('infusion-integration.index') }}" method="GET" class="flex items-center no-print">
                            <input type="hidden" name="status_filter" value="{{ $statusFilter }}">
                            <select name="duration" onchange="this.form.submit()" class="text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm mr-2">
                                <option value="30m" {{ $duration == '30m' ? 'selected' : '' }}>Last 30 Minutes</option>
                                <option value="1h" {{ $duration == '1h' ? 'selected' : '' }}>Last 1 Hour</option>
                                <option value="2h" {{ $duration == '2h' ? 'selected' : '' }}>Last 2 Hours</option>
                                <option value="6h" {{ $duration == '6h' ? 'selected' : '' }}>Last 6 Hours</option>
                                <option value="12h" {{ $duration == '12h' ? 'selected' : '' }}>Last 12 Hours</option>
                                <option value="24h" {{ $duration == '24h'  ? 'selected' : '' }}>Last 24 Hours</option>
                                <option value="48h" {{ $duration == '48h'  ? 'selected' : '' }}>Last 48 Hours</option>
                                <option value="7d" {{ $duration == '7d'  ? 'selected' : '' }}>Last 7 Days</option>
                                <option value="30d" {{ $duration == '30d'  ? 'selected' : '' }}>Last 30 Days</option>
                                <option value="all" {{ $duration == 'all' ? 'selected' : '' }}>All Time</option>
                            </select>
                        </form>

                        <!-- Status Filter -->
                        <form action="{{ route('infusion-integration.index') }}" method="GET" class="flex items-center no-print">
                            <input type="hidden" name="duration" value="{{ $duration }}">
                            <select name="status_filter" onchange="this.form.submit()" class="text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm mr-2">
                                <option value="mapped" {{ $statusFilter == 'mapped' ? 'selected' : '' }}>Mapped Only</option>
                                <option value="all" {{ $statusFilter == 'all' ? 'selected' : '' }}>All Messages</option>
                                <option value="error" {{ $statusFilter == 'error' ? 'selected' : '' }}>Errors Only</option>
                            </select>
                        </form>

                        <!-- Print Button -->
                        <button onclick="window.print()" class="no-print inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50 focus:outline-none transition-colors">
                            <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                            </svg>
                            Print
                        </button>

                        <!-- Export Button -->
                        <a href="{{ route('infusion-integration.export', ['duration' => $duration]) }}" class="no-print inline-flex items-center px-3 py-1.5 bg-green-50 border border-green-200 text-green-700 hover:bg-green-100 rounded-lg transition-colors text-sm font-medium">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Save to Excel
                        </a>

                        <form action="{{ route('infusion-integration.logs.clear') }}" method="POST" onsubmit="return confirm('Are you sure you want to clear all HL7 message logs?');" class="no-print">
                            @csrf
                            <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-red-50 hover:bg-red-100 border border-red-200 text-red-700 rounded-lg transition-colors text-sm font-medium">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                                Clear Logs
                            </button>
                        </form>
                    </div>
                    </div>
                </div>

                <div class="p-6">
                    @if($hl7Logs->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Time</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Message Type</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Device</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Patient MRN</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Medication</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Pump Status</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Status</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Source IP</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    @foreach($hl7Logs as $log)
                                        <tr class="hover:bg-gray-50 transition-colors cursor-pointer" @click="viewLogDetails({{ json_encode($log) }})">
                                            <td class="px-4 py-3 text-sm text-gray-600">{{ $log->created_at->format('M d, H:i:s') }}</td>
                                            <td class="px-4 py-3">
                                                <span class="text-xs font-medium px-2 py-0.5 rounded bg-purple-100 text-purple-700">{{ $log->message_type ?? 'N/A' }}</span>
                                                @if($log->event_type)
                                                    <span class="text-xs text-gray-500 ml-1">^{{ $log->event_type }}</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-sm font-mono text-gray-800">{{ $log->device_id ?? '-' }}</td>
                                            <td class="px-4 py-3 text-sm font-medium text-gray-700">{{ $log->patient_mrn ?? '-' }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-600 max-w-xs truncate">{{ $log->medication_name ?? '-' }}</td>
                                            <td class="px-4 py-3">
                                                @if($log->pump_status)
                                                    <span class="text-xs font-medium px-2 py-0.5 rounded {{ $log->pump_status_color }}">
                                                        {{ ucfirst($log->pump_status) }}
                                                    </span>
                                                @else
                                                    <span class="text-xs text-gray-400">-</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="text-xs font-medium px-2 py-0.5 rounded {{ $log->status_color }}">
                                                    {{ ucfirst($log->status) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-500 font-mono">{{ $log->source_ip ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-8">
                            <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <p class="text-gray-500">No HL7 messages received yet.</p>
                            <p class="text-sm text-gray-400 mt-1">Messages will appear here when pumps send HL7 data via MLLP.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Add/Edit Pump Modal -->
        <div x-show="showAddPumpModal || showEditPumpModal" 
             x-cloak
             class="fixed inset-0 z-50 overflow-y-auto"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeModals()"></div>

                <div class="relative inline-block w-full max-w-lg p-6 my-8 text-left align-middle transition-all transform bg-white shadow-xl rounded-2xl">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-bold text-gray-800" x-text="showEditPumpModal ? 'Edit Pump' : 'Register Pump'"></h3>
                        <button @click="closeModals()" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <form :action="showEditPumpModal ? '{{ url('infusion-integration/pump') }}/' + editingPump.id : '{{ route('infusion-integration.pump.store') }}'" method="POST">
                        @csrf
                        <template x-if="showEditPumpModal">
                            <input type="hidden" name="_method" value="PUT">
                        </template>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Serial No *</label>
                                <div class="flex space-x-2">
                                    <input type="text" name="serial_no" x-model="pumpFormData.serial_no" required
                                           @keydown.enter.prevent="autofillFromEngine()"
                                           class="flex-1 rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500"
                                           placeholder="e.g., I51316">
                                    <button type="button" @click="autofillFromEngine()"
                                            :disabled="autofilling || !(pumpFormData.serial_no || '').trim()"
                                            class="px-3 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-lg text-sm font-medium whitespace-nowrap transition-colors disabled:opacity-50">
                                        <span x-show="!autofilling">⚡ Autofill</span>
                                        <span x-show="autofilling" x-cloak>Fetching…</span>
                                    </button>
                                </div>
                                <p class="mt-1 text-xs text-gray-500">Serial from the pump label (e.g., I51316). Autofill pulls Device ID, type and ward from the Infusion Engine.</p>
                                <p class="mt-1 text-xs font-medium" x-show="autofillMsg" x-cloak
                                   :class="autofillMsgOk ? 'text-green-600' : 'text-red-600'" x-text="autofillMsg"></p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Device ID</label>
                                <input type="text" name="device_id" x-model="pumpFormData.device_id"
                                       class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500"
                                       placeholder="e.g., PUMP-001 (auto-assigned if empty)">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Asset No</label>
                                <input type="text" name="asset_no" x-model="pumpFormData.asset_no"
                                       class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500"
                                       placeholder="e.g., AST-001">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Device Name</label>
                                <input type="text" name="device_name" x-model="pumpFormData.device_name"
                                       class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500"
                                       placeholder="e.g., B.Braun Space Pump #1">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Device Type</label>
                                <select name="device_type" x-model="pumpFormData.device_type"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                    <option value="">Select type...</option>
                                    <option value="syringe_pump">Syringe Pump</option>
                                    <option value="volumetric_pump">Volumetric Pump</option>
                                    <option value="pca_pump">PCA Pump</option>
                                    <option value="enteral_pump">Enteral Pump</option>
                                    <option value="space_pump">B.Braun Space Pump</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                                <input type="text" name="location" x-model="pumpFormData.location"
                                       class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500"
                                       placeholder="e.g., Ward 5A Room 201">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Ward</label>
                                <select name="ward_id" x-model="pumpFormData.ward_id"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                    <option value="">Select ward...</option>
                                    @foreach(\App\Models\Ward::all() as $ward)
                                        <option value="{{ $ward->id }}">{{ $ward->ward_name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div x-show="showEditPumpModal">
                                <label class="flex items-center">
                                    <input type="checkbox" name="is_active" value="1" x-model="pumpFormData.is_active"
                                           class="rounded border-gray-300 text-purple-600 shadow-sm focus:ring-purple-500">
                                    <span class="ml-2 text-sm text-gray-700">Active</span>
                                </label>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end space-x-3">
                            <button type="button" @click="closeModals()" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg transition-colors">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg transition-colors">
                                <span x-text="showEditPumpModal ? 'Update Pump' : 'Register Pump'"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Live Pump Status Modal (data from the Qmed Infusion Engine REST API) -->
        <div x-show="showPumpStatusModal"
             x-cloak
             class="fixed inset-0 z-50 overflow-y-auto"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closePumpStatus()"></div>

                <div class="relative inline-block w-full max-w-3xl p-6 my-8 text-left align-middle transition-all transform bg-white shadow-xl rounded-2xl">
                    <div class="flex justify-between items-center mb-1">
                        <h3 class="text-xl font-bold text-gray-800">
                            Live Pump Status
                            <span class="text-gray-400 font-normal">·</span>
                            <span x-text="statusPump.serial_no || statusPump.device_name || statusPump.device_id"></span>
                        </h3>
                        <button @click="closePumpStatus()" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <p class="text-xs text-gray-400 mb-4">
                        Fetched live from the Qmed Infusion Engine REST API
                        (<span class="font-mono">/api/pumps/{device_id}</span>)
                        · auto-refreshes every <span x-text="pumpStatusRefreshMs / 1000"></span>s
                    </p>

                    <!-- Loading (first fetch) -->
                    <div x-show="pumpStatusLoading && !pumpStatus && !pumpStatusError" class="py-10 text-center text-gray-500">
                        <svg class="w-6 h-6 mx-auto mb-2 animate-spin text-purple-500" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                        Loading status from the engine…
                    </div>

                    <!-- Error -->
                    <div x-show="pumpStatusError" x-cloak class="mb-4 p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm">
                        <b>Cannot show live status:</b> <span x-text="pumpStatusError"></span>
                    </div>

                    <template x-if="pumpStatus">
                        <div class="space-y-4">
                            <!-- Status line -->
                            <div class="flex items-center flex-wrap gap-3">
                                <span class="text-xs font-bold px-3 py-1 rounded-full"
                                      :class="pumpStatus.active_alarm ? 'bg-red-100 text-red-700'
                                            : ((pumpStatus.pump_status || '') === 'infusing' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600')"
                                      x-text="pumpStatus.active_alarm ? 'ALARM' : (pumpStatus.pump_status || 'unknown').toUpperCase()"></span>
                                <span class="text-sm font-semibold text-gray-700"
                                      x-text="pumpStatus.drug_name || pumpStatus.medication || 'No drug reported'"></span>
                                <span class="text-xs text-gray-400 ml-auto">
                                    Device ID: <span class="font-mono" x-text="pumpStatus.device_id"></span>
                                </span>
                            </div>

                            <!-- Active alarm banner -->
                            <div x-show="pumpStatus.active_alarm" class="p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm font-semibold">
                                ⚠ <span x-text="pumpStatus.active_alarm"></span>
                                <span class="font-normal" x-text="pumpStatus.alarm_priority ? ' · priority ' + pumpStatus.alarm_priority : ''"></span>
                            </div>

                            <!-- Volume progress -->
                            <div>
                                <div class="flex justify-between text-xs text-gray-500 mb-1">
                                    <span>Infused: <b class="text-gray-700" x-text="(pumpStatus.volume_infused ?? '—') + ' mL'"></b></span>
                                    <span>VTBI: <b class="text-gray-700" x-text="(pumpStatus.vtbi ?? '—') + ' mL'"></b></span>
                                </div>
                                <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-purple-500 to-pink-500 transition-all" :style="'width:' + pumpProgress() + '%'"></div>
                                </div>
                            </div>

                            <!-- Detail cards -->
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <div class="text-xs font-semibold text-gray-500 uppercase">Flow Rate</div>
                                    <div class="text-sm font-bold text-gray-800"
                                         x-text="pumpStatus.flow_rate != null ? pumpStatus.flow_rate + ' ' + (pumpStatus.flow_rate_unit || 'mL/h') : '—'"></div>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <div class="text-xs font-semibold text-gray-500 uppercase">Remaining</div>
                                    <div class="text-sm font-bold text-gray-800"
                                         x-text="pumpStatus.volume_remaining != null ? pumpStatus.volume_remaining + ' mL' : '—'"></div>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <div class="text-xs font-semibold text-gray-500 uppercase">Time Left</div>
                                    <div class="text-sm font-bold text-gray-800" x-text="fmtSecs(pumpStatus.time_remaining_sec)"></div>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <div class="text-xs font-semibold text-gray-500 uppercase">Battery</div>
                                    <div class="text-sm font-bold text-gray-800"
                                         x-text="pumpStatus.battery_percent != null ? pumpStatus.battery_percent + '%' + (pumpStatus.power_status ? ' · ' + pumpStatus.power_status : '') : (pumpStatus.power_status || '—')"></div>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <div class="text-xs font-semibold text-gray-500 uppercase">Delivery</div>
                                    <div class="text-sm text-gray-800"
                                         x-text="(pumpStatus.delivery_status || '—') + (pumpStatus.not_delivering_reason ? ' (' + pumpStatus.not_delivering_reason + ')' : '')"></div>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <div class="text-xs font-semibold text-gray-500 uppercase">Ward</div>
                                    <div class="text-sm text-gray-800"
                                         x-text="[pumpStatus.ward, pumpStatus.facility].filter(Boolean).join(' · ') || '—'"></div>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <div class="text-xs font-semibold text-gray-500 uppercase">Model</div>
                                    <div class="text-sm text-gray-800" x-text="pumpStatus.pump_model || pumpStatus.pump_type || '—'"></div>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <div class="text-xs font-semibold text-gray-500 uppercase">Last Seen</div>
                                    <div class="text-sm text-gray-800" x-text="fmtEngineTime(pumpStatus.last_seen_at)"></div>
                                </div>
                            </div>

                            <!-- Recent alarms from the engine history -->
                            <div x-show="(pumpStatus.alarms || []).length">
                                <h4 class="text-sm font-semibold text-gray-700 mb-2">Recent Alarms</h4>
                                <div class="overflow-x-auto border border-gray-100 rounded-lg">
                                    <table class="min-w-full divide-y divide-gray-100">
                                        <thead>
                                            <tr class="bg-gray-50">
                                                <th class="px-3 py-2 text-left text-xs font-bold text-gray-600 uppercase">Time</th>
                                                <th class="px-3 py-2 text-left text-xs font-bold text-gray-600 uppercase">Alarm</th>
                                                <th class="px-3 py-2 text-left text-xs font-bold text-gray-600 uppercase">Priority</th>
                                                <th class="px-3 py-2 text-left text-xs font-bold text-gray-600 uppercase">State</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-50">
                                            <template x-for="alarm in (pumpStatus.alarms || []).slice(0, 5)" :key="alarm.id">
                                                <tr>
                                                    <td class="px-3 py-2 text-xs text-gray-600" x-text="fmtEngineTime(alarm.observed_at || alarm.created_at)"></td>
                                                    <td class="px-3 py-2 text-xs font-medium text-gray-800" x-text="alarm.alert_text || alarm.event_name || '—'"></td>
                                                    <td class="px-3 py-2 text-xs text-gray-600" x-text="alarm.priority || '—'"></td>
                                                    <td class="px-3 py-2">
                                                        <span class="text-xs font-medium px-2 py-0.5 rounded"
                                                              :class="alarm.state === 'active' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600'"
                                                              x-text="alarm.state || '—'"></span>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </template>

                    <div class="mt-6 flex justify-between items-center">
                        <span class="text-xs text-gray-400" x-show="pumpStatusUpdatedAt"
                              x-text="'Last updated: ' + pumpStatusUpdatedAt"></span>
                        <div class="flex space-x-3">
                            <button @click="fetchPumpStatus()" class="px-4 py-2 bg-purple-50 hover:bg-purple-100 text-purple-700 font-medium rounded-lg transition-colors">
                                Refresh
                            </button>
                            <button @click="closePumpStatus()" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg transition-colors">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Raw HL7 Debug Modal (latest 100 messages from the Qmed Infusion Engine) -->
        <div x-show="showPumpHl7Modal"
             x-cloak
             class="fixed inset-0 z-50 overflow-y-auto"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showPumpHl7Modal = false"></div>

                <div class="relative inline-block w-full max-w-5xl p-6 my-8 text-left align-middle transition-all transform bg-white shadow-xl rounded-2xl">
                    <div class="flex justify-between items-center mb-1">
                        <h3 class="text-xl font-bold text-gray-800">
                            Raw HL7 Messages
                            <span class="text-gray-400 font-normal">·</span>
                            <span x-text="hl7Pump.serial_no || hl7Pump.device_name || hl7Pump.device_id"></span>
                        </h3>
                        <button @click="showPumpHl7Modal = false" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <p class="text-xs text-gray-400 mb-4">
                        Debug view — latest 100 HL7 messages stored by the Qmed Infusion Engine for this pump
                        (<span class="font-mono">/api/messages?device_id=…&raw=1</span>).
                        Click a row to see the full raw HL7.
                        <span x-show="pumpHl7DeviceId"> Engine device: <span class="font-mono" x-text="pumpHl7DeviceId"></span></span>
                    </p>

                    <!-- Loading -->
                    <div x-show="pumpHl7Loading && !pumpHl7.length && !pumpHl7Error" class="py-10 text-center text-gray-500">
                        <svg class="w-6 h-6 mx-auto mb-2 animate-spin text-indigo-500" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                        Loading messages from the engine…
                    </div>

                    <!-- Error -->
                    <div x-show="pumpHl7Error" x-cloak class="mb-4 p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm">
                        <b>Cannot load HL7 messages:</b> <span x-text="pumpHl7Error"></span>
                    </div>

                    <!-- Empty -->
                    <div x-show="!pumpHl7Loading && !pumpHl7Error && !pumpHl7.length" x-cloak class="py-10 text-center text-gray-400">
                        No HL7 messages stored for this pump yet.
                    </div>

                    <!-- Message list -->
                    <div x-show="pumpHl7.length" class="max-h-[60vh] overflow-y-auto border border-gray-100 rounded-lg">
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead class="sticky top-0">
                                <tr class="bg-gray-50">
                                    <th class="px-3 py-2 text-left text-xs font-bold text-gray-600 uppercase">ID</th>
                                    <th class="px-3 py-2 text-left text-xs font-bold text-gray-600 uppercase">Received</th>
                                    <th class="px-3 py-2 text-left text-xs font-bold text-gray-600 uppercase">Type</th>
                                    <th class="px-3 py-2 text-left text-xs font-bold text-gray-600 uppercase">Event</th>
                                    <th class="px-3 py-2 text-left text-xs font-bold text-gray-600 uppercase">Parse</th>
                                    <th class="px-3 py-2 text-left text-xs font-bold text-gray-600 uppercase">Source</th>
                                    <th class="px-3 py-2 text-left text-xs font-bold text-gray-600 uppercase">Size</th>
                                </tr>
                            </thead>
                            <template x-for="msg in pumpHl7" :key="msg.id">
                                <tbody class="bg-white divide-y divide-gray-50">
                                    <tr class="hover:bg-indigo-50/50 cursor-pointer transition-colors"
                                        @click="expandedHl7 = expandedHl7 === msg.id ? null : msg.id">
                                        <td class="px-3 py-2 text-xs font-mono text-gray-500" x-text="msg.id"></td>
                                        <td class="px-3 py-2 text-xs text-gray-700 whitespace-nowrap" x-text="fmtEngineTime(msg.received_at)"></td>
                                        <td class="px-3 py-2">
                                            <span class="text-xs font-medium px-2 py-0.5 rounded bg-purple-100 text-purple-700" x-text="msg.message_type || '?'"></span>
                                        </td>
                                        <td class="px-3 py-2 text-xs text-gray-600" x-text="msg.trigger_event || '—'"></td>
                                        <td class="px-3 py-2 text-xs"
                                            :class="msg.parse_status === 'parsed' ? 'text-green-600' : 'text-red-600 font-semibold'"
                                            x-text="msg.parse_status === 'parsed' ? '✓' : (msg.parse_status || '?')"></td>
                                        <td class="px-3 py-2 text-xs font-mono text-gray-500" x-text="msg.source_ip || '—'"></td>
                                        <td class="px-3 py-2 text-xs text-gray-500" x-text="(msg.raw || '').length + ' B'"></td>
                                    </tr>
                                    <tr x-show="expandedHl7 === msg.id" x-cloak>
                                        <td colspan="7" class="px-3 pb-3 pt-0 bg-gray-50/50">
                                            <pre class="bg-gray-900 text-green-400 p-3 rounded-lg text-xs overflow-x-auto font-mono whitespace-pre-wrap break-all"
                                                 x-text="fmtHl7Raw(msg.raw)"></pre>
                                            <div x-show="msg.parse_error" class="mt-2 text-xs text-red-600">
                                                Parse error: <span x-text="msg.parse_error"></span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </template>
                        </table>
                    </div>

                    <div class="mt-6 flex justify-between items-center">
                        <span class="text-xs text-gray-400" x-show="pumpHl7.length"
                              x-text="pumpHl7.length + ' messages (newest first)'"></span>
                        <div class="flex space-x-3">
                            <button @click="fetchPumpHl7()" class="px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-medium rounded-lg transition-colors">
                                Refresh
                            </button>
                            <button @click="showPumpHl7Modal = false" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg transition-colors">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- View Log Details Modal -->
        <div x-show="showLogDetailsModal" 
             x-cloak
             class="fixed inset-0 z-50 overflow-y-auto"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showLogDetailsModal = false"></div>

                <div class="relative inline-block w-full max-w-4xl p-6 my-8 text-left align-middle transition-all transform bg-white shadow-xl rounded-2xl">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-bold text-gray-800">HL7 Message Details</h3>
                        <button @click="showLogDetailsModal = false" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="space-y-4 max-h-[70vh] overflow-y-auto">
                        <!-- Message Info -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="bg-gray-50 rounded-lg p-3">
                                <div class="text-xs font-semibold text-gray-500 uppercase">Message Type</div>
                                <div class="text-sm font-semibold text-purple-600" x-text="selectedLog?.message_type || 'N/A'"></div>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <div class="text-xs font-semibold text-gray-500 uppercase">Control ID</div>
                                <div class="text-sm font-mono" x-text="selectedLog?.message_control_id || 'N/A'"></div>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <div class="text-xs font-semibold text-gray-500 uppercase">Device ID</div>
                                <div class="text-sm font-mono" x-text="selectedLog?.device_id || 'N/A'"></div>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <div class="text-xs font-semibold text-gray-500 uppercase">Source IP</div>
                                <div class="text-sm font-mono" x-text="selectedLog?.source_ip || 'N/A'"></div>
                            </div>
                        </div>

                        <!-- Patient & Infusion Info -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="bg-gray-50 rounded-lg p-3">
                                <div class="text-xs font-semibold text-gray-500 uppercase">Patient MRN</div>
                                <div class="text-sm font-semibold" x-text="selectedLog?.patient_mrn || 'N/A'"></div>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <div class="text-xs font-semibold text-gray-500 uppercase">Medication</div>
                                <div class="text-sm" x-text="selectedLog?.medication_name || 'N/A'"></div>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <div class="text-xs font-semibold text-gray-500 uppercase">Flow Rate</div>
                                <div class="text-sm" x-text="selectedLog?.flow_rate ? selectedLog.flow_rate + ' ml/hr' : 'N/A'"></div>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <div class="text-xs font-semibold text-gray-500 uppercase">Pump Status</div>
                                <div class="text-sm font-semibold" x-text="selectedLog?.pump_status ? selectedLog.pump_status.charAt(0).toUpperCase() + selectedLog.pump_status.slice(1) : 'N/A'"></div>
                            </div>
                        </div>

                        <!-- Volume Info -->
                        <div class="grid grid-cols-3 gap-4">
                            <div class="bg-blue-50 rounded-lg p-3">
                                <div class="text-xs font-semibold text-blue-600 uppercase">Total Volume</div>
                                <div class="text-lg font-bold text-blue-700" x-text="selectedLog?.total_volume ? selectedLog.total_volume + ' ml' : 'N/A'"></div>
                            </div>
                            <div class="bg-green-50 rounded-lg p-3">
                                <div class="text-xs font-semibold text-green-600 uppercase">Infused</div>
                                <div class="text-lg font-bold text-green-700" x-text="selectedLog?.infused_volume ? selectedLog.infused_volume + ' ml' : 'N/A'"></div>
                            </div>
                            <div class="bg-amber-50 rounded-lg p-3">
                                <div class="text-xs font-semibold text-amber-600 uppercase">Remaining</div>
                                <div class="text-lg font-bold text-amber-700" x-text="selectedLog?.remaining_volume ? selectedLog.remaining_volume + ' ml' : 'N/A'"></div>
                            </div>
                        </div>

                        <!-- Raw Message -->
                        <div>
                            <h4 class="text-sm font-semibold text-gray-700 mb-2">Raw HL7 Message</h4>
                            <pre class="bg-gray-900 text-green-400 p-4 rounded-lg text-xs overflow-x-auto font-mono" x-text="selectedLog?.raw_message || 'No raw message available'"></pre>
                        </div>

                        <!-- Error Message if any -->
                        <div x-show="selectedLog?.error_message">
                            <h4 class="text-sm font-semibold text-red-700 mb-2">Error Message</h4>
                            <pre class="bg-red-50 text-red-700 p-4 rounded-lg text-xs overflow-x-auto" x-text="selectedLog?.error_message"></pre>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button @click="showLogDetailsModal = false" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg transition-colors">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function integrationModeCard() {
            return {
                mode: @json($integrationMode),
                engineUrl: @json($engineConfig['url'] ?? 'http://127.0.0.1:6001'),
                engineApiKey: @json($engineConfig['api_key'] ?? ''),
                engineTimeout: @json($engineConfig['timeout'] ?? 5),
                engineRefreshSec: @json($engineConfig['refresh_sec'] ?? 10),
                testing: false,
                testResult: null,

                async testEngine() {
                    this.testing = true;
                    this.testResult = null;
                    try {
                        const response = await fetch(@json(route('infusion-integration.engine.test')), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                url: this.engineUrl,
                                api_key: this.engineApiKey,
                                timeout: this.engineTimeout,
                            }),
                        });
                        if (!response.ok) {
                            const err = await response.json().catch(() => ({}));
                            this.testResult = { ok: false, error: err.message || ('HTTP ' + response.status) };
                        } else {
                            this.testResult = await response.json();
                        }
                    } catch (e) {
                        this.testResult = { ok: false, error: e.message };
                    } finally {
                        this.testing = false;
                    }
                },
            };
        }

        function infusionIntegration() {
            return {
                showAddPumpModal: false,
                showEditPumpModal: false,
                showLogDetailsModal: false,
                editingPump: {},
                selectedLog: null,
                pumpFormData: {
                    device_id: '',
                    asset_no: '',
                    serial_no: '',
                    device_name: '',
                    device_type: '',
                    location: '',
                    ward_id: '',
                    is_active: true
                },

                closeModals() {
                    this.showAddPumpModal = false;
                    this.showEditPumpModal = false;
                    this.resetPumpForm();
                    this.autofillMsg = '';
                },

                // ---- Autofill the pump form from the Infusion Engine by Serial No ----
                autofilling: false,
                autofillMsg: '',
                autofillMsgOk: true,

                async autofillFromEngine() {
                    const serial = (this.pumpFormData.serial_no || '').trim();
                    if (!serial || this.autofilling) return;
                    this.autofilling = true;
                    this.autofillMsg = '';
                    try {
                        const res = await fetch('{{ route('infusion-integration.engine.pump-lookup') }}?serial=' + encodeURIComponent(serial), {
                            headers: { 'Accept': 'application/json' },
                        });
                        const data = await res.json().catch(() => ({}));
                        if (data.ok) {
                            const p = data.pump;
                            this.pumpFormData.serial_no = p.serial_no || serial;
                            if (p.device_id) this.pumpFormData.device_id = p.device_id;
                            if (p.device_name) this.pumpFormData.device_name = p.device_name;
                            if (p.device_type) this.pumpFormData.device_type = p.device_type;
                            if (p.location) this.pumpFormData.location = p.location;
                            if (p.ward_id) this.pumpFormData.ward_id = String(p.ward_id);
                            this.autofillMsgOk = true;
                            this.autofillMsg = '✓ Filled from engine'
                                + (p.last_seen_at ? ' — last seen ' + new Date(p.last_seen_at).toLocaleString() : '')
                                + (p.ward && !p.ward_id ? ' (ward "' + p.ward + '" not registered in SmartWard — pick manually)' : '');
                        } else {
                            this.autofillMsgOk = false;
                            this.autofillMsg = data.error || ('Lookup failed (HTTP ' + res.status + ')');
                        }
                    } catch (e) {
                        this.autofillMsgOk = false;
                        this.autofillMsg = 'Lookup failed: ' + e.message;
                    } finally {
                        this.autofilling = false;
                    }
                },

                resetPumpForm() {
                    this.pumpFormData = {
                        device_id: '',
                        asset_no: '',
                        serial_no: '',
                        device_name: '',
                        device_type: '',
                        location: '',
                        ward_id: '',
                        is_active: true
                    };
                    this.editingPump = {};
                },

                editPump(pump) {
                    this.editingPump = pump;
                    this.pumpFormData = {
                        device_id: pump.device_id,
                        asset_no: pump.asset_no || '',
                        serial_no: pump.serial_no || '',
                        device_name: pump.device_name || '',
                        device_type: pump.device_type || '',
                        location: pump.location || '',
                        ward_id: pump.ward_id || '',
                        is_active: pump.is_active
                    };
                    this.showEditPumpModal = true;
                },

                viewLogDetails(log) {
                    this.selectedLog = log;
                    this.showLogDetailsModal = true;
                },

                // ---- Live pump status (Qmed Infusion Engine REST API) ----
                showPumpStatusModal: false,
                statusPump: {},
                pumpStatus: null,
                pumpStatusError: null,
                pumpStatusLoading: false,
                pumpStatusUpdatedAt: null,
                pumpStatusRefreshMs: @json(max(2, (int) ($engineConfig['refresh_sec'] ?? 10)) * 1000),
                _pumpStatusTimer: null,

                viewPumpStatus(pump) {
                    this.statusPump = pump;
                    this.pumpStatus = null;
                    this.pumpStatusError = null;
                    this.pumpStatusUpdatedAt = null;
                    this.showPumpStatusModal = true;
                    this.fetchPumpStatus();
                    clearInterval(this._pumpStatusTimer);
                    this._pumpStatusTimer = setInterval(() => this.fetchPumpStatus(), this.pumpStatusRefreshMs);
                },

                async fetchPumpStatus() {
                    if (!this.showPumpStatusModal || !this.statusPump.id) return;
                    this.pumpStatusLoading = true;
                    try {
                        const res = await fetch('{{ url('infusion-integration/pump') }}/' + this.statusPump.id + '/status', {
                            headers: { 'Accept': 'application/json' },
                        });
                        const data = await res.json().catch(() => ({}));
                        if (data.ok) {
                            this.pumpStatus = data.pump;
                            this.pumpStatusError = null;
                        } else {
                            this.pumpStatusError = data.error || ('Engine request failed (HTTP ' + res.status + ')');
                        }
                    } catch (e) {
                        this.pumpStatusError = 'Request failed: ' + e.message;
                    } finally {
                        this.pumpStatusLoading = false;
                        this.pumpStatusUpdatedAt = new Date().toLocaleTimeString();
                    }
                },

                closePumpStatus() {
                    this.showPumpStatusModal = false;
                    clearInterval(this._pumpStatusTimer);
                },

                pumpProgress() {
                    const p = this.pumpStatus;
                    if (!p || !p.vtbi) return 0;
                    return Math.min(100, Math.round((p.volume_infused || 0) / p.vtbi * 100));
                },

                fmtSecs(sec) {
                    if (sec == null) return '—';
                    sec = Math.max(0, Math.round(sec));
                    const h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60);
                    return h ? h + 'h ' + m + 'm' : m + 'm';
                },

                fmtEngineTime(iso) {
                    if (!iso) return '—';
                    const d = new Date(iso);
                    return isNaN(d) ? iso : d.toLocaleString();
                },

                // ---- Raw HL7 debug view (latest 100 engine messages) ----
                showPumpHl7Modal: false,
                hl7Pump: {},
                pumpHl7: [],
                pumpHl7Error: null,
                pumpHl7Loading: false,
                pumpHl7DeviceId: null,
                expandedHl7: null,

                viewPumpHl7(pump) {
                    this.hl7Pump = pump;
                    this.pumpHl7 = [];
                    this.pumpHl7Error = null;
                    this.pumpHl7DeviceId = null;
                    this.expandedHl7 = null;
                    this.showPumpHl7Modal = true;
                    this.fetchPumpHl7();
                },

                async fetchPumpHl7() {
                    if (!this.hl7Pump.id) return;
                    this.pumpHl7Loading = true;
                    try {
                        const res = await fetch('{{ url('infusion-integration/pump') }}/' + this.hl7Pump.id + '/hl7', {
                            headers: { 'Accept': 'application/json' },
                        });
                        const data = await res.json().catch(() => ({}));
                        if (data.ok) {
                            this.pumpHl7 = data.messages || [];
                            this.pumpHl7DeviceId = data.device_id;
                            this.pumpHl7Error = null;
                        } else {
                            this.pumpHl7Error = data.error || ('Engine request failed (HTTP ' + res.status + ')');
                        }
                    } catch (e) {
                        this.pumpHl7Error = 'Request failed: ' + e.message;
                    } finally {
                        this.pumpHl7Loading = false;
                    }
                },

                fmtHl7Raw(raw) {
                    return (raw || '').replace(/\r/g, '\n').trim();
                }
            };
        }
    </script>
</x-app-layout>
