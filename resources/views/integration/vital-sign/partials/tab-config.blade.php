{{-- Configuration tab: Gateway server/auth config + config.py example. --}}
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
                <p class="text-sm text-gray-500">Configuration settings for Raspberry Pi / HL7 Gateway (API V1)</p>
            </div>
        </div>
    </div>

    <div class="p-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Server URL Info -->
            <div class="bg-gradient-to-br from-slate-50 to-gray-50 rounded-xl p-5 border border-gray-200">
                <h4 class="font-bold text-gray-800 mb-4 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                    </svg>
                    Server URL
                </h4>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">API Base URL</label>
                        <div class="flex items-center bg-white border border-gray-300 rounded-lg">
                            <code class="flex-1 px-3 py-2 text-sm text-emerald-700 font-mono">{{ $gatewayConfig['api_base_url'] }}</code>
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
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Server IP</label>
                        <div class="flex items-center bg-white border border-gray-300 rounded-lg">
                            <code class="flex-1 px-3 py-2 text-sm text-blue-700 font-mono">{{ $gatewayConfig['server_ip'] }}</code>
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
                            <code class="flex-1 px-3 py-2 text-sm text-purple-700 font-mono">{{ $gatewayConfig['server_port'] }}</code>
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
            <div class="bg-gradient-to-br from-amber-50 to-orange-50 rounded-xl p-5 border border-orange-200">
                <h4 class="font-bold text-gray-800 mb-4 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                    </svg>
                    API Authentication
                </h4>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">API Passphrase (X-Passphrase Header)</label>
                        <div class="flex items-center bg-white border border-orange-300 rounded-lg">
                            <code class="flex-1 px-3 py-2 text-sm text-orange-700 font-mono"
                                x-text="showPassphrase ? '{{ $gatewayConfig['passphrase'] }}' : '••••••••'"></code>
                            <button @click="showPassphrase = !showPassphrase"
                                class="px-3 py-2 text-gray-500 hover:text-orange-600 border-l border-orange-300">
                                <svg x-show="!showPassphrase" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg x-show="showPassphrase" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                        <p class="text-xs text-gray-500 mt-1">Set in <code class="bg-gray-200 px-1 rounded">.env</code> file as <code class="bg-gray-200 px-1 rounded">VITAL_SIGN_API_PASSPHRASE</code></p>
                    </div>
                    <div class="pt-2 border-t border-orange-200">
                        <p class="text-sm text-gray-700"><strong>Device Username/Password:</strong> Create an API User (see the API Users tab) to authenticate gateway devices.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gateway config.py Example -->
        <div class="mt-6 bg-slate-900 rounded-xl p-5">
            <div class="flex justify-between items-center mb-3">
                <h4 class="font-bold text-white flex items-center">
                    <svg class="w-5 h-5 mr-2 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
