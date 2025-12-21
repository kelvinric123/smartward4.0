<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-bold text-2xl text-gray-800 leading-tight">
                    {{ __('Vital Sign Integration') }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">Receive vital sign readings from Gateway devices via RESTful API
                </p>
            </div>
            <button onclick="document.getElementById('apiInfoModal').classList.remove('hidden')"
                class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                API Documentation
            </button>
        </div>
    </x-slot>

    <!-- API Info Modal -->
    <div id="apiInfoModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity"
                onclick="document.getElementById('apiInfoModal').classList.add('hidden')"></div>

            <div
                class="relative inline-block w-full max-w-5xl p-8 my-8 text-left align-middle transition-all transform bg-gradient-to-br from-slate-900 to-slate-800 shadow-2xl rounded-3xl border border-slate-700">
                <div class="flex justify-between items-center mb-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-xl mr-4">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-white">Vital Sign API Documentation</h3>
                    </div>
                    <button onclick="document.getElementById('apiInfoModal').classList.add('hidden')"
                        class="text-gray-400 hover:text-white transition-colors">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-6 max-h-[70vh] overflow-y-auto pr-2">
                    <!-- API Mode Tabs -->
                    <div x-data="{ apiTab: 'v1' }" class="space-y-4">
                        <div class="flex space-x-2 bg-slate-800 rounded-xl p-1">
                            <button @click="apiTab = 'v1'"
                                :class="apiTab === 'v1' ? 'bg-gradient-to-r from-orange-500 to-amber-500 text-white' : 'text-gray-400 hover:text-white'"
                                class="flex-1 px-4 py-2.5 rounded-lg font-semibold transition-all text-sm">
                                🔸 API V1 (Gateway/HL7)
                            </button>
                            <button @click="apiTab = 'v2'"
                                :class="apiTab === 'v2' ? 'bg-gradient-to-r from-emerald-500 to-teal-500 text-white' : 'text-gray-400 hover:text-white'"
                                class="flex-1 px-4 py-2.5 rounded-lg font-semibold transition-all text-sm">
                                🔹 API V2 (Bearer Token)
                            </button>
                        </div>

                        <!-- API V1 Documentation (Gateway/HL7) -->
                        <div x-show="apiTab === 'v1'" class="space-y-4">
                            <!-- Base URL V1 -->
                            <div
                                class="bg-gradient-to-r from-orange-900/30 to-amber-900/30 rounded-xl p-4 border border-orange-700">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-lg font-bold text-white">API V1 - Raspberry Pi / HL7 Gateway</h4>
                                    <span
                                        class="px-2 py-1 bg-orange-600 text-white text-xs font-bold rounded">RECOMMENDED</span>
                                </div>
                                <p class="text-sm text-gray-400 mb-3">Per-request authentication with passphrase header.
                                    No login step required.</p>
                                <div class="flex items-center">
                                    <span class="text-xs text-gray-400 mr-2">Base URL:</span>
                                    <code
                                        class="text-orange-300 bg-slate-800 px-3 py-1.5 rounded-lg text-sm">{{ url('/api/v1') }}</code>
                                </div>
                            </div>

                            <!-- V1 Authentication -->
                            <div class="bg-slate-700/50 rounded-xl p-4 border border-slate-600">
                                <h4 class="text-lg font-bold text-white mb-3 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-orange-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                    </svg>
                                    Authentication Method
                                </h4>
                                <div class="bg-slate-800/70 rounded-lg p-3">
                                    <p class="text-xs font-semibold text-gray-400 mb-2">EVERY REQUEST REQUIRES</p>
                                    <pre class="text-sm text-gray-300 font-mono"><span class="text-orange-300">Header:</span>  X-Passphrase: {{ $gatewayConfig['passphrase'] }}
<span class="text-orange-300">Body:</span>    username, password (API User credentials)</pre>
                                </div>
                            </div>

                            <!-- V1 Submit Vital Signs -->
                            <div class="bg-slate-700/50 rounded-xl p-4 border border-slate-600">
                                <h4 class="text-lg font-bold text-white mb-3 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-blue-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                    </svg>
                                    Submit Vital Signs
                                </h4>
                                <div class="grid grid-cols-2 gap-4 mb-3">
                                    <div>
                                        <span class="text-xs font-semibold text-gray-400 uppercase">Method</span>
                                        <p class="text-green-400 font-mono">POST</p>
                                    </div>
                                    <div>
                                        <span class="text-xs font-semibold text-gray-400 uppercase">Endpoint</span>
                                        <p class="text-cyan-300 font-mono">/vital-signs</p>
                                    </div>
                                </div>
                                <div class="bg-slate-800/70 rounded-lg p-3 mb-3">
                                    <p class="text-xs font-semibold text-gray-400 mb-2">HEADERS</p>
                                    <pre class="text-sm text-gray-300 font-mono">X-Passphrase: {{ $gatewayConfig['passphrase'] }}
Content-Type: application/json</pre>
                                </div>
                                <div class="bg-slate-800/70 rounded-lg p-3">
                                    <p class="text-xs font-semibold text-gray-400 mb-2">REQUEST BODY</p>
                                    <pre class="text-sm text-gray-300 font-mono">{
    "username": "gateway_user",
    "password": "your_password",
    "patient_code": "MRN001",
    "measured_at": "2024-12-03 10:30:00",
    "blood_pressure_systolic": 120,
    "blood_pressure_diastolic": 80,
    "pulse_rate": 72,
    "temperature": 36.5,
    "spo2": 98,
    "respiratory_rate": 16
}</pre>
                                </div>
                            </div>

                            <!-- V1 Search Patient -->
                            <div class="bg-slate-700/50 rounded-xl p-4 border border-slate-600">
                                <h4 class="text-lg font-bold text-white mb-3 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-purple-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                    Search Patient
                                </h4>
                                <div class="grid grid-cols-2 gap-4 mb-3">
                                    <div>
                                        <span class="text-xs font-semibold text-gray-400 uppercase">Method</span>
                                        <p class="text-blue-400 font-mono">GET</p>
                                    </div>
                                    <div>
                                        <span class="text-xs font-semibold text-gray-400 uppercase">Endpoint</span>
                                        <p class="text-cyan-300 font-mono">/patients/{patient_code}</p>
                                    </div>
                                </div>
                                <div class="bg-slate-800/70 rounded-lg p-3 mb-3">
                                    <p class="text-xs font-semibold text-gray-400 mb-2">HEADERS</p>
                                    <pre
                                        class="text-sm text-gray-300 font-mono">X-Passphrase: {{ $gatewayConfig['passphrase'] }}</pre>
                                </div>
                                <div class="bg-slate-800/70 rounded-lg p-3">
                                    <p class="text-xs font-semibold text-gray-400 mb-2">QUERY PARAMETERS</p>
                                    <pre
                                        class="text-sm text-gray-300 font-mono">?username=gateway_user&password=your_password</pre>
                                </div>
                            </div>

                            <!-- V1 Parameters -->
                            <div class="bg-slate-700/50 rounded-xl p-4 border border-slate-600">
                                <h4 class="text-lg font-bold text-white mb-3 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-teal-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    API V1 Parameters
                                </h4>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="text-left text-gray-400 border-b border-slate-600">
                                                <th class="pb-2">Parameter</th>
                                                <th class="pb-2">Type</th>
                                                <th class="pb-2">Range</th>
                                                <th class="pb-2">Required</th>
                                            </tr>
                                        </thead>
                                        <tbody class="text-gray-300">
                                            <tr class="border-b border-slate-700">
                                                <td class="py-2 font-mono text-cyan-300">patient_code</td>
                                                <td>string</td>
                                                <td>MRN/Visit Number</td>
                                                <td class="text-red-400">Yes</td>
                                            </tr>
                                            <tr class="border-b border-slate-700">
                                                <td class="py-2 font-mono text-cyan-300">blood_pressure_systolic</td>
                                                <td>number</td>
                                                <td>0-300 mmHg</td>
                                                <td class="text-gray-500">No</td>
                                            </tr>
                                            <tr class="border-b border-slate-700">
                                                <td class="py-2 font-mono text-cyan-300">blood_pressure_diastolic</td>
                                                <td>number</td>
                                                <td>0-200 mmHg</td>
                                                <td class="text-gray-500">No</td>
                                            </tr>
                                            <tr class="border-b border-slate-700">
                                                <td class="py-2 font-mono text-cyan-300">pulse_rate / heart_rate</td>
                                                <td>number</td>
                                                <td>0-300 bpm</td>
                                                <td class="text-gray-500">No</td>
                                            </tr>
                                            <tr class="border-b border-slate-700">
                                                <td class="py-2 font-mono text-cyan-300">temperature</td>
                                                <td>number</td>
                                                <td>20-50 °C</td>
                                                <td class="text-gray-500">No</td>
                                            </tr>
                                            <tr class="border-b border-slate-700">
                                                <td class="py-2 font-mono text-cyan-300">spo2</td>
                                                <td>number</td>
                                                <td>0-100 %</td>
                                                <td class="text-gray-500">No</td>
                                            </tr>
                                            <tr class="border-b border-slate-700">
                                                <td class="py-2 font-mono text-cyan-300">respiratory_rate</td>
                                                <td>number</td>
                                                <td>0-100 /min</td>
                                                <td class="text-gray-500">No</td>
                                            </tr>
                                            <tr class="border-b border-slate-700">
                                                <td class="py-2 font-mono text-cyan-300">weight</td>
                                                <td>number</td>
                                                <td>0-500 kg</td>
                                                <td class="text-gray-500">No</td>
                                            </tr>
                                            <tr class="border-b border-slate-700">
                                                <td class="py-2 font-mono text-cyan-300">height</td>
                                                <td>number</td>
                                                <td>0-300 cm</td>
                                                <td class="text-gray-500">No</td>
                                            </tr>
                                            <tr>
                                                <td class="py-2 font-mono text-cyan-300">measured_at</td>
                                                <td>datetime</td>
                                                <td>YYYY-MM-DD HH:MM:SS</td>
                                                <td class="text-gray-500">No (defaults to now)</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- API V2 Documentation (Bearer Token) -->
                        <div x-show="apiTab === 'v2'" class="space-y-4">
                            <!-- Base URL V2 -->
                            <div
                                class="bg-gradient-to-r from-emerald-900/30 to-teal-900/30 rounded-xl p-4 border border-emerald-700">
                                <h4 class="text-lg font-bold text-white mb-2">API V2 - Bearer Token Authentication</h4>
                                <p class="text-sm text-gray-400 mb-3">Traditional login-first authentication flow.
                                    Useful for web applications.</p>
                                <div class="flex items-center">
                                    <span class="text-xs text-gray-400 mr-2">Base URL:</span>
                                    <code
                                        class="text-emerald-300 bg-slate-800 px-3 py-1.5 rounded-lg text-sm">{{ url('/api/vital-sign') }}</code>
                                </div>
                            </div>

                            <!-- Authentication -->
                            <div class="bg-slate-700/50 rounded-xl p-4 border border-slate-600">
                                <h4 class="text-lg font-bold text-white mb-3 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-amber-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                    </svg>
                                    1. Login (Get Bearer Token)
                                </h4>
                                <div class="grid grid-cols-2 gap-4 mb-3">
                                    <div>
                                        <span class="text-xs font-semibold text-gray-400 uppercase">Method</span>
                                        <p class="text-green-400 font-mono">POST</p>
                                    </div>
                                    <div>
                                        <span class="text-xs font-semibold text-gray-400 uppercase">Endpoint</span>
                                        <p class="text-cyan-300 font-mono">/login</p>
                                    </div>
                                </div>
                                <div class="bg-slate-800/70 rounded-lg p-3 mb-3">
                                    <p class="text-xs font-semibold text-gray-400 mb-2">REQUEST BODY</p>
                                    <pre class="text-sm text-gray-300 font-mono">{
    "username": "gateway_user",
    "password": "your_password"
}</pre>
                                </div>
                                <div class="bg-slate-800/70 rounded-lg p-3">
                                    <p class="text-xs font-semibold text-gray-400 mb-2">RESPONSE</p>
                                    <pre class="text-sm text-gray-300 font-mono">{
    "success": true,
    "message": "Login successful",
    "data": {
        "token": "your_bearer_token",
        "token_type": "Bearer",
        "expires_at": "2024-12-04T10:00:00Z"
    }
}</pre>
                                </div>
                            </div>

                            <!-- Single Reading -->
                            <div class="bg-slate-700/50 rounded-xl p-4 border border-slate-600">
                                <h4 class="text-lg font-bold text-white mb-3 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-blue-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                    </svg>
                                    2. Submit Single Vital Sign Reading
                                </h4>
                                <div class="grid grid-cols-2 gap-4 mb-3">
                                    <div>
                                        <span class="text-xs font-semibold text-gray-400 uppercase">Method</span>
                                        <p class="text-green-400 font-mono">POST</p>
                                    </div>
                                    <div>
                                        <span class="text-xs font-semibold text-gray-400 uppercase">Endpoint</span>
                                        <p class="text-cyan-300 font-mono">/reading</p>
                                    </div>
                                </div>
                                <div class="bg-slate-800/70 rounded-lg p-3 mb-3">
                                    <p class="text-xs font-semibold text-gray-400 mb-2">HEADERS</p>
                                    <pre class="text-sm text-gray-300 font-mono">Authorization: Bearer your_token_here
Content-Type: application/json</pre>
                                </div>
                                <div class="bg-slate-800/70 rounded-lg p-3">
                                    <p class="text-xs font-semibold text-gray-400 mb-2">REQUEST BODY</p>
                                    <pre class="text-sm text-gray-300 font-mono">{
    "patient_mrn": "MRN001",
    "systolic_bp": 120,
    "diastolic_bp": 80,
    "pulse_rate": 72,
    "temperature": 36.5,
    "spo2": 98,
    "respiratory_rate": 16,
    "device_id": "GW-001",
    "recorded_at": "2024-12-03T10:30:00Z"
}</pre>
                                </div>
                            </div>

                            <!-- Multiple Readings -->
                            <div class="bg-slate-700/50 rounded-xl p-4 border border-slate-600">
                                <h4 class="text-lg font-bold text-white mb-3 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-purple-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                    </svg>
                                    3. Submit Multiple Vital Sign Readings (Batch)
                                </h4>
                                <div class="grid grid-cols-2 gap-4 mb-3">
                                    <div>
                                        <span class="text-xs font-semibold text-gray-400 uppercase">Method</span>
                                        <p class="text-green-400 font-mono">POST</p>
                                    </div>
                                    <div>
                                        <span class="text-xs font-semibold text-gray-400 uppercase">Endpoint</span>
                                        <p class="text-cyan-300 font-mono">/readings</p>
                                    </div>
                                </div>
                                <div class="bg-slate-800/70 rounded-lg p-3">
                                    <p class="text-xs font-semibold text-gray-400 mb-2">REQUEST BODY</p>
                                    <pre class="text-sm text-gray-300 font-mono">{
    "readings": [
        {
            "patient_mrn": "MRN001",
            "systolic_bp": 120,
            "diastolic_bp": 80,
            "pulse_rate": 72,
            "temperature": 36.5,
            "spo2": 98,
            "respiratory_rate": 16
        },
        {
            "patient_mrn": "MRN002",
            "systolic_bp": 130,
            "diastolic_bp": 85,
            "pulse_rate": 78
        }
    ]
}</pre>
                                </div>
                            </div>

                            <!-- Vital Sign Parameters -->
                            <div class="bg-slate-700/50 rounded-xl p-4 border border-slate-600">
                                <h4 class="text-lg font-bold text-white mb-3 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-teal-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    API V2 Parameters
                                </h4>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="text-left text-gray-400 border-b border-slate-600">
                                                <th class="pb-2">Parameter</th>
                                                <th class="pb-2">Type</th>
                                                <th class="pb-2">Range</th>
                                                <th class="pb-2">Required</th>
                                            </tr>
                                        </thead>
                                        <tbody class="text-gray-300">
                                            <tr class="border-b border-slate-700">
                                                <td class="py-2 font-mono text-cyan-300">patient_mrn</td>
                                                <td>string</td>
                                                <td>-</td>
                                                <td class="text-red-400">Yes</td>
                                            </tr>
                                            <tr class="border-b border-slate-700">
                                                <td class="py-2 font-mono text-cyan-300">systolic_bp</td>
                                                <td>integer</td>
                                                <td>0-300 mmHg</td>
                                                <td class="text-gray-500">No</td>
                                            </tr>
                                            <tr class="border-b border-slate-700">
                                                <td class="py-2 font-mono text-cyan-300">diastolic_bp</td>
                                                <td>integer</td>
                                                <td>0-200 mmHg</td>
                                                <td class="text-gray-500">No</td>
                                            </tr>
                                            <tr class="border-b border-slate-700">
                                                <td class="py-2 font-mono text-cyan-300">pulse_rate</td>
                                                <td>integer</td>
                                                <td>0-300 bpm</td>
                                                <td class="text-gray-500">No</td>
                                            </tr>
                                            <tr class="border-b border-slate-700">
                                                <td class="py-2 font-mono text-cyan-300">temperature</td>
                                                <td>decimal</td>
                                                <td>30-45 °C</td>
                                                <td class="text-gray-500">No</td>
                                            </tr>
                                            <tr class="border-b border-slate-700">
                                                <td class="py-2 font-mono text-cyan-300">spo2</td>
                                                <td>integer</td>
                                                <td>0-100 %</td>
                                                <td class="text-gray-500">No</td>
                                            </tr>
                                            <tr class="border-b border-slate-700">
                                                <td class="py-2 font-mono text-cyan-300">respiratory_rate</td>
                                                <td>integer</td>
                                                <td>0-100 /min</td>
                                                <td class="text-gray-500">No</td>
                                            </tr>
                                            <tr class="border-b border-slate-700">
                                                <td class="py-2 font-mono text-cyan-300">device_id</td>
                                                <td>string</td>
                                                <td>max 100 chars</td>
                                                <td class="text-gray-500">No</td>
                                            </tr>
                                            <tr>
                                                <td class="py-2 font-mono text-cyan-300">recorded_at</td>
                                                <td>datetime</td>
                                                <td>ISO 8601</td>
                                                <td class="text-gray-500">No (defaults to now)</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button onclick="document.getElementById('apiInfoModal').classList.add('hidden')"
                        class="px-6 py-2.5 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white font-semibold rounded-lg transition-all">
                        Got it!
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="py-8" x-data="vitalSignIntegration()">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Gateway Configuration Section -->
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-orange-100">
                <div class="p-6 border-b border-orange-100 bg-gradient-to-r from-orange-50 to-amber-50">
                    <div class="flex items-center">
                        <div class="p-3 bg-gradient-to-br from-orange-500 to-amber-600 rounded-xl mr-4">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800">Gateway Configuration</h3>
                            <p class="text-sm text-gray-500">Configuration settings for Raspberry Pi / HL7 Gateway (API
                                V1)</p>
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Server URL Info -->
                        <div class="bg-gradient-to-br from-slate-50 to-gray-50 rounded-xl p-5 border border-gray-200">
                            <h4 class="font-bold text-gray-800 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                                </svg>
                                Server URL
                            </h4>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">API Base
                                        URL</label>
                                    <div class="flex items-center bg-white border border-gray-300 rounded-lg">
                                        <code
                                            class="flex-1 px-3 py-2 text-sm text-emerald-700 font-mono">{{ $gatewayConfig['api_base_url'] }}</code>
                                        <button onclick="copyToClipboard('{{ $gatewayConfig['api_base_url'] }}')"
                                            class="px-3 py-2 text-gray-500 hover:text-emerald-600 border-l border-gray-300">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Server
                                        IP</label>
                                    <div class="flex items-center bg-white border border-gray-300 rounded-lg">
                                        <code
                                            class="flex-1 px-3 py-2 text-sm text-blue-700 font-mono">{{ $gatewayConfig['server_ip'] }}</code>
                                        <button onclick="copyToClipboard('{{ $gatewayConfig['server_ip'] }}')"
                                            class="px-3 py-2 text-gray-500 hover:text-blue-600 border-l border-gray-300">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Port</label>
                                    <div class="flex items-center bg-white border border-gray-300 rounded-lg">
                                        <code
                                            class="flex-1 px-3 py-2 text-sm text-purple-700 font-mono">{{ $gatewayConfig['server_port'] }}</code>
                                        <button onclick="copyToClipboard('{{ $gatewayConfig['server_port'] }}')"
                                            class="px-3 py-2 text-gray-500 hover:text-purple-600 border-l border-gray-300">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Authentication Info -->
                        <div
                            class="bg-gradient-to-br from-amber-50 to-orange-50 rounded-xl p-5 border border-orange-200">
                            <h4 class="font-bold text-gray-800 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-orange-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                </svg>
                                API Authentication
                            </h4>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">API
                                        Passphrase (X-Passphrase Header)</label>
                                    <div class="flex items-center bg-white border border-orange-300 rounded-lg">
                                        <code class="flex-1 px-3 py-2 text-sm text-orange-700 font-mono"
                                            x-text="showPassphrase ? '{{ $gatewayConfig['passphrase'] }}' : '••••••••'"></code>
                                        <button @click="showPassphrase = !showPassphrase"
                                            class="px-3 py-2 text-gray-500 hover:text-orange-600 border-l border-orange-300">
                                            <svg x-show="!showPassphrase" class="w-4 h-4" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            <svg x-show="showPassphrase" class="w-4 h-4" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                            </svg>
                                        </button>
                                        <button onclick="copyToClipboard('{{ $gatewayConfig['passphrase'] }}')"
                                            class="px-3 py-2 text-gray-500 hover:text-orange-600 border-l border-orange-300">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                            </svg>
                                        </button>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">Set in <code
                                            class="bg-gray-200 px-1 rounded">.env</code> file as <code
                                            class="bg-gray-200 px-1 rounded">VITAL_SIGN_API_PASSPHRASE</code></p>
                                </div>
                                <div class="pt-2 border-t border-orange-200">
                                    <p class="text-sm text-gray-700"><strong>Device Username/Password:</strong> Create
                                        an API User below to authenticate gateway devices.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Gateway config.py Example -->
                    <div class="mt-6 bg-slate-900 rounded-xl p-5">
                        <div class="flex justify-between items-center mb-3">
                            <h4 class="font-bold text-white flex items-center">
                                <svg class="w-5 h-5 mr-2 text-yellow-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                                </svg>
                                Gateway config.py Example
                            </h4>
                            <button onclick="copyGatewayConfig()"
                                class="text-xs px-3 py-1 bg-slate-700 hover:bg-slate-600 text-gray-300 rounded-lg transition-colors">
                                Copy Config
                            </button>
                        </div>
                        <pre id="gatewayConfigCode" class="text-sm text-gray-300 font-mono overflow-x-auto"><span class="text-gray-500"># Laravel API Configuration</span>
<span class="text-cyan-400">API_BASE_URL</span> = <span class="text-green-400">"http://{{ $gatewayConfig['server_ip'] }}:{{ $gatewayConfig['server_port'] }}/api/v1"</span>

<span class="text-gray-500"># API Passphrase (must match Laravel configuration)</span>
<span class="text-cyan-400">API_PASSPHRASE</span> = <span class="text-green-400">"{{ $gatewayConfig['passphrase'] }}"</span>

<span class="text-gray-500"># Device Credentials (create an API User below)</span>
<span class="text-cyan-400">DEVICE_USERNAME</span> = <span class="text-green-400">"your_api_user@example.com"</span>
<span class="text-cyan-400">DEVICE_PASSWORD</span> = <span class="text-green-400">"your_password"</span></pre>
                    </div>
                </div>
            </div>

            @if (session('success'))
                <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-l-4 border-green-500 text-green-800 px-6 py-4 rounded-lg shadow-md"
                    role="alert">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="font-medium">{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if (session('error'))
                <div class="bg-gradient-to-r from-red-50 to-pink-50 border-l-4 border-red-500 text-red-800 px-6 py-4 rounded-lg shadow-md"
                    role="alert">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="font-medium">{{ session('error') }}</span>
                    </div>
                </div>
            @endif

            <!-- API Users Section -->
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-emerald-100">
                <div class="p-6 border-b border-emerald-100 bg-gradient-to-r from-emerald-50 to-teal-50">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center">
                            <div class="p-3 bg-emerald-600 rounded-xl mr-4">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800">API Users</h3>
                                <p class="text-sm text-gray-500">Manage API credentials for Gateway devices</p>
                            </div>
                        </div>
                        <button @click="showAddUserModal = true"
                            class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v16m8-8H4" />
                            </svg>
                            Add API User
                        </button>
                    </div>
                </div>

                <div class="p-6">
                    @if($apiUsers->count() > 0)
                        <div class="grid gap-4">
                            @foreach($apiUsers as $user)
                                <div
                                    class="border border-gray-200 rounded-xl p-5 hover:border-emerald-300 hover:shadow-md transition-all bg-white">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <div class="flex items-center mb-2">
                                                <h4 class="text-lg font-bold text-gray-800">{{ $user->name }}</h4>
                                                <span
                                                    class="ml-2 px-2 py-0.5 text-xs font-medium {{ $user->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }} rounded-full">
                                                    {{ $user->is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                                @if($user->hasValidToken())
                                                    <span
                                                        class="ml-2 px-2 py-0.5 text-xs font-medium bg-blue-100 text-blue-700 rounded-full">
                                                        Token Valid
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-2 text-sm text-gray-600">
                                                <div>
                                                    <span class="font-medium">Username:</span>
                                                    <code class="bg-gray-100 px-2 py-0.5 rounded">{{ $user->username }}</code>
                                                </div>
                                                <div>
                                                    <span class="font-medium">Requests:</span>
                                                    {{ number_format($user->request_count) }}
                                                </div>
                                                <div>
                                                    <span class="font-medium">Last Login:</span>
                                                    {{ $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never' }}
                                                </div>
                                            </div>
                                            @if($user->description)
                                                <p class="text-sm text-gray-500 mt-2">{{ $user->description }}</p>
                                            @endif
                                        </div>
                                        <div class="flex items-center space-x-2 ml-4">
                                            <button @click="editUser({{ json_encode($user) }})"
                                                class="inline-flex items-center px-3 py-1.5 bg-blue-100 hover:bg-blue-200 text-blue-700 rounded-lg transition-colors text-sm font-medium">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                                Edit
                                            </button>
                                            <form action="{{ route('vital-sign-integration.api-user.destroy', $user) }}"
                                                method="POST" class="inline"
                                                onsubmit="return confirm('Are you sure you want to delete this API user?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="inline-flex items-center px-3 py-1.5 bg-red-100 hover:bg-red-200 text-red-700 rounded-lg transition-colors text-sm font-medium">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-12">
                            <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                            </svg>
                            <h4 class="text-lg font-medium text-gray-600 mb-2">No API Users</h4>
                            <p class="text-gray-500 mb-4">Create an API user to allow Gateway devices to send vital signs.
                            </p>
                            <button @click="showAddUserModal = true"
                                class="inline-flex items-center px-4 py-2 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 transition-colors">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4" />
                                </svg>
                                Add API User
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Monitor Devices Section (for MP5SC Listener) -->
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-cyan-100">
                <div class="p-6 border-b border-cyan-100 bg-gradient-to-r from-cyan-50 to-sky-50">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center">
                            <div class="p-3 bg-cyan-600 rounded-xl mr-4">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800">Monitor Devices</h3>
                                <p class="text-sm text-gray-500">Philips IntelliVue MP5SC monitors for vital sign
                                    collection</p>
                            </div>
                        </div>
                        <button @click="showAddDeviceModal = true"
                            class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-cyan-600 to-sky-600 hover:from-cyan-700 hover:to-sky-700 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v16m8-8H4" />
                            </svg>
                            Add Device
                        </button>
                    </div>
                </div>

                <div class="p-6">
                    @if($monitorDevices->count() > 0)
                        <div class="grid gap-4">
                            @foreach($monitorDevices as $device)
                                <div
                                    class="border border-gray-200 rounded-xl p-5 hover:border-cyan-300 hover:shadow-md transition-all bg-white">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <div class="flex items-center mb-2">
                                                <h4 class="text-lg font-bold text-gray-800">{{ $device->name }}</h4>
                                                <span
                                                    class="ml-2 px-2 py-0.5 text-xs font-medium {{ $device->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }} rounded-full">
                                                    {{ $device->is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                                @if($device->last_status)
                                                    <span
                                                        class="ml-2 px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-600 rounded-full">
                                                        {{ $device->last_status }}
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-2 text-sm text-gray-600">
                                                <div>
                                                    <span class="font-medium">IP Address:</span>
                                                    <code
                                                        class="bg-gray-100 px-2 py-0.5 rounded">{{ $device->ip_address }}</code>
                                                </div>
                                                <div>
                                                    <span class="font-medium">Port:</span> {{ $device->port }}
                                                </div>
                                                <div>
                                                    <span class="font-medium">Last Connected:</span>
                                                    {{ $device->last_connected_at ? $device->last_connected_at->diffForHumans() : 'Never' }}
                                                </div>
                                            </div>
                                            @if($device->location)
                                                <p class="text-sm text-gray-500 mt-2"><span class="font-medium">Location:</span>
                                                    {{ $device->location }}</p>
                                            @endif
                                            @if($device->description)
                                                <p class="text-sm text-gray-400 mt-1">{{ $device->description }}</p>
                                            @endif
                                        </div>
                                        <div class="flex items-center space-x-2 ml-4">
                                            <button @click="editDevice({{ json_encode($device) }})"
                                                class="inline-flex items-center px-3 py-1.5 bg-blue-100 hover:bg-blue-200 text-blue-700 rounded-lg transition-colors text-sm font-medium">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                                Edit
                                            </button>
                                            <form action="{{ route('vital-sign-integration.device.destroy', $device) }}"
                                                method="POST" class="inline"
                                                onsubmit="return confirm('Are you sure you want to delete this device?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="inline-flex items-center px-3 py-1.5 bg-red-100 hover:bg-red-200 text-red-700 rounded-lg transition-colors text-sm font-medium">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <!-- Docker Configuration Help -->
                        <div class="mt-6 bg-slate-900 rounded-xl p-5">
                            <div class="flex justify-between items-center mb-3">
                                <h4 class="font-bold text-white flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-cyan-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                                    </svg>
                                    MP5SC Listener Configuration
                                </h4>
                            </div>
                            <p class="text-sm text-gray-400 mb-3">The mp5sc_listener container will automatically fetch
                                active devices from the API. Make sure the following environment variables are set:</p>
                            <pre
                                class="text-sm text-gray-300 font-mono overflow-x-auto"><span class="text-cyan-400">API_BASE_URL</span>=<span class="text-green-400">"http://{{ $gatewayConfig['server_ip'] }}:{{ $gatewayConfig['server_port'] }}/api/v1"</span>
                                <span class="text-cyan-400">API_PASSPHRASE</span>=<span class="text-green-400">"{{ $gatewayConfig['passphrase'] }}"</span>
                                <span class="text-cyan-400">API_USERNAME</span>=<span class="text-green-400">"your_api_user"</span>
                                <span class="text-cyan-400">API_PASSWORD</span>=<span class="text-green-400">"your_password"</span></pre>
                            <p class="text-xs text-gray-500 mt-3">Devices configured here will be fetched via <code
                                    class="text-cyan-300">GET /api/v1/monitor-devices</code></p>
                        </div>
                    @else
                        <div class="text-center py-12">
                            <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                            </svg>
                            <h4 class="text-lg font-medium text-gray-600 mb-2">No Monitor Devices</h4>
                            <p class="text-gray-500 mb-4">Add Philips IntelliVue MP5SC monitors to collect vital signs
                                automatically.</p>
                            <button @click="showAddDeviceModal = true"
                                class="inline-flex items-center px-4 py-2 bg-cyan-600 text-white font-medium rounded-lg hover:bg-cyan-700 transition-colors">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4" />
                                </svg>
                                Add Monitor Device
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            <!-- API Testing Section -->
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-purple-100">
                <div class="p-6 border-b border-purple-100 bg-gradient-to-r from-purple-50 to-indigo-50">
                    <div class="flex items-center">
                        <div class="p-3 bg-purple-600 rounded-xl mr-4">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800">API Testing Console</h3>
                            <p class="text-sm text-gray-500">Test the API endpoints directly from this page</p>
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Login Test -->
                        <div class="border border-gray-200 rounded-xl p-5 bg-gray-50">
                            <h4 class="font-bold text-gray-800 mb-4 flex items-center">
                                <span
                                    class="w-6 h-6 bg-amber-500 text-white text-xs font-bold rounded-full flex items-center justify-center mr-2">1</span>
                                Login & Get Token
                            </h4>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                                    <input type="text" x-model="testLogin.username"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500"
                                        placeholder="Enter API username">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                                    <input type="password" x-model="testLogin.password"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500"
                                        placeholder="Enter API password">
                                </div>
                                <button @click="performLogin()" :disabled="loginLoading"
                                    class="w-full inline-flex items-center justify-center px-4 py-2 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all disabled:opacity-50">
                                    <svg x-show="!loginLoading" class="w-5 h-5 mr-2" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                                    </svg>
                                    <svg x-show="loginLoading" class="w-5 h-5 mr-2 animate-spin" fill="none"
                                        viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                        </path>
                                    </svg>
                                    Get Bearer Token
                                </button>
                                <template x-if="bearerToken">
                                    <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                                        <p class="text-xs font-semibold text-green-700 mb-1">Bearer Token (valid for 24
                                            hours):</p>
                                        <div class="flex items-center">
                                            <code
                                                class="text-xs text-green-800 bg-green-100 px-2 py-1 rounded flex-1 overflow-x-auto"
                                                x-text="bearerToken"></code>
                                            <button @click="copyToken()" class="ml-2 p-1 hover:bg-green-200 rounded">
                                                <svg class="w-4 h-4 text-green-700" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Send Vital Sign Test -->
                        <div class="border border-gray-200 rounded-xl p-5 bg-gray-50">
                            <h4 class="font-bold text-gray-800 mb-4 flex items-center">
                                <span
                                    class="w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded-full flex items-center justify-center mr-2">2</span>
                                Send Vital Sign Reading
                            </h4>
                            <div class="space-y-3">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Patient MRN</label>
                                        <input type="text" x-model="testVital.patient_mrn"
                                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500"
                                            placeholder="e.g., MRN001">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Patient RN</label>
                                        <input type="text" x-model="testVital.patient_rn"
                                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500"
                                            placeholder="e.g., RN001">
                                    </div>
                                </div>
                                <p class="text-xs text-gray-500 text-center">Use either MRN or RN to identify the
                                    patient</p>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Systolic BP</label>
                                        <input type="number" x-model="testVital.systolic_bp"
                                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500"
                                            placeholder="120">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Diastolic BP</label>
                                        <input type="number" x-model="testVital.diastolic_bp"
                                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500"
                                            placeholder="80">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Pulse Rate</label>
                                        <input type="number" x-model="testVital.pulse_rate"
                                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500"
                                            placeholder="72">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Temperature</label>
                                        <input type="number" step="0.1" x-model="testVital.temperature"
                                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500"
                                            placeholder="36.5">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">SpO2 (%)</label>
                                        <input type="number" x-model="testVital.spo2"
                                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500"
                                            placeholder="98">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Respiratory
                                            Rate</label>
                                        <input type="number" x-model="testVital.respiratory_rate"
                                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500"
                                            placeholder="16">
                                    </div>
                                </div>
                                <button @click="sendVitalSign()" :disabled="!bearerToken || vitalLoading"
                                    class="w-full inline-flex items-center justify-center px-4 py-2 bg-gradient-to-r from-blue-500 to-indigo-500 hover:from-blue-600 hover:to-indigo-600 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                                    <svg x-show="!vitalLoading" class="w-5 h-5 mr-2" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                    </svg>
                                    <svg x-show="vitalLoading" class="w-5 h-5 mr-2 animate-spin" fill="none"
                                        viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                        </path>
                                    </svg>
                                    Send Vital Sign
                                </button>
                                <p x-show="!bearerToken" class="text-xs text-amber-600 text-center">⚠️ Login first to
                                    get a bearer token</p>
                            </div>
                        </div>
                    </div>

                    <!-- Response Output -->
                    <div class="mt-6">
                        <h4 class="font-bold text-gray-800 mb-3">API Response</h4>
                        <div class="bg-slate-900 rounded-xl p-4 min-h-[150px]">
                            <pre class="text-sm text-green-400 font-mono whitespace-pre-wrap"
                                x-text="apiResponse || 'Response will appear here...'"></pre>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent API Logs Section -->
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-blue-100">
                <div class="p-6 border-b border-blue-100 bg-gradient-to-r from-blue-50 to-cyan-50">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center">
                            <div class="p-3 bg-blue-600 rounded-xl mr-4">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800">Recent API Logs</h3>
                                <p class="text-sm text-gray-500">Last 20 API requests received</p>
                            </div>
                        </div>
                        <form action="{{ route('vital-sign-integration.logs.clear') }}" method="POST"
                            onsubmit="return confirm('Are you sure you want to clear all API logs?');">
                            @csrf
                            <button type="submit"
                                class="inline-flex items-center px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors text-sm font-medium">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                Clear Logs
                            </button>
                        </form>
                    </div>
                </div>

                <div class="p-6">
                    @if($recentLogs->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Time</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">User</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Endpoint
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Status
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Response
                                            Time</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">IP</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    @foreach($recentLogs as $log)
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-4 py-3 text-sm text-gray-600">
                                                {{ $log->created_at->format('M d, H:i:s') }}
                                            </td>
                                            <td class="px-4 py-3 text-sm font-medium text-gray-800">
                                                {{ $log->apiUser->name ?? 'Unknown' }}
                                            </td>
                                            <td class="px-4 py-3">
                                                <span
                                                    class="text-xs font-medium px-2 py-0.5 rounded {{ $log->method === 'POST' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700' }}">{{ $log->method }}</span>
                                                <code class="ml-1 text-xs text-gray-600">{{ $log->endpoint }}</code>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span
                                                    class="text-xs font-medium px-2 py-0.5 rounded {{ $log->status_code >= 200 && $log->status_code < 300 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                                    {{ $log->status_code }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-600">{{ $log->response_time_ms }}ms</td>
                                            <td class="px-4 py-3 text-sm text-gray-500">{{ $log->ip_address }}</td>
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
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <p class="text-gray-500">No API logs yet.</p>
                            <p class="text-sm text-gray-400 mt-1">Logs will appear here when API requests are made.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Add/Edit User Modal -->
        <div x-show="showAddUserModal || showEditUserModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto"
            x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeModals()"></div>

                <div
                    class="relative inline-block w-full max-w-lg p-6 my-8 text-left align-middle transition-all transform bg-white shadow-xl rounded-2xl">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-bold text-gray-800"
                            x-text="showEditUserModal ? 'Edit API User' : 'Add API User'"></h3>
                        <button @click="closeModals()" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form
                        :action="showEditUserModal ? '{{ url('vital-sign-integration/api-user') }}/' + editingUser.id : '{{ route('vital-sign-integration.api-user.store') }}'"
                        method="POST">
                        @csrf
                        <template x-if="showEditUserModal">
                            <input type="hidden" name="_method" value="PUT">
                        </template>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                                <input type="text" name="name" x-model="userFormData.name" required
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                    placeholder="e.g., Gateway Device 1">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Username *</label>
                                <input type="text" name="username" x-model="userFormData.username" required
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                    placeholder="e.g., gateway_user_1">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Password <span x-show="!showEditUserModal">*</span>
                                    <span x-show="showEditUserModal" class="text-gray-400 font-normal">(leave empty to
                                        keep current)</span>
                                </label>
                                <input type="password" name="password" x-model="userFormData.password"
                                    :required="!showEditUserModal"
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                    placeholder="Minimum 8 characters">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                <textarea name="description" x-model="userFormData.description" rows="2"
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                    placeholder="Optional description for this API user"></textarea>
                            </div>

                            <div x-show="showEditUserModal">
                                <label class="flex items-center">
                                    <input type="checkbox" name="is_active" x-model="userFormData.is_active"
                                        class="rounded border-gray-300 text-emerald-600 shadow-sm focus:ring-emerald-500">
                                    <span class="ml-2 text-sm text-gray-700">Active</span>
                                </label>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end space-x-3">
                            <button type="button" @click="closeModals()"
                                class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg transition-colors">
                                Cancel
                            </button>
                            <button type="submit"
                                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-medium rounded-lg transition-colors">
                                <span x-text="showEditUserModal ? 'Update User' : 'Create User'"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Add/Edit Device Modal -->
        <div x-show="showAddDeviceModal || showEditDeviceModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto"
            x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeDeviceModals()">
                </div>

                <div
                    class="relative inline-block w-full max-w-lg p-6 my-8 text-left align-middle transition-all transform bg-white shadow-xl rounded-2xl">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-bold text-gray-800"
                            x-text="showEditDeviceModal ? 'Edit Monitor Device' : 'Add Monitor Device'"></h3>
                        <button @click="closeDeviceModals()" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form
                        :action="showEditDeviceModal ? '{{ url('vital-sign-integration/device') }}/' + editingDevice.id : '{{ route('vital-sign-integration.device.store') }}'"
                        method="POST">
                        @csrf
                        <template x-if="showEditDeviceModal">
                            <input type="hidden" name="_method" value="PUT">
                        </template>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Device Name *</label>
                                <input type="text" name="name" x-model="deviceFormData.name" required
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-cyan-500 focus:ring-cyan-500"
                                    placeholder="e.g., MP5SC Ward A">
                            </div>

                            <div class="grid grid-cols-3 gap-4">
                                <div class="col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">IP Address *</label>
                                    <input type="text" name="ip_address" x-model="deviceFormData.ip_address" required
                                        pattern="^((25[0-5]|(2[0-4]|1\d|[1-9]|)\d)\.?\b){4}$"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-cyan-500 focus:ring-cyan-500"
                                        placeholder="192.168.0.5">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Port</label>
                                    <input type="number" name="port" x-model="deviceFormData.port"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-cyan-500 focus:ring-cyan-500"
                                        placeholder="24105">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                                <input type="text" name="location" x-model="deviceFormData.location"
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-cyan-500 focus:ring-cyan-500"
                                    placeholder="e.g., Ward A - Bed 1">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                <textarea name="description" x-model="deviceFormData.description" rows="2"
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-cyan-500 focus:ring-cyan-500"
                                    placeholder="Optional description for this device"></textarea>
                            </div>

                            <div x-show="showEditDeviceModal">
                                <label class="flex items-center">
                                    <input type="checkbox" name="is_active" x-model="deviceFormData.is_active"
                                        class="rounded border-gray-300 text-cyan-600 shadow-sm focus:ring-cyan-500">
                                    <span class="ml-2 text-sm text-gray-700">Active</span>
                                </label>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end space-x-3">
                            <button type="button" @click="closeDeviceModals()"
                                class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg transition-colors">
                                Cancel
                            </button>
                            <button type="submit"
                                class="px-4 py-2 bg-cyan-600 hover:bg-cyan-700 text-white font-medium rounded-lg transition-colors">
                                <span x-text="showEditDeviceModal ? 'Update Device' : 'Add Device'"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Toast Notification -->
        <div x-show="toast.show" x-cloak x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform translate-y-2"
            x-transition:enter-end="opacity-100 transform translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 transform translate-y-0"
            x-transition:leave-end="opacity-0 transform translate-y-2" class="fixed bottom-4 right-4 z-50">
            <div :class="toast.success ? 'bg-green-500' : 'bg-red-500'"
                class="text-white px-6 py-4 rounded-lg shadow-lg max-w-md">
                <div class="flex items-center">
                    <svg x-show="toast.success" class="w-6 h-6 mr-3" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <svg x-show="!toast.success" class="w-6 h-6 mr-3" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span x-text="toast.message" class="font-medium"></span>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Global helper functions
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                // Show a brief toast notification
                const toast = document.createElement('div');
                toast.className = 'fixed bottom-4 right-4 z-50 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg';
                toast.textContent = 'Copied to clipboard!';
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 2000);
            });
        }

        function copyGatewayConfig() {
            const configText = `# Laravel API Configuration
API_BASE_URL = "http://{{ $gatewayConfig['server_ip'] }}:{{ $gatewayConfig['server_port'] }}/api/v1"

# API Passphrase (must match Laravel configuration)
API_PASSPHRASE = "{{ $gatewayConfig['passphrase'] }}"

# Device Credentials (create an API User in Laravel)
DEVICE_USERNAME = "your_api_user@example.com"
DEVICE_PASSWORD = "your_password"`;
            copyToClipboard(configText);
        }

        function vitalSignIntegration() {
            return {
                // User modal state
                showAddUserModal: false,
                showEditUserModal: false,
                showPassphrase: false,
                editingUser: {},
                userFormData: {
                    name: '',
                    username: '',
                    password: '',
                    description: '',
                    is_active: true
                },
                // Device modal state
                showAddDeviceModal: false,
                showEditDeviceModal: false,
                editingDevice: {},
                deviceFormData: {
                    name: '',
                    ip_address: '',
                    port: '24105',
                    location: '',
                    description: '',
                    is_active: true
                },
                // Testing state
                testLogin: {
                    username: '',
                    password: ''
                },
                testVital: {
                    patient_mrn: '',
                    patient_rn: '',
                    systolic_bp: '',
                    diastolic_bp: '',
                    pulse_rate: '',
                    temperature: '',
                    spo2: '',
                    respiratory_rate: ''
                },
                bearerToken: '',
                apiResponse: '',
                loginLoading: false,
                vitalLoading: false,
                toast: {
                    show: false,
                    success: false,
                    message: ''
                },

                // User modal methods
                closeModals() {
                    this.showAddUserModal = false;
                    this.showEditUserModal = false;
                    this.resetUserForm();
                },

                resetUserForm() {
                    this.userFormData = {
                        name: '',
                        username: '',
                        password: '',
                        description: '',
                        is_active: true
                    };
                    this.editingUser = {};
                },

                editUser(user) {
                    this.editingUser = user;
                    this.userFormData = {
                        name: user.name,
                        username: user.username,
                        password: '',
                        description: user.description || '',
                        is_active: user.is_active
                    };
                    this.showEditUserModal = true;
                },

                // Device modal methods
                closeDeviceModals() {
                    this.showAddDeviceModal = false;
                    this.showEditDeviceModal = false;
                    this.resetDeviceForm();
                },

                resetDeviceForm() {
                    this.deviceFormData = {
                        name: '',
                        ip_address: '',
                        port: '24105',
                        location: '',
                        description: '',
                        is_active: true
                    };
                    this.editingDevice = {};
                },

                editDevice(device) {
                    this.editingDevice = device;
                    this.deviceFormData = {
                        name: device.name,
                        ip_address: device.ip_address,
                        port: device.port || '24105',
                        location: device.location || '',
                        description: device.description || '',
                        is_active: device.is_active
                    };
                    this.showEditDeviceModal = true;
                },

                async performLogin() {
                    if (!this.testLogin.username || !this.testLogin.password) {
                        this.showToast(false, 'Please enter username and password');
                        return;
                    }

                    this.loginLoading = true;
                    this.apiResponse = 'Sending login request...';

                    try {
                        const response = await fetch('{{ url('/api/vital-sign/login') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                username: this.testLogin.username,
                                password: this.testLogin.password
                            })
                        });

                        const data = await response.json();
                        this.apiResponse = JSON.stringify(data, null, 2);

                        if (data.success && data.data && data.data.token) {
                            this.bearerToken = data.data.token;
                            this.showToast(true, 'Login successful! Token received.');
                        } else {
                            this.showToast(false, data.message || 'Login failed');
                        }
                    } catch (error) {
                        this.apiResponse = 'Error: ' + error.message;
                        this.showToast(false, 'Connection error: ' + error.message);
                    } finally {
                        this.loginLoading = false;
                    }
                },

                async sendVitalSign() {
                    if (!this.bearerToken) {
                        this.showToast(false, 'Please login first to get a bearer token');
                        return;
                    }

                    if (!this.testVital.patient_mrn && !this.testVital.patient_rn) {
                        this.showToast(false, 'Patient MRN or RN is required');
                        return;
                    }

                    this.vitalLoading = true;
                    this.apiResponse = 'Sending vital sign reading...';

                    try {
                        const payload = {};

                        if (this.testVital.patient_mrn) payload.patient_mrn = this.testVital.patient_mrn;
                        if (this.testVital.patient_rn) payload.patient_rn = this.testVital.patient_rn;

                        // Only include non-empty values
                        if (this.testVital.systolic_bp) payload.systolic_bp = parseInt(this.testVital.systolic_bp);
                        if (this.testVital.diastolic_bp) payload.diastolic_bp = parseInt(this.testVital.diastolic_bp);
                        if (this.testVital.pulse_rate) payload.pulse_rate = parseInt(this.testVital.pulse_rate);
                        if (this.testVital.temperature) payload.temperature = parseFloat(this.testVital.temperature);
                        if (this.testVital.spo2) payload.spo2 = parseInt(this.testVital.spo2);
                        if (this.testVital.respiratory_rate) payload.respiratory_rate = parseInt(this.testVital.respiratory_rate);

                        const response = await fetch('{{ url('/api/vital-sign/reading') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'Authorization': 'Bearer ' + this.bearerToken
                            },
                            body: JSON.stringify(payload)
                        });

                        const data = await response.json();
                        this.apiResponse = JSON.stringify(data, null, 2);

                        if (data.success) {
                            this.showToast(true, 'Vital sign recorded successfully!');
                        } else {
                            this.showToast(false, data.message || 'Failed to record vital sign');
                        }
                    } catch (error) {
                        this.apiResponse = 'Error: ' + error.message;
                        this.showToast(false, 'Connection error: ' + error.message);
                    } finally {
                        this.vitalLoading = false;
                    }
                },

                copyToken() {
                    navigator.clipboard.writeText(this.bearerToken);
                    this.showToast(true, 'Token copied to clipboard!');
                },

                showToast(success, message) {
                    this.toast = { show: true, success, message };
                    setTimeout(() => {
                        this.toast.show = false;
                    }, 4000);
                }
            };
        }
    </script>
</x-app-layout>