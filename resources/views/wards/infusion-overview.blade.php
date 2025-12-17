<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ward Infusion Overview</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Inter', sans-serif; }
        @keyframes pulse-warning {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        .pulse-warning {
            animation: pulse-warning 2s ease-in-out infinite;
        }
        @keyframes blink-alarm {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0.3; }
        }
        .blink-alarm {
            animation: blink-alarm 1s steps(1) infinite;
        }
    </style>
    <meta http-equiv="refresh" content="30">
</head>
<body class="bg-gray-50">
    <div class="p-4" x-data="{ filter: '{{ $filter }}', wardId: '{{ $wardId }}' }">
        <!-- Header with Stats -->
        <div class="mb-4">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center">
                    <div class="p-2 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-lg mr-3">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-gray-800">Ward Infusion Overview</h2>
                        <p class="text-xs text-gray-500">Real-time infusion pump monitoring</p>
                    </div>
                </div>
                <div class="text-xs text-gray-400">
                    Auto-refreshes every 30s
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="grid grid-cols-5 gap-2 mb-4">
                <a href="?ward_id={{ $wardId }}&filter=active" 
                   class="p-3 rounded-lg text-center transition-all {{ $filter === 'active' ? 'bg-green-100 border-2 border-green-500' : 'bg-white border border-gray-200 hover:border-green-300' }}">
                    <div class="text-xl font-bold text-green-600">{{ $stats['running'] }}</div>
                    <div class="text-xs text-gray-600">Running</div>
                </a>
                <a href="?ward_id={{ $wardId }}&filter=active" 
                   class="p-3 rounded-lg text-center transition-all {{ $filter === 'active' ? 'bg-yellow-100 border-2 border-yellow-500' : 'bg-white border border-gray-200 hover:border-yellow-300' }}">
                    <div class="text-xl font-bold text-yellow-600">{{ $stats['paused'] }}</div>
                    <div class="text-xs text-gray-600">Paused</div>
                </a>
                <a href="?ward_id={{ $wardId }}&filter=warnings" 
                   class="p-3 rounded-lg text-center transition-all {{ $filter === 'warnings' ? 'bg-amber-100 border-2 border-amber-500' : 'bg-white border border-gray-200 hover:border-amber-300' }}">
                    <div class="text-xl font-bold text-amber-600 {{ $stats['warnings'] > 0 ? 'pulse-warning' : '' }}">{{ $stats['warnings'] }}</div>
                    <div class="text-xs text-gray-600">Warnings</div>
                </a>
                <a href="?ward_id={{ $wardId }}&filter=alarms" 
                   class="p-3 rounded-lg text-center transition-all {{ $filter === 'alarms' ? 'bg-red-100 border-2 border-red-500' : 'bg-white border border-gray-200 hover:border-red-300' }}">
                    <div class="text-xl font-bold text-red-600 {{ $stats['alarms'] > 0 ? 'blink-alarm' : '' }}">{{ $stats['alarms'] }}</div>
                    <div class="text-xs text-gray-600">Alarms</div>
                </a>
                <a href="?ward_id={{ $wardId }}&filter=completed" 
                   class="p-3 rounded-lg text-center transition-all {{ $filter === 'completed' ? 'bg-blue-100 border-2 border-blue-500' : 'bg-white border border-gray-200 hover:border-blue-300' }}">
                    <div class="text-xl font-bold text-blue-600">{{ $stats['completed'] }}</div>
                    <div class="text-xs text-gray-600">Completed</div>
                </a>
            </div>
        </div>

        @if($infusions->count() > 0)
            <!-- Infusion Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($infusions as $infusion)
                    @php
                        $statusColors = [
                            'running' => 'border-green-400 bg-gradient-to-br from-green-50 to-emerald-50',
                            'paused' => 'border-yellow-400 bg-gradient-to-br from-yellow-50 to-amber-50',
                            'completed' => 'border-blue-400 bg-gradient-to-br from-blue-50 to-cyan-50',
                            'stopped' => 'border-gray-400 bg-gradient-to-br from-gray-50 to-slate-50',
                            'alarming' => 'border-red-500 bg-gradient-to-br from-red-50 to-rose-50 blink-alarm',
                            'pending' => 'border-gray-300 bg-white',
                        ];
                        $headerColors = [
                            'running' => 'from-green-500 to-emerald-500',
                            'paused' => 'from-yellow-500 to-amber-500',
                            'completed' => 'from-blue-500 to-cyan-500',
                            'stopped' => 'from-gray-500 to-slate-500',
                            'alarming' => 'from-red-500 to-rose-500',
                            'pending' => 'from-gray-400 to-gray-500',
                        ];
                    @endphp
                    <div class="rounded-xl border-2 overflow-hidden shadow-md {{ $statusColors[$infusion->status] ?? 'border-gray-200 bg-white' }} {{ $infusion->is_warning && $infusion->status === 'running' ? 'pulse-warning' : '' }}">
                        <!-- Header -->
                        <div class="px-3 py-2 bg-gradient-to-r {{ $headerColors[$infusion->status] ?? 'from-gray-400 to-gray-500' }} text-white flex items-center justify-between">
                            <div class="flex items-center">
                                <span class="font-bold text-sm">{{ $infusion->patient->bed_number ?? 'N/A' }}</span>
                                @if($infusion->is_warning)
                                    <span class="ml-2 px-2 py-0.5 bg-white/30 rounded text-xs font-bold flex items-center">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                        </svg>
                                        LOW
                                    </span>
                                @endif
                            </div>
                            <span class="text-xs bg-white/20 px-2 py-0.5 rounded uppercase font-semibold">
                                {{ $infusion->status }}
                            </span>
                        </div>

                        <!-- Content -->
                        <div class="p-3 space-y-2">
                            <!-- Patient Info -->
                            <div class="text-sm">
                                <div class="font-semibold text-gray-800 truncate">{{ $infusion->patient->name ?? 'Unknown' }}</div>
                                <div class="text-xs text-gray-500">MRN: {{ $infusion->patient->mrn ?? 'N/A' }}</div>
                            </div>

                            <!-- Medication -->
                            <div class="bg-white/70 rounded-lg p-2 border border-gray-200">
                                <div class="text-xs text-gray-500">Medication</div>
                                <div class="font-semibold text-gray-800 text-sm truncate">{{ $infusion->medication_name }}</div>
                            </div>

                            <!-- Progress Bar -->
                            @if($infusion->total_volume > 0)
                                <div>
                                    <div class="flex justify-between text-xs text-gray-600 mb-1">
                                        <span>{{ number_format($infusion->infused_volume, 1) }} / {{ number_format($infusion->total_volume, 1) }} ml</span>
                                        <span class="font-bold">{{ $infusion->progress_percent }}%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2.5 overflow-hidden">
                                        <div class="h-2.5 rounded-full transition-all duration-500 {{ $infusion->is_warning ? 'bg-amber-500' : ($infusion->status === 'running' ? 'bg-green-500' : 'bg-blue-500') }}" 
                                             style="width: {{ $infusion->progress_percent }}%"></div>
                                    </div>
                                </div>
                            @endif

                            <!-- Stats Row -->
                            <div class="grid grid-cols-3 gap-2 text-center">
                                <div class="bg-white/70 rounded p-1.5 border border-gray-100">
                                    <div class="text-xs text-gray-500">Rate</div>
                                    <div class="font-bold text-gray-800 text-sm">{{ $infusion->flow_rate ? number_format($infusion->flow_rate, 1) . ' ml/hr' : '--' }}</div>
                                </div>
                                <div class="bg-white/70 rounded p-1.5 border border-gray-100 {{ $infusion->is_warning ? 'bg-amber-100 border-amber-300' : '' }}">
                                    <div class="text-xs {{ $infusion->is_warning ? 'text-amber-700' : 'text-gray-500' }}">Remaining</div>
                                    <div class="font-bold {{ $infusion->is_warning ? 'text-amber-700' : 'text-gray-800' }} text-sm">{{ $infusion->formatted_remaining_time }}</div>
                                </div>
                                <div class="bg-white/70 rounded p-1.5 border border-gray-100">
                                    <div class="text-xs text-gray-500">Pump</div>
                                    <div class="font-bold text-gray-800 text-xs truncate">{{ $infusion->infusionPump->device_id ?? 'N/A' }}</div>
                                </div>
                            </div>

                            <!-- Alarm Message -->
                            @if($infusion->status === 'alarming' && $infusion->alarm_message)
                                <div class="bg-red-100 border border-red-300 rounded-lg p-2 text-xs text-red-700 flex items-start">
                                    <svg class="w-4 h-4 mr-1 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                    </svg>
                                    <div>
                                        <strong>{{ $infusion->alarm_type ? ucfirst(str_replace('_', ' ', $infusion->alarm_type)) : 'ALARM' }}:</strong>
                                        {{ $infusion->alarm_message }}
                                    </div>
                                </div>
                            @endif

                            <!-- Last Updated -->
                            <div class="text-xs text-gray-400 text-right">
                                Updated: {{ $infusion->last_updated_at ? $infusion->last_updated_at->diffForHumans() : 'N/A' }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <!-- Empty State -->
            <div class="bg-white rounded-xl border border-gray-200 p-8 text-center">
                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-700 mb-1">No Infusions Found</h3>
                <p class="text-sm text-gray-500">
                    @if($filter === 'active')
                        There are no active infusions in this ward.
                    @elseif($filter === 'completed')
                        No completed infusions to display.
                    @elseif($filter === 'warnings')
                        No infusion warnings at this time.
                    @elseif($filter === 'alarms')
                        No active alarms - all systems normal.
                    @else
                        No infusion data available.
                    @endif
                </p>
                <p class="text-xs text-gray-400 mt-2">Infusions will appear here when pump gateways send data.</p>
            </div>
        @endif
    </div>
</body>
</html>






























