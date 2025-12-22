<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-bold text-2xl text-gray-800 leading-tight">
                    {{ __('EKad Integration') }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">SEEKINK E-Ink device management - push patient info to cloud and
                    sync to local displays</p>
            </div>
            <button onclick="document.getElementById('ekadInfoModal').classList.remove('hidden')"
                class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-teal-500 to-cyan-600 hover:from-teal-600 hover:to-cyan-700 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                How It Works
            </button>
        </div>
    </x-slot>

    <!-- Info Modal -->
    <div id="ekadInfoModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity"
                onclick="document.getElementById('ekadInfoModal').classList.add('hidden')"></div>

            <div
                class="relative inline-block w-full max-w-3xl p-8 my-8 text-left align-middle transition-all transform bg-gradient-to-br from-slate-900 to-slate-800 shadow-2xl rounded-3xl border border-slate-700">
                <div class="flex justify-between items-center mb-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-gradient-to-br from-teal-500 to-cyan-600 rounded-xl mr-4">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-white">SEEKINK E-Ink Integration</h3>
                    </div>
                    <button onclick="document.getElementById('ekadInfoModal').classList.add('hidden')"
                        class="text-gray-400 hover:text-white transition-colors">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-6">
                    <!-- Flow Diagram -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                        <div class="text-center p-4 bg-slate-700/50 rounded-xl border border-slate-600">
                            <div
                                class="w-16 h-16 mx-auto mb-3 bg-teal-500/20 rounded-full flex items-center justify-center">
                                <svg class="w-8 h-8 text-teal-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                </svg>
                            </div>
                            <p class="text-sm font-semibold text-teal-300">1. Authenticate</p>
                            <p class="text-xs text-gray-400 mt-1">Login to SEEKINK Cloud</p>
                        </div>
                        <div class="text-center p-4 bg-slate-700/50 rounded-xl border border-slate-600">
                            <div
                                class="w-16 h-16 mx-auto mb-3 bg-cyan-500/20 rounded-full flex items-center justify-center">
                                <svg class="w-8 h-8 text-cyan-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                </svg>
                            </div>
                            <p class="text-sm font-semibold text-cyan-300">2. Select Label</p>
                            <p class="text-xs text-gray-400 mt-1">Choose E-Ink device by MAC</p>
                        </div>
                        <div class="text-center p-4 bg-slate-700/50 rounded-xl border border-slate-600">
                            <div
                                class="w-16 h-16 mx-auto mb-3 bg-green-500/20 rounded-full flex items-center justify-center">
                                <svg class="w-8 h-8 text-green-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                            </div>
                            <p class="text-sm font-semibold text-green-300">3. Push Data</p>
                            <p class="text-xs text-gray-400 mt-1">Send patient info to display</p>
                        </div>
                    </div>

                    <div class="bg-slate-700/30 rounded-xl p-4 border border-slate-600">
                        <h4 class="text-lg font-bold text-white mb-3">Features</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                            <div class="flex items-start">
                                <span
                                    class="w-6 h-6 bg-teal-600 text-white text-xs font-bold rounded-full flex items-center justify-center mr-2 mt-0.5">✓</span>
                                <span class="text-gray-300">Push patient name, room, and doctor info to E-Ink
                                    displays</span>
                            </div>
                            <div class="flex items-start">
                                <span
                                    class="w-6 h-6 bg-teal-600 text-white text-xs font-bold rounded-full flex items-center justify-center mr-2 mt-0.5">✓</span>
                                <span class="text-gray-300">Send emergency text messages</span>
                            </div>
                            <div class="flex items-start">
                                <span
                                    class="w-6 h-6 bg-teal-600 text-white text-xs font-bold rounded-full flex items-center justify-center mr-2 mt-0.5">✓</span>
                                <span class="text-gray-300">Query available labels/devices</span>
                            </div>
                            <div class="flex items-start">
                                <span
                                    class="w-6 h-6 bg-teal-600 text-white text-xs font-bold rounded-full flex items-center justify-center mr-2 mt-0.5">✓</span>
                                <span class="text-gray-300">Low-power E-Ink technology</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button onclick="document.getElementById('ekadInfoModal').classList.add('hidden')"
                        class="px-6 py-2.5 bg-gradient-to-r from-teal-500 to-cyan-600 hover:from-teal-600 hover:to-cyan-700 text-white font-semibold rounded-lg transition-all">
                        Got it!
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="py-8" x-data="ekadManager()">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Connection Settings -->
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-teal-100">
                <div class="p-6 border-b border-teal-100 bg-gradient-to-r from-teal-50 to-cyan-50">
                    <div class="flex items-center">
                        <div class="p-3 bg-teal-600 rounded-xl mr-4">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800">Connection Settings</h3>
                            <p class="text-sm text-gray-500">Configure SEEKINK Cloud API credentials</p>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">API Base URL</label>
                            <input type="text" x-model="baseUrl"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 font-mono text-sm"
                                placeholder="http://iot.seekink.com/cloud/prod-api">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                            <input type="text" x-model="username"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500"
                                placeholder="moe">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                            <input type="password" x-model="password"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500"
                                placeholder="••••••••">
                        </div>
                    </div>
                    <div class="mt-4 flex items-center justify-between">
                        <div x-show="token" class="flex items-center text-green-600">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-sm font-medium">Authenticated</span>
                            <span class="ml-2 text-xs text-gray-500 font-mono truncate max-w-xs"
                                x-text="'Token: ' + token.substring(0, 20) + '...'"></span>
                        </div>
                        <div x-show="!token" class="text-sm text-gray-500">Not authenticated</div>
                        <button @click="testLogin()" :disabled="loggingIn"
                            class="inline-flex items-center px-4 py-2 bg-teal-600 hover:bg-teal-700 disabled:bg-teal-400 text-white font-medium rounded-lg transition-colors">
                            <svg x-show="!loggingIn" class="w-4 h-4 mr-2" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                            </svg>
                            <svg x-show="loggingIn" class="w-4 h-4 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <span x-text="loggingIn ? 'Connecting...' : 'Test Login'"></span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Label Management -->
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-cyan-100">
                <div class="p-6 border-b border-cyan-100 bg-gradient-to-r from-cyan-50 to-blue-50">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="p-3 bg-cyan-600 rounded-xl mr-4">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800">Label Management</h3>
                                <p class="text-sm text-gray-500">Query and select available E-Ink devices</p>
                            </div>
                        </div>
                        <button @click="queryLabels()" :disabled="!token || queryingLabels"
                            class="inline-flex items-center px-4 py-2 bg-cyan-600 hover:bg-cyan-700 disabled:bg-gray-400 text-white font-medium rounded-lg transition-colors">
                            <svg x-show="!queryingLabels" class="w-4 h-4 mr-2" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <svg x-show="queryingLabels" class="w-4 h-4 mr-2 animate-spin" fill="none"
                                viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Query Labels
                        </button>
                    </div>
                </div>
                <div class="p-6">
                    <div x-show="labels.length > 0">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">MAC
                                            Address</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Name
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Status
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Action
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    <template x-for="label in labels" :key="label.mac || label.id">
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-4 py-3 font-mono text-sm" x-text="label.mac"></td>
                                            <td class="px-4 py-3 text-sm" x-text="label.name || '-'"></td>
                                            <td class="px-4 py-3">
                                                <span
                                                    :class="label.online ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'"
                                                    class="px-2 py-1 rounded text-xs font-medium"
                                                    x-text="label.online ? 'Online' : 'Offline'"></span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <button @click="selectedMac = label.mac"
                                                    class="text-cyan-600 hover:text-cyan-800 text-sm font-medium">
                                                    Select
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div x-show="labels.length === 0" class="text-center py-8">
                        <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                        </svg>
                        <p class="text-gray-500">No labels found. Click "Query Labels" to fetch available devices.</p>
                        <p class="text-sm text-gray-400 mt-1">You can also enter a MAC address manually below.</p>
                    </div>
                </div>
            </div>

            <!-- Push Patient Info -->
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-green-100">
                <div class="p-6 border-b border-green-100 bg-gradient-to-r from-green-50 to-emerald-50">
                    <div class="flex items-center">
                        <div class="p-3 bg-green-600 rounded-xl mr-4">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800">Push Patient Info</h3>
                            <p class="text-sm text-gray-500">Send patient information to E-Ink display via template</p>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">MAC Address *</label>
                            <input type="text" x-model="selectedMac"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 font-mono text-sm"
                                placeholder="D4:3D:39:3C:C0:2C">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Template ID *</label>
                            <input type="text" x-model="templateId"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 font-mono text-sm"
                                placeholder="Enter template ID">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Patient Name *</label>
                            <input type="text" x-model="patientName"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                                placeholder="Anti Gravity">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Room Number</label>
                            <input type="text" x-model="roomNumber"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                                placeholder="Room 402">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Doctor</label>
                            <input type="text" x-model="doctor"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                                placeholder="Dr. Smith">
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button @click="pushPatientInfo()"
                            :disabled="!token || !selectedMac || !templateId || !patientName || pushingInfo"
                            class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600 disabled:from-gray-400 disabled:to-gray-500 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transition-all">
                            <svg x-show="!pushingInfo" class="w-5 h-5 mr-2" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                            <svg x-show="pushingInfo" class="w-5 h-5 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <span x-text="pushingInfo ? 'Pushing...' : 'Push Patient Info'"></span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Send Text Message -->
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-orange-100">
                <div class="p-6 border-b border-orange-100 bg-gradient-to-r from-orange-50 to-amber-50">
                    <div class="flex items-center">
                        <div class="p-3 bg-orange-600 rounded-xl mr-4">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800">Emergency Text Message</h3>
                            <p class="text-sm text-gray-500">Send urgent text message to E-Ink display</p>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">MAC Address *</label>
                            <input type="text" x-model="msgMac"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500 font-mono text-sm"
                                placeholder="D4:3D:39:3C:C0:2C">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                            <select x-model="msgPriority"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                                <option value="1">1 - High</option>
                                <option value="2">2 - Medium</option>
                                <option value="3">3 - Normal</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                            <input type="text" x-model="msgTitle"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500"
                                placeholder="Alert">
                        </div>
                        <div class="lg:col-span-1">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Content *</label>
                            <input type="text" x-model="msgContent"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500"
                                placeholder="Patient needs assistance">
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button @click="sendTextMessage()"
                            :disabled="!token || !msgMac || !msgTitle || !msgContent || sendingMessage"
                            class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 disabled:from-gray-400 disabled:to-gray-500 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transition-all">
                            <svg x-show="!sendingMessage" class="w-5 h-5 mr-2" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                            </svg>
                            <svg x-show="sendingMessage" class="w-5 h-5 mr-2 animate-spin" fill="none"
                                viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <span x-text="sendingMessage ? 'Sending...' : 'Send Message'"></span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Response Log -->
            <div x-show="responseLog.length > 0" x-transition
                class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-gray-200">
                <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-slate-50">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="p-3 bg-gray-600 rounded-xl mr-4">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800">Response Log</h3>
                                <p class="text-sm text-gray-500">API responses and debug information</p>
                            </div>
                        </div>
                        <button @click="responseLog = []" class="text-sm text-gray-500 hover:text-gray-700">Clear
                            Log</button>
                    </div>
                </div>
                <div class="p-6 max-h-96 overflow-y-auto">
                    <div class="space-y-3">
                        <template x-for="(log, index) in responseLog" :key="index">
                            <div :class="log.success ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'"
                                class="border rounded-lg p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-medium text-gray-500" x-text="log.time"></span>
                                    <span :class="log.success ? 'text-green-600' : 'text-red-600'"
                                        class="text-xs font-bold" x-text="log.action"></span>
                                </div>
                                <p :class="log.success ? 'text-green-700' : 'text-red-700'" class="text-sm font-medium"
                                    x-text="log.message"></p>
                                <pre x-show="log.data"
                                    class="mt-2 text-xs text-gray-600 bg-white/50 rounded p-2 overflow-x-auto"
                                    x-text="JSON.stringify(log.data, null, 2)"></pre>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function ekadManager() {
            return {
                // Connection
                baseUrl: @json($config['base_url']),
                username: @json($config['username']),
                password: @json($config['password']),
                token: '',
                loggingIn: false,

                // Labels
                labels: [],
                queryingLabels: false,

                // Push Patient Info
                selectedMac: @json($config['test_mac']),
                templateId: '',
                patientName: '',
                roomNumber: '',
                doctor: '',
                pushingInfo: false,

                // Text Message
                msgMac: @json($config['test_mac']),
                msgPriority: '1',
                msgTitle: 'Alert',
                msgContent: '',
                sendingMessage: false,

                // Response Log
                responseLog: [],

                addLog(action, success, message, data = null) {
                    const now = new Date();
                    this.responseLog.unshift({
                        time: now.toLocaleTimeString(),
                        action: action,
                        success: success,
                        message: message,
                        data: data
                    });
                    // Keep only last 20 logs
                    if (this.responseLog.length > 20) {
                        this.responseLog.pop();
                    }
                },

                async testLogin() {
                    this.loggingIn = true;
                    try {
                        const response = await fetch('{{ route("ekad.login") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                username: this.username,
                                password: this.password,
                                base_url: this.baseUrl
                            })
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.token = data.token;
                            this.addLog('LOGIN', true, data.message);
                        } else {
                            this.token = '';
                            this.addLog('LOGIN', false, data.message, data);
                        }
                    } catch (error) {
                        this.addLog('LOGIN', false, 'Request failed: ' + error.message);
                    }
                    this.loggingIn = false;
                },

                async queryLabels() {
                    this.queryingLabels = true;
                    try {
                        const response = await fetch('{{ route("ekad.labels") }}?token=' + encodeURIComponent(this.token) + '&base_url=' + encodeURIComponent(this.baseUrl), {
                            method: 'GET',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            }
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.labels = data.data || [];
                            this.addLog('QUERY LABELS', true, data.message + ' (' + this.labels.length + ' found)');
                        } else {
                            this.addLog('QUERY LABELS', false, data.message, data);
                        }
                    } catch (error) {
                        this.addLog('QUERY LABELS', false, 'Request failed: ' + error.message);
                    }
                    this.queryingLabels = false;
                },

                async pushPatientInfo() {
                    this.pushingInfo = true;
                    try {
                        const response = await fetch('{{ route("ekad.push") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                token: this.token,
                                base_url: this.baseUrl,
                                template_id: this.templateId,
                                mac_list: this.selectedMac,
                                patient_name: this.patientName,
                                room_number: this.roomNumber,
                                doctor: this.doctor
                            })
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.addLog('PUSH INFO', true, data.message, data.data);
                        } else {
                            this.addLog('PUSH INFO', false, data.message, data);
                        }
                    } catch (error) {
                        this.addLog('PUSH INFO', false, 'Request failed: ' + error.message);
                    }
                    this.pushingInfo = false;
                },

                async sendTextMessage() {
                    this.sendingMessage = true;
                    try {
                        const response = await fetch('{{ route("ekad.text-message") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                token: this.token,
                                base_url: this.baseUrl,
                                mac_list: [this.msgMac],
                                priority: parseInt(this.msgPriority),
                                title: this.msgTitle,
                                content: this.msgContent
                            })
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.addLog('TEXT MESSAGE', true, data.message, data.data);
                        } else {
                            this.addLog('TEXT MESSAGE', false, data.message, data);
                        }
                    } catch (error) {
                        this.addLog('TEXT MESSAGE', false, 'Request failed: ' + error.message);
                    }
                    this.sendingMessage = false;
                }
            }
        }
    </script>
</x-app-layout>