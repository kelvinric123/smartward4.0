<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-bold text-2xl text-gray-800 leading-tight">
                    {{ __('Infusion Integration') }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">Receive infusion pump data via HL7/RESTful API from pump gateways</p>
            </div>
            <button onclick="document.getElementById('apiInfoModal').classList.remove('hidden')" 
                    class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                API Documentation
            </button>
        </div>
    </x-slot>

    <!-- API Info Modal -->
    <div id="apiInfoModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity" onclick="document.getElementById('apiInfoModal').classList.add('hidden')"></div>

            <div class="relative inline-block w-full max-w-5xl p-8 my-8 text-left align-middle transition-all transform bg-gradient-to-br from-slate-900 to-slate-800 shadow-2xl rounded-3xl border border-slate-700">
                <div class="flex justify-between items-center mb-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl mr-4">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-white">Infusion API Documentation</h3>
                    </div>
                    <button onclick="document.getElementById('apiInfoModal').classList.add('hidden')" class="text-gray-400 hover:text-white transition-colors">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="space-y-6 max-h-[70vh] overflow-y-auto pr-2">
                    <!-- Base URL -->
                    <div class="bg-slate-700/50 rounded-xl p-4 border border-slate-600">
                        <h4 class="text-lg font-bold text-white mb-2">Base URL</h4>
                        <code class="text-indigo-300 bg-slate-800 px-3 py-1.5 rounded-lg text-sm">{{ url('/api/infusion') }}</code>
                    </div>

                    <!-- Authentication -->
                    <div class="bg-slate-700/50 rounded-xl p-4 border border-slate-600">
                        <h4 class="text-lg font-bold text-white mb-3 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
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
    "username": "pump_gateway",
    "password": "your_password"
}</pre>
                        </div>
                    </div>

                    <!-- Single Status Update -->
                    <div class="bg-slate-700/50 rounded-xl p-4 border border-slate-600">
                        <h4 class="text-lg font-bold text-white mb-3 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                            </svg>
                            2. Submit Infusion Status (HL7 ORU)
                        </h4>
                        <div class="grid grid-cols-2 gap-4 mb-3">
                            <div>
                                <span class="text-xs font-semibold text-gray-400 uppercase">Method</span>
                                <p class="text-green-400 font-mono">POST</p>
                            </div>
                            <div>
                                <span class="text-xs font-semibold text-gray-400 uppercase">Endpoint</span>
                                <p class="text-cyan-300 font-mono">/status</p>
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
    "device_id": "PUMP-001",
    "medication_name": "Normal Saline 0.9%",
    "medication_code": "NS09",
    "total_volume": 500,
    "infused_volume": 250,
    "remaining_volume": 250,
    "flow_rate": 100,
    "remaining_minutes": 150,
    "status": "running",
    "alarm_type": null,
    "alarm_message": null,
    "timestamp": "2024-12-03T10:30:00Z"
}</pre>
                        </div>
                    </div>

                    <!-- Status Values -->
                    <div class="bg-slate-700/50 rounded-xl p-4 border border-slate-600">
                        <h4 class="text-lg font-bold text-white mb-3 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            Status Values
                        </h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-sm">
                            <div class="bg-gray-700/50 rounded px-3 py-2">
                                <span class="inline-block w-2 h-2 rounded-full bg-gray-400 mr-2"></span>
                                <code class="text-gray-300">pending</code>
                            </div>
                            <div class="bg-green-700/50 rounded px-3 py-2">
                                <span class="inline-block w-2 h-2 rounded-full bg-green-400 mr-2"></span>
                                <code class="text-green-300">running</code>
                            </div>
                            <div class="bg-yellow-700/50 rounded px-3 py-2">
                                <span class="inline-block w-2 h-2 rounded-full bg-yellow-400 mr-2"></span>
                                <code class="text-yellow-300">paused</code>
                            </div>
                            <div class="bg-blue-700/50 rounded px-3 py-2">
                                <span class="inline-block w-2 h-2 rounded-full bg-blue-400 mr-2"></span>
                                <code class="text-blue-300">completed</code>
                            </div>
                            <div class="bg-gray-700/50 rounded px-3 py-2">
                                <span class="inline-block w-2 h-2 rounded-full bg-gray-500 mr-2"></span>
                                <code class="text-gray-300">stopped</code>
                            </div>
                            <div class="bg-red-700/50 rounded px-3 py-2">
                                <span class="inline-block w-2 h-2 rounded-full bg-red-400 mr-2"></span>
                                <code class="text-red-300">alarming</code>
                            </div>
                        </div>
                    </div>

                    <!-- Alarm Types -->
                    <div class="bg-slate-700/50 rounded-xl p-4 border border-slate-600">
                        <h4 class="text-lg font-bold text-white mb-3 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            Common Alarm Types
                        </h4>
                        <div class="grid grid-cols-2 gap-2 text-sm text-gray-300">
                            <div><code class="text-red-300">occlusion</code> - Line blocked</div>
                            <div><code class="text-red-300">air_in_line</code> - Air detected</div>
                            <div><code class="text-red-300">empty</code> - Syringe/bag empty</div>
                            <div><code class="text-red-300">low_battery</code> - Low battery</div>
                            <div><code class="text-red-300">door_open</code> - Pump door open</div>
                            <div><code class="text-red-300">rate_error</code> - Flow rate issue</div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button onclick="document.getElementById('apiInfoModal').classList.add('hidden')" 
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

            <!-- Stats Overview -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div class="bg-white rounded-xl shadow-md p-4 border border-gray-100">
                    <div class="flex items-center">
                        <div class="p-2 bg-indigo-100 rounded-lg mr-3">
                            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-gray-800">{{ $stats['total_pumps'] }}</div>
                            <div class="text-xs text-gray-500">Total Pumps</div>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 border border-gray-100">
                    <div class="flex items-center">
                        <div class="p-2 bg-green-100 rounded-lg mr-3">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-gray-800">{{ $stats['active_pumps'] }}</div>
                            <div class="text-xs text-gray-500">Active Pumps</div>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 border border-gray-100">
                    <div class="flex items-center">
                        <div class="p-2 bg-blue-100 rounded-lg mr-3">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-gray-800">{{ $stats['active_infusions'] }}</div>
                            <div class="text-xs text-gray-500">Active Infusions</div>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 border border-gray-100">
                    <div class="flex items-center">
                        <div class="p-2 bg-amber-100 rounded-lg mr-3">
                            <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-amber-600">{{ $stats['warnings'] }}</div>
                            <div class="text-xs text-gray-500">Warnings</div>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 border border-gray-100">
                    <div class="flex items-center">
                        <div class="p-2 bg-red-100 rounded-lg mr-3">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-red-600">{{ $stats['alarms'] }}</div>
                            <div class="text-xs text-gray-500">Alarms</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- API Users Section -->
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-indigo-100">
                <div class="p-6 border-b border-indigo-100 bg-gradient-to-r from-indigo-50 to-purple-50">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center">
                            <div class="p-3 bg-indigo-600 rounded-xl mr-4">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800">API Users (Pump Gateways)</h3>
                                <p class="text-sm text-gray-500">Manage API credentials for infusion pump gateways</p>
                            </div>
                        </div>
                        <button @click="showAddUserModal = true" class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Add API User
                        </button>
                    </div>
                </div>

                <div class="p-6">
                    @if($apiUsers->count() > 0)
                        <div class="grid gap-4">
                            @foreach($apiUsers as $user)
                                <div class="border border-gray-200 rounded-xl p-5 hover:border-indigo-300 hover:shadow-md transition-all bg-white">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <div class="flex items-center mb-2">
                                                <h4 class="text-lg font-bold text-gray-800">{{ $user->name }}</h4>
                                                <span class="ml-2 px-2 py-0.5 text-xs font-medium {{ $user->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }} rounded-full">
                                                    {{ $user->is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                                @if($user->hasValidToken())
                                                    <span class="ml-2 px-2 py-0.5 text-xs font-medium bg-blue-100 text-blue-700 rounded-full">
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
                                                    <span class="font-medium">Requests:</span> {{ number_format($user->request_count) }}
                                                </div>
                                                <div>
                                                    <span class="font-medium">Last Login:</span> {{ $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never' }}
                                                </div>
                                            </div>
                                            @if($user->description)
                                                <p class="text-sm text-gray-500 mt-2">{{ $user->description }}</p>
                                            @endif
                                        </div>
                                        <div class="flex items-center space-x-2 ml-4">
                                            <button @click="editUser({{ json_encode($user) }})" class="inline-flex items-center px-3 py-1.5 bg-blue-100 hover:bg-blue-200 text-blue-700 rounded-lg transition-colors text-sm font-medium">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                                Edit
                                            </button>
                                            <form action="{{ route('infusion-integration.api-user.destroy', $user) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this API user?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-red-100 hover:bg-red-200 text-red-700 rounded-lg transition-colors text-sm font-medium">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
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
                            <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                            </svg>
                            <h4 class="text-lg font-medium text-gray-600 mb-2">No API Users</h4>
                            <p class="text-gray-500 mb-4">Create an API user to allow pump gateways to send infusion data.</p>
                            <button @click="showAddUserModal = true" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Add API User
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Registered Pumps Section -->
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-purple-100">
                <div class="p-6 border-b border-purple-100 bg-gradient-to-r from-purple-50 to-pink-50">
                    <div class="flex items-center">
                        <div class="p-3 bg-purple-600 rounded-xl mr-4">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800">Registered Infusion Pumps</h3>
                            <p class="text-sm text-gray-500">Pumps are auto-registered when they first send data</p>
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    @if($pumps->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Device ID</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Name</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Type</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Ward</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Status</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Last Seen</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    @foreach($pumps as $pump)
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-4 py-3 text-sm font-mono text-gray-800">{{ $pump->device_id }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-700">{{ $pump->device_name ?? '-' }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-600">{{ $pump->device_type ?? 'Unknown' }}</td>
                                            <td class="px-4 py-3 text-sm text-gray-600">{{ $pump->ward->ward_name ?? '-' }}</td>
                                            <td class="px-4 py-3">
                                                <span class="text-xs font-medium px-2 py-0.5 rounded {{ $pump->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                                    {{ $pump->is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-600">{{ $pump->last_seen_at ? $pump->last_seen_at->diffForHumans() : 'Never' }}</td>
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
                            <p class="text-sm text-gray-400 mt-1">Pumps will appear here when they send their first status update.</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Recent API Logs Section -->
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
                                <h3 class="text-lg font-bold text-gray-800">Recent API Logs</h3>
                                <p class="text-sm text-gray-500">Last 20 API requests received</p>
                            </div>
                        </div>
                        <form action="{{ route('infusion-integration.logs.clear') }}" method="POST" onsubmit="return confirm('Are you sure you want to clear all API logs?');">
                            @csrf
                            <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors text-sm font-medium">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
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
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Endpoint</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">HL7 Type</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Status</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Response Time</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    @foreach($recentLogs as $log)
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-4 py-3 text-sm text-gray-600">{{ $log->created_at->format('M d, H:i:s') }}</td>
                                            <td class="px-4 py-3 text-sm font-medium text-gray-800">{{ $log->apiUser->name ?? 'Unknown' }}</td>
                                            <td class="px-4 py-3">
                                                <span class="text-xs font-medium px-2 py-0.5 rounded {{ $log->method === 'POST' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700' }}">{{ $log->method }}</span>
                                                <code class="ml-1 text-xs text-gray-600">{{ $log->endpoint }}</code>
                                            </td>
                                            <td class="px-4 py-3">
                                                @if($log->hl7_message_type)
                                                    <span class="text-xs font-medium px-2 py-0.5 rounded bg-purple-100 text-purple-700">{{ $log->hl7_message_type }}</span>
                                                @else
                                                    <span class="text-xs text-gray-400">-</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="text-xs font-medium px-2 py-0.5 rounded {{ $log->status_code >= 200 && $log->status_code < 300 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                                    {{ $log->status_code }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-600">{{ $log->response_time_ms }}ms</td>
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
                            <p class="text-gray-500">No API logs yet.</p>
                            <p class="text-sm text-gray-400 mt-1">Logs will appear here when API requests are made.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Add/Edit User Modal -->
        <div x-show="showAddUserModal || showEditUserModal" 
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
                        <h3 class="text-xl font-bold text-gray-800" x-text="showEditUserModal ? 'Edit API User' : 'Add API User'"></h3>
                        <button @click="closeModals()" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <form :action="showEditUserModal ? '{{ url('infusion-integration/api-user') }}/' + editingUser.id : '{{ route('infusion-integration.api-user.store') }}'" method="POST">
                        @csrf
                        <template x-if="showEditUserModal">
                            <input type="hidden" name="_method" value="PUT">
                        </template>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                                <input type="text" name="name" x-model="userFormData.name" required
                                       class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                       placeholder="e.g., Pump Gateway Ward 5A">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Username *</label>
                                <input type="text" name="username" x-model="userFormData.username" required
                                       class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                       placeholder="e.g., pump_gateway_5a">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Password <span x-show="!showEditUserModal">*</span>
                                    <span x-show="showEditUserModal" class="text-gray-400 font-normal">(leave empty to keep current)</span>
                                </label>
                                <input type="password" name="password" x-model="userFormData.password" :required="!showEditUserModal"
                                       class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                       placeholder="Minimum 8 characters">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                <textarea name="description" x-model="userFormData.description" rows="2"
                                          class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                          placeholder="Optional description for this API user"></textarea>
                            </div>

                            <div x-show="showEditUserModal">
                                <label class="flex items-center">
                                    <input type="checkbox" name="is_active" x-model="userFormData.is_active"
                                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    <span class="ml-2 text-sm text-gray-700">Active</span>
                                </label>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end space-x-3">
                            <button type="button" @click="closeModals()" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg transition-colors">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors">
                                <span x-text="showEditUserModal ? 'Update User' : 'Create User'"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function infusionIntegration() {
            return {
                showAddUserModal: false,
                showEditUserModal: false,
                editingUser: {},
                userFormData: {
                    name: '',
                    username: '',
                    password: '',
                    description: '',
                    is_active: true
                },

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
                }
            };
        }
    </script>
</x-app-layout>
















