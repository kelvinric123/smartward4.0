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
                                                    <button @click="editPump({{ json_encode($pump) }})" class="text-blue-600 hover:text-blue-800 transition-colors">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                        </svg>
                                                    </button>
                                                    <form action="{{ route('infusion-integration.pump.destroy', $pump) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this pump?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-red-600 hover:text-red-800 transition-colors">
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
                                <input type="text" name="serial_no" x-model="pumpFormData.serial_no" required
                                       class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500"
                                       placeholder="e.g., I51541">
                                <p class="mt-1 text-xs text-gray-500">Equipment serial number from the pump label (e.g., I51541, I51568)</p>
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
                }
            };
        }
    </script>
</x-app-layout>
