<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-bold text-2xl text-gray-800 leading-tight">
                    {{ __('ECG Admin') }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">Manage ECG uploads from ECG machines via HTTP</p>
            </div>
            <button onclick="document.getElementById('setupModal').classList.remove('hidden')" 
                    class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-rose-500 to-pink-600 hover:from-rose-600 hover:to-pink-700 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Setup Guide
            </button>
        </div>
    </x-slot>

    <!-- Setup Guide Modal -->
    <div id="setupModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity" onclick="document.getElementById('setupModal').classList.add('hidden')"></div>

            <div class="relative inline-block w-full max-w-4xl p-8 my-8 text-left align-middle transition-all transform bg-gradient-to-br from-slate-900 to-slate-800 shadow-2xl rounded-3xl border border-slate-700">
                <div class="flex justify-between items-center mb-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-gradient-to-br from-rose-500 to-pink-600 rounded-xl mr-4">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-white">ECG Upload Setup Guide</h3>
                    </div>
                    <button onclick="document.getElementById('setupModal').classList.add('hidden')" class="text-gray-400 hover:text-white transition-colors">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="space-y-6 max-h-[70vh] overflow-y-auto pr-2">
                    <!-- Step 1: Start the Server -->
                    <div class="bg-slate-700/50 rounded-xl p-4 border border-slate-600">
                        <h4 class="text-lg font-bold text-white mb-3 flex items-center">
                            <span class="w-8 h-8 bg-rose-500 rounded-full flex items-center justify-center text-white font-bold mr-3">1</span>
                            Start the ECG HTTP Server
                        </h4>
                        <p class="text-gray-300 mb-3">Run the Python HTTP server to receive ECG uploads:</p>
                        <div class="bg-slate-800/70 rounded-lg p-3">
                            <p class="text-xs font-semibold text-gray-400 mb-2">COMMAND</p>
                            <pre class="text-sm text-cyan-300 font-mono">cd ecg
python http_server.py</pre>
                        </div>
                        <p class="text-gray-400 text-sm mt-3">Or double-click <code class="text-cyan-300">start_http_server.bat</code> on Windows.</p>
                    </div>

                    <!-- Step 2: Server Configuration -->
                    <div class="bg-slate-700/50 rounded-xl p-4 border border-slate-600">
                        <h4 class="text-lg font-bold text-white mb-3 flex items-center">
                            <span class="w-8 h-8 bg-rose-500 rounded-full flex items-center justify-center text-white font-bold mr-3">2</span>
                            Server Configuration
                        </h4>
                        <div class="grid grid-cols-2 gap-4 mb-3">
                            <div class="bg-slate-800/70 rounded-lg p-3">
                                <p class="text-xs font-semibold text-gray-400 mb-1">SERVER URL</p>
                                <code class="text-cyan-300 text-sm">http://{{ request()->getHost() }}:8080/</code>
                            </div>
                            <div class="bg-slate-800/70 rounded-lg p-3">
                                <p class="text-xs font-semibold text-gray-400 mb-1">METHOD</p>
                                <code class="text-green-300 text-sm">HTTP POST / PUT</code>
                            </div>
                        </div>
                        <div class="bg-slate-800/70 rounded-lg p-3">
                            <p class="text-xs font-semibold text-gray-400 mb-2">AUTHENTICATION (Basic Auth)</p>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-gray-400">Username:</span>
                                    <code class="ml-2 text-amber-300">admin</code>
                                </div>
                                <div>
                                    <span class="text-gray-400">Password:</span>
                                    <code class="ml-2 text-amber-300">admin123</code>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: ECG Machine Setup -->
                    <div class="bg-slate-700/50 rounded-xl p-4 border border-slate-600">
                        <h4 class="text-lg font-bold text-white mb-3 flex items-center">
                            <span class="w-8 h-8 bg-rose-500 rounded-full flex items-center justify-center text-white font-bold mr-3">3</span>
                            Configure Your ECG Machine
                        </h4>
                        <p class="text-gray-300 mb-3">Configure your ECG machine with these settings:</p>
                        <div class="bg-slate-800/70 rounded-lg p-3 space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-400">Export Method:</span>
                                <span class="text-white">HTTP POST</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-400">Server Address:</span>
                                <code class="text-cyan-300">http://[SERVER_IP]:8080/</code>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-400">Authentication:</span>
                                <span class="text-white">Basic Authentication</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-400">File Format:</span>
                                <span class="text-white">XML or PDF</span>
                            </div>
                        </div>
                        <div class="mt-3 p-3 bg-amber-500/20 rounded-lg border border-amber-500/30">
                            <p class="text-amber-300 text-sm flex items-start">
                                <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                <span><strong>Important:</strong> Set the Patient ID on your ECG machine to the patient's MRN for automatic matching.</span>
                            </p>
                        </div>
                    </div>

                    <!-- Supported ECG Machines -->
                    <div class="bg-slate-700/50 rounded-xl p-4 border border-slate-600">
                        <h4 class="text-lg font-bold text-white mb-3 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Supported ECG Machines
                        </h4>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div class="bg-slate-800/70 rounded px-3 py-2 text-gray-300">
                                <span class="text-green-400 mr-2">✓</span> Philips TC35
                            </div>
                            <div class="bg-slate-800/70 rounded px-3 py-2 text-gray-300">
                                <span class="text-green-400 mr-2">✓</span> GE MAC Series
                            </div>
                            <div class="bg-slate-800/70 rounded px-3 py-2 text-gray-300">
                                <span class="text-green-400 mr-2">✓</span> Mortara ELI Series
                            </div>
                            <div class="bg-slate-800/70 rounded px-3 py-2 text-gray-300">
                                <span class="text-green-400 mr-2">✓</span> Any HTTP POST device
                            </div>
                        </div>
                    </div>

                    <!-- File Storage -->
                    <div class="bg-slate-700/50 rounded-xl p-4 border border-slate-600">
                        <h4 class="text-lg font-bold text-white mb-3 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z"/>
                            </svg>
                            File Storage
                        </h4>
                        <p class="text-gray-300 text-sm mb-2">Uploaded files are stored in:</p>
                        <code class="text-cyan-300 text-sm bg-slate-800/70 px-3 py-1.5 rounded">ecg/store/</code>
                        <p class="text-gray-400 text-sm mt-3">XML files with embedded PDFs are automatically extracted.</p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button onclick="document.getElementById('setupModal').classList.add('hidden')" 
                            class="px-6 py-2.5 bg-gradient-to-r from-rose-500 to-pink-600 hover:from-rose-600 hover:to-pink-700 text-white font-semibold rounded-lg transition-all">
                        Got it!
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="py-8" x-data="ecgAdmin()">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            <!-- Stats Overview -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div class="bg-white rounded-xl shadow-md p-4 border border-gray-100">
                    <div class="flex items-center">
                        <div class="p-2 bg-rose-100 rounded-lg mr-3">
                            <svg class="w-6 h-6 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-gray-800">{{ $stats['total_files'] }}</div>
                            <div class="text-xs text-gray-500">Total Files</div>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 border border-gray-100">
                    <div class="flex items-center">
                        <div class="p-2 bg-pink-100 rounded-lg mr-3">
                            <svg class="w-6 h-6 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-gray-800">{{ $stats['ecg_xml_files'] ?? 0 }}</div>
                            <div class="text-xs text-gray-500">ECG XML</div>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 border border-gray-100">
                    <div class="flex items-center">
                        <div class="p-2 bg-blue-100 rounded-lg mr-3">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-gray-800">{{ $stats['standalone_pdfs'] ?? 0 }}</div>
                            <div class="text-xs text-gray-500">Standalone PDFs</div>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-4 border border-gray-100">
                    <div class="flex items-center">
                        <div class="p-2 bg-green-100 rounded-lg mr-3">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-gray-800">{{ $stats['matched_patients'] }}</div>
                            <div class="text-xs text-gray-500">Matched</div>
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
                            <div class="text-2xl font-bold text-amber-600">{{ $stats['unmatched_patients'] }}</div>
                            <div class="text-xs text-gray-500">Unmatched</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ECG Files List -->
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-rose-100">
                <div class="p-6 border-b border-rose-100 bg-gradient-to-r from-rose-50 to-pink-50">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center">
                            <div class="p-3 bg-rose-600 rounded-xl mr-4">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800">ECG Files</h3>
                                <p class="text-sm text-gray-500">All uploaded ECG files with patient matching status</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3">
                            <button @click="refreshFiles()" class="inline-flex items-center px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors text-sm font-medium">
                                <svg class="w-4 h-4 mr-1" :class="{'animate-spin': loading}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                Refresh
                            </button>
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    @if(count($ecgFiles) > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Date/Time</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Type</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">MRN</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Patient Name</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Status</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    @foreach($ecgFiles as $ecg)
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-4 py-3 text-sm text-gray-600">
                                                {{ \Carbon\Carbon::parse($ecg['timestamp'])->format('M d, Y H:i:s') }}
                                            </td>
                                            <td class="px-4 py-3">
                                                @if(($ecg['type'] ?? '') === 'ecg_xml')
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-rose-100 text-rose-700">
                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                                        </svg>
                                                        ECG XML
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">
                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                                        </svg>
                                                        PDF
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3">
                                                @if($ecg['mrn'])
                                                    <code class="text-sm font-mono bg-gray-100 px-2 py-0.5 rounded">{{ $ecg['mrn'] }}</code>
                                                @else
                                                    <span class="text-gray-400 text-xs">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-sm">
                                                @if($ecg['patient'])
                                                    <span class="font-medium text-gray-800">{{ $ecg['patient']['name'] }}</span>
                                                @elseif(($ecg['type'] ?? '') === 'standalone_pdf')
                                                    <span class="text-gray-400 italic text-xs">Standalone upload</span>
                                                @else
                                                    <span class="text-gray-400 italic">Not matched</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3">
                                                @if($ecg['patient'])
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">
                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                        </svg>
                                                        Matched
                                                    </span>
                                                @elseif(($ecg['type'] ?? '') === 'standalone_pdf')
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">
                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                                        </svg>
                                                        Upload
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700">
                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                                        </svg>
                                                        Unmatched
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center space-x-2">
                                                    @if($ecg['has_pdf'])
                                                        <a href="{{ route('ecg.pdf.public', ['file' => $ecg['pdf_file']]) }}" 
                                                           target="_blank"
                                                           class="inline-flex items-center px-2 py-1 bg-blue-100 hover:bg-blue-200 text-blue-700 rounded text-xs font-medium transition-colors">
                                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                            </svg>
                                                            View PDF
                                                        </a>
                                                    @endif
                                                    <button onclick="showDetails({{ json_encode($ecg) }})"
                                                            class="inline-flex items-center px-2 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded text-xs font-medium transition-colors">
                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                        </svg>
                                                        Details
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-12">
                            <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                            <h4 class="text-lg font-medium text-gray-600 mb-2">No ECG Files</h4>
                            <p class="text-gray-500 mb-4">Upload ECG files from your ECG machine to see them here.</p>
                            <button onclick="document.getElementById('setupModal').classList.remove('hidden')" 
                                    class="inline-flex items-center px-4 py-2 bg-rose-600 text-white font-medium rounded-lg hover:bg-rose-700 transition-colors">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                View Setup Guide
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Server Status Section -->
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-blue-100">
                <div class="p-6 border-b border-blue-100 bg-gradient-to-r from-brand-50 to-accent-50">
                    <div class="flex items-center">
                        <div class="p-3 bg-blue-600 rounded-xl mr-4">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800">HTTP Server Status</h3>
                            <p class="text-sm text-gray-500">ECG HTTP Upload Server on port 8080</p>
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="border border-gray-200 rounded-xl p-4">
                            <h4 class="text-sm font-medium text-gray-500 mb-2">Server URL</h4>
                            <code class="text-sm bg-gray-100 px-2 py-1 rounded">http://{{ request()->getHost() }}:8080/</code>
                        </div>
                        <div class="border border-gray-200 rounded-xl p-4">
                            <h4 class="text-sm font-medium text-gray-500 mb-2">Authentication</h4>
                            <div class="text-sm">
                                <span class="text-gray-600">User:</span> <code class="bg-gray-100 px-1 rounded">admin</code>
                                <span class="text-gray-600 ml-2">Pass:</span> <code class="bg-gray-100 px-1 rounded">admin123</code>
                            </div>
                        </div>
                        <div class="border border-gray-200 rounded-xl p-4">
                            <h4 class="text-sm font-medium text-gray-500 mb-2">Test Upload</h4>
                            <a href="http://{{ request()->getHost() }}:8080/" target="_blank"
                               class="inline-flex items-center text-sm text-blue-600 hover:text-blue-700 font-medium">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                </svg>
                                Open Upload Page
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Details Modal -->
        <div id="detailsModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="document.getElementById('detailsModal').classList.add('hidden')"></div>

                <div class="relative inline-block w-full max-w-lg p-6 my-8 text-left align-middle transition-all transform bg-white shadow-xl rounded-2xl">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-bold text-gray-800">ECG File Details</h3>
                        <button onclick="document.getElementById('detailsModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <div id="detailsContent" class="space-y-3">
                        <!-- Filled by JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function ecgAdmin() {
            return {
                loading: false,
                refreshFiles() {
                    this.loading = true;
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                }
            };
        }

        function showDetails(ecg) {
            const content = document.getElementById('detailsContent');
            const isStandalonePdf = ecg.type === 'standalone_pdf';
            
            let fileSection = '';
            if (isStandalonePdf) {
                fileSection = `
                <div class="bg-blue-50 rounded-lg p-3 border border-blue-200">
                    <p class="text-xs font-semibold text-blue-600 uppercase mb-1">PDF File (Standalone Upload)</p>
                    <code class="text-sm text-gray-800">${ecg.pdf_file}</code>
                </div>`;
            } else {
                fileSection = `
                <div class="bg-gray-50 rounded-lg p-3">
                    <p class="text-xs font-semibold text-gray-500 uppercase mb-1">XML File</p>
                    <code class="text-sm text-gray-800">${ecg.xml_file}</code>
                </div>
                ${ecg.pdf_file ? `
                <div class="bg-gray-50 rounded-lg p-3">
                    <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Extracted PDF</p>
                    <code class="text-sm text-gray-800">${ecg.pdf_file}</code>
                </div>
                ` : ''}`;
            }
            
            let patientSection = '';
            if (ecg.patient) {
                patientSection = `
                <div class="bg-green-50 rounded-lg p-3 border border-green-200">
                    <p class="text-xs font-semibold text-green-600 uppercase mb-1">Matched Patient</p>
                    <p class="text-sm font-medium text-gray-800">${ecg.patient.name}</p>
                    <p class="text-xs text-gray-500">MRN: ${ecg.patient.mrn}</p>
                </div>`;
            } else if (isStandalonePdf) {
                patientSection = `
                <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
                    <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Patient Status</p>
                    <p class="text-sm text-gray-600">Standalone PDF upload (no patient data)</p>
                </div>`;
            } else {
                patientSection = `
                <div class="bg-amber-50 rounded-lg p-3 border border-amber-200">
                    <p class="text-xs font-semibold text-amber-600 uppercase mb-1">Patient Status</p>
                    <p class="text-sm text-amber-800">No patient found with MRN: ${ecg.mrn || 'N/A'}</p>
                </div>`;
            }
            
            content.innerHTML = `
                ${fileSection}
                ${!isStandalonePdf ? `
                <div class="bg-gray-50 rounded-lg p-3">
                    <p class="text-xs font-semibold text-gray-500 uppercase mb-1">MRN (Patient ID)</p>
                    <code class="text-sm text-gray-800">${ecg.mrn || 'N/A'}</code>
                </div>
                ` : ''}
                <div class="bg-gray-50 rounded-lg p-3">
                    <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Timestamp</p>
                    <span class="text-sm text-gray-800">${ecg.timestamp}</span>
                </div>
                ${patientSection}
            `;
            document.getElementById('detailsModal').classList.remove('hidden');
        }
    </script>
</x-app-layout>
