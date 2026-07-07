{{-- API Documentation modal (toggled from the page header button). --}}
<div id="apiInfoModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity"
            onclick="document.getElementById('apiInfoModal').classList.add('hidden')"></div>

        <div class="relative inline-block w-full max-w-5xl p-8 my-8 text-left align-middle transition-all transform bg-gradient-to-br from-slate-900 to-slate-800 shadow-2xl rounded-3xl border border-slate-700">
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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
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
                        <div class="bg-gradient-to-r from-orange-900/30 to-amber-900/30 rounded-xl p-4 border border-orange-700">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-lg font-bold text-white">API V1 - Raspberry Pi / HL7 Gateway</h4>
                                <span class="px-2 py-1 bg-orange-600 text-white text-xs font-bold rounded">RECOMMENDED</span>
                            </div>
                            <p class="text-sm text-gray-400 mb-3">Per-request authentication with passphrase header. No login step required.</p>
                            <div class="flex items-center">
                                <span class="text-xs text-gray-400 mr-2">Base URL:</span>
                                <code class="text-orange-300 bg-slate-800 px-3 py-1.5 rounded-lg text-sm">{{ url('/api/v1') }}</code>
                            </div>
                        </div>

                        <!-- V1 Authentication -->
                        <div class="bg-slate-700/50 rounded-xl p-4 border border-slate-600">
                            <h4 class="text-lg font-bold text-white mb-3 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                <svg class="w-5 h-5 mr-2 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                <svg class="w-5 h-5 mr-2 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                <pre class="text-sm text-gray-300 font-mono">X-Passphrase: {{ $gatewayConfig['passphrase'] }}</pre>
                            </div>
                            <div class="bg-slate-800/70 rounded-lg p-3">
                                <p class="text-xs font-semibold text-gray-400 mb-2">QUERY PARAMETERS</p>
                                <pre class="text-sm text-gray-300 font-mono">?username=gateway_user&password=your_password</pre>
                            </div>
                        </div>

                        <!-- V1 Parameters -->
                        <div class="bg-slate-700/50 rounded-xl p-4 border border-slate-600">
                            <h4 class="text-lg font-bold text-white mb-3 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                        <div class="bg-gradient-to-r from-emerald-900/30 to-teal-900/30 rounded-xl p-4 border border-emerald-700">
                            <h4 class="text-lg font-bold text-white mb-2">API V2 - Bearer Token Authentication</h4>
                            <p class="text-sm text-gray-400 mb-3">Traditional login-first authentication flow. Useful for web applications.</p>
                            <div class="flex items-center">
                                <span class="text-xs text-gray-400 mr-2">Base URL:</span>
                                <code class="text-emerald-300 bg-slate-800 px-3 py-1.5 rounded-lg text-sm">{{ url('/api/vital-sign') }}</code>
                            </div>
                        </div>

                        <!-- Authentication -->
                        <div class="bg-slate-700/50 rounded-xl p-4 border border-slate-600">
                            <h4 class="text-lg font-bold text-white mb-3 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                <svg class="w-5 h-5 mr-2 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                <svg class="w-5 h-5 mr-2 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                <svg class="w-5 h-5 mr-2 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
