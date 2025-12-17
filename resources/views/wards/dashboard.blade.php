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

        /* Resolution-specific styles for 1920x1080 (Full HD) */
        .fullscreen-res-1920x1080 .bed-card { font-size: 0.9rem; }
        .fullscreen-res-1920x1080 .bed-card .text-sm { font-size: 0.85rem; }
        .fullscreen-res-1920x1080 .bed-card .text-xs { font-size: 0.75rem; }
        .fullscreen-res-1920x1080 .bed-card h3, 
        .fullscreen-res-1920x1080 .bed-card .font-bold { font-size: 1rem; }
        .fullscreen-res-1920x1080 .bed-card .px-4 { padding-left: 1rem; padding-right: 1rem; }
        .fullscreen-res-1920x1080 .bed-card .py-2 { padding-top: 0.5rem; padding-bottom: 0.5rem; }
        .fullscreen-res-1920x1080 .bed-card .p-3 { padding: 0.75rem; }
        .fullscreen-res-1920x1080 .bed-card .space-y-2 > * + * { margin-top: 0.5rem; }
        .fullscreen-res-1920x1080 .bed-card .w-4 { width: 1rem; height: 1rem; }
        .fullscreen-res-1920x1080 .bed-card .gap-4 { gap: 0.75rem; }

        /* Resolution-specific styles for 3840x2160 (4K UHD) */
        .fullscreen-res-3840x2160 .bed-card { font-size: 1.25rem; }
        .fullscreen-res-3840x2160 .bed-card .text-sm { font-size: 1.15rem; }
        .fullscreen-res-3840x2160 .bed-card .text-xs { font-size: 1rem; }
        .fullscreen-res-3840x2160 .bed-card h3, 
        .fullscreen-res-3840x2160 .bed-card .font-bold { font-size: 1.4rem; }
        .fullscreen-res-3840x2160 .bed-card .px-4 { padding-left: 1.5rem; padding-right: 1.5rem; }
        .fullscreen-res-3840x2160 .bed-card .py-2 { padding-top: 0.75rem; padding-bottom: 0.75rem; }
        .fullscreen-res-3840x2160 .bed-card .p-3 { padding: 1.25rem; }
        .fullscreen-res-3840x2160 .bed-card .space-y-2 > * + * { margin-top: 0.75rem; }
        .fullscreen-res-3840x2160 .bed-card .w-4 { width: 1.5rem; height: 1.5rem; }
        .fullscreen-res-3840x2160 .bed-card .gap-4 { gap: 1.25rem; }
        .fullscreen-res-3840x2160 .bed-card .rounded-lg { border-radius: 0.75rem; }
        .fullscreen-res-3840x2160 .bed-card .px-2 { padding-left: 0.75rem; padding-right: 0.75rem; }
        .fullscreen-res-3840x2160 .bed-card .py-1 { padding-top: 0.375rem; padding-bottom: 0.375rem; }
        .fullscreen-res-3840x2160 .bed-card .px-1\.5 { padding-left: 0.5rem; padding-right: 0.5rem; }
        /* 4K specific icon and badge sizing */
        .fullscreen-res-3840x2160 .bed-card svg { transform: scale(1.3); }
        .fullscreen-res-3840x2160 .bed-card .clinical-badge { font-size: 0.9rem; padding: 0.375rem 0.625rem; }

        /* 4K Resolution Modal Sizing - targets body class for fixed modals */
        body.fullscreen-res-3840x2160 .fixed.inset-0 .sm\:max-w-6xl {
            max-width: 90rem !important; /* Increased from 72rem */
        }
        body.fullscreen-res-3840x2160 .fixed.inset-0 .sm\:max-w-5xl {
            max-width: 80rem !important; /* Increased from 64rem */
        }
        body.fullscreen-res-3840x2160 .fixed.inset-0 .sm\:max-w-4xl {
            max-width: 72rem !important; /* Increased from 56rem */
        }
        /* 4K Resolution iframe heights */
        body.fullscreen-res-3840x2160 .fixed.inset-0 iframe.h-\[650px\] {
            height: 900px !important;
        }
        body.fullscreen-res-3840x2160 .fixed.inset-0 iframe.h-\[600px\] {
            height: 850px !important;
        }
        body.fullscreen-res-3840x2160 .fixed.inset-0 iframe.h-\[550px\] {
            height: 800px !important;
        }
        /* 4K Resolution content area heights for non-iframe modals */
        body.fullscreen-res-3840x2160 .fixed.inset-0 .max-h-\[600px\] {
            max-height: 850px !important;
        }
        /* 4K Resolution modal text and padding */
        body.fullscreen-res-3840x2160 .fixed.inset-0 h3.text-lg {
            font-size: 1.5rem !important;
            line-height: 2rem !important;
        }
        body.fullscreen-res-3840x2160 .fixed.inset-0 .text-sm {
            font-size: 1.1rem !important;
        }
        body.fullscreen-res-3840x2160 .fixed.inset-0 .text-xs {
            font-size: 0.95rem !important;
        }
        body.fullscreen-res-3840x2160 .fixed.inset-0 .sm\:p-6 {
            padding: 2rem !important;
        }
        /* 4K Resolution modal buttons */
        body.fullscreen-res-3840x2160 .fixed.inset-0 button,
        body.fullscreen-res-3840x2160 .fixed.inset-0 a.inline-flex {
            font-size: 1.1rem !important;
            padding: 0.75rem 1.5rem !important;
        }
        /* 4K Resolution modal icons */
        body.fullscreen-res-3840x2160 .fixed.inset-0 .h-12.w-12 {
            height: 4rem !important;
            width: 4rem !important;
        }
        body.fullscreen-res-3840x2160 .fixed.inset-0 .h-6.w-6 {
            height: 2rem !important;
            width: 2rem !important;
        }
        /* 4K Resolution grid items in modals */
        body.fullscreen-res-3840x2160 .fixed.inset-0 .grid.grid-cols-1 {
            gap: 1rem !important;
        }
        body.fullscreen-res-3840x2160 .fixed.inset-0 .p-3 {
            padding: 1.25rem !important;
        }
        body.fullscreen-res-3840x2160 .fixed.inset-0 .p-4 {
            padding: 1.5rem !important;
        }
        body.fullscreen-res-3840x2160 .fixed.inset-0 .mb-4 {
            margin-bottom: 1.5rem !important;
        }
        body.fullscreen-res-3840x2160 .fixed.inset-0 .mb-6 {
            margin-bottom: 2rem !important;
        }
        /* 4K Resolution font sizes for modal content */
        body.fullscreen-res-3840x2160 .fixed.inset-0 .font-medium {
            font-size: 1.2rem !important;
        }
        body.fullscreen-res-3840x2160 .fixed.inset-0 .font-semibold {
            font-size: 1.3rem !important;
        }
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
                    <a href="{{ route('ward.dashboard', request()->query()) }}" 
                       onclick="window.location.reload(); return false;"
                       title="Click to refresh"
                       class="inline-flex items-center group">
                        <h2 class="font-bold text-2xl text-gray-800 leading-tight group-hover:text-blue-600 transition-colors cursor-pointer">
                            Ward Dashboard
                        </h2>
                        <svg class="w-4 h-4 ml-2 text-gray-400 group-hover:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="Refresh">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                    </a>
                    <p class="text-sm text-gray-500 mt-1">Real-time bed and patient management <span class="text-xs text-gray-400">(click title to refresh)</span></p>
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
                <div class="text-sm text-gray-500">
                    {{ date('l, F j, Y \a\t g:i A') }}
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
        
        // Resolution classes for fullscreen mode
        $fullscreenResolutionClasses = [
            'default' => '',
            '1920x1080' => 'fullscreen-res-1920x1080',
            '3840x2160' => 'fullscreen-res-3840x2160',
        ];
        $fullscreenResolution = $dashboardDisplay['fullscreen_resolution'] ?? 'default';
        $fullscreenResolutionClass = $fullscreenResolutionClasses[$fullscreenResolution] ?? '';
        
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
            // If not in config, default to true (especially for new keys like prebook_button)
            if (!isset($bedBoxConfig[$key])) {
                return true;
            }
            return $bedBoxConfig[$key]['visible'] ?? true;
        };
    @endphp

    <div class="py-6 flex flex-col" x-data="{ 
        customFullscreen: localStorage.getItem('wardDashboardFullscreen') === 'true', 
        fullscreenGrid: '{{ $fullscreenGrid }}', 
        fullscreenTextClass: '{{ $fullscreenTextClass }}',
        fullscreenResolutionClass: '{{ $fullscreenResolutionClass }}',
        init() {
            // Apply body class on initial load if fullscreen is enabled
            if (this.customFullscreen && this.fullscreenResolutionClass) {
                document.body.classList.add(this.fullscreenResolutionClass);
            }
        }
    }" @toggle-custom-fullscreen.window="
        customFullscreen = $event.detail.enabled;
        // Toggle resolution class on body for fixed modals
        if (customFullscreen && fullscreenResolutionClass) {
            document.body.classList.add(fullscreenResolutionClass);
        } else if (fullscreenResolutionClass) {
            document.body.classList.remove(fullscreenResolutionClass);
        }
    " :class="[customFullscreen ? fullscreenTextClass : '', customFullscreen ? fullscreenResolutionClass : '', customFullscreen ? 'h-[calc(100vh-80px)] overflow-hidden' : '']">
        <div class="mx-auto flex-1 flex flex-col overflow-hidden w-full" :class="customFullscreen ? 'px-2' : 'px-[5%]'">
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
            <div class="flex-1 overflow-y-auto pb-4" :class="customFullscreen ? 'pr-2' : ''">
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
                            class="bed-card bg-gradient-to-br from-orange-50 to-amber-50 rounded-lg shadow-md border-2 border-orange-400 transition-all duration-300 h-[280px] flex flex-col animate-pulse-subtle"
                            style="overflow: visible;"
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
                        <!-- Occupied Bed - Color based on Gender: Pink for Female, Blue for Male -->
                        <!-- Pending Discharge: Yellow border and header -->
                        @php
                            $isPendingDischarge = $bed['is_pending_discharge'] ?? false;
                            if ($isPendingDischarge) {
                                // Pending discharge - use yellow theme
                                $borderClass = 'border-yellow-500';
                                $bgClass = 'bg-gradient-to-r from-yellow-500 to-yellow-600';
                            } else {
                                // Normal - color based on gender
                                $genderColor = strtolower($bed['gender']) === 'female' ? 'pink' : 'blue';
                                $borderClass = strtolower($bed['gender']) === 'female' ? 'border-pink-500' : 'border-blue-500';
                                $bgClass = strtolower($bed['gender']) === 'female' ? 'bg-pink-500' : 'bg-blue-500';
                            }
                        @endphp
                        <div
                            class="bed-card bg-white rounded-lg shadow-md border-2 {{ $borderClass }} transition-all duration-300 h-[280px] flex flex-col {{ $isPendingDischarge ? 'ring-2 ring-yellow-300' : '' }}"
                            style="overflow: visible;"
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
                                @if($isVisible('nurse'))
                                <div class="flex items-center text-xs text-gray-600">
                                    <svg class="w-3 h-3 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    </svg>
                                    @if(!empty($bed['nurse_on_duty']))
                                        <span class="text-pink-600 font-medium" title="Nurse on duty ({{ $bed['current_shift'] ?? '' }} shift)">{{ $bed['nurse_on_duty'] }}</span>
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

                                <!-- Clinical Indicators Row - Touch Screen Friendly -->
                                <div class="flex items-center justify-between pt-2 overflow-visible" x-data="{ openPopover: null }">
                                    {{-- Left side: EWS/No Vitals + Pending Discharge --}}
                                    <div class="flex items-center gap-1 shrink-0">
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
                                                <span class="px-1.5 py-0.5 bg-gray-400 text-white text-xs rounded font-medium">No vitals</span>
                                            @endif
                                        @endif
                                        
                                        {{-- Pending Discharge Status Badge --}}
                                        @if($bed['is_pending_discharge'] ?? false)
                                        <span class="px-1.5 py-0.5 bg-yellow-500 text-white text-xs rounded font-bold flex items-center">
                                            <svg class="w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                            </svg>
                                            DC
                                        </span>
                                        @endif
                                    </div>

                                    {{-- Right side: Clinical Status Grid (4x2) --}}
                                    <div class="grid grid-cols-4 gap-1 overflow-visible" style="width: 116px;">
                                        {{-- Row 1: Nursing Level, NBM, Fall Risk, Isolation --}}
                                        {{-- Nursing Level - Click to show details --}}
                                        @if($showPatientInfo('nursing_level') && !empty($bed['nursing_level']) && $bed['nursing_level'] !== 'none')
                                        @php
                                            $levelColors = [
                                                'level_1' => ['bg' => 'bg-green-500', 'text' => 'text-white', 'border' => 'border-green-600'],
                                                'level_2' => ['bg' => 'bg-blue-500', 'text' => 'text-white', 'border' => 'border-blue-600'],
                                                'level_3' => ['bg' => 'bg-yellow-500', 'text' => 'text-white', 'border' => 'border-yellow-600'],
                                                'level_4' => ['bg' => 'bg-red-500', 'text' => 'text-white', 'border' => 'border-red-600'],
                                            ];
                                            $levelNum = ['level_1' => '1', 'level_2' => '2', 'level_3' => '3', 'level_4' => '4'];
                                            $levelDesc = ['level_1' => 'Minimal Care', 'level_2' => 'Moderate Care', 'level_3' => 'Maximum Care', 'level_4' => 'Intensive Care'];
                                            $currentLevel = $levelColors[$bed['nursing_level']] ?? ['bg' => 'bg-purple-500', 'text' => 'text-white', 'border' => 'border-purple-600'];
                                        @endphp
                                        <div class="relative">
                                            <button type="button" @click="openPopover = openPopover === 'nursing_{{ $bed['patient_id'] }}' ? null : 'nursing_{{ $bed['patient_id'] }}'" 
                                                    class="w-6 h-6 {{ $currentLevel['bg'] }} {{ $currentLevel['text'] }} text-xs rounded flex items-center justify-center cursor-pointer border {{ $currentLevel['border'] }}" title="Nursing Level">
                                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                                </svg>
                                            </button>
                                            <div x-show="openPopover === 'nursing_{{ $bed['patient_id'] }}'" 
                                                 @click.away="openPopover = null"
                                                 x-transition
                                                 class="absolute z-[9999] bottom-full left-1/2 -translate-x-1/2 mb-2 w-40 bg-white rounded-lg shadow-2xl border border-gray-200 p-2 whitespace-nowrap">
                                                <div class="text-xs font-bold text-gray-800 mb-1">Nursing Level {{ $levelNum[$bed['nursing_level']] ?? '?' }}</div>
                                                <div class="text-xs text-gray-600">{{ $levelDesc[$bed['nursing_level']] ?? 'Unknown Level' }}</div>
                                            </div>
                                        </div>
                                        @else
                                        <div class="w-6 h-6 bg-gray-200 rounded flex items-center justify-center" title="Nursing Level">
                                            <svg class="w-3.5 h-3.5 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                            </svg>
                                        </div>
                                        @endif

                                        {{-- Diet Type - NBM (Nil by mouth) with click details --}}
                                        @if($showPatientInfo('diet_type') && ($bed['has_nbm'] ?? false))
                                        <div class="relative">
                                            <button type="button" @click="openPopover = openPopover === 'nbm_{{ $bed['patient_id'] }}' ? null : 'nbm_{{ $bed['patient_id'] }}'"
                                                    class="w-6 h-6 bg-red-600 text-white text-xs rounded flex items-center justify-center cursor-pointer border border-red-700" title="NBM">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                                    <path d="M3 3v6c0 1 1 2 2 2h3c1 0 2-1 2-2V3M6 3v18"/>
                                                    <line x1="2" y1="2" x2="22" y2="22" stroke-width="3"/>
                                                    <path d="M15 3h4v6a3 3 0 01-3 3h-1M17 12v9"/>
                                                </svg>
                                            </button>
                                            <div x-show="openPopover === 'nbm_{{ $bed['patient_id'] }}'" 
                                                 @click.away="openPopover = null"
                                                 x-transition
                                                 class="absolute z-[9999] bottom-full left-1/2 -translate-x-1/2 mb-2 w-36 bg-white rounded-lg shadow-2xl border border-gray-200 p-2 whitespace-nowrap">
                                                <div class="text-xs font-bold text-red-700 mb-1">⚠️ NBM</div>
                                                <div class="text-xs text-gray-600">Nil By Mouth</div>
                                                <div class="text-xs text-gray-500 mt-1">No food or drink</div>
                                            </div>
                                        </div>
                                        @else
                                        <div class="w-6 h-6 bg-gray-200 rounded flex items-center justify-center" title="Diet">
                                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path d="M3 3v6c0 1 1 2 2 2h3c1 0 2-1 2-2V3M6 3v18"/>
                                                <path d="M15 3h4v6a3 3 0 01-3 3h-1M17 12v9"/>
                                            </svg>
                                        </div>
                                        @endif

                                        {{-- Fall Risk - Click to show level --}}
                                        @if($showPatientInfo('fall_risk') && !empty($bed['fall_risk']) && $bed['fall_risk'] !== 'none')
                                        @php
                                            $fallColors = [
                                                'low' => ['bg' => 'bg-green-500', 'text' => 'text-white', 'border' => 'border-green-600', 'level' => '1'],
                                                'moderate' => ['bg' => 'bg-yellow-500', 'text' => 'text-white', 'border' => 'border-yellow-600', 'level' => '2'],
                                                'high' => ['bg' => 'bg-orange-500', 'text' => 'text-white', 'border' => 'border-orange-600', 'level' => '3'],
                                                'alert_active' => ['bg' => 'bg-red-500', 'text' => 'text-white', 'border' => 'border-red-600', 'level' => '4'],
                                            ];
                                            $fallDesc = [
                                                'low' => 'Low Risk - Standard precautions',
                                                'moderate' => 'Moderate Risk - Enhanced monitoring',
                                                'high' => 'High Risk - Close supervision',
                                                'alert_active' => 'Alert Active - Constant observation',
                                            ];
                                            $currentFall = $fallColors[$bed['fall_risk']] ?? ['bg' => 'bg-orange-500', 'text' => 'text-white', 'border' => 'border-orange-600', 'level' => '?'];
                                        @endphp
                                        <div class="relative">
                                            <button type="button" @click="openPopover = openPopover === 'fall_{{ $bed['patient_id'] }}' ? null : 'fall_{{ $bed['patient_id'] }}'"
                                                    class="w-6 h-6 {{ $currentFall['bg'] }} {{ $currentFall['text'] }} text-xs rounded flex items-center justify-center cursor-pointer border {{ $currentFall['border'] }}" title="Fall Risk">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                    <circle cx="17" cy="4" r="2"/>
                                                    <path d="M15 8l-3 4 4 2.5M12 12l-3.5-1.5M17 14.5l1.5 4.5M14 14.5l-2 5.5"/>
                                                    <path d="M3 20h18" stroke-width="1.5"/>
                                                </svg>
                                            </button>
                                            <div x-show="openPopover === 'fall_{{ $bed['patient_id'] }}'" 
                                                 @click.away="openPopover = null"
                                                 x-transition
                                                 class="absolute z-[9999] bottom-full left-1/2 -translate-x-1/2 mb-2 w-48 bg-white rounded-lg shadow-2xl border border-gray-200 p-2 whitespace-nowrap">
                                                <div class="text-xs font-bold text-gray-800 mb-1">⚠️ Fall Risk Level {{ $currentFall['level'] }}</div>
                                                <div class="text-xs text-gray-600">{{ $fallDesc[$bed['fall_risk']] ?? 'Unknown Risk' }}</div>
                                            </div>
                                        </div>
                                        @else
                                        <div class="w-6 h-6 bg-gray-200 rounded flex items-center justify-center" title="Fall Risk">
                                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <circle cx="17" cy="4" r="2"/>
                                                <path d="M15 8l-3 4 4 2.5M12 12l-3.5-1.5M17 14.5l1.5 4.5M14 14.5l-2 5.5"/>
                                                <path d="M3 20h18" stroke-width="1.5"/>
                                            </svg>
                                        </div>
                                        @endif

                                        {{-- Isolation Type - Click to show category --}}
                                        @if($showPatientInfo('isolation_type') && !empty($bed['isolation_type']) && $bed['isolation_type'] !== 'none')
                                        @php
                                            $criticalIsolations = ['covid', 'tb', 'airborne', 'COVID', 'TB', 'AIR'];
                                            $isoCode = strtoupper($bed['isolation_type']);
                                            $isCriticalIso = in_array($bed['isolation_type'], $criticalIsolations) || in_array($isoCode, $criticalIsolations);
                                            $isoColors = $isCriticalIso 
                                                ? ['bg' => 'bg-red-600', 'text' => 'text-white', 'border' => 'border-red-700'] 
                                                : ['bg' => 'bg-yellow-500', 'text' => 'text-white', 'border' => 'border-yellow-600'];
                                            $isoDisplayName = $bed['isolation_type_name'] ?? ucfirst(str_replace('_', ' ', $bed['isolation_type']));
                                        @endphp
                                        <div class="relative">
                                            <button type="button" @click="openPopover = openPopover === 'iso_{{ $bed['patient_id'] }}' ? null : 'iso_{{ $bed['patient_id'] }}'"
                                                    class="w-6 h-6 {{ $isoColors['bg'] }} {{ $isoColors['text'] }} text-xs rounded flex items-center justify-center cursor-pointer border {{ $isoColors['border'] }}" title="Isolation">
                                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                                    <circle cx="12" cy="12" r="4"/>
                                                    <circle cx="12" cy="3" r="1.5"/>
                                                    <circle cx="12" cy="21" r="1.5"/>
                                                    <circle cx="3" cy="12" r="1.5"/>
                                                    <circle cx="21" cy="12" r="1.5"/>
                                                    <circle cx="5.6" cy="5.6" r="1.2"/>
                                                    <circle cx="18.4" cy="18.4" r="1.2"/>
                                                    <circle cx="5.6" cy="18.4" r="1.2"/>
                                                    <circle cx="18.4" cy="5.6" r="1.2"/>
                                                </svg>
                                            </button>
                                            <div x-show="openPopover === 'iso_{{ $bed['patient_id'] }}'" 
                                                 @click.away="openPopover = null"
                                                 x-transition
                                                 class="absolute z-[9999] bottom-full left-1/2 -translate-x-1/2 mb-2 w-48 bg-white rounded-lg shadow-2xl border border-gray-200 p-2 whitespace-nowrap">
                                                <div class="text-xs font-bold {{ $isCriticalIso ? 'text-red-700' : 'text-yellow-700' }} mb-1">🦠 Isolation Required</div>
                                                <div class="text-xs text-gray-800 font-semibold">{{ $isoDisplayName }}</div>
                                                @if($isCriticalIso)
                                                <div class="text-xs text-red-600 mt-1">⚠️ Critical - Full PPE required</div>
                                                @endif
                                            </div>
                                        </div>
                                        @else
                                        <div class="w-6 h-6 bg-gray-200 rounded flex items-center justify-center" title="Isolation">
                                            <svg class="w-3.5 h-3.5 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                                                <circle cx="12" cy="12" r="4"/>
                                                <circle cx="12" cy="3" r="1.5"/>
                                                <circle cx="12" cy="21" r="1.5"/>
                                                <circle cx="3" cy="12" r="1.5"/>
                                                <circle cx="21" cy="12" r="1.5"/>
                                            </svg>
                                        </div>
                                        @endif

                                        {{-- Row 2: Allergies + 3 empty placeholders --}}
                                        {{-- Allergies - Click to show list --}}
                                        @if($showPatientInfo('allergies') && !empty($bed['allergies']) && is_array($bed['allergies']) && count($bed['allergies']) > 0)
                                        @php
                                            $allergyList = collect($bed['allergies'])->map(function($a) {
                                                return is_array($a) ? ($a['allergen'] ?? $a['allergen_code'] ?? 'Unknown') : $a;
                                            })->toArray();
                                            $allergyCount = count($allergyList);
                                        @endphp
                                        <div class="relative">
                                            <button type="button" @click="openPopover = openPopover === 'allergy_{{ $bed['patient_id'] }}' ? null : 'allergy_{{ $bed['patient_id'] }}'"
                                                    class="w-6 h-6 bg-pink-600 text-white text-xs rounded flex items-center justify-center cursor-pointer border border-pink-700" title="Allergies">
                                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M12 2L1 21h22L12 2zm0 3.5L19.5 19h-15L12 5.5zM11 10v4h2v-4h-2zm0 6v2h2v-2h-2z"/>
                                                </svg>
                                            </button>
                                            <div x-show="openPopover === 'allergy_{{ $bed['patient_id'] }}'" 
                                                 @click.away="openPopover = null"
                                                 x-transition
                                                 class="absolute z-[9999] bottom-full left-1/2 -translate-x-1/2 mb-2 w-52 bg-white rounded-lg shadow-2xl border border-gray-200 p-2">
                                                <div class="text-xs font-bold text-pink-700 mb-2">⚠️ Allergies ({{ $allergyCount }})</div>
                                                <ul class="space-y-1">
                                                    @foreach($allergyList as $allergy)
                                                    <li class="text-xs text-gray-700 flex items-center">
                                                        <span class="w-1.5 h-1.5 bg-pink-500 rounded-full mr-2"></span>
                                                        {{ $allergy }}
                                                    </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        </div>
                                        @else
                                        <div class="w-6 h-6 bg-gray-200 rounded flex items-center justify-center" title="Allergies">
                                            <svg class="w-3.5 h-3.5 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M12 2L1 21h22L12 2zm0 3.5L19.5 19h-15L12 5.5zM11 10v4h2v-4h-2zm0 6v2h2v-2h-2z"/>
                                            </svg>
                                        </div>
                                        @endif

                                        {{-- 3 Empty placeholder boxes for future indicators --}}
                                        <div class="w-6 h-6 bg-gray-200 rounded flex items-center justify-center" title="Reserved">
                                            <span class="text-gray-400 text-xs">-</span>
                                        </div>
                                        <div class="w-6 h-6 bg-gray-200 rounded flex items-center justify-center" title="Reserved">
                                            <span class="text-gray-400 text-xs">-</span>
                                        </div>
                                        <div class="w-6 h-6 bg-gray-200 rounded flex items-center justify-center" title="Reserved">
                                            <span class="text-gray-400 text-xs">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center space-x-1 border-t px-3 py-2">
                                {{-- Button 1: Patient Details --}}
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
                                {{-- Button 2: Vital Signs --}}
                                <button 
                                    class="flex-1 p-2 text-red-600 hover:bg-red-50 rounded transition-colors" 
                                    title="Vital Signs"
                                    onclick="window.dispatchEvent(new CustomEvent('open-vital-signs-modal', { detail: { patientId: {{ $bed['patient_id'] }} } }))"
                                >
                                    <svg class="w-4 h-4 mx-auto" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"/>
                                    </svg>
                                </button>
                                {{-- Button 3: ECG --}}
                                <button 
                                    class="flex-1 p-2 text-emerald-600 hover:bg-emerald-50 rounded transition-colors" 
                                    title="ECG"
                                    onclick="window.dispatchEvent(new CustomEvent('open-ecg-modal', { detail: { patientId: {{ $bed['patient_id'] }} } }))"
                                >
                                    <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12h4l3-9 4 18 3-9h4"/>
                                    </svg>
                                </button>
                                {{-- Button 4: Clinical --}}
                                <button 
                                    class="flex-1 p-2 text-cyan-600 hover:bg-cyan-50 rounded transition-colors" 
                                    title="Clinical"
                                    onclick="window.dispatchEvent(new CustomEvent('open-clinical-modal', { detail: { patientId: {{ $bed['patient_id'] }} } }))"
                                >
                                    <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    </svg>
                                </button>
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
                                <div class="text-xs text-gray-600 bg-pink-50 p-2 rounded border border-pink-200">
                                    <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Booked: {{ $bed['booked_datetime'] }}
                                </div>
                                <div class="flex gap-2 mt-2">
                                    <form method="POST" action="{{ route('ward.check-in-prebook', $bed['patient_id']) }}" class="flex-1">
                                        @csrf
                                        <button type="submit" class="w-full px-3 py-2 bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600 text-white rounded-lg font-bold transition-all shadow-md hover:shadow-lg flex items-center justify-center">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            Check In
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('ward.cancel-prebook', $bed['patient_id']) }}" onsubmit="return confirm('Are you sure you want to cancel this prebook?')">
                                        @csrf
                                        <button type="submit" class="px-3 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg font-bold transition-all shadow-md hover:shadow-lg flex items-center justify-center" title="Cancel Prebook">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
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

    @php
        $modalPatients = \App\Models\Patient::where('is_active', true)->whereNull('ward_id')->get(['id', 'name', 'mrn']);
        $modalConsultants = \App\Models\Consultant::where('is_active', true)->get(['id', 'name']);
        $modalNurses = \App\Models\Nurse::where('is_active', true)->get(['id', 'name']);
        $modalAnaesthetists = \App\Models\Anaesthetist::where('is_active', true)->get(['id', 'name']);
    @endphp

    <script>
        window.modalData = {
            patients: @json($modalPatients),
            consultants: @json($modalConsultants),
            nurses: @json($modalNurses),
            anaesthetists: @json($modalAnaesthetists)
        };

        function admitModalComponent() {
            return {
                open: false,
                bedNumber: '',
                wardId: null,
                patientSearch: '',
                patientDropdownOpen: false,
                selectedPatient: null,
                patients: window.modalData.patients,
                get filteredPatients() {
                    if (!this.patientSearch) return this.patients;
                    const search = this.patientSearch.toLowerCase();
                    return this.patients.filter(p => p.name.toLowerCase().includes(search) || (p.mrn && p.mrn.toLowerCase().includes(search)));
                },
                selectPatient(patient) {
                    this.selectedPatient = patient;
                    this.patientSearch = patient.name + ' (MRN: ' + patient.mrn + ')';
                    this.patientDropdownOpen = false;
                },
                clearPatient() {
                    this.selectedPatient = null;
                    this.patientSearch = '';
                },
                consultantSearch: '',
                consultantDropdownOpen: false,
                selectedConsultant: null,
                consultants: window.modalData.consultants,
                get filteredConsultants() {
                    if (!this.consultantSearch) return this.consultants;
                    const search = this.consultantSearch.toLowerCase();
                    return this.consultants.filter(c => c.name.toLowerCase().includes(search));
                },
                selectConsultant(consultant) {
                    this.selectedConsultant = consultant;
                    this.consultantSearch = consultant.name;
                    this.consultantDropdownOpen = false;
                },
                clearConsultant() {
                    this.selectedConsultant = null;
                    this.consultantSearch = '';
                },
                nurseSearch: '',
                nurseDropdownOpen: false,
                selectedNurse: null,
                nurses: window.modalData.nurses,
                get filteredNurses() {
                    if (!this.nurseSearch) return this.nurses;
                    const search = this.nurseSearch.toLowerCase();
                    return this.nurses.filter(n => n.name.toLowerCase().includes(search));
                },
                selectNurse(nurse) {
                    this.selectedNurse = nurse;
                    this.nurseSearch = nurse.name;
                    this.nurseDropdownOpen = false;
                },
                clearNurse() {
                    this.selectedNurse = null;
                    this.nurseSearch = '';
                },
                anaesthetistSearch: '',
                anaesthetistDropdownOpen: false,
                selectedAnaesthetist: null,
                anaesthetists: window.modalData.anaesthetists,
                get filteredAnaesthetists() {
                    if (!this.anaesthetistSearch) return this.anaesthetists;
                    const search = this.anaesthetistSearch.toLowerCase();
                    return this.anaesthetists.filter(a => a.name.toLowerCase().includes(search));
                },
                selectAnaesthetist(anaesthetist) {
                    this.selectedAnaesthetist = anaesthetist;
                    this.anaesthetistSearch = anaesthetist.name;
                    this.anaesthetistDropdownOpen = false;
                },
                clearAnaesthetist() {
                    this.selectedAnaesthetist = null;
                    this.anaesthetistSearch = '';
                },
                resetForm() {
                    this.patientSearch = '';
                    this.selectedPatient = null;
                    this.consultantSearch = '';
                    this.selectedConsultant = null;
                    this.nurseSearch = '';
                    this.selectedNurse = null;
                    this.anaesthetistSearch = '';
                    this.selectedAnaesthetist = null;
                }
            };
        }

        function prebookModalComponent() {
            return {
                open: false,
                bedNumber: '',
                wardId: null,
                patientSearch: '',
                patientDropdownOpen: false,
                selectedPatient: null,
                patients: window.modalData.patients,
                get filteredPatients() {
                    if (!this.patientSearch) return this.patients;
                    const search = this.patientSearch.toLowerCase();
                    return this.patients.filter(p => p.name.toLowerCase().includes(search) || (p.mrn && p.mrn.toLowerCase().includes(search)));
                },
                selectPatient(patient) {
                    this.selectedPatient = patient;
                    this.patientSearch = patient.name + ' (MRN: ' + patient.mrn + ')';
                    this.patientDropdownOpen = false;
                },
                clearPatient() {
                    this.selectedPatient = null;
                    this.patientSearch = '';
                },
                consultantSearch: '',
                consultantDropdownOpen: false,
                selectedConsultant: null,
                consultants: window.modalData.consultants,
                get filteredConsultants() {
                    if (!this.consultantSearch) return this.consultants;
                    const search = this.consultantSearch.toLowerCase();
                    return this.consultants.filter(c => c.name.toLowerCase().includes(search));
                },
                selectConsultant(consultant) {
                    this.selectedConsultant = consultant;
                    this.consultantSearch = consultant.name;
                    this.consultantDropdownOpen = false;
                },
                clearConsultant() {
                    this.selectedConsultant = null;
                    this.consultantSearch = '';
                },
                nurseSearch: '',
                nurseDropdownOpen: false,
                selectedNurse: null,
                nurses: window.modalData.nurses,
                get filteredNurses() {
                    if (!this.nurseSearch) return this.nurses;
                    const search = this.nurseSearch.toLowerCase();
                    return this.nurses.filter(n => n.name.toLowerCase().includes(search));
                },
                selectNurse(nurse) {
                    this.selectedNurse = nurse;
                    this.nurseSearch = nurse.name;
                    this.nurseDropdownOpen = false;
                },
                clearNurse() {
                    this.selectedNurse = null;
                    this.nurseSearch = '';
                },
                anaesthetistSearch: '',
                anaesthetistDropdownOpen: false,
                selectedAnaesthetist: null,
                anaesthetists: window.modalData.anaesthetists,
                get filteredAnaesthetists() {
                    if (!this.anaesthetistSearch) return this.anaesthetists;
                    const search = this.anaesthetistSearch.toLowerCase();
                    return this.anaesthetists.filter(a => a.name.toLowerCase().includes(search));
                },
                selectAnaesthetist(anaesthetist) {
                    this.selectedAnaesthetist = anaesthetist;
                    this.anaesthetistSearch = anaesthetist.name;
                    this.anaesthetistDropdownOpen = false;
                },
                clearAnaesthetist() {
                    this.selectedAnaesthetist = null;
                    this.anaesthetistSearch = '';
                },
                resetForm() {
                    this.patientSearch = '';
                    this.selectedPatient = null;
                    this.consultantSearch = '';
                    this.selectedConsultant = null;
                    this.nurseSearch = '';
                    this.selectedNurse = null;
                    this.anaesthetistSearch = '';
                    this.selectedAnaesthetist = null;
                }
            };
        }
    </script>

    <!-- Admit Patient Modal -->
    <div x-data="admitModalComponent()" 
    @open-admit-modal.window="open = true; bedNumber = $event.detail.bedNumber; wardId = $event.detail.wardId; resetForm()"
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
                    <input type="hidden" name="patient_id" :value="selectedPatient ? selectedPatient.id : ''">
                    <input type="hidden" name="consultant_id" :value="selectedConsultant ? selectedConsultant.id : ''">
                    <input type="hidden" name="nurse_id" :value="selectedNurse ? selectedNurse.id : ''">
                    <input type="hidden" name="anaesthetist_id" :value="selectedAnaesthetist ? selectedAnaesthetist.id : ''">
                    
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
                                    <!-- Patient Searchable Dropdown -->
                                    <div class="relative">
                                        <label class="block text-sm font-medium text-gray-700">Select Patient <span class="text-red-500">*</span></label>
                                        <div class="mt-1 relative">
                                            <input type="text" 
                                                   x-model="patientSearch" 
                                                   @focus="patientDropdownOpen = true"
                                                   @click="patientDropdownOpen = true"
                                                   @input="patientDropdownOpen = true; selectedPatient = null"
                                                   placeholder="Search by name or MRN..."
                                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 pr-10"
                                                   :class="{'border-red-300': !selectedPatient && patientSearch}"
                                                   required>
                                            <button type="button" x-show="patientSearch" @click="clearPatient()" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                        <div x-show="patientDropdownOpen && filteredPatients.length > 0" 
                                             @click.away="patientDropdownOpen = false"
                                             class="absolute z-10 mt-1 w-full bg-white shadow-lg max-h-48 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm">
                                            <template x-for="patient in filteredPatients" :key="patient.id">
                                                <div @click="selectPatient(patient)" 
                                                     class="cursor-pointer select-none relative py-2 pl-3 pr-9 hover:bg-green-50"
                                                     :class="{'bg-green-100': selectedPatient && selectedPatient.id === patient.id}">
                                                    <span class="block truncate" x-text="patient.name + ' (MRN: ' + patient.mrn + ')'"></span>
                                                </div>
                                            </template>
                                        </div>
                                        <p x-show="patientDropdownOpen && filteredPatients.length === 0 && patientSearch" class="mt-1 text-xs text-gray-500">No patients found matching your search</p>
                                    </div>
                                    
                                    <!-- Consultant Searchable Dropdown -->
                                    <div class="relative">
                                        <label class="block text-sm font-medium text-gray-700">Consultant (Optional)</label>
                                        <div class="mt-1 relative">
                                            <input type="text" 
                                                   x-model="consultantSearch" 
                                                   @focus="consultantDropdownOpen = true"
                                                   @click="consultantDropdownOpen = true"
                                                   @input="consultantDropdownOpen = true; selectedConsultant = null"
                                                   placeholder="Search consultant..."
                                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 pr-10">
                                            <button type="button" x-show="consultantSearch" @click="clearConsultant()" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                        <div x-show="consultantDropdownOpen && filteredConsultants.length > 0" 
                                             @click.away="consultantDropdownOpen = false"
                                             class="absolute z-10 mt-1 w-full bg-white shadow-lg max-h-48 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm">
                                            <template x-for="consultant in filteredConsultants" :key="consultant.id">
                                                <div @click="selectConsultant(consultant)" 
                                                     class="cursor-pointer select-none relative py-2 pl-3 pr-9 hover:bg-green-50"
                                                     :class="{'bg-green-100': selectedConsultant && selectedConsultant.id === consultant.id}">
                                                    <span class="block truncate" x-text="consultant.name"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                    
                                    <!-- Nurse Searchable Dropdown -->
                                    <div class="relative">
                                        <label class="block text-sm font-medium text-gray-700">Nurse (Optional)</label>
                                        <div class="mt-1 relative">
                                            <input type="text" 
                                                   x-model="nurseSearch" 
                                                   @focus="nurseDropdownOpen = true"
                                                   @click="nurseDropdownOpen = true"
                                                   @input="nurseDropdownOpen = true; selectedNurse = null"
                                                   placeholder="Search nurse..."
                                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 pr-10">
                                            <button type="button" x-show="nurseSearch" @click="clearNurse()" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                        <div x-show="nurseDropdownOpen && filteredNurses.length > 0" 
                                             @click.away="nurseDropdownOpen = false"
                                             class="absolute z-10 mt-1 w-full bg-white shadow-lg max-h-48 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm">
                                            <template x-for="nurse in filteredNurses" :key="nurse.id">
                                                <div @click="selectNurse(nurse)" 
                                                     class="cursor-pointer select-none relative py-2 pl-3 pr-9 hover:bg-green-50"
                                                     :class="{'bg-green-100': selectedNurse && selectedNurse.id === nurse.id}">
                                                    <span class="block truncate" x-text="nurse.name"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                    
                                    <!-- Anaesthetist Searchable Dropdown -->
                                    <div class="relative">
                                        <label class="block text-sm font-medium text-gray-700">Anaesthetist (Optional)</label>
                                        <div class="mt-1 relative">
                                            <input type="text" 
                                                   x-model="anaesthetistSearch" 
                                                   @focus="anaesthetistDropdownOpen = true"
                                                   @click="anaesthetistDropdownOpen = true"
                                                   @input="anaesthetistDropdownOpen = true; selectedAnaesthetist = null"
                                                   placeholder="Search anaesthetist..."
                                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 pr-10">
                                            <button type="button" x-show="anaesthetistSearch" @click="clearAnaesthetist()" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                        <div x-show="anaesthetistDropdownOpen && filteredAnaesthetists.length > 0" 
                                             @click.away="anaesthetistDropdownOpen = false"
                                             class="absolute z-10 mt-1 w-full bg-white shadow-lg max-h-48 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm">
                                            <template x-for="anaesthetist in filteredAnaesthetists" :key="anaesthetist.id">
                                                <div @click="selectAnaesthetist(anaesthetist)" 
                                                     class="cursor-pointer select-none relative py-2 pl-3 pr-9 hover:bg-green-50"
                                                     :class="{'bg-green-100': selectedAnaesthetist && selectedAnaesthetist.id === anaesthetist.id}">
                                                    <span class="block truncate" x-text="anaesthetist.name"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" 
                                :disabled="!selectedPatient"
                                :class="{'opacity-50 cursor-not-allowed': !selectedPatient}"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm">
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
    <div x-data="prebookModalComponent()"
    @open-prebook-modal.window="open = true; bedNumber = $event.detail.bedNumber; wardId = $event.detail.wardId; resetForm()"
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
                    <input type="hidden" name="patient_id" :value="selectedPatient ? selectedPatient.id : ''">
                    <input type="hidden" name="consultant_id" :value="selectedConsultant ? selectedConsultant.id : ''">
                    <input type="hidden" name="nurse_id" :value="selectedNurse ? selectedNurse.id : ''">
                    <input type="hidden" name="anaesthetist_id" :value="selectedAnaesthetist ? selectedAnaesthetist.id : ''">
                    
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
                                    <!-- Patient Searchable Dropdown (Optional) -->
                                    <div class="relative">
                                        <label class="block text-sm font-medium text-gray-700">Select Patient (Optional)</label>
                                        <div class="mt-1 relative">
                                            <input type="text" 
                                                   x-model="patientSearch" 
                                                   @focus="patientDropdownOpen = true"
                                                   @click="patientDropdownOpen = true"
                                                   @input="patientDropdownOpen = true; selectedPatient = null"
                                                   placeholder="Search by name or MRN..."
                                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 pr-10">
                                            <button type="button" x-show="patientSearch" @click="clearPatient()" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                        <div x-show="patientDropdownOpen && filteredPatients.length > 0" 
                                             @click.away="patientDropdownOpen = false"
                                             class="absolute z-10 mt-1 w-full bg-white shadow-lg max-h-48 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm">
                                            <template x-for="patient in filteredPatients" :key="patient.id">
                                                <div @click="selectPatient(patient)" 
                                                     class="cursor-pointer select-none relative py-2 pl-3 pr-9 hover:bg-blue-50"
                                                     :class="{'bg-blue-100': selectedPatient && selectedPatient.id === patient.id}">
                                                    <span class="block truncate" x-text="patient.name + ' (MRN: ' + patient.mrn + ')'"></span>
                                                </div>
                                            </template>
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500">Leave empty to prebook bed without patient details</p>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-4">
                                        <!-- Consultant Searchable Dropdown -->
                                        <div class="relative">
                                            <label class="block text-sm font-medium text-gray-700">Consultant (Optional)</label>
                                            <div class="mt-1 relative">
                                                <input type="text" 
                                                       x-model="consultantSearch" 
                                                       @focus="consultantDropdownOpen = true"
                                                       @click="consultantDropdownOpen = true"
                                                       @input="consultantDropdownOpen = true; selectedConsultant = null"
                                                       placeholder="Search..."
                                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 pr-8 text-sm">
                                                <button type="button" x-show="consultantSearch" @click="clearConsultant()" class="absolute inset-y-0 right-0 pr-2 flex items-center text-gray-400 hover:text-gray-600">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                </button>
                                            </div>
                                            <div x-show="consultantDropdownOpen && filteredConsultants.length > 0" 
                                                 @click.away="consultantDropdownOpen = false"
                                                 class="absolute z-10 mt-1 w-full bg-white shadow-lg max-h-40 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm">
                                                <template x-for="consultant in filteredConsultants" :key="consultant.id">
                                                    <div @click="selectConsultant(consultant)" 
                                                         class="cursor-pointer select-none relative py-2 pl-3 pr-9 hover:bg-blue-50"
                                                         :class="{'bg-blue-100': selectedConsultant && selectedConsultant.id === consultant.id}">
                                                        <span class="block truncate" x-text="consultant.name"></span>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                        
                                        <!-- Nurse Searchable Dropdown -->
                                        <div class="relative">
                                            <label class="block text-sm font-medium text-gray-700">Nurse (Optional)</label>
                                            <div class="mt-1 relative">
                                                <input type="text" 
                                                       x-model="nurseSearch" 
                                                       @focus="nurseDropdownOpen = true"
                                                       @click="nurseDropdownOpen = true"
                                                       @input="nurseDropdownOpen = true; selectedNurse = null"
                                                       placeholder="Search..."
                                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 pr-8 text-sm">
                                                <button type="button" x-show="nurseSearch" @click="clearNurse()" class="absolute inset-y-0 right-0 pr-2 flex items-center text-gray-400 hover:text-gray-600">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                </button>
                                            </div>
                                            <div x-show="nurseDropdownOpen && filteredNurses.length > 0" 
                                                 @click.away="nurseDropdownOpen = false"
                                                 class="absolute z-10 mt-1 w-full bg-white shadow-lg max-h-40 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm">
                                                <template x-for="nurse in filteredNurses" :key="nurse.id">
                                                    <div @click="selectNurse(nurse)" 
                                                         class="cursor-pointer select-none relative py-2 pl-3 pr-9 hover:bg-blue-50"
                                                         :class="{'bg-blue-100': selectedNurse && selectedNurse.id === nurse.id}">
                                                        <span class="block truncate" x-text="nurse.name"></span>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Anaesthetist Searchable Dropdown -->
                                    <div class="relative">
                                        <label class="block text-sm font-medium text-gray-700">Anaesthetist (Optional)</label>
                                        <div class="mt-1 relative">
                                            <input type="text" 
                                                   x-model="anaesthetistSearch" 
                                                   @focus="anaesthetistDropdownOpen = true"
                                                   @click="anaesthetistDropdownOpen = true"
                                                   @input="anaesthetistDropdownOpen = true; selectedAnaesthetist = null"
                                                   placeholder="Search anaesthetist..."
                                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 pr-10">
                                            <button type="button" x-show="anaesthetistSearch" @click="clearAnaesthetist()" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                        <div x-show="anaesthetistDropdownOpen && filteredAnaesthetists.length > 0" 
                                             @click.away="anaesthetistDropdownOpen = false"
                                             class="absolute z-10 mt-1 w-full bg-white shadow-lg max-h-48 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm">
                                            <template x-for="anaesthetist in filteredAnaesthetists" :key="anaesthetist.id">
                                                <div @click="selectAnaesthetist(anaesthetist)" 
                                                     class="cursor-pointer select-none relative py-2 pl-3 pr-9 hover:bg-blue-50"
                                                     :class="{'bg-blue-100': selectedAnaesthetist && selectedAnaesthetist.id === anaesthetist.id}">
                                                    <span class="block truncate" x-text="anaesthetist.name"></span>
                                                </div>
                                            </template>
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
            patientId: null
         }"
         @open-ecg-modal.window="open = true; patientId = $event.detail.patientId"
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
                                Patient ECG
                            </h3>

                            <div class="mt-2">
                                <template x-if="patientId">
                                    <iframe
                                        :src="'{{ route('ecg.patient') }}?patient_id=' + patientId"
                                        class="w-full h-[550px] border-0 rounded-lg"
                                        title="Patient ECG">
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

    <!-- Clinical Modal -->
    <div x-data="{ 
            open: false, 
            patientId: null
         }"
         @open-clinical-modal.window="open = true; patientId = $event.detail.patientId"
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
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-cyan-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Clinical Information
                            </h3>

                            <div class="mt-2">
                                <template x-if="patientId">
                                    <iframe
                                        :src="'{{ route('ward.patient-details') }}?patient_id=' + patientId + '&tabs=additional'"
                                        class="w-full h-[550px] border-0 rounded-lg"
                                        title="Clinical Information">
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
                            class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-500 sm:w-auto sm:text-sm">
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

    <!-- Consultants Modal Component Script -->
    <script>
        function consultantsModalComponent() {
            return {
                open: false,
                searchQuery: '',
                consultants: @json($consultantPatients ?? []),
                
                getFilteredConsultants() {
                    if (!this.searchQuery.trim()) return this.consultants;
                    const query = this.searchQuery.toLowerCase();
                    return this.consultants.filter(consultant => {
                        if (consultant.name.toLowerCase().includes(query)) return true;
                        return consultant.patients.some(patient => 
                            patient.name.toLowerCase().includes(query) ||
                            (patient.mrn && patient.mrn.toLowerCase().includes(query)) ||
                            (patient.bed_number && patient.bed_number.toLowerCase().includes(query))
                        );
                    });
                },
                
                getRoleBadgeClass(role) {
                    const classes = {
                        'attending': 'bg-blue-100 text-blue-800 border-blue-200',
                        'referring': 'bg-purple-100 text-purple-800 border-purple-200',
                        'consulting': 'bg-green-100 text-green-800 border-green-200'
                    };
                    return classes[role] || 'bg-gray-100 text-gray-800 border-gray-200';
                },
                
                getRoleLabel(role) {
                    const labels = {
                        'attending': 'Attending',
                        'referring': 'Referring',
                        'consulting': 'Consulting'
                    };
                    return labels[role] || role;
                },
                
                openModal() {
                    this.open = true;
                    this.searchQuery = '';
                },
                
                closeModal() {
                    this.open = false;
                }
            };
        }
    </script>

    <!-- Consultants Modal -->
    <div x-data="consultantsModalComponent()" 
         @open-consultants-modal.window="openModal()"
         x-show="open" 
         class="fixed inset-0 z-50 overflow-y-auto" 
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="open" @click="closeModal()" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div x-show="open" @click.stop x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                <div class="bg-white px-4 pt-4 pb-3 sm:p-4">
                    <div class="sm:flex sm:items-start">
                        <div class="hidden sm:flex mx-auto flex-shrink-0 items-center justify-center h-8 w-8 rounded-full bg-cyan-100 sm:mx-0">
                            <svg class="h-5 w-5 text-cyan-600" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/>
                            </svg>
                        </div>
                        <div class="text-center sm:text-left sm:ml-3 flex-1">
                            <h3 class="text-base font-medium text-gray-900 mb-1.5">
                                Consultants & Their Patients
                            </h3>
                            
                            <!-- Role Legend - Compact -->
                            <div class="flex flex-wrap items-center gap-2 mb-3 text-xs text-gray-500">
                                <span>Roles:</span>
                                <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-blue-500"></span>Attending</span>
                                <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-purple-500"></span>Referring</span>
                                <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-500"></span>Consulting</span>
                            </div>
                            
                            <!-- Search Box - Compact -->
                            <div class="mb-3">
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                        </svg>
                                    </div>
                                    <input type="text" 
                                           x-model="searchQuery"
                                           placeholder="Search consultant, patient, MRN, bed..."
                                           class="block w-full pl-8 pr-8 py-1.5 border border-gray-300 rounded-md text-sm placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-cyan-500 focus:border-cyan-500">
                                    <button x-show="searchQuery" 
                                            @click="searchQuery = ''" 
                                            class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-gray-400 hover:text-gray-600">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mt-3 max-h-[60vh] overflow-y-auto">
                                <template x-if="getFilteredConsultants().length > 0">
                                    <div class="space-y-3">
                                        <template x-for="consultant in getFilteredConsultants()" :key="consultant.id">
                                            <div class="bg-gray-50 rounded-lg p-2.5 border border-gray-200">
                                                <h4 class="font-medium text-sm text-cyan-700 mb-2 flex items-center cursor-pointer hover:text-cyan-900"
                                                    @click="highlightAndFilterBeds(consultant.patients.map(p => p.bed_number).filter(b => b), 'consultant')">
                                                    <svg class="w-4 h-4 mr-1.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/>
                                                    </svg>
                                                    <span x-text="consultant.name" class="truncate"></span>
                                                    <span class="ml-1.5 text-xs bg-cyan-100 text-cyan-800 px-1.5 py-0.5 rounded-full flex-shrink-0" x-text="consultant.patients.length"></span>
                                                </h4>
                                                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-1.5">
                                                    <template x-for="patient in consultant.patients" :key="patient.id + '_' + patient.role">
                                                        <div class="bg-white px-2 py-1.5 rounded border border-gray-200 hover:border-cyan-400 cursor-pointer transition-colors text-xs"
                                                             @click="highlightAndFilterBeds([patient.bed_number], 'bed')"
                                                             :title="patient.name + ' (MRN: ' + patient.mrn + ') - ' + getRoleLabel(patient.role)">
                                                            <div class="flex items-center gap-1.5">
                                                                <!-- Role indicator dot -->
                                                                <span class="w-2 h-2 rounded-full flex-shrink-0"
                                                                      :class="{
                                                                          'bg-blue-500': patient.role === 'attending',
                                                                          'bg-purple-500': patient.role === 'referring',
                                                                          'bg-green-500': patient.role === 'consulting',
                                                                          'bg-gray-400': !['attending', 'referring', 'consulting'].includes(patient.role)
                                                                      }"></span>
                                                                <span class="truncate flex-1 text-gray-800" x-text="patient.name"></span>
                                                                <span class="text-cyan-600 font-medium flex-shrink-0" x-text="patient.bed_number || '-'"></span>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="getFilteredConsultants().length === 0 && searchQuery">
                                    <div class="text-center text-gray-500 py-6">
                                        <svg class="w-10 h-10 mx-auto mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                        </svg>
                                        <p class="text-sm">No results for "<span x-text="searchQuery" class="font-medium"></span>"</p>
                                        <button @click="searchQuery = ''" class="mt-1 text-cyan-600 hover:text-cyan-800 text-xs">Clear</button>
                                    </div>
                                </template>
                                <template x-if="consultants.length === 0 && !searchQuery">
                                    <div class="text-center text-gray-500 py-6">
                                        <svg class="w-10 h-10 mx-auto mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                        </svg>
                                        <p class="text-sm">No consultants assigned yet.</p>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-2 sm:px-4 sm:flex sm:flex-row-reverse">
                    <button @click="closeModal()" type="button" class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-3 py-1.5 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-1 focus:ring-cyan-500 sm:w-auto">
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
    </script>
</x-app-layout>

