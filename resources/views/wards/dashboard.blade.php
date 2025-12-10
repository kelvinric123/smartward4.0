<x-app-layout>
    <style>
        @keyframes pulse-subtle {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.85; }
        }
        .animate-pulse-subtle {
            animation: pulse-subtle 2s ease-in-out infinite;
        }
        /* Fullscreen text size classes */
        .fullscreen-text-small .bed-card { font-size: 0.75rem; }
        .fullscreen-text-small .bed-card .text-sm { font-size: 0.7rem; }
        .fullscreen-text-small .bed-card .text-xs { font-size: 0.6rem; }
        .fullscreen-text-small .bed-card h3, .fullscreen-text-small .bed-card .font-bold { font-size: 0.8rem; }
        
        .fullscreen-text-medium .bed-card { font-size: 0.875rem; }
        .fullscreen-text-medium .bed-card .text-sm { font-size: 0.8rem; }
        .fullscreen-text-medium .bed-card .text-xs { font-size: 0.7rem; }
        
        .fullscreen-text-large .bed-card { font-size: 1rem; }
        .fullscreen-text-large .bed-card .text-sm { font-size: 0.95rem; }
        .fullscreen-text-large .bed-card .text-xs { font-size: 0.8rem; }
        .fullscreen-text-large .bed-card h3, .fullscreen-text-large .bed-card .font-bold { font-size: 1.1rem; }
    </style>
    <x-slot name="header">
        <div class="flex items-center justify-between" x-data="{ 
            customFullscreen: localStorage.getItem('wardDashboardFullscreen') === 'true',
            init() {
                // Dispatch initial state on load if fullscreen is saved
                if (this.customFullscreen) {
                    this.$nextTick(() => {
                        window.dispatchEvent(new CustomEvent('toggle-custom-fullscreen', { 
                            detail: { enabled: true } 
                        }));
                    });
                }
            }
        }">
            <div class="flex items-center space-x-4">
                <div>
                    <h2 class="font-bold text-2xl text-gray-800 leading-tight">
                        Ward Dashboard
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">Real-time bed and patient management</p>
                </div>
                <!-- Ward Selector -->
                <div class="relative">
                    <form method="GET" action="{{ route('ward.dashboard') }}">
                        <select name="ward_id" onchange="this.form.submit()" class="px-4 py-2 bg-white border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-semibold text-gray-700">
                            @if(count($wards) > 0)
                                @foreach($wards as $ward)
                                    <option value="{{ $ward->id }}" {{ request('ward_id') == $ward->id ? 'selected' : (empty(request('ward_id')) && $loop->first ? 'selected' : '') }}>
                                        {{ $ward->ward_name }} - {{ $ward->specialties }}
                                    </option>
                                @endforeach
                            @else
                                <option value="">No wards available</option>
                            @endif
                        </select>
                    </form>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <!-- Live DateTime Display - Click to open timezone settings -->
                <div x-data="dashboardClock()" x-init="init()" class="relative">
                    <button @click="showTimezoneModal = true" 
                            class="flex items-center space-x-2 px-3 py-2 bg-white/50 hover:bg-white/80 rounded-lg border border-gray-200 shadow-sm transition-all cursor-pointer group">
                        <svg class="w-4 h-4 text-gray-500 group-hover:text-blue-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div class="text-sm text-gray-600 group-hover:text-gray-800 transition-colors">
                            <span x-text="formattedDate" class="font-medium"></span>
                            <span class="mx-1 text-gray-400">|</span>
                            <span x-text="formattedTime" class="font-bold text-blue-600"></span>
                        </div>
                        <svg class="w-3 h-3 text-gray-400 group-hover:text-blue-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </button>

                    <!-- Timezone Settings Modal -->
                    <div x-show="showTimezoneModal" 
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         class="fixed inset-0 z-50 overflow-y-auto" 
                         style="display: none;">
                        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                            <div class="fixed inset-0 transition-opacity bg-gray-900/60 backdrop-blur-sm" @click="showTimezoneModal = false"></div>
                            
                            <div class="inline-block w-full max-w-lg p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-2xl sm:my-16">
                                <!-- Modal Header -->
                                <div class="flex items-center justify-between mb-6">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-cyan-500 rounded-xl flex items-center justify-center shadow-lg">
                                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 class="text-xl font-bold text-gray-900">Date & Time Settings</h3>
                                            <p class="text-sm text-gray-500">Configure timezone and sync options</p>
                                        </div>
                                    </div>
                                    <button @click="showTimezoneModal = false" class="p-2 hover:bg-gray-100 rounded-lg transition-colors">
                                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>

                                <!-- Current Time Display -->
                                <div class="bg-gradient-to-r from-blue-50 to-cyan-50 rounded-xl p-5 mb-6 border border-blue-100">
                                    <div class="text-center">
                                        <div class="text-4xl font-bold text-gray-800 tracking-wider" x-text="formattedTime"></div>
                                        <div class="text-lg text-gray-600 mt-1" x-text="formattedDate"></div>
                                        <div class="flex items-center justify-center mt-3 space-x-2">
                                            <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm font-medium" x-text="selectedTimezone"></span>
                                            <span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs font-medium flex items-center">
                                                <span class="w-2 h-2 bg-green-500 rounded-full mr-1 animate-pulse"></span>
                                                Live
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Timezone Selection -->
                                <div class="mb-6">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <svg class="w-4 h-4 inline mr-1 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        Select Timezone
                                    </label>
                                    <select x-model="selectedTimezone" @change="saveTimezone()" 
                                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white shadow-sm text-gray-700">
                                        <optgroup label="Southeast Asia">
                                            <option value="Asia/Kuala_Lumpur">Malaysia (GMT+8) - Kuala Lumpur</option>
                                            <option value="Asia/Singapore">Singapore (GMT+8)</option>
                                            <option value="Asia/Bangkok">Thailand (GMT+7) - Bangkok</option>
                                            <option value="Asia/Jakarta">Indonesia (GMT+7) - Jakarta</option>
                                            <option value="Asia/Manila">Philippines (GMT+8) - Manila</option>
                                            <option value="Asia/Ho_Chi_Minh">Vietnam (GMT+7) - Ho Chi Minh</option>
                                        </optgroup>
                                        <optgroup label="East Asia">
                                            <option value="Asia/Hong_Kong">Hong Kong (GMT+8)</option>
                                            <option value="Asia/Shanghai">China (GMT+8) - Shanghai</option>
                                            <option value="Asia/Tokyo">Japan (GMT+9) - Tokyo</option>
                                            <option value="Asia/Seoul">South Korea (GMT+9) - Seoul</option>
                                            <option value="Asia/Taipei">Taiwan (GMT+8) - Taipei</option>
                                        </optgroup>
                                        <optgroup label="South Asia">
                                            <option value="Asia/Kolkata">India (GMT+5:30) - Kolkata</option>
                                            <option value="Asia/Dhaka">Bangladesh (GMT+6) - Dhaka</option>
                                            <option value="Asia/Karachi">Pakistan (GMT+5) - Karachi</option>
                                        </optgroup>
                                        <optgroup label="Middle East">
                                            <option value="Asia/Dubai">UAE (GMT+4) - Dubai</option>
                                            <option value="Asia/Riyadh">Saudi Arabia (GMT+3) - Riyadh</option>
                                        </optgroup>
                                        <optgroup label="Oceania">
                                            <option value="Australia/Sydney">Australia (GMT+10/11) - Sydney</option>
                                            <option value="Australia/Perth">Australia (GMT+8) - Perth</option>
                                            <option value="Pacific/Auckland">New Zealand (GMT+12/13) - Auckland</option>
                                        </optgroup>
                                        <optgroup label="Europe">
                                            <option value="Europe/London">UK (GMT+0/1) - London</option>
                                            <option value="Europe/Paris">France (GMT+1/2) - Paris</option>
                                            <option value="Europe/Berlin">Germany (GMT+1/2) - Berlin</option>
                                        </optgroup>
                                        <optgroup label="Americas">
                                            <option value="America/New_York">USA Eastern (GMT-5/-4) - New York</option>
                                            <option value="America/Los_Angeles">USA Pacific (GMT-8/-7) - Los Angeles</option>
                                            <option value="America/Chicago">USA Central (GMT-6/-5) - Chicago</option>
                                        </optgroup>
                                        <optgroup label="UTC">
                                            <option value="UTC">UTC (GMT+0)</option>
                                        </optgroup>
                                    </select>
                                </div>

                                <!-- Time Sync Options -->
                                <div class="mb-6">
                                    <label class="block text-sm font-semibold text-gray-700 mb-3">
                                        <svg class="w-4 h-4 inline mr-1 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                        </svg>
                                        Time Synchronization
                                    </label>
                                    
                                    <div class="space-y-3">
                                        <!-- Use Internet Time -->
                                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl border border-gray-200 hover:border-blue-300 transition-colors">
                                            <div class="flex items-center space-x-3">
                                                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                                                    </svg>
                                                </div>
                                                <div>
                                                    <div class="font-medium text-gray-800">Use Internet Time</div>
                                                    <div class="text-xs text-gray-500">Sync with NTP server automatically</div>
                                                </div>
                                            </div>
                                            <label class="relative inline-flex items-center cursor-pointer">
                                                <input type="checkbox" x-model="useInternetTime" @change="toggleInternetTime()" class="sr-only peer">
                                                <div class="w-11 h-6 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                            </label>
                                        </div>

                                        <!-- Sync Now Button -->
                                        <button @click="syncNow()" 
                                                :disabled="syncing"
                                                class="w-full flex items-center justify-center space-x-2 px-4 py-3 bg-gradient-to-r from-blue-500 to-cyan-500 text-white rounded-xl font-medium shadow-lg hover:shadow-xl transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                                            <svg :class="{'animate-spin': syncing}" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                            </svg>
                                            <span x-text="syncing ? 'Syncing...' : 'Sync Now'"></span>
                                        </button>

                                        <!-- Last Sync Info -->
                                        <div x-show="lastSync" class="text-center text-xs text-gray-500">
                                            <span>Last synced: </span>
                                            <span x-text="lastSync" class="font-medium"></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Server Time Info -->
                                <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl">
                                    <div class="flex items-start space-x-3">
                                        <svg class="w-5 h-5 text-amber-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <div class="text-sm text-amber-800">
                                            <div class="font-medium">Server Timezone</div>
                                            <div class="text-xs mt-1">The server is configured to use <strong>{{ config('app.timezone', 'Asia/Kuala_Lumpur') }}</strong> timezone. This client-side setting only affects how time is displayed in your browser.</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Modal Footer -->
                                <div class="mt-6 flex justify-end">
                                    <button @click="showTimezoneModal = false" 
                                            class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-xl font-medium hover:bg-gray-200 transition-colors">
                                        Close
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Custom Fullscreen Toggle Button -->
                <button @click="
                    customFullscreen = !customFullscreen;
                    localStorage.setItem('wardDashboardFullscreen', customFullscreen);
                    window.dispatchEvent(new CustomEvent('toggle-custom-fullscreen', { 
                        detail: { enabled: customFullscreen } 
                    }));" 
                    class="p-2 bg-white hover:bg-gray-100 rounded-lg border border-gray-300 shadow-sm transition-colors"
                    :title="customFullscreen ? 'Exit Fullscreen' : 'Enter Fullscreen'">
                    <svg x-show="!customFullscreen" class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                    </svg>
                    <svg x-show="customFullscreen" class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
    </x-slot>

    @php
        // Fullscreen grid classes based on setting
        $fullscreenGridClasses = [
            'small' => 'grid-cols-1 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 2xl:grid-cols-8',
            'medium' => 'grid-cols-1 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 2xl:grid-cols-6',
            'large' => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-4',
        ];
        $fullscreenMode = $dashboardDisplay['fullscreen_mode'] ?? 'medium';
        $fullscreenGrid = $fullscreenGridClasses[$fullscreenMode] ?? $fullscreenGridClasses['medium'];
        
        // Text size classes for fullscreen mode
        $fullscreenTextSizeClasses = [
            'small' => 'fullscreen-text-small',
            'medium' => 'fullscreen-text-medium',
            'large' => 'fullscreen-text-large',
        ];
        $fullscreenTextSize = $dashboardDisplay['fullscreen_text_size'] ?? 'medium';
        $fullscreenTextClass = $fullscreenTextSizeClasses[$fullscreenTextSize] ?? $fullscreenTextSizeClasses['medium'];
        
        // Patient name masking function
        $maskPatientName = function($fullName) use ($dashboardDisplay) {
            $mask = $dashboardDisplay['patient_name_mask'] ?? 'full';
            if ($mask === 'full' || empty($fullName)) {
                return $fullName;
            }
            
            $parts = preg_split('/\s+/', trim($fullName));
            
            switch($mask) {
                case 'first_only':
                    return implode(' ', array_map(function($part, $i) {
                        return $i === 0 ? $part : $part[0] . str_repeat('*', max(strlen($part) - 1, 0));
                    }, $parts, array_keys($parts)));
                    
                case 'last_only':
                    $lastIndex = count($parts) - 1;
                    return implode(' ', array_map(function($part, $i) use ($lastIndex) {
                        return $i === $lastIndex ? $part : $part[0] . str_repeat('*', max(strlen($part) - 1, 0));
                    }, $parts, array_keys($parts)));
                    
                case 'initials':
                    return implode('', array_map(function($part) {
                        return strtoupper($part[0]) . '.';
                    }, $parts));
                    
                case 'first_last_initial':
                    if (count($parts) === 1) return $parts[0];
                    return $parts[0] . ' ' . strtoupper($parts[count($parts) - 1][0]) . '.';
                    
                case 'all_asterisk':
                    return implode(' ', array_map(function($part) {
                        return str_repeat('*', strlen($part));
                    }, $parts));
                    
                default:
                    return $fullName;
            }
        };
    @endphp

    @php
        // Helper function to check if field is visible - defined here so it's accessible for all bed types
        $isVisible = function($key) use ($bedBoxConfig) {
            return isset($bedBoxConfig[$key]) && ($bedBoxConfig[$key]['visible'] ?? true);
        };
    @endphp

    <div class="py-6 flex flex-col min-h-0 overscroll-contain" x-data="{ 
        customFullscreen: localStorage.getItem('wardDashboardFullscreen') === 'true', 
        fullscreenGrid: '{{ $fullscreenGrid }}', 
        fullscreenTextClass: '{{ $fullscreenTextClass }}' 
    }" @toggle-custom-fullscreen.window="customFullscreen = $event.detail.enabled" :class="[customFullscreen ? fullscreenTextClass : '', customFullscreen ? 'h-[calc(100vh-80px)] overflow-hidden' : '']">
        <div class="mx-auto px-[5%] flex-1 flex flex-col overflow-hidden w-full min-h-0" :class="customFullscreen ? 'px-2' : ''">
            <!-- Tabs and Action Buttons -->
            <div class="flex-shrink-0 mb-4 flex items-center justify-between" :class="customFullscreen ? 'mb-2' : 'mb-6'">
                <div class="flex items-center space-x-2">
                    <button onclick="clearBedFilters(); filterBySection(null)" class="px-4 py-2 bg-white text-gray-700 rounded-lg font-medium shadow-sm hover:bg-gray-50 border border-gray-300">
                        All
                    </button>
                    <button onclick="filterBySection(1)" class="px-4 py-2 bg-gray-100 text-gray-600 rounded-lg font-medium hover:bg-gray-200">
                        Section 1
                    </button>
                    <button onclick="filterBySection(2)" class="px-4 py-2 bg-gray-100 text-gray-600 rounded-lg font-medium hover:bg-gray-200">
                        Section 2
                    </button>
                    <button onclick="filterBySection(3)" class="px-4 py-2 bg-gray-100 text-gray-600 rounded-lg font-medium hover:bg-gray-200">
                        Section 3
                    </button>
                    <button class="px-4 py-2 bg-gray-100 text-gray-600 rounded-lg font-medium hover:bg-gray-200 flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                        </svg>
                        Map View
                    </button>
                </div>
                
                <div class="flex items-center space-x-2">
                    <button class="px-4 py-2 bg-yellow-500 text-white rounded-lg font-medium shadow hover:bg-yellow-600 flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        Notifications
                    </button>
                    <button onclick="window.dispatchEvent(new CustomEvent('open-settings-modal'))" class="px-4 py-2 bg-gray-600 text-white rounded-lg font-medium shadow hover:bg-gray-700 flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Settings
                    </button>
                    <button onclick="window.dispatchEvent(new CustomEvent('open-admission-logs-modal'))" class="px-4 py-2 bg-gray-700 text-white rounded-lg font-medium shadow hover:bg-gray-800 flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Admission Logs
                    </button>
                </div>
            </div>

            <!-- Beds Grid - scrollable area -->
            <div class="flex-1 overflow-y-auto pb-4 min-h-0 overscroll-contain" :class="customFullscreen ? 'pr-2' : ''">
            <div class="grid gap-4" :class="customFullscreen ? fullscreenGrid : 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5'">
                @php
                    $bedStatusColors = [
                        'available' => 'bg-green-100 text-green-800 border-green-200',
                        'occupied' => 'bg-red-100 text-red-800 border-red-200',
                        'reserved' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                        'maintenance' => 'bg-gray-100 text-gray-800 border-gray-200',
                    ];
                @endphp
                @if($selectedWard && count($beds) > 0)
                @foreach($beds as $bed)
                    @if($bed['status'] === 'occupied' && !empty($bed['is_outside']))
                        <!-- Patient is OUTSIDE (Sent to Procedure) - Orange/Amber Theme -->
                        <div
                            class="bed-card bg-gradient-to-br from-orange-50 to-amber-50 rounded-lg shadow-md border-2 border-orange-400 overflow-hidden transition-all duration-300 h-[280px] flex flex-col animate-pulse-subtle"
                            data-section="{{ $bed['section'] ?? 1 }}"
                            data-next-movement-time="{{ $bed['next_movement_time_iso'] ?? '' }}"
                            data-next-movement-location="{{ $bed['next_movement_location'] ?? '' }}"
                            data-patient-name="{{ $bed['patient_name'] ?? '' }}"
                            data-bed-number="{{ $bed['number'] }}"
                        >
                            <div class="px-4 py-2 bg-gradient-to-r from-orange-500 to-amber-500 text-white flex items-center justify-between">
                                <span class="font-bold bed-number cursor-pointer" onclick='highlightAndFilterBeds(@json([$bed["number"]]), "bed")'>{{ $bed['number'] }}</span>
                                <div class="flex items-center space-x-2">
                                    <span class="px-2 py-0.5 text-[11px] font-semibold rounded-full border {{ $bedStatusColors[$bed['status']] ?? 'bg-gray-100 text-gray-800 border-gray-200' }}">
                                        {{ ucfirst($bed['status']) }}
                                    </span>
                                    <span class="text-xs bg-white/30 px-2 py-1 rounded font-semibold flex items-center">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                    </svg>
                                    OUTSIDE
                                    </span>
                                </div>
                            </div>
                            <div class="p-3 space-y-2 flex-1">
                                <!-- Location Badge -->
                                <div class="bg-orange-100 border border-orange-300 rounded-lg p-3 text-center">
                                    <div class="flex items-center justify-center mb-1">
                                        <svg class="w-5 h-5 text-orange-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        <span class="text-sm font-bold text-orange-700">Sent to {{ $bed['current_movement_location'] }}</span>
                                    </div>
                                    <div class="text-xs text-orange-600">
                                        Since: {{ $bed['current_movement_sent_at'] ?? '-' }}
                                    </div>
                                </div>

                                <!-- Patient Info -->
                                <div class="flex items-center text-sm">
                                    <svg class="w-4 h-4 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                    <span class="font-semibold text-gray-800">{{ $maskPatientName($bed['patient_name']) }}</span>
                                </div>
                                <div class="flex items-center text-xs text-gray-600">
                                    <svg class="w-3 h-3 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                    </svg>
                                    <span>MRN: {{ $bed['mrn'] }}</span>
                                </div>
                                {{-- Nurse On Duty (from Ward Schedule) --}}
                                @if(!empty($bed['nurse_on_duty']))
                                <div class="flex items-center text-xs text-purple-600 font-medium">
                                    <svg class="w-3 h-3 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span class="bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded text-xs">
                                        {{ $bed['current_shift'] ?? 'Shift' }}: {{ $bed['nurse_on_duty'] }}
                                    </span>
                                </div>
                                @endif
                            </div>

                            <!-- Single Return Button -->
                            <div class="border-t border-orange-200 px-3 py-2 bg-orange-50">
                                <form method="POST" action="{{ route('ward.patient-movements.return', $bed['current_movement_id']) }}" class="w-full">
                                    @csrf
                                    <input type="hidden" name="from_dashboard" value="1">
                                    <input type="hidden" name="ward_id" value="{{ $selectedWard->id ?? '' }}">
                                    <button type="submit" class="w-full px-4 py-2 bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600 text-white rounded-lg font-bold transition-all shadow-md hover:shadow-lg flex items-center justify-center text-sm">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 15l-3-3m0 0l3-3m-3 3h8M3 12a9 9 0 1118 0 9 9 0 01-18 0z"/>
                                        </svg>
                                        Mark Returned
                                    </button>
                                </form>
                            </div>
                        </div>
                    @elseif($bed['status'] === 'occupied')
                        <!-- Occupied Bed - Color based on Gender: Red for Female, Blue for Male -->
                        <!-- Pending Discharge: Green border and header -->
                        @php
                            $isPendingDischarge = $bed['is_pending_discharge'] ?? false;
                            if ($isPendingDischarge) {
                                // Pending discharge - use green theme
                                $borderClass = 'border-green-500';
                                $bgClass = 'bg-gradient-to-r from-green-500 to-emerald-600';
                            } else {
                                // Normal - color based on gender
                                $genderColor = strtolower($bed['gender']) === 'female' ? 'red' : 'blue';
                                $borderClass = strtolower($bed['gender']) === 'female' ? 'border-red-500' : 'border-blue-500';
                                $bgClass = strtolower($bed['gender']) === 'female' ? 'bg-red-500' : 'bg-blue-500';
                            }
                        @endphp
                        <div
                            class="bed-card bg-white rounded-lg shadow-md border-2 {{ $borderClass }} overflow-hidden transition-all duration-300 h-[280px] flex flex-col {{ $isPendingDischarge ? 'ring-2 ring-green-300' : '' }}"
                            data-section="{{ $bed['section'] ?? 1 }}"
                            data-next-movement-time="{{ $bed['next_movement_time_iso'] ?? '' }}"
                            data-next-movement-location="{{ $bed['next_movement_location'] ?? '' }}"
                            data-patient-name="{{ $bed['patient_name'] ?? '' }}"
                            data-bed-number="{{ $bed['number'] }}"
                            data-pending-discharge="{{ $isPendingDischarge ? 'true' : 'false' }}"
                        >
                            <div class="px-4 py-2 {{ $bgClass }} text-white flex items-center justify-between">
                                <div class="flex items-center">
                                    <span class="font-bold bed-number cursor-pointer" onclick='highlightAndFilterBeds(@json([$bed["number"]]), "bed")'>{{ $bed['number'] }}</span>
                                    @if($isPendingDischarge)
                                    <span class="ml-2 px-1.5 py-0.5 bg-white/20 text-white text-xs rounded font-medium flex items-center" title="Pending Discharge since {{ $bed['pending_discharge_at'] ?? 'N/A' }}">
                                        <svg class="w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                        </svg>
                                        PD
                                    </span>
                                    @endif
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span class="px-2 py-0.5 text-[11px] font-semibold rounded-full border {{ $bedStatusColors[$bed['status']] ?? 'bg-gray-100 text-gray-800 border-gray-200' }}">
                                        {{ ucfirst($bed['status']) }}
                                    </span>
                                    @if($isVisible('mrn'))
                                    <span class="text-sm">MRN: {{ $bed['mrn'] }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="p-3 space-y-2 flex-1">
                                @if($isVisible('patient_name'))
                                <div class="flex items-center text-sm">
                                    <svg class="w-4 h-4 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                    <span class="font-semibold text-gray-800">{{ $maskPatientName($bed['patient_name']) }}</span>
                                </div>
                                @endif
                                @if($isVisible('consultant'))
                                <div class="flex items-center text-xs text-gray-600">
                                    <svg class="w-3 h-3 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span>{{ $bed['consultant'] }}</span>
                                </div>
                                @endif
                                {{-- Nurse On Duty (from Ward Schedule) --}}
                                @if($isVisible('nurse'))
                                <div class="flex items-center text-xs text-gray-600">
                                    <svg class="w-3 h-3 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    </svg>
                                    @if(!empty($bed['nurse_on_duty']))
                                    <span class="bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded text-xs font-medium">
                                        {{ $bed['current_shift'] ?? '' }}: {{ $bed['nurse_on_duty'] }}
                                    </span>
                                    @else
                                    <span class="text-gray-400">No nurse assigned</span>
                                    @endif
                                </div>
                                @endif
                                @if(!empty($bed['next_movement_location']) && !empty($bed['next_movement_time_display']))
                                <div class="flex items-center text-xs text-amber-600">
                                    <svg class="w-3 h-3 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span>Next: {{ $bed['next_movement_location'] }} at {{ $bed['next_movement_time_display'] }}</span>
                                </div>
                                @endif
                                @if($isVisible('admitted_duration'))
                                <div class="flex items-center text-xs text-gray-600">
                                    <svg class="w-3 h-3 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span>{{ $bed['days'] }} days, {{ $bed['hours'] }} hours</span>
                                </div>
                                @endif

                                @php
                                    $showPatientInfo = function($key) use ($patientInfoConfig) {
                                        return isset($patientInfoConfig[$key]) && ($patientInfoConfig[$key]['visible'] ?? true);
                                    };
                                @endphp

                                <!-- Clinical Indicators Row -->
                                <div class="flex items-center flex-wrap gap-1 pt-2">
                                    {{-- Pending Discharge Status Badge --}}
                                    @if($bed['is_pending_discharge'] ?? false)
                                    <span class="px-1.5 py-0.5 bg-green-500 text-white text-xs rounded font-bold flex items-center" title="Pending Discharge since {{ $bed['pending_discharge_at'] ?? 'N/A' }}">
                                        <svg class="w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                        </svg>
                                        PENDING DC
                                    </span>
                                    @endif
                                    
                                    @if($isVisible('ews'))
                                        @if($bed['ews_has_vitals'] && $bed['ews'] !== null)
                                            @php
                                                $ewsScore = $bed['ews'];
                                                if ($ewsScore <= 2) {
                                                    $ewsBgClass = 'bg-green-500';
                                                } elseif ($ewsScore <= 4) {
                                                    $ewsBgClass = 'bg-yellow-500';
                                                } elseif ($ewsScore <= 6) {
                                                    $ewsBgClass = 'bg-orange-500';
                                                } else {
                                                    $ewsBgClass = 'bg-red-500';
                                                }
                                            @endphp
                                            <span class="px-1.5 py-0.5 {{ $ewsBgClass }} text-white text-xs rounded font-bold">EWS: {{ $ewsScore }}</span>
                                            @if($ewsScore >= 5)
                                                <svg class="w-4 h-4 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                                </svg>
                                            @endif
                                        @else
                                            <span class="px-1.5 py-0.5 bg-gray-400 text-white text-xs rounded font-medium" title="No vital signs recorded">No vitals</span>
                                        @endif
                                    @endif

                                    {{-- Nursing Level --}}
                                    @if($showPatientInfo('nursing_level') && !empty($bed['nursing_level']) && $bed['nursing_level'] !== 'none')
                                    @php
                                        $levelColors = [
                                            'level_1' => 'bg-green-100 text-green-700',
                                            'level_2' => 'bg-blue-100 text-blue-700',
                                            'level_3' => 'bg-yellow-100 text-yellow-700',
                                            'level_4' => 'bg-red-100 text-red-700',
                                        ];
                                        $levelLabels = ['level_1' => 'L1', 'level_2' => 'L2', 'level_3' => 'L3', 'level_4' => 'L4'];
                                    @endphp
                                    <span class="px-1 py-0.5 {{ $levelColors[$bed['nursing_level']] ?? 'bg-purple-100 text-purple-700' }} text-xs rounded font-medium flex items-center" title="Nursing Level: {{ ucfirst(str_replace('_', ' ', $bed['nursing_level'])) }}">
                                        <svg class="w-3 h-3 mr-0.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"/>
                                        </svg>
                                        {{ $levelLabels[$bed['nursing_level']] ?? 'L?' }}
                                    </span>
                                    @endif

                                    {{-- Diet Type --}}
                                    @if($showPatientInfo('diet_type') && !empty($bed['diet_type']) && $bed['diet_type'] !== 'regular')
                                    @php
                                        $dietLabels = [
                                            'npo' => 'NPO',
                                            'clear_fluid' => 'CF',
                                            'full_fluid' => 'FF',
                                            'soft_diet' => 'SD',
                                            'vegetarian' => 'VEG',
                                            'diabetic' => 'DM',
                                            'renal' => 'RD',
                                            'low_salt' => 'LS',
                                            'halal' => 'HAL',
                                            'kosher' => 'KOS',
                                            'gluten_free' => 'GF',
                                        ];
                                        $dietColors = $bed['diet_type'] === 'npo' ? 'bg-red-100 text-red-700' : 'bg-orange-100 text-orange-700';
                                    @endphp
                                    <span class="px-1 py-0.5 {{ $dietColors }} text-xs rounded font-medium flex items-center" title="Diet: {{ ucfirst(str_replace('_', ' ', $bed['diet_type'])) }}">
                                        @if($bed['diet_type'] === 'npo')
                                        <svg class="w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                        </svg>
                                        @else
                                        <svg class="w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                        </svg>
                                        @endif
                                        {{ $dietLabels[$bed['diet_type']] ?? strtoupper(substr($bed['diet_type'], 0, 3)) }}
                                    </span>
                                    @endif

                                    {{-- Fall Risk --}}
                                    @if($showPatientInfo('fall_risk') && !empty($bed['fall_risk']) && $bed['fall_risk'] !== 'none')
                                    @php
                                        $fallColors = [
                                            'low' => 'bg-green-100 text-green-700',
                                            'moderate' => 'bg-yellow-100 text-yellow-700',
                                            'high' => 'bg-orange-100 text-orange-700',
                                            'alert_active' => 'bg-red-100 text-red-700',
                                        ];
                                    @endphp
                                    <span class="px-1 py-0.5 {{ $fallColors[$bed['fall_risk']] ?? 'bg-orange-100 text-orange-700' }} text-xs rounded font-medium flex items-center" title="Fall Risk: {{ ucfirst(str_replace('_', ' ', $bed['fall_risk'])) }}">
                                        <svg class="w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                        </svg>
                                        FR
                                    </span>
                                    @endif

                                    {{-- Isolation Type --}}
                                    @if($showPatientInfo('isolation_type') && !empty($bed['isolation_type']) && $bed['isolation_type'] !== 'none')
                                    @php
                                        $isoLabels = [
                                            'contact' => 'CON',
                                            'droplet' => 'DRP',
                                            'airborne' => 'AIR',
                                            'protective' => 'PRO',
                                            'mrsa' => 'MRSA',
                                            'vre' => 'VRE',
                                            'cdiff' => 'CD',
                                            'covid' => 'COV',
                                            'tb' => 'TB',
                                        ];
                                        $isoColors = in_array($bed['isolation_type'], ['covid', 'tb', 'airborne']) ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700';
                                    @endphp
                                    <span class="px-1 py-0.5 {{ $isoColors }} text-xs rounded font-medium flex items-center" title="Isolation: {{ ucfirst(str_replace('_', ' ', $bed['isolation_type'])) }}">
                                        <svg class="w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                        </svg>
                                        {{ $isoLabels[$bed['isolation_type']] ?? 'ISO' }}
                                    </span>
                                    @endif

                                    {{-- Allergies --}}
                                    @if($showPatientInfo('allergies') && !empty($bed['allergies']) && is_array($bed['allergies']) && count($bed['allergies']) > 0)
                                    @php
                                        $allergyNames = collect($bed['allergies'])->map(function($a) {
                                            return is_array($a) ? ($a['allergen'] ?? $a['allergen_code'] ?? 'Unknown') : $a;
                                        })->implode(', ');
                                    @endphp
                                    <span class="px-1 py-0.5 bg-pink-100 text-pink-700 text-xs rounded font-medium flex items-center" title="Allergies: {{ $allergyNames }}">
                                        <svg class="w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                        </svg>
                                        ALG
                                    </span>
                                    @endif
                                </div>
                            </div>

                            <div class="flex items-center space-x-1 border-t px-3 py-2">
                                @if($isVisible('admit_button'))
                                <button
                                    class="flex-1 p-2 text-gray-600 hover:bg-gray-100 rounded transition-colors"
                                    title="Patient Details"
                                    onclick="window.dispatchEvent(new CustomEvent('open-patient-details-modal', { detail: { patientId: {{ $bed['patient_id'] }} } }))"
                                >
                                    <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                                    </svg>
                                </button>
                                @endif
                                <button 
                                    class="flex-1 p-2 text-red-600 hover:bg-red-50 rounded transition-colors" 
                                    title="Vital Signs"
                                    onclick="window.dispatchEvent(new CustomEvent('open-vital-signs-modal', { detail: { patientId: {{ $bed['patient_id'] }} } }))"
                                >
                                    <svg class="w-4 h-4 mx-auto" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"/>
                                    </svg>
                                </button>
                                <button 
                                    class="flex-1 p-2 text-emerald-600 hover:bg-emerald-50 rounded transition-colors" 
                                    title="ECG"
                                    onclick="window.dispatchEvent(new CustomEvent('open-ecg-modal', { detail: { patientId: {{ $bed['patient_id'] }}, patientName: '{{ addslashes($bed['patient_name']) }}', mrn: '{{ $bed['mrn'] }}' } }))"
                                >
                                    <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12h4l3-9 4 18 3-9h4"/>
                                    </svg>
                                </button>
                                <button class="flex-1 p-2 text-blue-600 hover:bg-blue-50 rounded transition-colors" title="Assign">
                                    <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                </button>
                                <button class="flex-1 p-2 text-cyan-600 hover:bg-cyan-50 rounded transition-colors" title="Clinical">
                                    <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    </svg>
                                </button>
                                @if($isVisible('prebook_button'))
                                <button class="flex-1 p-2 text-purple-600 hover:bg-purple-50 rounded transition-colors" title="Prebook">
                                    <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </button>
                                @endif
                            </div>
                        </div>
                    @elseif($bed['status'] === 'reserved')
                        <!-- Reserved/Prebooked Bed - Color based on Gender -->
                        @php
                            $prebookGender = strtolower($bed['gender'] ?? '');
                            if ($prebookGender === 'female') {
                                $prebookBorderClass = 'border-red-400';
                                $prebookBgClass = 'bg-gradient-to-r from-red-500 to-pink-500';
                                $prebookInfoBg = 'bg-red-50 border-red-200';
                                $prebookInfoText = 'text-red-700';
                                $prebookIconColor = 'text-red-500';
                            } elseif ($prebookGender === 'male') {
                                $prebookBorderClass = 'border-blue-400';
                                $prebookBgClass = 'bg-gradient-to-r from-blue-500 to-indigo-500';
                                $prebookInfoBg = 'bg-blue-50 border-blue-200';
                                $prebookInfoText = 'text-blue-700';
                                $prebookIconColor = 'text-blue-500';
                            } else {
                                $prebookBorderClass = 'border-gray-400';
                                $prebookBgClass = 'bg-gradient-to-r from-gray-500 to-slate-500';
                                $prebookInfoBg = 'bg-gray-50 border-gray-200';
                                $prebookInfoText = 'text-gray-700';
                                $prebookIconColor = 'text-gray-500';
                            }
                        @endphp
                        <div class="bed-card bg-white rounded-lg shadow-md border-2 {{ $prebookBorderClass }} overflow-hidden transition-all duration-300 h-[280px] flex flex-col" data-section="{{ $bed['section'] ?? 1 }}">
                            <div class="px-4 py-2 {{ $prebookBgClass }} text-white flex items-center justify-between">
                                <span class="font-bold bed-number cursor-pointer" onclick='highlightAndFilterBeds(@json([$bed["number"]]), "bed")'>{{ $bed['number'] }}</span>
                                <div class="flex items-center space-x-2">
                                    <span class="px-2 py-0.5 text-[11px] font-semibold rounded-full border {{ $bedStatusColors[$bed['status']] ?? 'bg-gray-100 text-gray-800 border-gray-200' }}">
                                        {{ ucfirst($bed['status']) }}
                                    </span>
                                    <span class="text-xs bg-white/30 px-2 py-1 rounded font-semibold">PREBOOKED</span>
                                </div>
                            </div>
                            <div class="p-3 space-y-2 flex-1">
                                <div class="{{ $prebookInfoBg }} border rounded-lg p-3 mb-2">
                                    <div class="flex items-center justify-center mb-2">
                                        <svg class="w-6 h-6 {{ $prebookIconColor }} mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        <span class="text-sm font-bold {{ $prebookInfoText }}">Bed is Prebooked</span>
                                    </div>
                                </div>
                                <div class="flex items-center text-sm">
                                    <svg class="w-4 h-4 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                    <span class="font-semibold text-gray-800">{{ $maskPatientName($bed['patient_name']) }}</span>
                                </div>
                                @if($bed['mrn'])
                                <div class="flex items-center text-xs text-gray-600">
                                    <svg class="w-3 h-3 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                    </svg>
                                    <span>MRN: {{ $bed['mrn'] }}</span>
                                </div>
                                @endif
                                <div class="flex items-center text-xs text-gray-600">
                                    <svg class="w-3 h-3 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                    <span>Consultant: {{ $bed['consultant'] ?? 'Not Assigned' }}</span>
                                </div>
                                <div class="flex items-center text-xs text-gray-600">
                                    <svg class="w-3 h-3 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    <span>Gender: {{ ucfirst($bed['gender'] ?? 'N/A') }}</span>
                                </div>
                                @if($bed['age'])
                                <div class="flex items-center text-xs text-gray-600">
                                    <svg class="w-3 h-3 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span>Age: {{ $bed['age'] }}</span>
                                </div>
                                @endif
                                {{-- Nurse On Duty (from Ward Schedule) --}}
                                @if(!empty($bed['nurse_on_duty']))
                                <div class="flex items-center text-xs text-purple-600 font-medium">
                                    <svg class="w-3 h-3 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span class="bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded text-xs">
                                        {{ $bed['current_shift'] ?? 'Shift' }}: {{ $bed['nurse_on_duty'] }}
                                    </span>
                                </div>
                                @endif
                                <div class="text-xs text-gray-600 bg-pink-50 p-2 rounded border border-pink-200">
                                    <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Booked: {{ $bed['booked_datetime'] }}
                                </div>
                                <form method="POST" action="{{ route('ward.check-in-prebook', $bed['patient_id']) }}">
                                    @csrf
                                    <button type="submit" class="w-full mt-2 px-3 py-2 bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600 text-white rounded-lg font-bold transition-all shadow-md hover:shadow-lg flex items-center justify-center">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        Check In Patient
                                    </button>
                                </form>
                            </div>
                        </div>
                    @else
                        <!-- Available Bed -->
                        <div class="bed-card bg-white rounded-lg shadow-md border-2 border-gray-300 overflow-hidden hover:border-green-400 transition-all duration-300 h-[280px] flex flex-col" data-section="{{ $bed['section'] ?? 1 }}">
                            <div class="px-4 py-2 bg-gray-500 text-white flex items-center justify-between">
                                <span class="font-bold bed-number cursor-pointer" onclick='highlightAndFilterBeds(@json([$bed["number"]]), "bed")'>{{ $bed['number'] }}</span>
                                <span class="px-2 py-0.5 text-[11px] font-semibold rounded-full border {{ $bedStatusColors[$bed['status']] ?? 'bg-gray-100 text-gray-800 border-gray-200' }}">
                                    {{ ucfirst($bed['status']) }}
                                </span>
                            </div>
                            <div class="p-3 flex-1 flex items-center justify-center">
                                <div class="text-center">
                                    <svg class="w-16 h-16 mx-auto text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                    </svg>
                                    <p class="text-gray-500 text-sm mb-4">No Patient</p>
                                    @if($isVisible('admit_button'))
                                    <button onclick="window.dispatchEvent(new CustomEvent('open-admit-modal', { detail: { bedNumber: '{{ $bed['number'] }}', wardId: {{ $selectedWard->id }} } }))" class="w-full px-3 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg font-medium transition-colors shadow mb-2">
                                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                        Admit Patient
                                    </button>
                                    @endif
                                    @if($isVisible('prebook_button'))
                                    <button onclick="window.dispatchEvent(new CustomEvent('open-prebook-modal', { detail: { bedNumber: '{{ $bed['number'] }}', wardId: {{ $selectedWard->id }} } }))" class="w-full px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors shadow">
                                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        Prebook
                                    </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
                @else
                    <div class="col-span-full text-center py-12">
                        <svg class="w-24 h-24 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                        </svg>
                        <p class="text-xl font-semibold text-gray-600 mb-2">No Wards Available</p>
                        <p class="text-gray-500">Please create a ward first to start managing beds and patients.</p>
                    </div>
                @endif
            </div>
            </div>

            <!-- Statistics Bar (fixed at bottom) -->
            <div class="flex-shrink-0 z-10 mt-2">
                <div class="bg-gradient-to-r from-gray-700 to-gray-800 rounded-lg shadow-lg p-4">
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4">
                    <!-- Available -->
                    <div class="text-center">
                        <div class="text-sm text-gray-300 mb-1">AVAILABLE</div>
                        <div class="flex items-center justify-center">
                            <svg class="w-4 h-4 text-green-400 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            <span class="text-2xl font-bold text-white">{{ $statistics['available'] }}</span>
                        </div>
                    </div>

                    <!-- Infusion Overview -->
                    <div class="text-center cursor-pointer hover:bg-gray-600 rounded-lg p-2 transition-colors" onclick="window.dispatchEvent(new CustomEvent('open-infusion-modal'))">
                        <div class="text-sm text-gray-300 mb-1">INFUSIONS</div>
                        <div class="flex items-center justify-center">
                            <svg class="w-4 h-4 text-indigo-400 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                            </svg>
                            <span class="text-2xl font-bold text-white" id="infusion-count">--</span>
                        </div>
                    </div>

                    <!-- Patients -->
                    <div class="text-center cursor-pointer hover:bg-gray-600 rounded-lg p-2 transition-colors" onclick="window.dispatchEvent(new CustomEvent('open-patients-modal'))">
                        <div class="text-sm text-gray-300 mb-1">PATIENTS</div>
                        <div class="flex items-center justify-center">
                            <svg class="w-4 h-4 text-blue-400 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                            </svg>
                            <span class="text-2xl font-bold text-white">{{ $statistics['patients'] }}</span>
                        </div>
                    </div>

                    <!-- Consultants -->
                    <div class="text-center cursor-pointer hover:bg-gray-600 rounded-lg p-2 transition-colors" onclick="window.dispatchEvent(new CustomEvent('open-consultants-modal'))">
                        <div class="text-sm text-gray-300 mb-1">CONSULTANTS</div>
                        <div class="flex items-center justify-center">
                            <svg class="w-4 h-4 text-cyan-400 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/>
                            </svg>
                            <span class="text-2xl font-bold text-white">{{ $statistics['consultants'] }}</span>
                        </div>
                    </div>

                    <!-- Anaesthetists -->
                    <div class="text-center cursor-pointer hover:bg-gray-600 rounded-lg p-2 transition-colors" onclick="window.dispatchEvent(new CustomEvent('open-anaesthetists-modal'))">
                        <div class="text-sm text-gray-300 mb-1">ANAESTHETISTS</div>
                        <div class="flex items-center justify-center">
                            <svg class="w-4 h-4 text-purple-400 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/>
                            </svg>
                            <span class="text-2xl font-bold text-white">{{ $statistics['anaesthetists'] }}</span>
                        </div>
                    </div>

                    <!-- Nurses -->
                    <div class="text-center cursor-pointer hover:bg-gray-600 rounded-lg p-2 transition-colors" onclick="window.dispatchEvent(new CustomEvent('open-nurses-modal'))">
                        <div class="text-sm text-gray-300 mb-1">NURSES</div>
                        <div class="flex items-center justify-center">
                            <svg class="w-4 h-4 text-pink-400 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                            </svg>
                            <span class="text-2xl font-bold text-white">{{ $statistics['nurses'] }}</span>
                        </div>
                    </div>

                    <!-- Ratio -->
                    <div class="text-center">
                        <div class="text-sm text-gray-300 mb-1">RATIO</div>
                        <div class="flex items-center justify-center">
                            <svg class="w-4 h-4 text-yellow-400 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/>
                            </svg>
                            <span class="text-2xl font-bold text-white">{{ $statistics['ratio'] }}</span>
                        </div>
                    </div>

                    <!-- Occupancy -->
                    <div class="text-center">
                        <div class="text-sm text-gray-300 mb-1">OCCUPANCY</div>
                        <div class="flex items-center justify-center">
                            <span class="text-2xl font-bold text-red-400">{{ $statistics['occupancy'] }}%</span>
                        </div>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Admit Patient Modal -->
    <div x-data="{ 
        open: false, 
        bedNumber: '', 
        wardId: null 
    }" 
    @open-admit-modal.window="open = true; bedNumber = $event.detail.bedNumber; wardId = $event.detail.wardId"
    x-show="open" 
    class="fixed inset-0 z-50 overflow-y-auto" 
    style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="open" @click="open = false" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div x-show="open" @click.stop x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form method="POST" action="{{ route('ward.admit-patient') }}">
                    @csrf
                    <input type="hidden" name="ward_id" x-model="wardId">
                    <input type="hidden" name="bed_number" x-model="bedNumber">
                    
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-green-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                    Admit Patient to Bed <span x-text="bedNumber"></span>
                                </h3>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label for="patient_id" class="block text-sm font-medium text-gray-700">Select Patient</label>
                                        <select name="patient_id" id="patient_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            <option value="">Choose a patient...</option>
                                            @foreach(\App\Models\Patient::where('is_active', true)->whereNull('ward_id')->get() as $patient)
                                                <option value="{{ $patient->id }}">{{ $patient->name }} (MRN: {{ $patient->mrn }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label for="consultant_id" class="block text-sm font-medium text-gray-700">Consultant (Optional)</label>
                                        <select name="consultant_id" id="consultant_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            <option value="">Choose a consultant...</option>
                                            @foreach(\App\Models\Consultant::where('is_active', true)->get() as $consultant)
                                                <option value="{{ $consultant->id }}">{{ $consultant->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label for="anaesthetist_id" class="block text-sm font-medium text-gray-700">Anaesthetist (Optional)</label>
                                        <select name="anaesthetist_id" id="anaesthetist_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            <option value="">Choose an anaesthetist...</option>
                                            @foreach(\App\Models\Anaesthetist::where('is_active', true)->get() as $anaesthetist)
                                                <option value="{{ $anaesthetist->id }}">{{ $anaesthetist->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Admit Patient
                        </button>
                        <button @click="open = false" x-on:click="open = false" type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Prebook Patient Modal -->
    <div x-data="{ 
        open: false, 
        bedNumber: '', 
        wardId: null 
    }" 
    @open-prebook-modal.window="open = true; bedNumber = $event.detail.bedNumber; wardId = $event.detail.wardId"
    x-show="open" 
    class="fixed inset-0 z-50 overflow-y-auto" 
    style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="open" @click="open = false" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div x-show="open" @click.stop x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form method="POST" action="{{ route('ward.prebook-patient') }}">
                    @csrf
                    <input type="hidden" name="ward_id" x-model="wardId">
                    <input type="hidden" name="bed_number" x-model="bedNumber">
                    
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                    Prebook Bed <span x-text="bedNumber"></span>
                                </h3>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label for="prebook_patient_id" class="block text-sm font-medium text-gray-700">Select Patient (Optional)</label>
                                        <select name="patient_id" id="prebook_patient_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            <option value="">Choose a patient...</option>
                                            @foreach(\App\Models\Patient::where('is_active', true)->whereNull('ward_id')->get() as $patient)
                                                <option value="{{ $patient->id }}">{{ $patient->name }} (MRN: {{ $patient->mrn }})</option>
                                            @endforeach
                                        </select>
                                        <p class="mt-1 text-xs text-gray-500">Leave empty to prebook bed without patient details</p>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label for="prebook_consultant_id" class="block text-sm font-medium text-gray-700">Consultant (Optional)</label>
                                            <select name="consultant_id" id="prebook_consultant_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                <option value="">Choose a consultant...</option>
                                                @foreach(\App\Models\Consultant::where('is_active', true)->get() as $consultant)
                                                    <option value="{{ $consultant->id }}">{{ $consultant->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        
                                        <div>
                                            <label for="prebook_anaesthetist_id" class="block text-sm font-medium text-gray-700">Anaesthetist (Optional)</label>
                                            <select name="anaesthetist_id" id="prebook_anaesthetist_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                <option value="">Choose an anaesthetist...</option>
                                                @foreach(\App\Models\Anaesthetist::where('is_active', true)->get() as $anaesthetist)
                                                    <option value="{{ $anaesthetist->id }}">{{ $anaesthetist->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label for="prebook_gender" class="block text-sm font-medium text-gray-700">Gender (Optional)</label>
                                            <select name="gender" id="prebook_gender" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                <option value="">Select gender...</option>
                                                <option value="Male">Male</option>
                                                <option value="Female">Female</option>
                                            </select>
                                        </div>
                                        
                                        <div>
                                            <label for="prebook_age" class="block text-sm font-medium text-gray-700">Age (Optional)</label>
                                            <input type="number" name="age" id="prebook_age" min="0" max="150" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Enter age">
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label for="booked_at" class="block text-sm font-medium text-gray-700">Booking Date & Time (Optional)</label>
                                        <input type="datetime-local" name="booked_at" id="booked_at" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                    
                                    <div>
                                        <label for="prebook_notes" class="block text-sm font-medium text-gray-700">Notes (Optional)</label>
                                        <textarea name="notes" id="prebook_notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Enter any notes or special instructions..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Prebook Bed
                        </button>
                        <button @click="open = false" x-on:click="open = false" type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if(session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" class="fixed bottom-4 right-4 z-50 bg-green-500 text-white px-6 py-4 rounded-lg shadow-lg">
            <div class="flex items-center">
                <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>{{ session('success') }}</span>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" class="fixed bottom-4 right-4 z-50 bg-red-500 text-white px-6 py-4 rounded-lg shadow-lg">
            <div class="flex items-center">
                <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>{{ session('error') }}</span>
            </div>
        </div>
    @endif

    <!-- Admission Logs Modal -->
    <div x-data="{ open: false }" 
         @open-admission-logs-modal.window="open = true"
         x-show="open" 
         class="fixed inset-0 z-50 overflow-y-auto" 
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="open" 
                 @click="open = false"
                 x-transition:enter="ease-out duration-300" 
                 x-transition:enter-start="opacity-0" 
                 x-transition:enter-end="opacity-100" 
                 x-transition:leave="ease-in duration-200" 
                 x-transition:leave-start="opacity-100" 
                 x-transition:leave-end="opacity-0" 
                 class="fixed inset-0 transition-opacity" 
                 aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div x-show="open" 
                 @click.stop
                 x-transition:enter="ease-out duration-300" 
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
                 x-transition:leave="ease-in duration-200" 
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                 class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-6xl sm:w-full">
                
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Admission Logs
                            </h3>
                            
                            <div class="mt-2">
                                <iframe src="{{ route('ward.admission-logs') }}?ward_id={{ $selectedWard->id ?? '' }}" 
                                        class="w-full h-[600px] border-0 rounded-lg"
                                        title="Admission Logs">
                                </iframe>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button @click="open = false" x-on:click="open = false" 
                            type="button" 
                            class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:w-auto sm:text-sm">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Settings Modal -->
    <div x-data="{ open: false }"
         @open-settings-modal.window="open = true"
         x-show="open"
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="open"
                 @click="open = false"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="fixed inset-0 transition-opacity"
                 aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div x-show="open"
                 @click.stop
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-6xl sm:w-full">

                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-gray-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Settings
                            </h3>

                            <div class="mt-2">
                                <iframe
                                    src="{{ route('ward.settings') }}"
                                    class="w-full h-[650px] border-0 rounded-lg"
                                    title="Ward Settings">
                                </iframe>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button @click="open = false"
                            type="button"
                            class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:w-auto sm:text-sm">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Patient Details Modal -->
    <div x-data="{ 
            open: false, 
            patientId: null,
            closeAndRefresh() {
                this.open = false;
                // Refresh the page after a short delay to allow modal to close
                setTimeout(() => {
                    window.location.reload();
                }, 200);
            }
         }"
         @open-patient-details-modal.window="open = true; patientId = $event.detail.patientId"
         x-show="open"
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="open"
                 @click="closeAndRefresh()"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="fixed inset-0 transition-opacity"
                 aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div x-show="open"
                 @click.stop
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-6xl sm:w-full">

                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-gray-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Patient Details
                            </h3>

                            <div class="mt-2">
                                <template x-if="patientId">
                                    <iframe
                                        :src="'{{ route('ward.patient-details') }}?patient_id=' + patientId"
                                        class="w-full h-[650px] border-0 rounded-lg"
                                        title="Patient Details">
                                    </iframe>
                                </template>
                                <template x-if="!patientId">
                                    <div class="text-sm text-red-500">
                                        No patient selected.
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button @click="closeAndRefresh()"
                            type="button"
                            class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:w-auto sm:text-sm">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Vital Signs Modal -->
    <div x-data="{ 
            open: false, 
            patientId: null
         }"
         @open-vital-signs-modal.window="open = true; patientId = $event.detail.patientId"
         x-show="open"
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="open"
                 @click="open = false"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="fixed inset-0 transition-opacity"
                 aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div x-show="open"
                 @click.stop
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">

                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-rose-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-rose-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Patient Vital Signs
                            </h3>

                            <div class="mt-2">
                                <template x-if="patientId">
                                    <iframe
                                        :src="'{{ route('vital-signs.patient') }}?patient_id=' + patientId"
                                        class="w-full h-[550px] border-0 rounded-lg"
                                        title="Patient Vital Signs">
                                    </iframe>
                                </template>
                                <template x-if="!patientId">
                                    <div class="text-sm text-red-500">
                                        No patient selected.
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <a :href="'{{ route('vital-signs.index') }}?patient_id=' + patientId" 
                       class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-rose-600 text-base font-medium text-white hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-rose-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Record New Vitals
                    </a>
                    <button @click="open = false"
                            type="button"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:w-auto sm:text-sm">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ECG Modal -->
    <div x-data="{ 
            open: false, 
            patientId: null,
            patientName: '',
            mrn: ''
         }"
         @open-ecg-modal.window="open = true; patientId = $event.detail.patientId; patientName = $event.detail.patientName; mrn = $event.detail.mrn"
         x-show="open"
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="open"
                 @click="open = false"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="fixed inset-0 transition-opacity"
                 aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div x-show="open"
                 @click.stop
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-5xl sm:w-full">

                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-emerald-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12h4l3-9 4 18 3-9h4"/>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Patient ECG Records
                            </h3>

                            <div class="mt-2">
                                <template x-if="patientId">
                                    <iframe
                                        :src="'{{ route('ecg.patient') }}?patient_id=' + patientId"
                                        class="w-full h-[650px] border-0 rounded-lg"
                                        title="Patient ECG Records">
                                    </iframe>
                                </template>
                                <template x-if="!patientId">
                                    <div class="text-sm text-red-500">
                                        No patient selected.
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button @click="open = false"
                            type="button"
                            class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 sm:w-auto sm:text-sm">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Ward Infusion Overview Modal -->
    <div x-data="{ open: false }" 
         @open-infusion-modal.window="open = true"
         x-show="open" 
         class="fixed inset-0 z-50 overflow-y-auto" 
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="open" 
                 @click="open = false"
                 x-transition:enter="ease-out duration-300" 
                 x-transition:enter-start="opacity-0" 
                 x-transition:enter-end="opacity-100" 
                 x-transition:leave="ease-in duration-200" 
                 x-transition:leave-start="opacity-100" 
                 x-transition:leave-end="opacity-0" 
                 class="fixed inset-0 transition-opacity" 
                 aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div x-show="open" 
                 @click.stop
                 x-transition:enter="ease-out duration-300" 
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
                 x-transition:leave="ease-in duration-200" 
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                 class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-6xl sm:w-full">
                
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-indigo-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Ward Infusion Overview
                            </h3>
                            
                            <div class="mt-2">
                                <iframe src="{{ route('ward.infusion-overview') }}?ward_id={{ $selectedWard->id ?? '' }}" 
                                        class="w-full h-[600px] border-0 rounded-lg"
                                        title="Ward Infusion Overview">
                                </iframe>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button @click="open = false" 
                            type="button" 
                            class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:w-auto sm:text-sm">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Patients List Modal -->
    <div x-data="{ open: false }" 
         @open-patients-modal.window="open = true"
         x-show="open" 
         class="fixed inset-0 z-50 overflow-y-auto" 
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="open" 
                 @click="open = false"
                 x-transition:enter="ease-out duration-300" 
                 x-transition:enter-start="opacity-0" 
                 x-transition:enter-end="opacity-100" 
                 x-transition:leave="ease-in duration-200" 
                 x-transition:leave-start="opacity-100" 
                 x-transition:leave-end="opacity-0" 
                 class="fixed inset-0 transition-opacity" 
                 aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div x-show="open" 
                 @click.stop
                 x-transition:enter="ease-out duration-300" 
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
                 x-transition:leave="ease-in duration-200" 
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                 class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-6xl sm:w-full">
                
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Ward Patients
                            </h3>
                            
                            <div class="mt-2">
                                <iframe src="{{ route('ward.patients-list') }}?ward_id={{ $selectedWard->id ?? '' }}" 
                                        class="w-full h-[600px] border-0 rounded-lg"
                                        title="Ward Patients">
                                </iframe>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button @click="open = false" x-on:click="open = false" 
                            type="button" 
                            class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:w-auto sm:text-sm">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Consultants Modal -->
    <div x-data="{ open: false }" 
         @open-consultants-modal.window="open = true"
         x-show="open" 
         class="fixed inset-0 z-50 overflow-y-auto" 
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="open" @click="open = false" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div x-show="open" @click.stop x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-cyan-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-cyan-600" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Consultants & Their Patients
                            </h3>
                            <div class="mt-4 max-h-[600px] overflow-y-auto">
                                @if(isset($consultantPatients) && count($consultantPatients) > 0)
                                    @foreach($consultantPatients as $consultant)
                                        <div class="mb-6 bg-gray-50 rounded-lg p-4 border border-gray-200">
                                            <h4 class="font-semibold text-lg text-cyan-700 mb-3 flex items-center cursor-pointer"
                                                onclick='highlightAndFilterBeds(@json(collect($consultant["patients"])->pluck("bed_number")), "consultant")'>
                                                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/>
                                                </svg>
                                                {{ $consultant['name'] }}
                                                <span class="ml-2 text-sm bg-cyan-100 text-cyan-800 px-2 py-1 rounded-full">{{ count($consultant['patients']) }} patient(s)</span>
                                            </h4>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                                @foreach($consultant['patients'] as $patient)
                                                    <div class="bg-white p-3 rounded border border-gray-300 hover:border-cyan-500 cursor-pointer transition-all hover:shadow-md"
                                                         onclick='highlightAndFilterBeds(@json([$patient["bed_number"]]), "bed")'>
                                                        <div class="flex items-center justify-between">
                                                            <div>
                                                                <div class="font-medium text-gray-900">{{ $patient['name'] }}</div>
                                                                <div class="text-sm text-gray-600">MRN: {{ $patient['mrn'] }}</div>
                                                            </div>
                                                            <div class="text-right">
                                                                <div class="text-sm font-semibold text-cyan-700">{{ $patient['bed_number'] }}</div>
                                                                <div class="text-xs text-gray-500">Click to view</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                @else
                                    <div class="text-center text-gray-500 py-8">
                                        <svg class="w-16 h-16 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                        </svg>
                                        <p>No consultants assigned to patients yet.</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button @click="open = false" type="button" class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-500 sm:w-auto sm:text-sm">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Nurses Modal -->
    <div x-data="{ open: false }" 
         @open-nurses-modal.window="open = true"
         x-show="open" 
         class="fixed inset-0 z-50 overflow-y-auto" 
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="open" @click="open = false" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div x-show="open" @click.stop x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-pink-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-pink-600" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Nurses & Their Patients
                            </h3>
                            <div class="mt-4 max-h-[600px] overflow-y-auto">
                                @if(isset($nursePatients) && count($nursePatients) > 0)
                                    @foreach($nursePatients as $nurse)
                                        <div class="mb-6 bg-gray-50 rounded-lg p-4 border border-gray-200">
                                            <h4 class="font-semibold text-lg text-pink-700 mb-3 flex items-center cursor-pointer"
                                                onclick='highlightAndFilterBeds(@json(collect($nurse["patients"])->pluck("bed_number")), "nurse")'>
                                                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                                                </svg>
                                                {{ $nurse['name'] }}
                                                <span class="ml-2 text-sm bg-pink-100 text-pink-800 px-2 py-1 rounded-full">{{ count($nurse['patients']) }} patient(s)</span>
                                            </h4>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                                @foreach($nurse['patients'] as $patient)
                                                    <div class="bg-white p-3 rounded border border-gray-300 hover:border-pink-500 cursor-pointer transition-all hover:shadow-md"
                                                         onclick='highlightAndFilterBeds(@json([$patient["bed_number"]]), "bed")'>
                                                        <div class="flex items-center justify-between">
                                                            <div>
                                                                <div class="font-medium text-gray-900">{{ $patient['name'] }}</div>
                                                                <div class="text-sm text-gray-600">MRN: {{ $patient['mrn'] }}</div>
                                                            </div>
                                                            <div class="text-right">
                                                                <div class="text-sm font-semibold text-pink-700">{{ $patient['bed_number'] }}</div>
                                                                <div class="text-xs text-gray-500">Click to view</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                @else
                                    <div class="text-center text-gray-500 py-8">
                                        <svg class="w-16 h-16 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                        </svg>
                                        <p>No nurses assigned to patients yet.</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button @click="open = false" type="button" class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-pink-500 sm:w-auto sm:text-sm">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Anaesthetists Modal -->
    <div x-data="{ open: false }" 
         @open-anaesthetists-modal.window="open = true"
         x-show="open" 
         class="fixed inset-0 z-50 overflow-y-auto" 
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="open" @click="open = false" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div x-show="open" @click.stop x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-purple-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Anaesthetists & Their Patients
                            </h3>
                            <div class="mt-4 max-h-[600px] overflow-y-auto">
                                @if(isset($anaesthetistPatients) && count($anaesthetistPatients) > 0)
                                    @foreach($anaesthetistPatients as $anaesthetist)
                                        <div class="mb-6 bg-gray-50 rounded-lg p-4 border border-gray-200">
                                            <h4 class="font-semibold text-lg text-purple-700 mb-3 flex items-center cursor-pointer"
                                                onclick='highlightAndFilterBeds(@json(collect($anaesthetist["patients"])->pluck("bed_number")), "anaesthetist")'>
                                                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/>
                                                </svg>
                                                {{ $anaesthetist['name'] }}
                                                <span class="ml-2 text-sm bg-purple-100 text-purple-800 px-2 py-1 rounded-full">{{ count($anaesthetist['patients']) }} patient(s)</span>
                                            </h4>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                                @foreach($anaesthetist['patients'] as $patient)
                                                    <div class="bg-white p-3 rounded border border-gray-300 hover:border-purple-500 cursor-pointer transition-all hover:shadow-md"
                                                         onclick='highlightAndFilterBeds(@json([$patient["bed_number"]]), "bed")'>
                                                        <div class="flex items-center justify-between">
                                                            <div>
                                                                <div class="font-medium text-gray-900">{{ $patient['name'] }}</div>
                                                                <div class="text-sm text-gray-600">MRN: {{ $patient['mrn'] }}</div>
                                                            </div>
                                                            <div class="text-right">
                                                                <div class="text-sm font-semibold text-purple-700">{{ $patient['bed_number'] }}</div>
                                                                <div class="text-xs text-gray-500">Click to view</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                @else
                                    <div class="text-center text-gray-500 py-8">
                                        <svg class="w-16 h-16 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                        </svg>
                                        <p>No anaesthetists assigned to patients yet.</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button @click="open = false" type="button" class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 sm:w-auto sm:text-sm">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bed Highlighting, Filtering & Movement Reminder JavaScript -->
    <script>
        function clearBedFilters() {
            if (window.bedFilters) {
                Object.keys(window.bedFilters).forEach(key => {
                    if (window.bedFilters[key] && typeof window.bedFilters[key].clear === 'function') {
                        window.bedFilters[key].clear();
                    }
                });
            }

            const bedCards = document.querySelectorAll('.bed-card');
            bedCards.forEach(card => {
                card.classList.remove('hidden', 'opacity-40', 'ring-4', 'ring-yellow-400', 'shadow-2xl', 'scale-105');
            });
        }

        function getBedFilters() {
            if (!window.bedFilters) {
                window.bedFilters = {
                    consultant: new Set(),
                    nurse: new Set(),
                    anaesthetist: new Set(),
                    bed: new Set(),
                    section: new Set(),
                };
            }
            return window.bedFilters;
        }

        function applyBedFilters() {
            const filters = getBedFilters();
            const activeCategories = Object.keys(filters).filter(
                key => filters[key] && filters[key].size > 0
            );

            const bedCards = document.querySelectorAll('.bed-card');
            let firstHighlighted = null;

            bedCards.forEach(card => {
                const bedNumberElement = card.querySelector('.bed-number');
                const cardNumber = bedNumberElement ? bedNumberElement.textContent.trim() : '';

                // Reset highlight first
                card.classList.remove('ring-4', 'ring-yellow-400', 'shadow-2xl', 'scale-105');

                if (activeCategories.length === 0 || !cardNumber) {
                    // No active filters: show everything
                    card.classList.remove('hidden', 'opacity-40');
                    return;
                }

                // A bed must satisfy ALL active category filters (intersection)
                let allowed = true;
                for (const key of activeCategories) {
                    if (!filters[key].has(cardNumber)) {
                        allowed = false;
                        break;
                    }
                }

                if (allowed) {
                    card.classList.remove('hidden', 'opacity-40');
                    card.classList.add('ring-4', 'ring-yellow-400', 'shadow-2xl', 'scale-105');
                    if (!firstHighlighted) {
                        firstHighlighted = card;
                    }
                } else {
                    card.classList.add('hidden', 'opacity-40');
                }
            });

            return firstHighlighted;
        }

        function highlightAndFilterBeds(bedNumbers, category) {
            if (!Array.isArray(bedNumbers)) {
                bedNumbers = [bedNumbers];
            }

            // Default category when not provided
            if (!category) {
                category = 'bed';
            }

            const normalizedTargets = bedNumbers
                .filter(Boolean)
                .map(n => String(n).trim());

            const filters = getBedFilters();

            if (!filters[category]) {
                filters[category] = new Set();
            }

            // Replace the filter set for this category
            filters[category].clear();
            normalizedTargets.forEach(n => filters[category].add(n));

            const firstHighlighted = applyBedFilters();

            // Scroll to first highlighted bed
            if (firstHighlighted) {
                firstHighlighted.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            // Close all Alpine-powered modals that use `open` in their state
            document.querySelectorAll('[x-data]').forEach(element => {
                if (element.__x && typeof element.__x.$data.open !== 'undefined') {
                    element.__x.$data.open = false;
                }
            });
        }

        function filterBySection(sectionNumber) {
            const bedCards = document.querySelectorAll('.bed-card');

            // If no specific section is selected, show all beds and reapply any highlight filters
            if (sectionNumber === null || sectionNumber === 0) {
                bedCards.forEach(card => {
                    card.classList.remove('hidden', 'opacity-40');
                });
                applyBedFilters();
                return;
            }

            bedCards.forEach(card => {
                const sectionAttr = card.getAttribute('data-section') || '1';
                const section = parseInt(sectionAttr, 10);

                if (section === Number(sectionNumber)) {
                    card.classList.remove('hidden', 'opacity-40');
                } else {
                    card.classList.add('hidden');
                }
            });
        }

        // Movement reminder logic - show alert 15 minutes before scheduled movement
        document.addEventListener('DOMContentLoaded', function () {
            const shownMovementReminders = new Set();

            function showMovementReminder(details) {
                const container = document.createElement('div');
                container.className = 'fixed bottom-4 left-4 z-50 bg-amber-500 text-white px-4 py-3 rounded-lg shadow-lg max-w-sm text-sm flex items-start space-x-2';

                container.innerHTML = `
                    <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div class="flex-1">
                        <div class="font-semibold">Upcoming patient movement</div>
                        <div class="mt-0.5">
                            <span class="font-semibold">${details.patientName}</span>
                            (Bed ${details.bedNumber}) to
                            <span class="font-semibold">${details.location}</span>
                        </div>
                        <div class="text-xs mt-0.5">
                            Scheduled at ${details.scheduledDisplay} (in ~${details.minutesUntil} min)
                        </div>
                    </div>
                `;

                document.body.appendChild(container);

                setTimeout(() => {
                    container.remove();
                }, 8000);
            }

            function checkMovementReminders() {
                const now = new Date();
                const cards = document.querySelectorAll('.bed-card');

                cards.forEach(card => {
                    const timeIso = card.getAttribute('data-next-movement-time');
                    const location = card.getAttribute('data-next-movement-location');
                    const patientName = card.getAttribute('data-patient-name');
                    const bedNumber = card.getAttribute('data-bed-number');

                    if (!timeIso || !location || !patientName) {
                        return;
                    }

                    const scheduled = new Date(timeIso);
                    if (isNaN(scheduled.getTime())) {
                        return;
                    }

                    const diffMinutes = (scheduled.getTime() - now.getTime()) / (1000 * 60);

                    // Trigger between 0 and 15 minutes before, and don't spam duplicates
                    if (diffMinutes <= 15 && diffMinutes >= 0) {
                        const key = `${patientName}|${timeIso}`;
                        if (shownMovementReminders.has(key)) {
                            return;
                        }

                        shownMovementReminders.add(key);

                        showMovementReminder({
                            patientName,
                            bedNumber,
                            location,
                            scheduledDisplay: scheduled.toLocaleString(),
                            minutesUntil: Math.max(0, Math.round(diffMinutes)),
                        });
                    }
                });
            }

            // Initial check and then every minute
            checkMovementReminders();
            setInterval(checkMovementReminders, 60000);
        });

        // Dashboard Clock Component with Timezone Support
        function dashboardClock() {
            return {
                currentTime: new Date(),
                formattedDate: '',
                formattedTime: '',
                selectedTimezone: 'Asia/Kuala_Lumpur',
                showTimezoneModal: false,
                useInternetTime: true,
                syncing: false,
                lastSync: null,
                intervalId: null,

                init() {
                    // Load saved timezone from localStorage
                    const savedTimezone = localStorage.getItem('smartward_timezone');
                    if (savedTimezone) {
                        this.selectedTimezone = savedTimezone;
                    }

                    // Load internet time preference
                    const savedInternetTime = localStorage.getItem('smartward_use_internet_time');
                    this.useInternetTime = savedInternetTime !== 'false';

                    // Load last sync time
                    const savedLastSync = localStorage.getItem('smartward_last_sync');
                    if (savedLastSync) {
                        this.lastSync = savedLastSync;
                    }

                    // Update time immediately
                    this.updateTime();

                    // Update time every second
                    this.intervalId = setInterval(() => {
                        this.updateTime();
                    }, 1000);

                    // Auto-sync every 30 minutes if internet time is enabled
                    setInterval(() => {
                        if (this.useInternetTime) {
                            this.syncNow(true); // Silent sync
                        }
                    }, 30 * 60 * 1000);
                },

                updateTime() {
                    this.currentTime = new Date();
                    
                    try {
                        // Format date with selected timezone
                        this.formattedDate = this.currentTime.toLocaleDateString('en-US', {
                            weekday: 'long',
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric',
                            timeZone: this.selectedTimezone
                        });

                        // Format time with selected timezone
                        this.formattedTime = this.currentTime.toLocaleTimeString('en-US', {
                            hour: '2-digit',
                            minute: '2-digit',
                            second: '2-digit',
                            hour12: true,
                            timeZone: this.selectedTimezone
                        });
                    } catch (e) {
                        // Fallback to default formatting if timezone is invalid
                        this.formattedDate = this.currentTime.toLocaleDateString('en-US', {
                            weekday: 'long',
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        });
                        this.formattedTime = this.currentTime.toLocaleTimeString('en-US', {
                            hour: '2-digit',
                            minute: '2-digit',
                            second: '2-digit',
                            hour12: true
                        });
                    }
                },

                saveTimezone() {
                    localStorage.setItem('smartward_timezone', this.selectedTimezone);
                    this.updateTime();
                },

                toggleInternetTime() {
                    localStorage.setItem('smartward_use_internet_time', this.useInternetTime.toString());
                    if (this.useInternetTime) {
                        this.syncNow();
                    }
                },

                async syncNow(silent = false) {
                    if (this.syncing) return;
                    
                    this.syncing = true;
                    
                    try {
                        // Try to sync with WorldTimeAPI (free, no API key needed)
                        const response = await fetch(`https://worldtimeapi.org/api/timezone/${this.selectedTimezone}`);
                        
                        if (response.ok) {
                            const data = await response.json();
                            // WorldTimeAPI returns datetime in ISO format
                            const serverTime = new Date(data.datetime);
                            const clientTime = new Date();
                            const offset = serverTime.getTime() - clientTime.getTime();
                            
                            // Store offset for future corrections (not implemented for simplicity)
                            localStorage.setItem('smartward_time_offset', offset.toString());
                            
                            this.lastSync = new Date().toLocaleString('en-US', {
                                month: 'short',
                                day: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit',
                                timeZone: this.selectedTimezone
                            });
                            localStorage.setItem('smartward_last_sync', this.lastSync);
                            
                            if (!silent) {
                                // Show success notification
                                this.showNotification('Time synchronized successfully!', 'success');
                            }
                        } else {
                            throw new Error('Failed to sync with time server');
                        }
                    } catch (error) {
                        console.warn('Time sync failed:', error);
                        if (!silent) {
                            this.showNotification('Using local device time', 'warning');
                        }
                        
                        // Use local time as fallback
                        this.lastSync = new Date().toLocaleString('en-US', {
                            month: 'short',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit',
                            timeZone: this.selectedTimezone
                        }) + ' (local)';
                        localStorage.setItem('smartward_last_sync', this.lastSync);
                    } finally {
                        this.syncing = false;
                    }
                },

                showNotification(message, type = 'info') {
                    const colors = {
                        success: 'bg-green-500',
                        warning: 'bg-amber-500',
                        error: 'bg-red-500',
                        info: 'bg-blue-500'
                    };

                    const notification = document.createElement('div');
                    notification.className = `fixed bottom-4 right-4 z-50 ${colors[type]} text-white px-4 py-3 rounded-lg shadow-lg flex items-center space-x-2 animate-fade-in`;
                    notification.innerHTML = `
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            ${type === 'success' 
                                ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>'
                                : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'}
                        </svg>
                        <span>${message}</span>
                    `;

                    document.body.appendChild(notification);

                    setTimeout(() => {
                        notification.style.opacity = '0';
                        notification.style.transition = 'opacity 0.3s';
                        setTimeout(() => notification.remove(), 300);
                    }, 3000);
                }
            };
        }
    </script>
</x-app-layout>

