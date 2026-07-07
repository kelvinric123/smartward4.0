{{-- API Testing tab: exercise the login + reading endpoints from the browser. --}}
<div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-purple-100">
    <div class="p-6 border-b border-purple-100 bg-gradient-to-r from-purple-50 to-indigo-50">
        <div class="flex items-center">
            <div class="p-3 bg-purple-600 rounded-xl mr-4">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
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
                    <span class="w-6 h-6 bg-amber-500 text-white text-xs font-bold rounded-full flex items-center justify-center mr-2">1</span>
                    Login &amp; Get Token
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
                        <svg x-show="!loginLoading" class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                        </svg>
                        <svg x-show="loginLoading" class="w-5 h-5 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Get Bearer Token
                    </button>
                    <template x-if="bearerToken">
                        <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                            <p class="text-xs font-semibold text-green-700 mb-1">Bearer Token (valid for 24 hours):</p>
                            <div class="flex items-center">
                                <code class="text-xs text-green-800 bg-green-100 px-2 py-1 rounded flex-1 overflow-x-auto" x-text="bearerToken"></code>
                                <button @click="copyToken()" class="ml-2 p-1 hover:bg-green-200 rounded">
                                    <svg class="w-4 h-4 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
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
                    <span class="w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded-full flex items-center justify-center mr-2">2</span>
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
                    <p class="text-xs text-gray-500 text-center">Use either MRN or RN to identify the patient</p>
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
                            <label class="block text-sm font-medium text-gray-700 mb-1">Respiratory Rate</label>
                            <input type="number" x-model="testVital.respiratory_rate"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500"
                                placeholder="16">
                        </div>
                    </div>
                    <button @click="sendVitalSign()" :disabled="!bearerToken || vitalLoading"
                        class="w-full inline-flex items-center justify-center px-4 py-2 bg-gradient-to-r from-blue-500 to-indigo-500 hover:from-blue-600 hover:to-indigo-600 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg x-show="!vitalLoading" class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                        <svg x-show="vitalLoading" class="w-5 h-5 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Send Vital Sign
                    </button>
                    <p x-show="!bearerToken" class="text-xs text-amber-600 text-center">⚠️ Login first to get a bearer token</p>
                </div>
            </div>
        </div>

        <!-- Response Output -->
        <div class="mt-6">
            <h4 class="font-bold text-gray-800 mb-3">API Response</h4>
            <div class="bg-slate-900 rounded-xl p-4 min-h-[150px]">
                <pre class="text-sm text-green-400 font-mono whitespace-pre-wrap" x-text="apiResponse || 'Response will appear here...'"></pre>
            </div>
        </div>
    </div>
</div>
