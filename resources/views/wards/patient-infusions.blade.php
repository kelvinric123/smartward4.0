<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Infusion Details</title>
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
    </style>
    <meta http-equiv="refresh" content="30">
</head>
<body class="bg-gray-50">
    <div class="p-4">
        @if($patient)
            <!-- Active Infusions -->
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center">
                    <div class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></div>
                    Active Infusions ({{ $activeInfusions->count() }})
                </h3>
                
                @if($activeInfusions->count() > 0)
                    <div class="space-y-3">
                        @foreach($activeInfusions as $infusion)
                            @php
                                $statusColors = [
                                    'running' => 'border-green-400 bg-green-50',
                                    'paused' => 'border-yellow-400 bg-yellow-50',
                                    'alarming' => 'border-red-500 bg-red-50',
                                ];
                            @endphp
                            <div class="border-2 rounded-xl p-4 {{ $statusColors[$infusion->status] ?? 'border-gray-200 bg-white' }} {{ $infusion->is_warning ? 'pulse-warning' : '' }}">
                                <div class="flex items-start justify-between mb-3">
                                    <div>
                                        <div class="font-bold text-gray-800">{{ $infusion->medication_name }}</div>
                                        <div class="text-xs text-gray-500">
                                            Pump: {{ $infusion->infusionPump->device_id ?? 'Unknown' }}
                                            @if($infusion->medication_code)
                                                • Code: {{ $infusion->medication_code }}
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        @if($infusion->is_warning)
                                            <span class="px-2 py-1 bg-amber-500 text-white text-xs font-bold rounded-lg flex items-center">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                                </svg>
                                                FINISHING SOON
                                            </span>
                                        @endif
                                        <span class="px-2 py-1 {{ $infusion->status === 'running' ? 'bg-green-500' : ($infusion->status === 'paused' ? 'bg-yellow-500' : 'bg-red-500') }} text-white text-xs font-bold rounded-lg uppercase">
                                            {{ $infusion->status }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Progress -->
                                @if($infusion->total_volume > 0)
                                    <div class="mb-3">
                                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                                            <span>Progress: {{ number_format($infusion->infused_volume, 1) }} / {{ number_format($infusion->total_volume, 1) }} ml</span>
                                            <span class="font-bold">{{ $infusion->progress_percent }}%</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                                            <div class="h-3 rounded-full transition-all duration-500 {{ $infusion->is_warning ? 'bg-amber-500' : 'bg-green-500' }}" 
                                                 style="width: {{ $infusion->progress_percent }}%"></div>
                                        </div>
                                    </div>
                                @endif

                                <!-- Stats Grid -->
                                <div class="grid grid-cols-4 gap-3 text-center text-sm">
                                    <div class="bg-white rounded-lg p-2 border border-gray-200">
                                        <div class="text-xs text-gray-500">Flow Rate</div>
                                        <div class="font-bold text-gray-800">{{ $infusion->flow_rate ? number_format($infusion->flow_rate, 1) : '--' }}</div>
                                        <div class="text-xs text-gray-400">ml/hr</div>
                                    </div>
                                    <div class="bg-white rounded-lg p-2 border {{ $infusion->is_warning ? 'border-amber-400 bg-amber-50' : 'border-gray-200' }}">
                                        <div class="text-xs {{ $infusion->is_warning ? 'text-amber-700' : 'text-gray-500' }}">Remaining</div>
                                        <div class="font-bold {{ $infusion->is_warning ? 'text-amber-700' : 'text-gray-800' }}">{{ $infusion->formatted_remaining_time }}</div>
                                        <div class="text-xs {{ $infusion->is_warning ? 'text-amber-600' : 'text-gray-400' }}">hrs:min</div>
                                    </div>
                                    <div class="bg-white rounded-lg p-2 border border-gray-200">
                                        <div class="text-xs text-gray-500">Remaining Vol</div>
                                        <div class="font-bold text-gray-800">{{ $infusion->remaining_volume ? number_format($infusion->remaining_volume, 1) : '--' }}</div>
                                        <div class="text-xs text-gray-400">ml</div>
                                    </div>
                                    <div class="bg-white rounded-lg p-2 border border-gray-200">
                                        <div class="text-xs text-gray-500">Started</div>
                                        <div class="font-bold text-gray-800 text-xs">{{ $infusion->started_at ? $infusion->started_at->format('H:i') : '--' }}</div>
                                        <div class="text-xs text-gray-400">{{ $infusion->started_at ? $infusion->started_at->format('M d') : '' }}</div>
                                    </div>
                                </div>

                                <!-- Alarm Info -->
                                @if($infusion->status === 'alarming' && $infusion->alarm_message)
                                    <div class="mt-3 bg-red-100 border border-red-300 rounded-lg p-3 text-sm text-red-700 flex items-start">
                                        <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                        </svg>
                                        <div>
                                            <strong class="uppercase">{{ $infusion->alarm_type ? str_replace('_', ' ', $infusion->alarm_type) : 'ALARM' }}:</strong>
                                            {{ $infusion->alarm_message }}
                                        </div>
                                    </div>
                                @endif

                                <div class="mt-2 text-xs text-gray-400 text-right">
                                    Last update: {{ $infusion->last_updated_at ? $infusion->last_updated_at->diffForHumans() : 'N/A' }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="border border-dashed border-gray-300 rounded-xl p-6 text-center">
                        <svg class="w-10 h-10 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                        </svg>
                        <p class="text-sm text-gray-500">No active infusions for this patient.</p>
                    </div>
                @endif
            </div>

            <!-- Completed Infusions -->
            @if($completedInfusions->count() > 0)
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center">
                        <div class="w-2 h-2 bg-blue-500 rounded-full mr-2"></div>
                        Completed Infusions ({{ $completedInfusions->count() }})
                    </h3>
                    
                    <div class="overflow-x-auto border border-gray-200 rounded-xl">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700 border-b">Medication</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700 border-b">Volume</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700 border-b">Rate</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700 border-b">Started</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700 border-b">Completed</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-700 border-b">Pump</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($completedInfusions->take(10) as $infusion)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 py-2 border-b">
                                            <div class="font-medium text-gray-800">{{ $infusion->medication_name }}</div>
                                            @if($infusion->medication_code)
                                                <div class="text-xs text-gray-400">{{ $infusion->medication_code }}</div>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 border-b text-gray-600">
                                            {{ number_format($infusion->total_volume, 1) }} ml
                                        </td>
                                        <td class="px-3 py-2 border-b text-gray-600">
                                            {{ $infusion->flow_rate ? number_format($infusion->flow_rate, 1) . ' ml/hr' : '-' }}
                                        </td>
                                        <td class="px-3 py-2 border-b text-gray-600">
                                            {{ $infusion->started_at ? $infusion->started_at->format('M d, H:i') : '-' }}
                                        </td>
                                        <td class="px-3 py-2 border-b text-gray-600">
                                            {{ $infusion->completed_at ? $infusion->completed_at->format('M d, H:i') : '-' }}
                                        </td>
                                        <td class="px-3 py-2 border-b text-gray-600">
                                            <code class="text-xs bg-gray-100 px-1 py-0.5 rounded">{{ $infusion->infusionPump->device_id ?? '-' }}</code>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @else
            <div class="text-center py-8">
                <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                <p class="text-gray-500">Patient not found.</p>
            </div>
        @endif
    </div>
</body>
</html>






























