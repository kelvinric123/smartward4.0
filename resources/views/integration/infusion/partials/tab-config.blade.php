{{-- Configuration tab: where infusion data comes from, and the MLLP
     listener the built-in mode uses. --}}
<div class="space-y-6">
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
                                    <input type="password" name="engine_api_key" x-model="engineApiKey" placeholder="X-API-Key"
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
</div>
