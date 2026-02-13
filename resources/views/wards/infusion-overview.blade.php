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
    @if($tab === 'infusions')
        <meta http-equiv="refresh" content="30">
    @endif
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="bg-gray-50">
    <div class="p-4" x-data="{ filter: '{{ $filter }}', wardId: '{{ $wardId }}', tab: '{{ $tab }}', showUnbindModal: false, unbindPumpId: null, unbindPumpName: '', unbindPumpHasActiveInfusion: false }">
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
                @if($tab === 'infusions')
                    <div class="text-xs text-gray-400">
                        Auto-refreshes every 30s
                    </div>
                @endif
            </div>
            
            <!-- Tab Navigation -->
            <div class="flex items-center space-x-2 mb-4 border-b border-gray-200">
                <a href="?ward_id={{ $wardId }}&tab=infusions&filter={{ $filter }}" 
                   class="px-4 py-2 text-sm font-semibold transition-all {{ $tab === 'infusions' ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-600 hover:text-gray-800' }}">
                    Infusions
                </a>
                <a href="?ward_id={{ $wardId }}&tab=devices" 
                   class="px-4 py-2 text-sm font-semibold transition-all {{ $tab === 'devices' ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-600 hover:text-gray-800' }}">
                    Devices
                </a>
            </div>

            <!-- Quick Stats (Only show on Infusions tab) -->
            <div x-show="tab === 'infusions'" class="grid grid-cols-5 gap-2 mb-4">
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

        <!-- Infusions Tab Content -->
        <div x-show="tab === 'infusions'">
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
                                <div class="flex justify-between items-start">
                                    <div class="flex-1 min-w-0">
                                        <div class="text-xs text-gray-500">Medication</div>
                                        <div class="font-semibold text-gray-800 text-sm truncate">{{ $infusion->medication_name }}</div>
                                        @if($infusion->formatted_concentration)
                                            <div class="text-xs text-gray-500">{{ $infusion->formatted_concentration }}</div>
                                        @endif
                                    </div>
                                    @if($infusion->delivery_mode)
                                        <span class="ml-2 px-1.5 py-0.5 bg-purple-100 text-purple-700 text-xs rounded">
                                            {{ $infusion->delivery_mode_display }}
                                        </span>
                                    @endif
                                </div>
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
                            <div class="grid grid-cols-4 gap-2 text-center">
                                <div class="bg-white/70 rounded p-1.5 border border-gray-100">
                                    <div class="text-xs text-gray-500">Rate</div>
                                    <div class="font-bold text-gray-800 text-sm">{{ $infusion->flow_rate ? number_format($infusion->flow_rate, 1) . ' ml/hr' : '--' }}</div>
                                </div>
                                <div class="bg-white/70 rounded p-1.5 border border-gray-100 {{ $infusion->is_warning ? 'bg-amber-100 border-amber-300' : '' }}">
                                    <div class="text-xs {{ $infusion->is_warning ? 'text-amber-700' : 'text-gray-500' }}">Remaining</div>
                                    <div class="font-bold {{ $infusion->is_warning ? 'text-amber-700' : 'text-gray-800' }} text-sm">{{ $infusion->formatted_remaining_time }}</div>
                                    @if($infusion->estimated_completion)
                                        <div class="text-xs {{ $infusion->is_warning ? 'text-amber-600' : 'text-gray-400' }}">ETA {{ $infusion->estimated_completion->format('H:i') }}</div>
                                    @endif
                                </div>
                                <div class="bg-white/70 rounded p-1.5 border border-gray-100">
                                    <div class="text-xs text-gray-500">Syringe</div>
                                    @if($infusion->syringe_size)
                                        <div class="font-bold text-gray-800 text-sm">{{ number_format($infusion->syringe_size, 0) }}mL</div>
                                        @if($infusion->syringe_actual_volume && $infusion->syringe_actual_volume != $infusion->syringe_size)
                                            <div class="text-xs text-blue-600">{{ number_format($infusion->syringe_actual_volume, 1) }}mL actual</div>
                                        @endif
                                    @else
                                        <div class="font-bold text-gray-800 text-xs truncate">{{ $infusion->infusionPump->device_id ?? 'N/A' }}</div>
                                    @endif
                                </div>
                                <div class="bg-white/70 rounded p-1.5 border border-gray-100">
                                    <div class="text-xs text-gray-500">Brand</div>
                                    <div class="font-bold text-gray-800 text-xs truncate" title="{{ $infusion->syringe_manufacturer ?? 'N/A' }}">
                                        {{ $infusion->syringe_manufacturer ? \Illuminate\Support\Str::limit($infusion->syringe_manufacturer, 12) : '--' }}
                                    </div>
                                </div>
                            </div>

                            <!-- Pump Info with Battery/Power Status -->
                            @if($infusion->infusionPump)
                                @php $pump = $infusion->infusionPump; @endphp
                                <div class="flex items-center justify-between text-xs bg-gray-50 rounded p-1.5 border border-gray-100">
                                    <div class="flex items-center space-x-2">
                                        <!-- Pump Model -->
                                        <span class="text-gray-600 truncate max-w-[100px]" title="{{ $pump->pump_model ?? $pump->device_id }}">
                                            {{ $pump->pump_model ? \Illuminate\Support\Str::limit($pump->pump_model, 20) : $pump->device_id }}
                                        </span>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <!-- Power/Battery Status -->
                                        @if($pump->power_status === 'mains')
                                            <span class="flex items-center text-green-600" title="Plugged In">
                                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/>
                                                </svg>
                                            </span>
                                        @elseif($pump->battery_percent !== null)
                                            @php
                                                $batteryColor = $pump->battery_percent >= 50 ? 'text-green-600' : ($pump->battery_percent >= 20 ? 'text-yellow-600' : 'text-red-600');
                                            @endphp
                                            <span class="flex items-center {{ $batteryColor }}" title="Battery: {{ $pump->battery_percent }}%">
                                                <svg class="w-3.5 h-3.5 mr-0.5" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M2 6h12v8H2V6zm14 2h1.5a.5.5 0 01.5.5v3a.5.5 0 01-.5.5H16V8z"/>
                                                    <path fill-rule="evenodd" d="M3 7h10v6H3V7z" clip-rule="evenodd" style="opacity: {{ $pump->battery_percent / 100 }}"/>
                                                </svg>
                                                <span class="text-xs">{{ $pump->battery_percent }}%</span>
                                            </span>
                                        @endif
                                        
                                        <!-- WiFi Strength -->
                                        @if($pump->wifi_strength !== null)
                                            @php
                                                $wifiColor = $pump->wifi_strength >= 60 ? 'text-green-600' : ($pump->wifi_strength >= 40 ? 'text-yellow-600' : 'text-red-600');
                                            @endphp
                                            <span class="flex items-center {{ $wifiColor }}" title="WiFi: {{ $pump->wifi_strength }}%">
                                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M17.778 8.222c-4.296-4.296-11.26-4.296-15.556 0A1 1 0 01.808 6.808c5.076-5.077 13.308-5.077 18.384 0a1 1 0 01-1.414 1.414zM14.95 11.05a7 7 0 00-9.9 0 1 1 0 01-1.414-1.414 9 9 0 0112.728 0 1 1 0 01-1.414 1.414zM12.12 13.88a3 3 0 00-4.242 0 1 1 0 01-1.415-1.415 5 5 0 017.072 0 1 1 0 01-1.415 1.415zM9 16a1 1 0 011-1h.01a1 1 0 110 2H10a1 1 0 01-1-1z" clip-rule="evenodd"/>
                                                </svg>
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            <!-- Alarm Message -->
                            @if($infusion->status === 'alarming' && $infusion->alarm_message)
                                @php
                                    $alarmBgColor = match($infusion->alarm_priority) {
                                        'high' => 'bg-red-100 border-red-400 text-red-800',
                                        'medium' => 'bg-orange-100 border-orange-400 text-orange-800',
                                        'low' => 'bg-yellow-100 border-yellow-400 text-yellow-800',
                                        'technical' => 'bg-blue-100 border-blue-400 text-blue-800',
                                        default => 'bg-red-100 border-red-300 text-red-700',
                                    };
                                @endphp
                                <div class="{{ $alarmBgColor }} border rounded-lg p-2 text-xs flex items-start">
                                    <svg class="w-4 h-4 mr-1 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                    </svg>
                                    <div>
                                        @if($infusion->alarm_priority)
                                            <span class="font-bold uppercase text-[10px]">{{ $infusion->alarm_priority_display }}</span> • 
                                        @endif
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
        
        <!-- Recently Completed Section (Only show when filter is 'active' and there are completed infusions) -->
        @if($filter === 'active' && isset($recentlyCompleted) && $recentlyCompleted->count() > 0)
            <div class="mt-8 border-t-2 border-dashed border-gray-200 pt-6">
                <h3 class="text-lg font-bold text-gray-700 mb-4 flex items-center">
                    <span class="w-3 h-3 bg-blue-500 rounded-full mr-2"></span>
                    Recently Completed Infusions (Last 24h)
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($recentlyCompleted as $infusion)
                        <div class="rounded-xl border border-gray-200 bg-gray-50 opacity-80 hover:opacity-100 transition-opacity">
                            <!-- Header -->
                            <div class="px-3 py-2 bg-gradient-to-r from-blue-500 to-cyan-500 text-white flex items-center justify-between rounded-t-xl">
                                <div class="flex items-center">
                                    <span class="font-bold text-sm">{{ $infusion->patient->bed_number ?? 'N/A' }}</span>
                                </div>
                                <span class="text-xs bg-white/20 px-2 py-0.5 rounded uppercase font-semibold">
                                    Completed
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
                                <div class="bg-white rounded-lg p-2 border border-gray-200">
                                    <div class="text-xs text-gray-500">Medication</div>
                                    <div class="font-semibold text-gray-800 text-sm truncate">{{ $infusion->medication_name }}</div>
                                    <div class="text-xs text-gray-500">
                                        {{ number_format($infusion->total_volume, 1) }} ml • Completed {{ $infusion->completed_at ? $infusion->completed_at->format('H:i') : '' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
        </div>

        <!-- Devices Tab Content -->
        <div x-show="tab === 'devices'">
            <div class="bg-white rounded-xl shadow-md border border-gray-200">
                <div class="p-4 border-b border-gray-200 bg-gradient-to-r from-purple-50 to-indigo-50">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="p-2 bg-purple-500 rounded-lg mr-3">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-800">Registered Devices</h3>
                                <p class="text-xs text-gray-500">All infusion pumps and their bindings</p>
                            </div>
                        </div>
                        <div class="text-sm font-semibold text-gray-600">
                            {{ $pumps->count() }} {{ $pumps->count() === 1 ? 'Device' : 'Devices' }}
                        </div>
                    </div>
                </div>
                <div class="p-4">
                    @if($pumps->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Device</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Asset No</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Serial No</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Bed</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Patient</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">MRN</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Linked At</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($pumps as $pump)
                                        <tr class="hover:bg-gray-50 {{ $pump->patient_id ? 'bg-green-50/30' : '' }}">
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="p-1.5 {{ $pump->patient_id ? 'bg-green-100' : 'bg-gray-100' }} rounded mr-2">
                                                        <svg class="w-4 h-4 {{ $pump->patient_id ? 'text-green-600' : 'text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                                                        </svg>
                                                    </div>
                                                    <div>
                                                        @if($pump->device_name)
                                                            <div class="text-sm font-bold text-gray-900">{{ $pump->device_name }}</div>
                                                            <div class="text-xs text-gray-500">{{ $pump->device_id }}</div>
                                                        @else
                                                            <div class="text-sm font-bold text-gray-900">{{ $pump->device_id }}</div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                                {{ $pump->asset_no ?? '-' }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                                {{ $pump->serial_no ?? '-' }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                                @if($pump->patient && $pump->patient->bed_number)
                                                    <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-bold">
                                                        {{ $pump->patient->bed_number }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                                {{ $pump->patient->name ?? '-' }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                                {{ $pump->patient->mrn ?? '-' }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                                @if($pump->linked_at)
                                                    <span class="text-xs" title="{{ $pump->linked_at->format('Y-m-d H:i:s') }}">
                                                        {{ $pump->linked_at->diffForHumans() }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">Not linked</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                                @if($pump->patient_id)
                                                    @php
                                                        $hasActiveInfusion = \App\Models\Infusion::where('patient_id', $pump->patient_id)
                                                            ->where('infusion_pump_id', $pump->id)
                                                            ->active()
                                                            ->exists();
                                                    @endphp
                                                    <button 
                                                        @click="showUnbindModal = true; unbindPumpId = {{ $pump->id }}; unbindPumpName = '{{ $pump->device_id }}'; unbindPumpHasActiveInfusion = {{ $hasActiveInfusion ? 'true' : 'false' }}"
                                                        class="px-3 py-1.5 bg-red-100 hover:bg-red-200 text-red-700 rounded-lg text-xs font-medium transition-colors">
                                                        <svg class="w-3.5 h-3.5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                        </svg>
                                                        Unbind
                                                    </button>
                                                @else
                                                    <span class="text-gray-400 text-xs">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-8 text-gray-500">
                            <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                            </svg>
                            <p class="text-sm font-medium">No devices registered</p>
                            <p class="text-xs text-gray-400 mt-1">Devices will appear here when they are registered</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Unbind Confirmation Modal -->
        <div x-show="showUnbindModal" 
             x-cloak 
             class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
             @click.self="showUnbindModal = false">
            <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 p-6" @click.stop>
                <div class="flex items-center mb-4">
                    <div class="p-3 bg-red-100 rounded-full mr-4">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Confirm Unbind</h3>
                        <p class="text-sm text-gray-500">This action will remove the pump binding</p>
                    </div>
                </div>
                <!-- Warning for Active Infusion -->
                <div x-show="unbindPumpHasActiveInfusion" class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                    <p class="text-sm text-red-800 flex items-start">
                        <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <span>
                            <strong>CRITICAL WARNING:</strong> This pump has an <strong>ACTIVE INFUSION</strong>. Unbinding it will automatically mark the infusion as <strong>COMPLETED</strong>.
                        </span>
                    </p>
                </div>
                <!-- Standard Warning -->
                <div x-show="!unbindPumpHasActiveInfusion" class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4">
                    <p class="text-sm text-amber-800">
                        <strong>Warning:</strong> Unbinding this pump will remove the patient association and may affect ongoing infusion tracking.
                    </p>
                </div>
                <p class="text-sm text-gray-700 mb-6">
                    Are you sure you want to unbind pump <strong x-text="unbindPumpName"></strong> from the patient?
                </p>
                <div class="flex space-x-3">
                    <button 
                        @click="showUnbindModal = false"
                        class="flex-1 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-medium transition-colors">
                        Cancel
                    </button>
                    <button 
                        @click="unbindPump(unbindPumpId)"
                        class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition-colors">
                        Unbind
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        async function unbindPump(pumpId) {
            try {
                const response = await fetch(`{{ url('ward-dashboard/unlink-pump') }}/${pumpId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    }
                });

                const data = await response.json();
                
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Failed to unbind pump');
                }
            } catch (error) {
                alert('An error occurred. Please try again.');
            }
        }
    </script>
</body>
</html>
































