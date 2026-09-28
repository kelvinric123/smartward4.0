{{-- HL7 Logs tab: the raw message log with its duration/status filters. --}}
    <!-- Recent HL7 Logs Section -->
    <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-blue-100">
        <div class="p-6 border-b border-blue-100 bg-gradient-to-r from-brand-50 to-accent-50">
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
