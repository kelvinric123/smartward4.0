<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-bold text-2xl text-gray-800 leading-tight">
                    {{ __('ADT Integration') }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">Configure HL7 ADT message handling for patient Admit, Discharge, and Transfer</p>
            </div>
            <div class="flex space-x-2">
                <button onclick="openMappingIframe()" 
                        class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-indigo-500 to-blue-600 hover:from-indigo-600 hover:to-blue-700 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Mappings
                </button>
                <button onclick="document.getElementById('adtInfoModal').classList.remove('hidden')" 
                        class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-teal-500 to-cyan-600 hover:from-teal-600 hover:to-cyan-700 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    How ADT Works
                </button>
            </div>
        </div>
    </x-slot>

    <!-- ADT Info Modal -->
    <div id="adtInfoModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity" onclick="document.getElementById('adtInfoModal').classList.add('hidden')"></div>

            <div class="relative inline-block w-full max-w-4xl p-8 my-8 text-left align-middle transition-all transform bg-gradient-to-br from-slate-900 to-slate-800 shadow-2xl rounded-3xl border border-slate-700">
                <div class="flex justify-between items-center mb-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-gradient-to-br from-teal-500 to-cyan-600 rounded-xl mr-4">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-white">ADT Message Flow</h3>
                    </div>
                    <button onclick="document.getElementById('adtInfoModal').classList.add('hidden')" class="text-gray-400 hover:text-white transition-colors">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="space-y-6">
                    <!-- Flow Diagram -->
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-center">
                        <div class="text-center p-4 bg-slate-700/50 rounded-xl border border-slate-600">
                            <div class="w-16 h-16 mx-auto mb-3 bg-orange-500/20 rounded-full flex items-center justify-center">
                                <svg class="w-8 h-8 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
                                </svg>
                            </div>
                            <p class="text-sm font-semibold text-orange-300">1. HIS System</p>
                            <p class="text-xs text-gray-400 mt-1">Sends HL7 ADT message</p>
                        </div>
                        <div class="hidden md:flex justify-center">
                            <svg class="w-8 h-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                            </svg>
                        </div>
                        <div class="text-center p-4 bg-slate-700/50 rounded-xl border border-slate-600">
                            <div class="w-16 h-16 mx-auto mb-3 bg-cyan-500/20 rounded-full flex items-center justify-center">
                                <svg class="w-8 h-8 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2"/>
                                </svg>
                            </div>
                            <p class="text-sm font-semibold text-cyan-300">2. HL7 Listener</p>
                            <p class="text-xs text-gray-400 mt-1">Python MLLP server</p>
                        </div>
                        <div class="hidden md:flex justify-center">
                            <svg class="w-8 h-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                            </svg>
                        </div>
                        <div class="text-center p-4 bg-slate-700/50 rounded-xl border border-slate-600">
                            <div class="w-16 h-16 mx-auto mb-3 bg-green-500/20 rounded-full flex items-center justify-center">
                                <svg class="w-8 h-8 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <p class="text-sm font-semibold text-green-300">3. SmartWard</p>
                            <p class="text-xs text-gray-400 mt-1">Process & update beds</p>
                        </div>
                    </div>

                    <!-- Supported Events -->
                    <div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600">
                        <h4 class="text-lg font-bold text-white mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
                            </svg>
                            Supported ADT Events
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-green-500/10 rounded-lg p-4 border border-green-500/30">
                                <p class="font-bold text-green-400 text-lg">A01 - Admit</p>
                                <p class="text-sm text-gray-300 mt-1">Admits patient to a bed</p>
                            </div>
                            <div class="bg-blue-500/10 rounded-lg p-4 border border-blue-500/30">
                                <p class="font-bold text-blue-400 text-lg">A02 - Transfer</p>
                                <p class="text-sm text-gray-300 mt-1">Transfers patient between beds</p>
                            </div>
                            <div class="bg-red-500/10 rounded-lg p-4 border border-red-500/30">
                                <p class="font-bold text-red-400 text-lg">A03 - Discharge</p>
                                <p class="text-sm text-gray-300 mt-1">Discharges patient from bed</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button onclick="document.getElementById('adtInfoModal').classList.add('hidden')" 
                            class="px-6 py-2.5 bg-gradient-to-r from-teal-500 to-cyan-600 hover:from-teal-600 hover:to-cyan-700 text-white font-semibold rounded-lg transition-all">
                        Got it!
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="py-8" x-data="adtManager()">
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

            <!-- Server Status & Configuration -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Server Status Card -->
                <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-teal-100">
                    <div class="p-6 border-b border-teal-100 bg-gradient-to-r from-teal-50 to-cyan-50">
                        <div class="flex items-center">
                            <div class="p-3 bg-teal-600 rounded-xl mr-4">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800">HL7 Listener Status</h3>
                                <p class="text-sm text-gray-500">ADT Message Server</p>
                            </div>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <span class="text-sm font-medium text-gray-600">Host IP</span>
                                <span class="font-mono text-sm bg-gray-200 px-3 py-1 rounded">{{ $configuration->listener_host }}</span>
                            </div>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <span class="text-sm font-medium text-gray-600">Port</span>
                                <span class="font-mono text-sm bg-teal-100 text-teal-700 px-3 py-1 rounded font-bold">{{ $configuration->listener_port }}</span>
                            </div>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <span class="text-sm font-medium text-gray-600">Status</span>
                                <span x-show="!connectionStatus" class="text-gray-500 text-sm">Checking...</span>
                                <span x-show="connectionStatus === 'online'" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                    <span class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></span>
                                    Online
                                </span>
                                <span x-show="connectionStatus === 'offline'" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                                    <span class="w-2 h-2 bg-red-500 rounded-full mr-2"></span>
                                    Offline
                                </span>
                            </div>
                            <button @click="testConnection()" 
                                    :disabled="testing"
                                    class="w-full inline-flex items-center justify-center px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white font-medium rounded-lg transition-colors">
                                <svg x-show="!testing" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                <svg x-show="testing" class="w-4 h-4 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <span x-text="testing ? 'Testing...' : 'Test Connection'"></span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="lg:col-span-2 grid grid-cols-2 md:grid-cols-5 gap-4">
                    <div class="bg-white/90 backdrop-blur-sm p-4 rounded-2xl shadow-lg border border-blue-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-500">Total</p>
                                <p class="text-2xl font-bold text-gray-800">{{ number_format($stats['total_messages']) }}</p>
                            </div>
                            <div class="p-2 bg-blue-100 rounded-xl">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white/90 backdrop-blur-sm p-4 rounded-2xl shadow-lg border border-green-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-500">Processed</p>
                                <p class="text-2xl font-bold text-green-600">{{ number_format($stats['processed']) }}</p>
                            </div>
                            <div class="p-2 bg-green-100 rounded-xl">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white/90 backdrop-blur-sm p-4 rounded-2xl shadow-lg border border-yellow-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-500">Unmapped</p>
                                <p class="text-2xl font-bold text-yellow-600">{{ number_format($stats['unmapped']) }}</p>
                            </div>
                            <div class="p-2 bg-yellow-100 rounded-xl">
                                <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white/90 backdrop-blur-sm p-4 rounded-2xl shadow-lg border border-red-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-500">Failed</p>
                                <p class="text-2xl font-bold text-red-600">{{ number_format($stats['failed']) }}</p>
                            </div>
                            <div class="p-2 bg-red-100 rounded-xl">
                                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white/90 backdrop-blur-sm p-4 rounded-2xl shadow-lg border border-purple-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-gray-500">Today</p>
                                <p class="text-2xl font-bold text-purple-600">{{ number_format($stats['today']) }}</p>
                            </div>
                            <div class="p-2 bg-purple-100 rounded-xl">
                                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Configuration Settings -->
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-teal-100">
                <div class="p-6 border-b border-teal-100 bg-gradient-to-r from-teal-50 to-cyan-50">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center">
                            <div class="p-3 bg-teal-600 rounded-xl mr-4">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800">ADT Configuration</h3>
                                <p class="text-sm text-gray-500">Configure listener settings and automation</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    <form action="{{ route('adt.update-configuration') }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Configuration Name</label>
                                <input type="text" name="name" value="{{ $configuration->name }}" 
                                       class="w-full rounded-lg border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Listener Host</label>
                                <input type="text" name="listener_host" value="{{ $configuration->listener_host }}" 
                                       class="w-full rounded-lg border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 font-mono">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Listener Port</label>
                                <input type="number" name="listener_port" value="{{ $configuration->listener_port }}" 
                                       class="w-full rounded-lg border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 font-mono">
                            </div>
                            <div class="flex items-end">
                                <button type="submit" class="w-full px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white font-medium rounded-lg transition-colors">
                                    Save Settings
                                </button>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-6">
                            <label class="flex items-center">
                                <input type="checkbox" name="auto_admit" {{ $configuration->auto_admit ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-teal-600 shadow-sm focus:ring-teal-500">
                                <span class="ml-2 text-sm text-gray-700">Auto Admit (A01)</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="auto_discharge" {{ $configuration->auto_discharge ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-teal-600 shadow-sm focus:ring-teal-500">
                                <span class="ml-2 text-sm text-gray-700">Auto Discharge (A03)</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="auto_transfer" {{ $configuration->auto_transfer ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-teal-600 shadow-sm focus:ring-teal-500">
                                <span class="ml-2 text-sm text-gray-700">Auto Transfer (A02)</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="is_active" {{ $configuration->is_active ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-teal-600 shadow-sm focus:ring-teal-500">
                                <span class="ml-2 text-sm text-gray-700">Active</span>
                            </label>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Mappings Section -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                <!-- Hospital Mapping -->
                <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-orange-100">
                    <div class="p-4 border-b border-orange-100 bg-gradient-to-r from-orange-50 to-amber-50">
                        <div class="flex justify-between items-center">
                            <div class="flex items-center">
                                <div class="p-2 bg-orange-500 rounded-lg mr-3">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
                                    </svg>
                                </div>
                                <h3 class="text-md font-bold text-gray-800">Hospital Mapping</h3>
                            </div>
                            <button @click="showHospitalModal = true" class="p-2 bg-orange-100 hover:bg-orange-200 text-orange-700 rounded-lg transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="p-4 max-h-64 overflow-y-auto">
                        @forelse($hospitalMappings as $mapping)
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg mb-2">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-800 truncate">{{ $mapping->adt_hospital_code }}</p>
                                    <p class="text-xs text-gray-500 truncate">→ {{ $mapping->hospital->name }}</p>
                                </div>
                                <form action="{{ route('adt.hospital-mapping.destroy', $mapping) }}" method="POST" class="ml-2">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700" onclick="return confirm('Delete this mapping?')">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 text-center py-4">No hospital mappings configured</p>
                        @endforelse
                    </div>
                </div>

                <!-- Ward Mapping -->
                <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-blue-100">
                    <div class="p-4 border-b border-blue-100 bg-gradient-to-r from-blue-50 to-indigo-50">
                        <div class="flex justify-between items-center">
                            <div class="flex items-center">
                                <div class="p-2 bg-blue-500 rounded-lg mr-3">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <h3 class="text-md font-bold text-gray-800">Ward Mapping</h3>
                            </div>
                            <button @click="showWardModal = true" class="p-2 bg-blue-100 hover:bg-blue-200 text-blue-700 rounded-lg transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="p-4 max-h-64 overflow-y-auto">
                        @forelse($wardMappings as $mapping)
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg mb-2">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-800 truncate">{{ $mapping->adt_ward_code }}</p>
                                    <p class="text-xs text-gray-500 truncate">→ {{ $mapping->ward->ward_name }} ({{ $mapping->ward->hospital->name ?? 'N/A' }})</p>
                                </div>
                                <form action="{{ route('adt.ward-mapping.destroy', $mapping) }}" method="POST" class="ml-2">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700" onclick="return confirm('Delete this mapping?')">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 text-center py-4">No ward mappings configured</p>
                        @endforelse
                    </div>
                </div>

                <!-- Bed Mapping -->
                <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-green-100">
                    <div class="p-4 border-b border-green-100 bg-gradient-to-r from-green-50 to-emerald-50">
                        <div class="flex justify-between items-center">
                            <div class="flex items-center">
                                <div class="p-2 bg-green-500 rounded-lg mr-3">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                                    </svg>
                                </div>
                                <h3 class="text-md font-bold text-gray-800">Bed Mapping</h3>
                            </div>
                            <button @click="showBedModal = true" class="p-2 bg-green-100 hover:bg-green-200 text-green-700 rounded-lg transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="p-4 max-h-64 overflow-y-auto">
                        @forelse($bedMappings as $mapping)
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg mb-2">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-800 truncate">{{ $mapping->adt_bed_code }}</p>
                                    <p class="text-xs text-gray-500 truncate">→ {{ $mapping->bed->bed_number }} ({{ $mapping->bed->ward->ward_name ?? 'N/A' }})</p>
                                </div>
                                <form action="{{ route('adt.bed-mapping.destroy', $mapping) }}" method="POST" class="ml-2">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700" onclick="return confirm('Delete this mapping?')">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 text-center py-4">No bed mappings configured</p>
                        @endforelse
                    </div>
                </div>

                <!-- Doctor Mapping -->
                <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-purple-100">
                    <div class="p-4 border-b border-purple-100 bg-gradient-to-r from-purple-50 to-violet-50">
                        <div class="flex justify-between items-center">
                            <div class="flex items-center">
                                <div class="p-2 bg-purple-500 rounded-lg mr-3">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                </div>
                                <h3 class="text-md font-bold text-gray-800">Doctor Mapping</h3>
                            </div>
                            <button @click="showDoctorModal = true" class="p-2 bg-purple-100 hover:bg-purple-200 text-purple-700 rounded-lg transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="p-4 max-h-64 overflow-y-auto">
                        @forelse($doctorMappings as $mapping)
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg mb-2">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-800 truncate">{{ $mapping->adt_doctor_code }}</p>
                                    <p class="text-xs text-gray-500 truncate">→ {{ $mapping->consultant->name ?? 'N/A' }} ({{ ucfirst($mapping->doctor_type) }})</p>
                                </div>
                                <form action="{{ route('adt.doctor-mapping.destroy', $mapping) }}" method="POST" class="ml-2">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700" onclick="return confirm('Delete this mapping?')">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 text-center py-4">No doctor mappings configured</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Message Logs Section -->
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-gray-200">
                <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-slate-50">
                    <div class="flex flex-col lg:flex-row lg:justify-between lg:items-center gap-4">
                        <div class="flex items-center">
                            <div class="p-3 bg-gray-600 rounded-xl mr-4">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800">ADT Message Logs</h3>
                                <p class="text-sm text-gray-500">Recent HL7 ADT messages received</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button @click="refreshLogs()" class="inline-flex items-center px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg transition-colors">
                                <svg class="w-4 h-4 mr-1" :class="{ 'animate-spin': loadingLogs }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                Refresh
                            </button>
                            <form action="{{ route('adt.logs.clear') }}" method="POST" class="inline" onsubmit="return confirm('Clear all logs?')">
                                @csrf
                                <button type="submit" class="inline-flex items-center px-3 py-2 bg-red-100 hover:bg-red-200 text-red-700 font-medium rounded-lg transition-colors">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                    Clear Logs
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Filters -->
                    <form method="GET" action="{{ route('adt.index') }}" class="mt-4 flex flex-wrap gap-3 items-end">
                        <div class="flex-1 min-w-[150px]">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                            <select name="status" class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                                <option value="">All Status</option>
                                <option value="processed" {{ request('status') === 'processed' ? 'selected' : '' }}>Processed</option>
                                <option value="unmapped" {{ request('status') === 'unmapped' ? 'selected' : '' }}>Unmapped</option>
                                <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
                                <option value="received" {{ request('status') === 'received' ? 'selected' : '' }}>Received</option>
                                <option value="ignored" {{ request('status') === 'ignored' ? 'selected' : '' }}>Ignored</option>
                            </select>
                        </div>
                        <div class="flex-1 min-w-[120px]">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Event Type</label>
                            <select name="event_type" class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                                <option value="">All Events</option>
                                <option value="A01" {{ request('event_type') === 'A01' ? 'selected' : '' }}>A01 - Admit</option>
                                <option value="A02" {{ request('event_type') === 'A02' ? 'selected' : '' }}>A02 - Transfer</option>
                                <option value="A03" {{ request('event_type') === 'A03' ? 'selected' : '' }}>A03 - Discharge</option>
                                <option value="A08" {{ request('event_type') === 'A08' ? 'selected' : '' }}>A08 - Update</option>
                            </select>
                        </div>
                        <div class="flex-1 min-w-[180px]">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Search (MRN/Name)</label>
                            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search patient..." 
                                   class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                        </div>
                        <div class="flex-1 min-w-[140px]">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Date From</label>
                            <input type="date" name="date_from" value="{{ request('date_from') }}" 
                                   class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                        </div>
                        <div class="flex-1 min-w-[140px]">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Date To</label>
                            <input type="date" name="date_to" value="{{ request('date_to') }}" 
                                   class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white font-medium text-sm rounded-lg transition-colors">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                                </svg>
                                Filter
                            </button>
                            @if(request()->hasAny(['status', 'event_type', 'search', 'date_from', 'date_to']))
                                <a href="{{ route('adt.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium text-sm rounded-lg transition-colors">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                    Clear
                                </a>
                            @endif
                        </div>
                    </form>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Time</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Event</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Patient</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Ward / Bed</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">View</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @forelse($recentLogs as $log)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <div class="font-medium">{{ $log->created_at->format('M d, H:i:s') }}</div>
                                        <div class="text-xs text-gray-400">{{ $log->created_at->diffForHumans() }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            {{ $log->event_type === 'A01' ? 'bg-green-100 text-green-800' : '' }}
                                            {{ $log->event_type === 'A02' ? 'bg-blue-100 text-blue-800' : '' }}
                                            {{ $log->event_type === 'A03' ? 'bg-red-100 text-red-800' : '' }}
                                            {{ !in_array($log->event_type, ['A01', 'A02', 'A03']) ? 'bg-gray-100 text-gray-800' : '' }}">
                                            {{ $log->event_type }}
                                        </span>
                                        <div class="text-xs text-gray-500 mt-1">{{ $log->event_description }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="text-sm font-medium text-gray-800">{{ $log->patient_name ?: 'N/A' }}</div>
                                        <div class="text-xs text-gray-500">MRN: {{ $log->patient_mrn ?: $log->patient_id ?: 'N/A' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        @php
                                            $actionData = $log->action_taken ?? [];
                                            $adtWardCode = $actionData['adt_ward_code'] ?? null;
                                            $adtBedCode = $actionData['adt_bed_code'] ?? null;
                                            $assignedWard = $actionData['assigned_ward'] ?? null;
                                            $assignedBed = $actionData['assigned_bed'] ?? null;
                                        @endphp
                                        @if($log->status === 'processed' && $assignedWard && $assignedBed)
                                            <div class="text-green-700 font-medium">
                                                {{ $assignedWard }} → {{ $assignedBed }}
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                From: {{ $adtWardCode ?? 'N/A' }} / {{ $adtBedCode ?? 'N/A' }}
                                            </div>
                                        @elseif($log->status === 'unmapped')
                                            <div class="text-yellow-700 font-medium">
                                                <span class="inline-flex items-center">
                                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                                    </svg>
                                                    Not Mapped
                                                </span>
                                            </div>
                                            <div class="text-xs text-yellow-600 font-mono">
                                                Ward: {{ $adtWardCode ?? 'N/A' }} / Bed: {{ $adtBedCode ?? 'N/A' }}
                                            </div>
                                        @else
                                            <div class="text-gray-600">{{ $log->assigned_location ?: 'N/A' }}</div>
                                            @if($adtWardCode || $adtBedCode)
                                                <div class="text-xs text-gray-500 font-mono">
                                                    {{ $adtWardCode ?? '-' }} / {{ $adtBedCode ?? '-' }}
                                                </div>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            {{ $log->status === 'processed' ? 'bg-green-100 text-green-800' : '' }}
                                            {{ $log->status === 'received' ? 'bg-blue-100 text-blue-800' : '' }}
                                            {{ $log->status === 'failed' ? 'bg-red-100 text-red-800' : '' }}
                                            {{ $log->status === 'ignored' ? 'bg-gray-100 text-gray-800' : '' }}
                                            {{ $log->status === 'unmapped' ? 'bg-yellow-100 text-yellow-800' : '' }}">
                                            {{ ucfirst($log->status) }}
                                        </span>
                                        @if($log->status === 'unmapped' && $log->error_message)
                                            <div class="text-xs text-yellow-600 mt-1 max-w-xs truncate" title="{{ $log->error_message }}">
                                                {{ Str::limit($log->error_message, 40) }}
                                            </div>
                                        @elseif($log->status === 'failed' && $log->error_message)
                                            <div class="text-xs text-red-600 mt-1 max-w-xs truncate" title="{{ $log->error_message }}">
                                                {{ Str::limit($log->error_message, 40) }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <button @click="viewLogDetail({{ $log->id }})" class="text-teal-600 hover:text-teal-800">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-12 text-center text-gray-500">
                                        <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                        </svg>
                                        <p>No ADT messages received yet</p>
                                        <p class="text-sm text-gray-400 mt-1">Messages will appear here when the HL7 listener receives them</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Hospital Mapping Modal -->
        <div x-show="showHospitalModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showHospitalModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Add Hospital Mapping</h3>
                    <form action="{{ route('adt.hospital-mapping.store') }}" method="POST">
                        @csrf
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ADT Hospital Code *</label>
                                <input type="text" name="adt_hospital_code" required placeholder="e.g., PHKL, HKL"
                                       class="w-full rounded-lg border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500 font-mono">
                                <p class="text-xs text-gray-500 mt-1">The sending_facility code from HL7 messages (MSH-4)</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ADT Hospital Name</label>
                                <input type="text" name="adt_hospital_name" placeholder="Optional description"
                                       class="w-full rounded-lg border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Map to Hospital *</label>
                                <select name="hospital_id" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                                    <option value="">Select hospital...</option>
                                    @foreach($hospitals as $hospital)
                                        <option value="{{ $hospital->id }}">{{ $hospital->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end space-x-3">
                            <button type="button" @click="showHospitalModal = false" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white font-medium rounded-lg">Add Mapping</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Ward Mapping Modal -->
        <div x-show="showWardModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showWardModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Add Ward Mapping</h3>
                    <form action="{{ route('adt.ward-mapping.store') }}" method="POST">
                        @csrf
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ADT Ward Code *</label>
                                <input type="text" name="adt_ward_code" required placeholder="e.g., WWD6, D6"
                                       class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 font-mono">
                                <p class="text-xs text-gray-500 mt-1">The ward/unit code from PV1-3 location field</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ADT Ward Name</label>
                                <input type="text" name="adt_ward_name" placeholder="Optional description"
                                       class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Map to Ward *</label>
                                <select name="ward_id" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Select ward...</option>
                                    @foreach($wards as $ward)
                                        <option value="{{ $ward->id }}">{{ $ward->ward_name }} ({{ $ward->hospital->name ?? 'N/A' }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end space-x-3">
                            <button type="button" @click="showWardModal = false" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white font-medium rounded-lg">Add Mapping</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Bed Mapping Modal -->
        <div x-show="showBedModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showBedModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Add Bed Mapping</h3>
                    <form action="{{ route('adt.bed-mapping.store') }}" method="POST">
                        @csrf
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ADT Bed Code *</label>
                                <input type="text" name="adt_bed_code" required placeholder="e.g., D601, D610, D622"
                                       class="w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 font-mono">
                                <p class="text-xs text-gray-500 mt-1">The bed code from HIS (e.g., D610 for bed 10 in ward D6)</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ADT Bed Name</label>
                                <input type="text" name="adt_bed_name" placeholder="Optional description"
                                       class="w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Map to Bed *</label>
                                <select name="bed_id" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                                    <option value="">Select bed...</option>
                                    @foreach($beds as $bed)
                                        <option value="{{ $bed->id }}">{{ $bed->bed_number }} - {{ $bed->ward->ward_name ?? 'N/A' }}</option>
                                    @endforeach
                                </select>
                                <p class="text-xs text-gray-500 mt-1">💡 Select the SmartWard bed that matches this HIS code</p>
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end space-x-3">
                            <button type="button" @click="showBedModal = false" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white font-medium rounded-lg">Add Mapping</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Doctor Mapping Modal -->
        <div x-show="showDoctorModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showDoctorModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Add Doctor Mapping</h3>
                    <form action="{{ route('adt.doctor-mapping.store') }}" method="POST">
                        @csrf
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ADT Doctor Code *</label>
                                <input type="text" name="adt_doctor_code" required placeholder="e.g., DKAMJIT, DR001"
                                       class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 font-mono">
                                <p class="text-xs text-gray-500 mt-1">The doctor code from PV1 (Attending/Referring/Consulting)</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ADT Doctor Name</label>
                                <input type="text" name="adt_doctor_name" placeholder="Optional description"
                                       class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Doctor Type *</label>
                                <select name="doctor_type" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                    @foreach($doctorTypes as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Map to Consultant *</label>
                                <select name="consultant_id" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                    <option value="">Select consultant...</option>
                                    @foreach($consultants as $consultant)
                                        <option value="{{ $consultant->id }}">{{ $consultant->name }} ({{ $consultant->specialty->name ?? 'N/A' }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end space-x-3">
                            <button type="button" @click="showDoctorModal = false" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-purple-500 hover:bg-purple-600 text-white font-medium rounded-lg">Add Mapping</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Log Detail Modal -->
        <div x-show="showLogModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showLogModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-xl max-w-3xl w-full p-6 max-h-[80vh] overflow-y-auto">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold text-gray-800">Message Details</h3>
                        <button @click="showLogModal = false" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <div x-show="logDetail" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="bg-gray-50 rounded-lg p-3">
                                <p class="text-xs font-medium text-gray-500">Event Type</p>
                                <p class="text-sm font-bold text-gray-800" x-text="logDetail?.event_type + ' - ' + logDetail?.event_description"></p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <p class="text-xs font-medium text-gray-500">Status</p>
                                <p class="text-sm font-bold" :class="{
                                    'text-green-600': logDetail?.status === 'processed',
                                    'text-red-600': logDetail?.status === 'failed',
                                    'text-blue-600': logDetail?.status === 'received',
                                    'text-gray-600': logDetail?.status === 'ignored'
                                }" x-text="logDetail?.status?.toUpperCase()"></p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <p class="text-xs font-medium text-gray-500">Patient ID</p>
                                <p class="text-sm font-bold text-gray-800" x-text="logDetail?.patient_id || 'N/A'"></p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <p class="text-xs font-medium text-gray-500">Patient Name</p>
                                <p class="text-sm font-bold text-gray-800" x-text="logDetail?.patient_name || 'N/A'"></p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <p class="text-xs font-medium text-gray-500">Location</p>
                                <p class="text-sm font-bold text-gray-800" x-text="logDetail?.assigned_location || 'N/A'"></p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <p class="text-xs font-medium text-gray-500">Source</p>
                                <p class="text-sm font-bold text-gray-800" x-text="(logDetail?.sending_application || 'N/A') + ' @ ' + (logDetail?.sending_facility || 'N/A')"></p>
                            </div>
                        </div>
                        <div x-show="logDetail?.error_message" class="bg-red-50 border border-red-200 rounded-lg p-3">
                            <p class="text-xs font-medium text-red-600">Error Message</p>
                            <p class="text-sm text-red-800" x-text="logDetail?.error_message"></p>
                        </div>
                        <div x-show="logDetail?.raw_message">
                            <p class="text-xs font-medium text-gray-500 mb-2">Raw HL7 Message</p>
                            <pre class="bg-gray-900 text-green-400 rounded-lg p-4 text-xs overflow-x-auto max-h-48" x-text="logDetail?.raw_message"></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toast Notification -->
        <div x-show="toast.show" x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform translate-y-2"
             x-transition:enter-end="opacity-100 transform translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 transform translate-y-0"
             x-transition:leave-end="opacity-0 transform translate-y-2"
             class="fixed bottom-4 right-4 z-50">
            <div :class="toast.success ? 'bg-green-500' : 'bg-red-500'" class="text-white px-6 py-4 rounded-lg shadow-lg max-w-md">
                <div class="flex items-center">
                    <svg x-show="toast.success" class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <svg x-show="!toast.success" class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span x-text="toast.message" class="font-medium"></span>
                </div>
            </div>
        </div>
    </div>

    <script>
        function adtManager() {
            return {
                showHospitalModal: false,
                showWardModal: false,
                showBedModal: false,
                showDoctorModal: false,
                showLogModal: false,
                logDetail: null,
                testing: false,
                connectionStatus: null,
                loadingLogs: false,
                toast: {
                    show: false,
                    success: true,
                    message: ''
                },

                init() {
                    this.testConnection();
                },

                async testConnection() {
                    this.testing = true;
                    try {
                        const response = await fetch('{{ route('adt.test-connection') }}');
                        const data = await response.json();
                        this.connectionStatus = data.success ? 'online' : 'offline';
                        if (!data.success) {
                            this.showToast(false, data.message);
                        }
                    } catch (error) {
                        this.connectionStatus = 'offline';
                        this.showToast(false, 'Failed to check connection');
                    } finally {
                        this.testing = false;
                    }
                },

                async viewLogDetail(id) {
                    try {
                        const response = await fetch(`{{ url('adt/logs') }}/${id}`);
                        this.logDetail = await response.json();
                        this.showLogModal = true;
                    } catch (error) {
                        this.showToast(false, 'Failed to load log details');
                    }
                },

                async refreshLogs() {
                    this.loadingLogs = true;
                    // Simply reload the page for now
                    window.location.reload();
                },

                showToast(success, message) {
                    this.toast = { show: true, success, message };
                    setTimeout(() => {
                        this.toast.show = false;
                    }, 5000);
                }
            };
        }

        function openMappingIframe() {
            const modal = document.getElementById('adtMappingsModal');
            const iframe = document.getElementById('adtMappingsFrame');
            if (modal && iframe) {
                iframe.src = "{{ route('adt.mappings.frame') }}";
                modal.classList.remove('hidden');
            }
        }
    </script>
</x-app-layout>

<!-- ADT Mappings Modal -->
<div id="adtMappingsModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity" onclick="document.getElementById('adtMappingsModal').classList.add('hidden')"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl max-w-6xl w-full h-[85vh] overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 border-b">
                <div class="flex items-center space-x-2">
                    <div class="p-2 bg-indigo-500 rounded-lg text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">ADT</p>
                        <p class="text-lg font-bold text-gray-800">Mappings</p>
                    </div>
                </div>
                <button class="text-gray-500 hover:text-gray-700" onclick="document.getElementById('adtMappingsModal').classList.add('hidden')">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <iframe id="adtMappingsFrame" src="" class="w-full h-full border-0"></iframe>
        </div>
    </div>
</div>





