<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ward Settings</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .sortable-ghost {
            opacity: 0.4;
            background: #f3f4f6;
        }

        .sortable-chosen {
            background: #e5e7eb;
        }

        .drag-handle {
            cursor: grab;
        }

        .drag-handle:active {
            cursor: grabbing;
        }
    </style>
</head>

<body class="bg-gray-50">
    <div class="p-4" x-data="settingsManager()">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-xl font-bold text-gray-800">Settings</h2>
                <p class="text-sm text-gray-600 mt-1">Manage ward dashboard configurations.</p>
            </div>
        </div>

        @if(session('success'))
            <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-800 border border-green-200">
                {{ session('success') }}
            </div>
            <script>
                // Notify parent window to refresh dashboard when settings are updated
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({ type: 'settings-updated' }, '*');
                }
            </script>
        @endif

        <!-- Tabs Navigation -->
        <div class="border-b border-gray-200 mb-4">
            <nav class="-mb-px flex flex-wrap space-x-4 text-sm" aria-label="Tabs">
                <button type="button" @click="activeTab = 'dashboard-display'"
                    :class="activeTab === 'dashboard-display' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-2 px-3 border-b-2 font-medium transition-colors">
                    Dashboard Display
                </button>
                <button type="button" @click="activeTab = 'patient-details'"
                    :class="activeTab === 'patient-details' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-2 px-3 border-b-2 font-medium transition-colors">
                    Patient Details Tab
                </button>
                <button type="button" @click="activeTab = 'bed-box-config'"
                    :class="activeTab === 'bed-box-config' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-2 px-3 border-b-2 font-medium transition-colors">
                    Bed Box Config
                </button>
                <button type="button" @click="activeTab = 'patient-info'"
                    :class="activeTab === 'patient-info' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-2 px-3 border-b-2 font-medium transition-colors">
                    Patient Info
                </button>
                <button type="button" @click="activeTab = 'clinical-setting'"
                    :class="activeTab === 'clinical-setting' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-2 px-3 border-b-2 font-medium transition-colors">
                    Clinical Setting
                </button>
                <button type="button" @click="activeTab = 'logout'"
                    :class="activeTab === 'logout' ? 'border-red-500 text-red-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-2 px-3 border-b-2 font-medium transition-colors">
                    Logout
                </button>
            </nav>
        </div>

        <!-- Dashboard Display Tab Panel -->
        <div x-show="activeTab === 'dashboard-display'" x-transition
            class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <h3 class="text-lg font-semibold text-gray-800 mb-3">Dashboard Display Settings</h3>
            <p class="text-sm text-gray-600 mb-4">
                Configure how patient information is displayed on the ward dashboard.
            </p>

            <form method="POST" action="{{ route('ward.settings.update') }}" id="dashboardDisplayForm">
                @csrf
                <input type="hidden" name="setting_type" value="dashboard_display">
                <input type="hidden" name="dashboard_display_config" x-model="dashboardDisplayJson">

                <div class="space-y-6">
                    <!-- Patient Name Masking Section -->
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">
                            <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            Patient Name Display
                        </h4>
                        <p class="text-xs text-gray-500 mb-4">Choose how patient names are displayed for privacy
                            purposes.</p>

                        <!-- Preview Card -->
                        <div class="bg-gray-100 rounded-lg p-4 mb-4">
                            <div
                                class="bg-white rounded-lg shadow-sm border border-blue-500 overflow-hidden max-w-xs mx-auto">
                                <div class="px-4 py-2 bg-blue-500 text-white flex items-center justify-between">
                                    <span class="font-bold">Bed 01</span>
                                    <span class="text-sm">MRN: 12345678</span>
                                </div>
                                <div class="p-3">
                                    <div class="flex items-center text-sm font-semibold text-gray-800">
                                        <svg class="w-4 h-4 mr-2 text-gray-600" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        <span x-text="getMaskedNamePreview('John Michael Smith')"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Options -->
                        <div class="space-y-2">
                            <label
                                class="flex items-center p-3 bg-gray-50 hover:bg-gray-100 rounded-lg border border-gray-200 cursor-pointer transition-colors"
                                :class="dashboardDisplaySettings.patient_name_mask === 'full' ? 'ring-2 ring-blue-500 border-blue-500' : ''">
                                <input type="radio" name="patient_name_mask_radio" value="full"
                                    x-model="dashboardDisplaySettings.patient_name_mask"
                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                <div class="ml-3">
                                    <span class="text-sm font-medium text-gray-700">Full Name</span>
                                    <p class="text-xs text-gray-500">Display complete name: John Michael Smith</p>
                                </div>
                            </label>

                            <label
                                class="flex items-center p-3 bg-gray-50 hover:bg-gray-100 rounded-lg border border-gray-200 cursor-pointer transition-colors"
                                :class="dashboardDisplaySettings.patient_name_mask === 'first_only' ? 'ring-2 ring-blue-500 border-blue-500' : ''">
                                <input type="radio" name="patient_name_mask_radio" value="first_only"
                                    x-model="dashboardDisplaySettings.patient_name_mask"
                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                <div class="ml-3">
                                    <span class="text-sm font-medium text-gray-700">First Name Only</span>
                                    <p class="text-xs text-gray-500">Show first name, mask rest: John M**** S****</p>
                                </div>
                            </label>

                            <label
                                class="flex items-center p-3 bg-gray-50 hover:bg-gray-100 rounded-lg border border-gray-200 cursor-pointer transition-colors"
                                :class="dashboardDisplaySettings.patient_name_mask === 'last_only' ? 'ring-2 ring-blue-500 border-blue-500' : ''">
                                <input type="radio" name="patient_name_mask_radio" value="last_only"
                                    x-model="dashboardDisplaySettings.patient_name_mask"
                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                <div class="ml-3">
                                    <span class="text-sm font-medium text-gray-700">Last Name Only</span>
                                    <p class="text-xs text-gray-500">Show last name, mask rest: J*** M****** Smith</p>
                                </div>
                            </label>

                            <label
                                class="flex items-center p-3 bg-gray-50 hover:bg-gray-100 rounded-lg border border-gray-200 cursor-pointer transition-colors"
                                :class="dashboardDisplaySettings.patient_name_mask === 'initials' ? 'ring-2 ring-blue-500 border-blue-500' : ''">
                                <input type="radio" name="patient_name_mask_radio" value="initials"
                                    x-model="dashboardDisplaySettings.patient_name_mask"
                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                <div class="ml-3">
                                    <span class="text-sm font-medium text-gray-700">Initials Only</span>
                                    <p class="text-xs text-gray-500">Display initials only: J.M.S.</p>
                                </div>
                            </label>

                            <label
                                class="flex items-center p-3 bg-gray-50 hover:bg-gray-100 rounded-lg border border-gray-200 cursor-pointer transition-colors"
                                :class="dashboardDisplaySettings.patient_name_mask === 'first_last_initial' ? 'ring-2 ring-blue-500 border-blue-500' : ''">
                                <input type="radio" name="patient_name_mask_radio" value="first_last_initial"
                                    x-model="dashboardDisplaySettings.patient_name_mask"
                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                <div class="ml-3">
                                    <span class="text-sm font-medium text-gray-700">First + Last Initial</span>
                                    <p class="text-xs text-gray-500">First name with last initial: John S.</p>
                                </div>
                            </label>

                            <label
                                class="flex items-center p-3 bg-gray-50 hover:bg-gray-100 rounded-lg border border-gray-200 cursor-pointer transition-colors"
                                :class="dashboardDisplaySettings.patient_name_mask === 'all_asterisk' ? 'ring-2 ring-blue-500 border-blue-500' : ''">
                                <input type="radio" name="patient_name_mask_radio" value="all_asterisk"
                                    x-model="dashboardDisplaySettings.patient_name_mask"
                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                <div class="ml-3">
                                    <span class="text-sm font-medium text-gray-700">Full Asterisk</span>
                                    <p class="text-xs text-gray-500">Completely masked: **** ******* *****</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Fullscreen Mode Section -->
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">
                            <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                            </svg>
                            Fullscreen Mode Layout
                        </h4>
                        <p class="text-xs text-gray-500 mb-4">Configure the number of patient bed boxes per row when in
                            fullscreen mode.</p>

                        <!-- Visual Preview -->
                        <div class="bg-gray-100 rounded-lg p-4 mb-4">
                            <div class="text-center mb-3">
                                <span class="text-sm font-medium text-gray-600">Preview: <span
                                        x-text="getFullscreenLabel()"></span> beds per row</span>
                            </div>
                            <div class="flex justify-center gap-1">
                                <template x-for="i in getFullscreenCount()" :key="i">
                                    <div
                                        class="w-8 h-8 bg-blue-500 rounded text-white flex items-center justify-center text-xs font-bold">
                                        <span x-text="i"></span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Options -->
                        <div class="grid grid-cols-3 gap-3">
                            <label
                                class="flex flex-col items-center p-4 bg-gray-50 hover:bg-gray-100 rounded-lg border-2 cursor-pointer transition-all"
                                :class="dashboardDisplaySettings.fullscreen_mode === 'small' ? 'border-blue-500 bg-blue-50' : 'border-gray-200'">
                                <input type="radio" name="fullscreen_mode_radio" value="small"
                                    x-model="dashboardDisplaySettings.fullscreen_mode" class="sr-only">
                                <div class="flex gap-0.5 mb-2">
                                    <template x-for="i in 8" :key="i">
                                        <div class="w-3 h-3 bg-blue-400 rounded-sm"></div>
                                    </template>
                                </div>
                                <span class="text-sm font-semibold text-gray-700">Small</span>
                                <span class="text-xs text-gray-500">8 per row</span>
                            </label>

                            <label
                                class="flex flex-col items-center p-4 bg-gray-50 hover:bg-gray-100 rounded-lg border-2 cursor-pointer transition-all"
                                :class="dashboardDisplaySettings.fullscreen_mode === 'medium' ? 'border-blue-500 bg-blue-50' : 'border-gray-200'">
                                <input type="radio" name="fullscreen_mode_radio" value="medium"
                                    x-model="dashboardDisplaySettings.fullscreen_mode" class="sr-only">
                                <div class="flex gap-0.5 mb-2">
                                    <template x-for="i in 6" :key="i">
                                        <div class="w-4 h-4 bg-green-400 rounded-sm"></div>
                                    </template>
                                </div>
                                <span class="text-sm font-semibold text-gray-700">Medium</span>
                                <span class="text-xs text-gray-500">6 per row</span>
                            </label>

                            <label
                                class="flex flex-col items-center p-4 bg-gray-50 hover:bg-gray-100 rounded-lg border-2 cursor-pointer transition-all"
                                :class="dashboardDisplaySettings.fullscreen_mode === 'large' ? 'border-blue-500 bg-blue-50' : 'border-gray-200'">
                                <input type="radio" name="fullscreen_mode_radio" value="large"
                                    x-model="dashboardDisplaySettings.fullscreen_mode" class="sr-only">
                                <div class="flex gap-1 mb-2">
                                    <template x-for="i in 4" :key="i">
                                        <div class="w-5 h-5 bg-purple-400 rounded-sm"></div>
                                    </template>
                                </div>
                                <span class="text-sm font-semibold text-gray-700">Large</span>
                                <span class="text-xs text-gray-500">4 per row</span>
                            </label>
                        </div>

                        <!-- Text Size for Fullscreen -->
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <h5 class="text-xs font-semibold text-gray-600 mb-3 flex items-center">
                                <svg class="w-3.5 h-3.5 mr-1.5 text-gray-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 6h16M4 12h8m-8 6h16" />
                                </svg>
                                Text Size in Fullscreen Mode
                            </h5>
                            <div class="grid grid-cols-3 gap-2">
                                <label
                                    class="flex flex-col items-center p-3 bg-gray-50 hover:bg-gray-100 rounded-lg border-2 cursor-pointer transition-all"
                                    :class="dashboardDisplaySettings.fullscreen_text_size === 'small' ? 'border-blue-500 bg-blue-50' : 'border-gray-200'">
                                    <input type="radio" name="fullscreen_text_size_radio" value="small"
                                        x-model="dashboardDisplaySettings.fullscreen_text_size" class="sr-only">
                                    <span class="text-xs font-medium text-gray-600">Aa</span>
                                    <span class="text-xs font-semibold text-gray-700 mt-1">Small</span>
                                </label>

                                <label
                                    class="flex flex-col items-center p-3 bg-gray-50 hover:bg-gray-100 rounded-lg border-2 cursor-pointer transition-all"
                                    :class="dashboardDisplaySettings.fullscreen_text_size === 'medium' ? 'border-blue-500 bg-blue-50' : 'border-gray-200'">
                                    <input type="radio" name="fullscreen_text_size_radio" value="medium"
                                        x-model="dashboardDisplaySettings.fullscreen_text_size" class="sr-only">
                                    <span class="text-sm font-medium text-gray-600">Aa</span>
                                    <span class="text-xs font-semibold text-gray-700 mt-1">Medium</span>
                                </label>

                                <label
                                    class="flex flex-col items-center p-3 bg-gray-50 hover:bg-gray-100 rounded-lg border-2 cursor-pointer transition-all"
                                    :class="dashboardDisplaySettings.fullscreen_text_size === 'large' ? 'border-blue-500 bg-blue-50' : 'border-gray-200'">
                                    <input type="radio" name="fullscreen_text_size_radio" value="large"
                                        x-model="dashboardDisplaySettings.fullscreen_text_size" class="sr-only">
                                    <span class="text-base font-medium text-gray-600">Aa</span>
                                    <span class="text-xs font-semibold text-gray-700 mt-1">Large</span>
                                </label>
                            </div>
                        </div>

                        <!-- Resolution for Fullscreen -->
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <h5 class="text-xs font-semibold text-gray-600 mb-3 flex items-center">
                                <svg class="w-3.5 h-3.5 mr-1.5 text-gray-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                                Resolution
                            </h5>
                            <p class="text-xs text-gray-500 mb-3">Optimize display for specific screen resolutions in
                                fullscreen mode.</p>
                            <div class="grid grid-cols-3 gap-2">
                                <label
                                    class="flex flex-col items-center p-3 bg-gray-50 hover:bg-gray-100 rounded-lg border-2 cursor-pointer transition-all"
                                    :class="dashboardDisplaySettings.fullscreen_resolution === 'default' ? 'border-blue-500 bg-blue-50' : 'border-gray-200'">
                                    <input type="radio" name="fullscreen_resolution_radio" value="default"
                                        x-model="dashboardDisplaySettings.fullscreen_resolution" class="sr-only">
                                    <div class="w-8 h-5 bg-gray-300 rounded mb-1 flex items-center justify-center">
                                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <span class="text-xs font-semibold text-gray-700">Default</span>
                                    <span class="text-[10px] text-gray-500">Auto-detect</span>
                                </label>

                                <label
                                    class="flex flex-col items-center p-3 bg-gray-50 hover:bg-gray-100 rounded-lg border-2 cursor-pointer transition-all"
                                    :class="dashboardDisplaySettings.fullscreen_resolution === '1920x1080' ? 'border-blue-500 bg-blue-50' : 'border-gray-200'">
                                    <input type="radio" name="fullscreen_resolution_radio" value="1920x1080"
                                        x-model="dashboardDisplaySettings.fullscreen_resolution" class="sr-only">
                                    <div
                                        class="w-10 h-6 bg-blue-200 rounded mb-1 flex items-center justify-center border border-blue-300">
                                        <span class="text-[8px] font-bold text-blue-600">FHD</span>
                                    </div>
                                    <span class="text-xs font-semibold text-gray-700">1920×1080</span>
                                    <span class="text-[10px] text-gray-500">Full HD</span>
                                </label>

                                <label
                                    class="flex flex-col items-center p-3 bg-gray-50 hover:bg-gray-100 rounded-lg border-2 cursor-pointer transition-all"
                                    :class="dashboardDisplaySettings.fullscreen_resolution === '3840x2160' ? 'border-blue-500 bg-blue-50' : 'border-gray-200'">
                                    <input type="radio" name="fullscreen_resolution_radio" value="3840x2160"
                                        x-model="dashboardDisplaySettings.fullscreen_resolution" class="sr-only">
                                    <div
                                        class="w-12 h-7 bg-purple-200 rounded mb-1 flex items-center justify-center border border-purple-300">
                                        <span class="text-[8px] font-bold text-purple-600">4K</span>
                                    </div>
                                    <span class="text-xs font-semibold text-gray-700">3840×2160</span>
                                    <span class="text-[10px] text-gray-500">4K UHD</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-4 flex justify-end border-t mt-4">
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Save Settings
                    </button>
                </div>
            </form>
        </div>

        <!-- Patient Details Tab Panel -->
        <div x-show="activeTab === 'patient-details'" x-transition
            class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <h3 class="text-lg font-semibold text-gray-800 mb-3">Patient Details Modal Tabs</h3>
            <p class="text-sm text-gray-600 mb-4">
                Toggle the visibility of tabs in the Patient Details popup. Changes are saved per user.
            </p>

            <form method="POST" action="{{ route('ward.settings.update') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="setting_type" value="patient_details">

                @php
                    $labels = [
                        'info' => 'Patient Info',
                        'additional' => 'Additional Info',
                        'vitals' => 'Vital Signs',
                        'movement' => 'Patient Movement',
                        'careprovider' => 'Care Provider',
                        'infusion' => 'Infusion Management',
                        'transfer' => 'Transfer Bed',
                        'discharge' => 'Discharge',
                    ];

                    $descs = [
                        'info' => 'Basic demographics and admission details.',
                        'additional' => 'Extended details, contacts, and allergies.',
                        'vitals' => 'Vital signs charts and tables.',
                        'movement' => 'Schedule procedures outside the ward.',
                        'careprovider' => 'Attending, referring, and consulting doctors from ADT.',
                        'infusion' => 'IV fluids and medication management.',
                        'transfer' => 'Move patient to another bed or ward.',
                        'discharge' => 'Process patient discharge.',
                    ];
                @endphp

                @foreach($tabs as $key => $enabled)
                    <label
                        class="flex items-center space-x-3 cursor-pointer p-2 hover:bg-gray-50 rounded border border-transparent hover:border-gray-100">
                        <div class="relative flex items-start">
                            <div class="flex items-center h-5">
                                <input type="checkbox" id="toggle-{{ $key }}" name="tabs[{{ $key }}]" value="1"
                                    @checked($enabled)
                                    class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                            </div>
                        </div>
                        <div class="text-sm select-none">
                            <span class="font-medium text-gray-700">{{ $labels[$key] ?? ucfirst($key) }}</span>
                            <p class="text-gray-500 text-xs">{{ $descs[$key] ?? '' }}</p>
                        </div>
                    </label>
                @endforeach

                <div class="pt-3 flex justify-end">
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Save Settings
                    </button>
                </div>
            </form>

        </div>

        <!-- Bed Box Config Tab Panel -->
        <div x-show="activeTab === 'bed-box-config'" x-transition
            class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <h3 class="text-lg font-semibold text-gray-800 mb-3">Bed Box Display Configuration</h3>
            <p class="text-sm text-gray-600 mb-4">
                Configure what information to display on each bed card in the dashboard. Drag to reorder, toggle to
                show/hide.
            </p>

            <form method="POST" action="{{ route('ward.settings.update') }}" id="bedBoxForm">
                @csrf
                <input type="hidden" name="setting_type" value="bed_box_display">
                <input type="hidden" name="bed_box_order" x-model="orderJson">

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Bed Box Preview -->
                    <div class="order-2 lg:order-1">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">
                            <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            Live Preview
                        </h4>
                        <div class="bg-gray-100 rounded-lg p-4">
                            <!-- Bed Box Preview Card -->
                            <div
                                class="bg-white rounded-lg shadow-md border-2 border-blue-500 overflow-hidden max-w-xs mx-auto h-[280px] flex flex-col">
                                <!-- Header -->
                                <div
                                    class="px-4 py-2 bg-blue-500 text-white flex items-center justify-between flex-shrink-0">
                                    <span class="font-bold">Bed 01</span>
                                    <span class="text-sm"
                                        x-show="bedBoxItems.find(i => i.key === 'mrn')?.visible">12345678</span>
                                </div>

                                <!-- Content Area -->
                                <div class="p-3 space-y-1.5 flex-1 overflow-hidden">
                                    <template x-for="item in visibleItems" :key="item.key">
                                        <div class="flex items-center text-sm min-w-0"
                                            :class="item.key === 'patient_name' ? 'font-semibold text-gray-800' : 'text-xs text-gray-600'">
                                            <svg class="w-4 h-4 mr-2 text-gray-500 flex-shrink-0" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24"
                                                x-html="getIcon(item.key)"></svg>
                                            <span class="truncate" x-text="getSampleValue(item.key)"></span>
                                        </div>
                                    </template>

                                    <!-- EWS Badge (if visible) -->
                                    <div class="flex items-center justify-between pt-2"
                                        x-show="bedBoxItems.find(i => i.key === 'ews')?.visible">
                                        <span class="px-2 py-1 bg-green-500 text-white text-xs rounded font-bold">EWS:
                                            3</span>
                                    </div>
                                </div>

                                <!-- Footer Buttons: 1-Patient Details, 2-Vital Signs, 3-ECG, 4-Clinical -->
                                <div class="flex items-center space-x-1 border-t px-3 py-2 flex-shrink-0 mt-auto">
                                    {{-- Button 1: Patient Details --}}
                                    <template x-if="bedBoxItems.find(i => i.key === 'patient_details_button')?.visible">
                                        <button type="button"
                                            class="flex-1 p-2 text-gray-600 hover:bg-gray-100 rounded transition-colors"
                                            title="Patient Details">
                                            <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M4 6h16M4 12h16M4 18h16" />
                                            </svg>
                                        </button>
                                    </template>
                                    {{-- Button 2: Vital Signs --}}
                                    <button type="button"
                                        class="flex-1 p-2 text-red-600 hover:bg-red-50 rounded transition-colors"
                                        title="Vital Signs">
                                        <svg class="w-4 h-4 mx-auto" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                    {{-- Button 3: ECG --}}
                                    <button type="button"
                                        class="flex-1 p-2 text-emerald-600 hover:bg-emerald-50 rounded transition-colors"
                                        title="ECG">
                                        <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M3 12h4l3-9 4 18 3-9h4" />
                                        </svg>
                                    </button>
                                    {{-- Button 4: Clinical --}}
                                    <button type="button"
                                        class="flex-1 p-2 text-cyan-600 hover:bg-cyan-50 rounded transition-colors"
                                        title="Clinical">
                                        <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Configuration Controls -->
                    <div class="order-1 lg:order-2">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">
                            <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                            </svg>
                            Display Fields
                        </h4>
                        <p class="text-xs text-gray-500 mb-3">Drag to reorder • Toggle to show/hide</p>

                        <div id="sortableList" class="space-y-2">
                            <template x-for="(item, index) in bedBoxItems" :key="item.key">
                                <div class="flex items-center bg-gray-50 hover:bg-gray-100 rounded-lg p-3 border border-gray-200 transition-all"
                                    :data-key="item.key">
                                    <!-- Drag Handle -->
                                    <div class="drag-handle mr-3 text-gray-400 hover:text-gray-600">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4 8h16M4 16h16" />
                                        </svg>
                                    </div>

                                    <!-- Icon -->
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3"
                                        :class="item.visible ? 'bg-blue-100 text-blue-600' : 'bg-gray-200 text-gray-400'">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            x-html="getIcon(item.key)"></svg>
                                    </div>

                                    <!-- Label -->
                                    <div class="flex-1">
                                        <span class="text-sm font-medium"
                                            :class="item.visible ? 'text-gray-800' : 'text-gray-400'"
                                            x-text="item.label"></span>
                                        <p class="text-xs" :class="item.visible ? 'text-gray-500' : 'text-gray-400'"
                                            x-text="item.description"></p>
                                    </div>

                                    <!-- Toggle Switch -->
                                    <div class="ml-3">
                                        <button type="button" @click="toggleVisibility(item.key)"
                                            :class="item.visible ? 'bg-blue-600' : 'bg-gray-300'"
                                            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                            <span :class="item.visible ? 'translate-x-5' : 'translate-x-0'"
                                                class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="pt-6 flex justify-between items-center border-t mt-6">
                    <button type="button" @click="resetToDefaults()"
                        class="inline-flex items-center px-4 py-2 text-gray-600 text-sm font-medium hover:text-gray-800 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Reset to Defaults
                    </button>
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Save Settings
                    </button>
                </div>
            </form>

            <!-- Vital Signs Data Mode Section -->
            <div class="border-t border-gray-200 mt-6 pt-6">
                <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">
                    <svg class="w-4 h-4 mr-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                    </svg>
                    Vital Signs Data Mode
                </h4>
                <p class="text-xs text-gray-500 mb-4">
                    Configure the data source for vital signs displayed on bed cards and in patient details.
                </p>

                <form method="POST" action="{{ route('ward.settings.update') }}">
                    @csrf
                    <input type="hidden" name="setting_type" value="bed_box_vitals_mode">

                    <div class="grid grid-cols-3 gap-3">
                        <label
                            class="flex flex-col items-center p-4 bg-gray-50 hover:bg-gray-100 rounded-lg border-2 cursor-pointer transition-all"
                            :class="bedBoxVitalsMode === 'demo' ? 'border-blue-500 bg-blue-50' : 'border-gray-200'"
                            x-data="" @click="bedBoxVitalsMode = 'demo'">
                            <input type="radio" name="bed_box_vitals_mode" value="demo" x-model="bedBoxVitalsMode"
                                class="sr-only">
                            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center mb-2">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <span class="text-sm font-semibold text-gray-700">Demo</span>
                            <span class="text-xs text-gray-500 text-center mt-1">Show demo/sample vital signs
                                data</span>
                        </label>

                        <label
                            class="flex flex-col items-center p-4 bg-gray-50 hover:bg-gray-100 rounded-lg border-2 cursor-pointer transition-all"
                            :class="bedBoxVitalsMode === 'real' ? 'border-green-500 bg-green-50' : 'border-gray-200'"
                            x-data="" @click="bedBoxVitalsMode = 'real'">
                            <input type="radio" name="bed_box_vitals_mode" value="real" x-model="bedBoxVitalsMode"
                                class="sr-only">
                            <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center mb-2">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <span class="text-sm font-semibold text-gray-700">Real</span>
                            <span class="text-xs text-gray-500 text-center mt-1">Show real vital signs from
                                database</span>
                        </label>

                        <label
                            class="flex flex-col items-center p-4 bg-gray-50 hover:bg-gray-100 rounded-lg border-2 cursor-pointer transition-all"
                            :class="bedBoxVitalsMode === 'off' ? 'border-gray-500 bg-gray-100' : 'border-gray-200'"
                            x-data="" @click="bedBoxVitalsMode = 'off'">
                            <input type="radio" name="bed_box_vitals_mode" value="off" x-model="bedBoxVitalsMode"
                                class="sr-only">
                            <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center mb-2">
                                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                </svg>
                            </div>
                            <span class="text-sm font-semibold text-gray-700">Off</span>
                            <span class="text-xs text-gray-500 text-center mt-1">Hide vital signs completely</span>
                        </label>
                    </div>

                    <div class="pt-4 flex justify-end">
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Save Vitals Mode
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Patient Info Tab Panel (Clinical Indicators) -->
        <div x-show="activeTab === 'patient-info'" x-transition
            class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <h3 class="text-lg font-semibold text-gray-800 mb-3">Patient Clinical Indicators Configuration</h3>
            <p class="text-sm text-gray-600 mb-4">
                Configure clinical indicators display and manage available options. Changes here will be reflected in
                Patient Details &gt; Additional Info tab.
            </p>

            <!-- Two forms: one for visibility, one for options -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Left: Live Preview & Visibility Toggles -->
                <div>
                    <form method="POST" action="{{ route('ward.settings.update') }}" id="patientInfoForm">
                        @csrf
                        <input type="hidden" name="setting_type" value="patient_info_display">
                        <input type="hidden" name="patient_info_config" x-model="patientInfoJson">

                        <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">
                            <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            Live Preview & Display Settings
                        </h4>

                        <!-- Live Preview -->
                        <div class="bg-gray-100 rounded-lg p-4 mb-4">
                            <div
                                class="bg-white rounded-lg shadow-md border-2 border-blue-500 overflow-hidden max-w-sm mx-auto">
                                <div class="px-4 py-2 bg-blue-500 text-white flex items-center justify-between">
                                    <span class="font-bold">Bed 01</span>
                                    <span class="text-sm">MRN: 12345678</span>
                                </div>
                                <div class="p-3 space-y-2">
                                    <div class="flex items-center text-sm font-semibold text-gray-800">John Smith</div>
                                    <div class="flex items-center flex-wrap gap-1.5 pt-2 border-t border-gray-100">
                                        <span class="px-2 py-1 bg-green-500 text-white text-xs rounded font-bold">EWS:
                                            3</span>
                                        <template x-if="patientInfoItems.find(i => i.key === 'nursing_level')?.visible">
                                            <span
                                                class="px-1.5 py-1 bg-purple-100 text-purple-700 text-xs rounded font-medium">L2</span>
                                        </template>
                                        <template x-if="patientInfoItems.find(i => i.key === 'diet_type')?.visible">
                                            <span
                                                class="px-1.5 py-1 bg-red-100 text-red-700 text-xs rounded font-medium">NPO</span>
                                        </template>
                                        <template x-if="patientInfoItems.find(i => i.key === 'fall_risk')?.visible">
                                            <span
                                                class="px-1.5 py-1 bg-orange-100 text-orange-700 text-xs rounded font-medium">FR</span>
                                        </template>
                                        <template
                                            x-if="patientInfoItems.find(i => i.key === 'isolation_type')?.visible">
                                            <span
                                                class="px-1.5 py-1 bg-yellow-100 text-yellow-700 text-xs rounded font-medium">ISO</span>
                                        </template>
                                        <template x-if="patientInfoItems.find(i => i.key === 'allergies')?.visible">
                                            <span
                                                class="px-1.5 py-1 bg-pink-100 text-pink-700 text-xs rounded font-medium">ALG</span>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Toggle Switches -->
                        <div class="space-y-2">
                            <template x-for="item in patientInfoItems" :key="item.key">
                                <div
                                    class="flex items-center justify-between bg-gray-50 hover:bg-gray-100 rounded-lg p-2 border border-gray-200">
                                    <div class="flex items-center">
                                        <div class="w-7 h-7 rounded-full flex items-center justify-center mr-2"
                                            :class="item.visible ? item.bgColor + ' ' + item.textColor : 'bg-gray-200 text-gray-400'">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24" x-html="getPatientInfoIcon(item.key)"></svg>
                                        </div>
                                        <span class="text-sm font-medium"
                                            :class="item.visible ? 'text-gray-800' : 'text-gray-400'"
                                            x-text="item.label"></span>
                                    </div>
                                    <button type="button" @click="togglePatientInfo(item.key)"
                                        :class="item.visible ? 'bg-blue-600' : 'bg-gray-300'"
                                        class="relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out">
                                        <span :class="item.visible ? 'translate-x-4' : 'translate-x-0'"
                                            class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                                    </button>
                                </div>
                            </template>
                        </div>

                        <div class="pt-4 flex justify-end">
                            <button type="submit"
                                class="inline-flex items-center px-3 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-blue-700">
                                Save Display Settings
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Right: Options Configuration -->
                <div>
                    <form method="POST" action="{{ route('ward.settings.clinical-options') }}" id="clinicalOptionsForm">
                        @csrf
                        <input type="hidden" name="clinical_options" x-model="clinicalOptionsJson">

                        <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">
                            <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                            </svg>
                            Configure Available Options
                        </h4>
                        <p class="text-xs text-gray-500 mb-3">These options appear in Patient Details &gt; Additional
                            Info tab dropdowns</p>

                        <!-- Tabs for each indicator type -->
                        <div class="border-b border-gray-200 mb-3">
                            <nav class="-mb-px flex space-x-2 text-xs overflow-x-auto">
                                <button type="button" @click="activeOptionsTab = 'nursing_level'"
                                    :class="activeOptionsTab === 'nursing_level' ? 'border-purple-500 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                                    class="whitespace-nowrap py-2 px-2 border-b-2 font-medium">
                                    Nursing Level
                                </button>
                                <button type="button" @click="activeOptionsTab = 'diet_type'"
                                    :class="activeOptionsTab === 'diet_type' ? 'border-red-500 text-red-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                                    class="whitespace-nowrap py-2 px-2 border-b-2 font-medium">
                                    Diet Type
                                </button>
                                <button type="button" @click="activeOptionsTab = 'fall_risk'"
                                    :class="activeOptionsTab === 'fall_risk' ? 'border-orange-500 text-orange-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                                    class="whitespace-nowrap py-2 px-2 border-b-2 font-medium">
                                    Fall Risk
                                </button>
                                <button type="button" @click="activeOptionsTab = 'isolation_type'"
                                    :class="activeOptionsTab === 'isolation_type' ? 'border-yellow-500 text-yellow-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
                                    class="whitespace-nowrap py-2 px-2 border-b-2 font-medium">
                                    Isolation
                                </button>
                            </nav>
                        </div>

                        <!-- Deprecation Warning for Diet Type and Isolation -->
                        <div x-show="activeOptionsTab === 'diet_type' || activeOptionsTab === 'isolation_type'"
                            class="mb-3 p-3 bg-yellow-50 rounded-lg border-2 border-yellow-400">
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-yellow-600 mr-2 flex-shrink-0 mt-0.5" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <div class="text-sm text-yellow-800">
                                    <strong>⚠️ DEPRECATED:</strong> This configuration method is deprecated and may
                                    cause double updates.
                                    Please use the <a href="/diet-types"
                                        class="underline font-semibold hover:text-yellow-900">/diet-types</a> page to
                                    manage
                                    <span
                                        x-text="activeOptionsTab === 'diet_type' ? 'Diet Types' : 'Isolation Types'"></span>
                                    instead.
                                </div>
                            </div>
                        </div>

                        <!-- Options List for each type -->
                        <div class="bg-gray-50 rounded-lg p-3 border border-gray-200 max-h-64 overflow-y-auto">
                            <template x-for="(option, index) in clinicalOptions[activeOptionsTab]"
                                :key="option.value + index">
                                <div class="flex items-center justify-between py-1.5 px-2 hover:bg-gray-100 rounded">
                                    <div class="flex items-center">
                                        <span class="px-2 py-0.5 text-xs rounded mr-2" :class="option.color"
                                            x-text="option.label"></span>
                                        <span class="text-xs text-gray-500" x-text="option.value"></span>
                                    </div>
                                    <button type="button" @click="removeOption(activeOptionsTab, index)"
                                        class="text-red-500 hover:text-red-700 p-1"
                                        :disabled="option.value === 'none'|| activeOptionsTab === 'diet_type' || activeOptionsTab === 'isolation_type'"
                                        :class="option.value === 'none' || activeOptionsTab === 'diet_type' || activeOptionsTab === 'isolation_type' ? 'opacity-30 cursor-not-allowed' : ''">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </template>
                        </div>

                        <!-- Add New Option -->
                        <div class="mt-3 p-3 bg-gray-50 rounded-lg border border-gray-200"
                            x-show="activeOptionsTab !== 'diet_type' && activeOptionsTab !== 'isolation_type'">
                            <h5 class="text-xs font-semibold text-gray-600 mb-2">Add New Option</h5>
                            <div class="grid grid-cols-2 gap-2">
                                <input type="text" x-model="newOption.value" placeholder="Value (e.g., low_sodium)"
                                    class="text-xs rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <input type="text" x-model="newOption.label" placeholder="Label (e.g., Low Sodium)"
                                    class="text-xs rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div class="mt-2 flex items-center justify-between">
                                <select x-model="newOption.colorClass"
                                    class="text-xs rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="bg-gray-100 text-gray-700">Gray</option>
                                    <option value="bg-blue-100 text-blue-700">Blue</option>
                                    <option value="bg-green-100 text-green-700">Green</option>
                                    <option value="bg-yellow-100 text-yellow-700">Yellow</option>
                                    <option value="bg-orange-100 text-orange-700">Orange</option>
                                    <option value="bg-red-100 text-red-700">Red</option>
                                    <option value="bg-purple-100 text-purple-700">Purple</option>
                                    <option value="bg-pink-100 text-pink-700">Pink</option>
                                    <option value="bg-cyan-100 text-cyan-700">Cyan</option>
                                    <option value="bg-emerald-100 text-emerald-700">Emerald</option>
                                </select>
                                <button type="button" @click="addOption()"
                                    class="inline-flex items-center px-3 py-1.5 bg-green-600 text-white text-xs font-semibold rounded-md hover:bg-green-700">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 4v16m8-8H4" />
                                    </svg>
                                    Add Option
                                </button>
                            </div>
                        </div>

                        <div class="pt-4 flex justify-between items-center">
                            <button type="button" @click="resetClinicalOptions()"
                                x-show="activeOptionsTab !== 'diet_type' && activeOptionsTab !== 'isolation_type'"
                                class="inline-flex items-center px-3 py-2 text-gray-600 text-xs font-medium hover:text-gray-800">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                Reset Options
                            </button>
                            <button type="submit"
                                x-show="activeOptionsTab !== 'diet_type' && activeOptionsTab !== 'isolation_type'"
                                class="inline-flex items-center px-3 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-blue-700">
                                Save Options
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Info Box -->
            <div class="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                <div class="flex items-start">
                    <svg class="w-5 h-5 text-blue-500 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div class="text-sm text-blue-700">
                        <strong>Note:</strong> Options configured here will appear in Patient Details &gt; Additional
                        Info tab when editing a patient's clinical indicators. The display toggles control what shows on
                        the bed cards in the dashboard.
                    </div>
                </div>
            </div>
        </div>

        <!-- Clinical Setting Tab Panel -->
        <div x-show="activeTab === 'clinical-setting'" x-transition
            class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <h3 class="text-lg font-semibold text-gray-800 mb-3">Clinical Setting</h3>
            <p class="text-sm text-gray-600 mb-4">
                Configure clinical scoring systems and ward-specific clinical settings.
            </p>

            <form method="POST" action="{{ route('ward.settings.update') }}" id="clinicalSettingForm">
                @csrf
                <input type="hidden" name="setting_type" value="clinical_setting">
                <input type="hidden" name="clinical_setting_config" x-model="clinicalSettingJson">

                <!-- EWS Section -->
                <div class="border border-gray-200 rounded-lg p-4">
                    <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">
                        <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        Early Warning Score (EWS) System
                    </h4>
                    <p class="text-xs text-gray-500 mb-4">Select the EWS scoring system to use for this ward. This
                        affects how patient vital signs are assessed and scored.</p>

                    <!-- EWS Preview -->
                    <div class="bg-gray-100 rounded-lg p-4 mb-4">
                        <div class="flex items-center justify-center gap-3">
                            <template x-for="score in [0, 1, 2, 3, 5, 7, 9]" :key="score">
                                <div class="text-center">
                                    <div class="w-10 h-10 rounded-lg flex items-center justify-center text-white font-bold text-sm shadow"
                                        :class="{
                                             'bg-green-500': score <= 2,
                                             'bg-yellow-500': score >= 3 && score <= 4,
                                             'bg-orange-500': score >= 5 && score <= 6,
                                             'bg-red-500': score >= 7
                                         }">
                                        <span x-text="score"></span>
                                    </div>
                                    <span class="text-xs text-gray-500 mt-1 block" x-text="getEWSLabel(score)"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- EWS Options -->
                    <div class="space-y-2">
                        <!-- EWS IHH Option (Default) -->
                        <label
                            class="flex items-start p-3 bg-gray-50 hover:bg-gray-100 rounded-lg border border-gray-200 cursor-pointer transition-colors"
                            :class="clinicalSettings.ews_system === 'ews_ihh' ? 'ring-2 ring-blue-500 border-blue-500' : ''">
                            <input type="radio" name="ews_system_radio" value="ews_ihh"
                                x-model="clinicalSettings.ews_system"
                                class="h-4 w-4 mt-0.5 text-blue-600 focus:ring-blue-500 border-gray-300">
                            <div class="ml-3 flex-1">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-gray-700">EWS IHH (IJN Hospital Hijau)</span>
                                    <button type="button" @click.prevent="showEwsIhhModal = true"
                                        class="ml-2 p-1 text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded-full transition-colors"
                                        title="View EWS IHH Scoring Table">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </button>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">IHH standard. Uses: Pulse, RR, Systolic BP, SpO2,
                                    Temperature. Yellow Zone (Score 1) and Pink Zone (Score 2) triggers.</p>
                                <span
                                    class="inline-flex items-center mt-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Recommended
                                </span>
                            </div>
                        </label>

                        <label
                            class="flex items-start p-3 bg-gray-50 hover:bg-gray-100 rounded-lg border border-gray-200 cursor-pointer transition-colors"
                            :class="clinicalSettings.ews_system === 'news2' ? 'ring-2 ring-blue-500 border-blue-500' : ''">
                            <input type="radio" name="ews_system_radio" value="news2"
                                x-model="clinicalSettings.ews_system"
                                class="h-4 w-4 mt-0.5 text-blue-600 focus:ring-blue-500 border-gray-300">
                            <div class="ml-3">
                                <span class="text-sm font-medium text-gray-700">NEWS2 (National Early Warning Score
                                    2)</span>
                                <p class="text-xs text-gray-500 mt-1">The UK standard. Uses: RR, SpO2, Systolic BP,
                                    Pulse, Consciousness, Temperature. Includes SpO2 Scale 2 for COPD patients.</p>
                            </div>
                        </label>

                        <label
                            class="flex items-start p-3 bg-gray-50 hover:bg-gray-100 rounded-lg border border-gray-200 cursor-pointer transition-colors"
                            :class="clinicalSettings.ews_system === 'news' ? 'ring-2 ring-blue-500 border-blue-500' : ''">
                            <input type="radio" name="ews_system_radio" value="news"
                                x-model="clinicalSettings.ews_system"
                                class="h-4 w-4 mt-0.5 text-blue-600 focus:ring-blue-500 border-gray-300">
                            <div class="ml-3">
                                <span class="text-sm font-medium text-gray-700">NEWS (National Early Warning
                                    Score)</span>
                                <p class="text-xs text-gray-500 mt-1">Original NEWS scoring. Uses: RR, SpO2, Systolic
                                    BP, Pulse, Consciousness, Temperature.</p>
                            </div>
                        </label>

                        <label
                            class="flex items-start p-3 bg-gray-50 hover:bg-gray-100 rounded-lg border border-gray-200 cursor-pointer transition-colors"
                            :class="clinicalSettings.ews_system === 'mews' ? 'ring-2 ring-blue-500 border-blue-500' : ''">
                            <input type="radio" name="ews_system_radio" value="mews"
                                x-model="clinicalSettings.ews_system"
                                class="h-4 w-4 mt-0.5 text-blue-600 focus:ring-blue-500 border-gray-300">
                            <div class="ml-3">
                                <span class="text-sm font-medium text-gray-700">MEWS (Modified Early Warning
                                    Score)</span>
                                <p class="text-xs text-gray-500 mt-1">Simplified version. Uses: Systolic BP, Pulse, RR,
                                    Temperature, AVPU consciousness level.</p>
                            </div>
                        </label>

                        <label
                            class="flex items-start p-3 bg-gray-50 hover:bg-gray-100 rounded-lg border border-gray-200 cursor-pointer transition-colors"
                            :class="clinicalSettings.ews_system === 'pews' ? 'ring-2 ring-blue-500 border-blue-500' : ''">
                            <input type="radio" name="ews_system_radio" value="pews"
                                x-model="clinicalSettings.ews_system"
                                class="h-4 w-4 mt-0.5 text-blue-600 focus:ring-blue-500 border-gray-300">
                            <div class="ml-3">
                                <span class="text-sm font-medium text-gray-700">PEWS (Pediatric Early Warning
                                    Score)</span>
                                <p class="text-xs text-gray-500 mt-1">For pediatric wards. Age-adjusted parameters for
                                    children.</p>
                            </div>
                        </label>

                        <label
                            class="flex items-start p-3 bg-gray-50 hover:bg-gray-100 rounded-lg border border-gray-200 cursor-pointer transition-colors"
                            :class="clinicalSettings.ews_system === 'custom' ? 'ring-2 ring-blue-500 border-blue-500' : ''">
                            <input type="radio" name="ews_system_radio" value="custom"
                                x-model="clinicalSettings.ews_system"
                                class="h-4 w-4 mt-0.5 text-blue-600 focus:ring-blue-500 border-gray-300">
                            <div class="ml-3">
                                <span class="text-sm font-medium text-gray-700">Custom/Hospital Specific</span>
                                <p class="text-xs text-gray-500 mt-1">Use hospital-specific early warning scoring
                                    system.</p>
                            </div>
                        </label>
                    </div>

                    <!-- Selected EWS Info -->
                    <div class="mt-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
                        <div class="flex items-start">
                            <svg class="w-4 h-4 text-blue-500 mr-2 flex-shrink-0 mt-0.5" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div class="text-xs text-blue-700">
                                <strong>Selected:</strong> <span x-text="getEWSSystemName()"></span>
                                <p class="mt-1" x-text="getEWSSystemDescription()"></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-4 flex justify-end border-t mt-4">
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Save Clinical Settings
                    </button>
                </div>
            </form>
        </div>

        <!-- Logout Tab Panel -->
        <div x-show="activeTab === 'logout'" x-transition
            class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <h3 class="text-lg font-semibold text-gray-800 mb-3">Logout</h3>
            <p class="text-sm text-gray-600 mb-6">
                Sign out of the current session. You will be redirected to the login page.
            </p>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                    class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 shadow-md transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    Sign Out
                </button>
            </form>
        </div>

        <!-- EWS IHH Modal -->
        <div x-show="showEwsIhhModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" @click="showEwsIhhModal = false">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

                <div x-show="showEwsIhhModal" x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" @click.stop
                    class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full">

                    <!-- Modal Header -->
                    <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-bold text-white flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                                EWS IHH Scoring Table
                            </h3>
                            <button @click="showEwsIhhModal = false" class="text-white hover:text-gray-200">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <p class="text-sm text-blue-100 mt-1">IJN Hospital Hijau Early Warning Score System</p>
                    </div>

                    <!-- Modal Body -->
                    <div class="px-6 py-4">
                        <div class="overflow-x-auto">
                            <table class="min-w-full border-collapse text-sm">
                                <thead>
                                    <tr>
                                        <th class="border border-gray-300 px-3 py-2 bg-gray-100 text-left font-semibold"
                                            rowspan="2">Early Warning Signs (EWS)</th>
                                        <th class="border border-gray-300 px-3 py-2 bg-yellow-100 text-center font-semibold text-yellow-800"
                                            colspan="2">
                                            Yellow Zone<br>
                                            <span class="font-normal text-xs">Warning - Attention required (1)</span>
                                        </th>
                                        <th class="border border-gray-300 px-3 py-2 bg-red-100 text-center font-semibold text-red-800"
                                            colspan="2">
                                            Pink Zone<br>
                                            <span class="font-normal text-xs">Activate Trigger Protocol (2)</span>
                                        </th>
                                    </tr>
                                    <tr>
                                        <th
                                            class="border border-gray-300 px-3 py-2 bg-yellow-50 text-center text-xs font-medium">
                                            Higher range</th>
                                        <th
                                            class="border border-gray-300 px-3 py-2 bg-yellow-50 text-center text-xs font-medium">
                                            Lower range</th>
                                        <th
                                            class="border border-gray-300 px-3 py-2 bg-red-50 text-center text-xs font-medium">
                                            Higher range</th>
                                        <th
                                            class="border border-gray-300 px-3 py-2 bg-red-50 text-center text-xs font-medium">
                                            Lower range</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="border border-gray-300 px-3 py-2 font-medium">Pulse / Heart rate
                                            (bpm)</td>
                                        <td class="border border-gray-300 px-3 py-2 text-center bg-yellow-50">100 - 120
                                        </td>
                                        <td class="border border-gray-300 px-3 py-2 text-center bg-yellow-50">41 - 59
                                        </td>
                                        <td
                                            class="border border-gray-300 px-3 py-2 text-center bg-red-50 font-semibold">
                                            &gt;120</td>
                                        <td
                                            class="border border-gray-300 px-3 py-2 text-center bg-red-50 font-semibold">
                                            ≤40</td>
                                    </tr>
                                    <tr>
                                        <td class="border border-gray-300 px-3 py-2 font-medium">Respiration rate
                                            (breath/min)</td>
                                        <td class="border border-gray-300 px-3 py-2 text-center bg-yellow-50">21 - 24
                                        </td>
                                        <td class="border border-gray-300 px-3 py-2 text-center bg-yellow-50">9 - 11
                                        </td>
                                        <td
                                            class="border border-gray-300 px-3 py-2 text-center bg-red-50 font-semibold">
                                            &gt;25</td>
                                        <td
                                            class="border border-gray-300 px-3 py-2 text-center bg-red-50 font-semibold">
                                            ≤8</td>
                                    </tr>
                                    <tr>
                                        <td class="border border-gray-300 px-3 py-2 font-medium">Blood Pressure (mmHg)
                                            Systolic</td>
                                        <td class="border border-gray-300 px-3 py-2 text-center bg-yellow-50">160 - 199
                                        </td>
                                        <td class="border border-gray-300 px-3 py-2 text-center bg-yellow-50">91 - 100
                                        </td>
                                        <td
                                            class="border border-gray-300 px-3 py-2 text-center bg-red-50 font-semibold">
                                            &gt;200</td>
                                        <td
                                            class="border border-gray-300 px-3 py-2 text-center bg-red-50 font-semibold">
                                            ≤90</td>
                                    </tr>
                                    <tr>
                                        <td class="border border-gray-300 px-3 py-2 font-medium">Oxygen Saturation
                                            (SpO2)</td>
                                        <td class="border border-gray-300 px-3 py-2 text-center bg-yellow-50">N/A</td>
                                        <td class="border border-gray-300 px-3 py-2 text-center bg-yellow-50">92 - 95
                                        </td>
                                        <td
                                            class="border border-gray-300 px-3 py-2 text-center bg-red-50 font-semibold">
                                            N/A</td>
                                        <td
                                            class="border border-gray-300 px-3 py-2 text-center bg-red-50 font-semibold">
                                            ≤91</td>
                                    </tr>
                                    <tr>
                                        <td class="border border-gray-300 px-3 py-2 font-medium">Temperature (Celsius)
                                        </td>
                                        <td class="border border-gray-300 px-3 py-2 text-center bg-yellow-50">38 - 38.9
                                        </td>
                                        <td class="border border-gray-300 px-3 py-2 text-center bg-yellow-50">35.1 -
                                            35.9</td>
                                        <td
                                            class="border border-gray-300 px-3 py-2 text-center bg-red-50 font-semibold">
                                            ≥39</td>
                                        <td
                                            class="border border-gray-300 px-3 py-2 text-center bg-red-50 font-semibold">
                                            ≤35</td>
                                    </tr>
                                    <tr class="bg-gray-50">
                                        <td class="border border-gray-300 px-3 py-2 font-medium">Level of Consciousness
                                        </td>
                                        <td class="border border-gray-300 px-3 py-2 text-center" colspan="2">&lt; 5</td>
                                        <td class="border border-gray-300 px-3 py-2 text-center text-xs" colspan="2">
                                            Patient responds to Verbal / Pain Stimulus / New Onset of Confusion /
                                            Unconscious</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Score Interpretation -->
                        <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                                <div class="flex items-center">
                                    <div
                                        class="w-8 h-8 bg-green-500 text-white rounded-lg flex items-center justify-center font-bold mr-2">
                                        0</div>
                                    <div>
                                        <span class="text-sm font-semibold text-green-800">Normal</span>
                                        <p class="text-xs text-green-600">All vitals in normal range</p>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                                <div class="flex items-center">
                                    <div
                                        class="w-8 h-8 bg-yellow-500 text-white rounded-lg flex items-center justify-center font-bold mr-2">
                                        1</div>
                                    <div>
                                        <span class="text-sm font-semibold text-yellow-800">Warning</span>
                                        <p class="text-xs text-yellow-600">Yellow Zone - Monitor closely</p>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                                <div class="flex items-center">
                                    <div
                                        class="w-8 h-8 bg-red-500 text-white rounded-lg flex items-center justify-center font-bold mr-2">
                                        2</div>
                                    <div>
                                        <span class="text-sm font-semibold text-red-800">Trigger</span>
                                        <p class="text-xs text-red-600">Pink Zone - Activate protocol</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Response Guidelines -->
                        <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                            <h4 class="text-sm font-semibold text-blue-800 mb-2">Response Guidelines</h4>
                            <ul class="text-xs text-blue-700 space-y-1">
                                <li><span class="font-semibold">Total Score 0:</span> Continue routine monitoring (every
                                    4-6 hours)</li>
                                <li><span class="font-semibold">Total Score 1-3:</span> Increase monitoring frequency,
                                    notify nurse-in-charge</li>
                                <li><span class="font-semibold">Total Score ≥4:</span> Urgent assessment required,
                                    notify medical officer immediately</li>
                                <li><span class="font-semibold">Any single parameter Score 2:</span> Immediate clinical
                                    review regardless of total score</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="bg-gray-50 px-6 py-3 flex justify-end">
                        <button type="button" @click="showEwsIhhModal = false"
                            class="px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-300 transition-colors">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function settingsManager() {
            const savedBedBoxConfig = @json($bedBoxDisplay ?? []);
            const savedPatientInfoConfig = @json($patientInfoDisplay ?? []);
            const savedClinicalOptions = @json($clinicalIndicatorOptions ?? []);
            const savedDashboardDisplay = @json($dashboardDisplay ?? []);

            const defaultBedBoxItems = [
                { key: 'patient_name', label: 'Patient Name', description: 'Full name of the patient', visible: true, order: 0 },
                { key: 'consultant', label: 'Consultant Name', description: 'Attending consultant doctor', visible: true, order: 1 },
                { key: 'nurse', label: 'Nurse on Duty', description: 'Nurse assigned from ward schedule', visible: true, order: 2 },
                { key: 'admitted_duration', label: 'Admitted Duration', description: 'Days and hours since admission', visible: true, order: 3 },
                { key: 'ews', label: 'EWS', description: 'Early Warning Score indicator', visible: true, order: 4 },
                { key: 'mrn', label: 'MRN', description: 'Medical Record Number in header', visible: true, order: 5 },
                { key: 'patient_details_button', label: 'Patient Details Button', description: 'Show/hide Patient Details button on occupied beds', visible: true, order: 6 },
                { key: 'admit_button', label: 'Admit Patient Button', description: 'Show/hide Admit button on empty beds', visible: true, order: 7 },
                { key: 'prebook_button', label: 'Prebook Button', description: 'Show/hide Prebook button on empty beds', visible: true, order: 8 },
            ];

            const defaultPatientInfoItems = [
                {
                    key: 'nursing_level',
                    label: 'Nursing Level of Care',
                    description: 'Patient care level classification',
                    visible: true,
                    bgColor: 'bg-purple-100',
                    textColor: 'text-purple-600',
                    options: [
                        { value: 'none', label: 'None', color: 'bg-gray-100 text-gray-600' },
                        { value: 'level_1', label: 'Level 1', color: 'bg-green-100 text-green-700' },
                        { value: 'level_2', label: 'Level 2', color: 'bg-blue-100 text-blue-700' },
                        { value: 'level_3', label: 'Level 3', color: 'bg-yellow-100 text-yellow-700' },
                        { value: 'level_4', label: 'Level 4', color: 'bg-red-100 text-red-700' },
                    ]
                },
                {
                    key: 'diet_type',
                    label: 'Diet Type',
                    description: 'Patient dietary requirements',
                    visible: true,
                    bgColor: 'bg-red-100',
                    textColor: 'text-red-600',
                    options: [
                        { value: 'npo', label: 'NPO (Nil By Mouth)', color: 'bg-red-100 text-red-700' },
                        { value: 'clear_fluid', label: 'Clear Fluid', color: 'bg-blue-100 text-blue-700' },
                        { value: 'full_fluid', label: 'Full Fluid', color: 'bg-cyan-100 text-cyan-700' },
                        { value: 'soft_diet', label: 'Soft Diet', color: 'bg-orange-100 text-orange-700' },
                        { value: 'regular', label: 'Regular', color: 'bg-green-100 text-green-700' },
                        { value: 'vegetarian', label: 'Vegetarian', color: 'bg-lime-100 text-lime-700' },
                        { value: 'diabetic', label: 'Diabetic', color: 'bg-purple-100 text-purple-700' },
                        { value: 'renal', label: 'Renal', color: 'bg-pink-100 text-pink-700' },
                        { value: 'low_salt', label: 'Low Salt', color: 'bg-amber-100 text-amber-700' },
                        { value: 'halal', label: 'Halal', color: 'bg-emerald-100 text-emerald-700' },
                        { value: 'kosher', label: 'Kosher', color: 'bg-indigo-100 text-indigo-700' },
                        { value: 'gluten_free', label: 'Gluten Free', color: 'bg-rose-100 text-rose-700' },
                    ]
                },
                {
                    key: 'fall_risk',
                    label: 'Fall Risk Alert',
                    description: 'Patient fall risk assessment',
                    visible: true,
                    bgColor: 'bg-orange-100',
                    textColor: 'text-orange-600',
                    options: [
                        { value: 'none', label: 'None', color: 'bg-gray-100 text-gray-600' },
                        { value: 'low', label: 'Low', color: 'bg-green-100 text-green-700' },
                        { value: 'moderate', label: 'Moderate', color: 'bg-yellow-100 text-yellow-700' },
                        { value: 'high', label: 'High', color: 'bg-orange-100 text-orange-700' },
                        { value: 'alert_active', label: 'FR Alert Active', color: 'bg-red-100 text-red-700' },
                    ]
                },
                {
                    key: 'isolation_type',
                    label: 'Isolation Precautions',
                    description: 'Infection control measures',
                    visible: true,
                    bgColor: 'bg-yellow-100',
                    textColor: 'text-yellow-600',
                    options: [
                        { value: 'none', label: 'None', color: 'bg-gray-100 text-gray-600' },
                        { value: 'contact', label: 'Contact', color: 'bg-blue-100 text-blue-700' },
                        { value: 'droplet', label: 'Droplet', color: 'bg-cyan-100 text-cyan-700' },
                        { value: 'airborne', label: 'Airborne', color: 'bg-purple-100 text-purple-700' },
                        { value: 'protective', label: 'Protective', color: 'bg-green-100 text-green-700' },
                        { value: 'mrsa', label: 'MRSA', color: 'bg-orange-100 text-orange-700' },
                        { value: 'vre', label: 'VRE', color: 'bg-pink-100 text-pink-700' },
                        { value: 'cdiff', label: 'C.Diff', color: 'bg-amber-100 text-amber-700' },
                        { value: 'covid', label: 'COVID-19', color: 'bg-red-100 text-red-700' },
                        { value: 'tb', label: 'TB', color: 'bg-rose-100 text-rose-700' },
                    ]
                },
                {
                    key: 'allergies',
                    label: 'Medical Allergies',
                    description: 'Patient allergy alerts',
                    visible: true,
                    bgColor: 'bg-pink-100',
                    textColor: 'text-pink-600',
                    options: null
                },
            ];

            // Merge saved bed box config with defaults
            let bedBoxItems = defaultBedBoxItems.map(def => {
                const saved = savedBedBoxConfig.find(s => s.key === def.key);
                if (saved) {
                    return { ...def, visible: saved.visible, order: saved.order };
                }
                return def;
            });
            bedBoxItems.sort((a, b) => a.order - b.order);

            // Merge saved patient info config with defaults
            let patientInfoItems = defaultPatientInfoItems.map(def => {
                const saved = savedPatientInfoConfig.find(s => s.key === def.key);
                if (saved) {
                    return { ...def, visible: saved.visible };
                }
                return def;
            });

            // Initialize clinical options from saved or defaults
            const defaultClinicalOptions = {
                nursing_level: [
                    { value: 'none', label: 'None', color: 'bg-gray-100 text-gray-600' },
                    { value: 'level_1', label: 'Level 1', color: 'bg-green-100 text-green-700' },
                    { value: 'level_2', label: 'Level 2', color: 'bg-blue-100 text-blue-700' },
                    { value: 'level_3', label: 'Level 3', color: 'bg-yellow-100 text-yellow-700' },
                    { value: 'level_4', label: 'Level 4', color: 'bg-red-100 text-red-700' },
                ],
                diet_type: [
                    { value: 'npo', label: 'NPO (Nil By Mouth)', color: 'bg-red-100 text-red-700' },
                    { value: 'clear_fluid', label: 'Clear Fluid', color: 'bg-blue-100 text-blue-700' },
                    { value: 'full_fluid', label: 'Full Fluid', color: 'bg-cyan-100 text-cyan-700' },
                    { value: 'soft_diet', label: 'Soft Diet', color: 'bg-orange-100 text-orange-700' },
                    { value: 'regular', label: 'Regular', color: 'bg-green-100 text-green-700' },
                    { value: 'vegetarian', label: 'Vegetarian', color: 'bg-lime-100 text-lime-700' },
                    { value: 'diabetic', label: 'Diabetic', color: 'bg-purple-100 text-purple-700' },
                    { value: 'renal', label: 'Renal', color: 'bg-pink-100 text-pink-700' },
                    { value: 'low_salt', label: 'Low Salt', color: 'bg-amber-100 text-amber-700' },
                    { value: 'halal', label: 'Halal', color: 'bg-emerald-100 text-emerald-700' },
                    { value: 'kosher', label: 'Kosher', color: 'bg-indigo-100 text-indigo-700' },
                    { value: 'gluten_free', label: 'Gluten Free', color: 'bg-rose-100 text-rose-700' },
                ],
                fall_risk: [
                    { value: 'none', label: 'None', color: 'bg-gray-100 text-gray-600' },
                    { value: 'low', label: 'Low', color: 'bg-green-100 text-green-700' },
                    { value: 'moderate', label: 'Moderate', color: 'bg-yellow-100 text-yellow-700' },
                    { value: 'high', label: 'High', color: 'bg-orange-100 text-orange-700' },
                    { value: 'alert_active', label: 'FR Alert Active', color: 'bg-red-100 text-red-700' },
                ],
                isolation_type: [
                    { value: 'none', label: 'None', color: 'bg-gray-100 text-gray-600' },
                    { value: 'contact', label: 'Contact', color: 'bg-blue-100 text-blue-700' },
                    { value: 'droplet', label: 'Droplet', color: 'bg-cyan-100 text-cyan-700' },
                    { value: 'airborne', label: 'Airborne', color: 'bg-purple-100 text-purple-700' },
                    { value: 'protective', label: 'Protective', color: 'bg-green-100 text-green-700' },
                    { value: 'mrsa', label: 'MRSA', color: 'bg-orange-100 text-orange-700' },
                    { value: 'vre', label: 'VRE', color: 'bg-pink-100 text-pink-700' },
                    { value: 'cdiff', label: 'C.Diff', color: 'bg-amber-100 text-amber-700' },
                    { value: 'covid', label: 'COVID-19', color: 'bg-red-100 text-red-700' },
                    { value: 'tb', label: 'TB', color: 'bg-rose-100 text-rose-700' },
                ],
            };

            // Merge saved clinical options with defaults
            let clinicalOptions = { ...defaultClinicalOptions };
            if (savedClinicalOptions && typeof savedClinicalOptions === 'object') {
                for (const key in defaultClinicalOptions) {
                    if (savedClinicalOptions[key] && Array.isArray(savedClinicalOptions[key])) {
                        clinicalOptions[key] = savedClinicalOptions[key];
                    }
                }
            }

            // Dashboard display settings
            const defaultDashboardDisplay = {
                patient_name_mask: 'full',
                fullscreen_mode: 'medium',
                fullscreen_text_size: 'medium',
                fullscreen_resolution: 'default'
            };
            const dashboardDisplaySettings = { ...defaultDashboardDisplay, ...savedDashboardDisplay };

            // Clinical settings
            const savedClinicalSettings = @json($clinicalSettings ?? []);
            const defaultClinicalSettings = {
                ews_system: 'ews_ihh'
            };
            const clinicalSettingsData = { ...defaultClinicalSettings, ...savedClinicalSettings };

            // Vitals mode settings
            const savedPatientVitalsMode = @json($patientVitalsMode ?? 'demo');
            const savedBedBoxVitalsMode = @json($bedBoxVitalsMode ?? 'demo');

            return {
                activeTab: 'dashboard-display',
                activeOptionsTab: 'nursing_level',
                bedBoxItems: bedBoxItems,
                patientInfoItems: patientInfoItems,
                clinicalOptions: clinicalOptions,
                dashboardDisplaySettings: dashboardDisplaySettings,
                clinicalSettings: clinicalSettingsData,
                patientVitalsMode: savedPatientVitalsMode,
                bedBoxVitalsMode: savedBedBoxVitalsMode,
                showEwsIhhModal: false,
                newOption: {
                    value: '',
                    label: '',
                    colorClass: 'bg-gray-100 text-gray-700'
                },

                get orderJson() {
                    return JSON.stringify(this.bedBoxItems.map((item, index) => ({
                        key: item.key,
                        visible: item.visible,
                        order: index
                    })));
                },

                get patientInfoJson() {
                    return JSON.stringify(this.patientInfoItems.map(item => ({
                        key: item.key,
                        visible: item.visible
                    })));
                },

                get clinicalOptionsJson() {
                    return JSON.stringify(this.clinicalOptions);
                },

                get dashboardDisplayJson() {
                    return JSON.stringify(this.dashboardDisplaySettings);
                },

                getMaskedNamePreview(fullName) {
                    const parts = fullName.split(' ');
                    const mask = this.dashboardDisplaySettings.patient_name_mask;

                    switch (mask) {
                        case 'full':
                            return fullName;
                        case 'first_only':
                            return parts.map((part, i) => i === 0 ? part : part[0] + '*'.repeat(part.length - 1)).join(' ');
                        case 'last_only':
                            return parts.map((part, i) => i === parts.length - 1 ? part : part[0] + '*'.repeat(part.length - 1)).join(' ');
                        case 'initials':
                            return parts.map(part => part[0] + '.').join('');
                        case 'first_last_initial':
                            if (parts.length === 1) return parts[0];
                            return parts[0] + ' ' + parts[parts.length - 1][0] + '.';
                        case 'all_asterisk':
                            return parts.map(part => '*'.repeat(part.length)).join(' ');
                        default:
                            return fullName;
                    }
                },

                getFullscreenCount() {
                    const counts = { small: 8, medium: 6, large: 4 };
                    return counts[this.dashboardDisplaySettings.fullscreen_mode] || 6;
                },

                getFullscreenLabel() {
                    const counts = { small: 8, medium: 6, large: 4 };
                    return counts[this.dashboardDisplaySettings.fullscreen_mode] || 6;
                },

                get clinicalSettingJson() {
                    return JSON.stringify(this.clinicalSettings);
                },

                getEWSLabel(score) {
                    if (score <= 2) return 'Low';
                    if (score <= 4) return 'Med';
                    if (score <= 6) return 'High';
                    return 'Critical';
                },

                getEWSSystemName() {
                    const names = {
                        'ews_ihh': 'EWS IHH (IJN Hospital Hijau)',
                        'news2': 'NEWS2 (National Early Warning Score 2)',
                        'news': 'NEWS (National Early Warning Score)',
                        'mews': 'MEWS (Modified Early Warning Score)',
                        'pews': 'PEWS (Pediatric Early Warning Score)',
                        'custom': 'Custom/Hospital Specific'
                    };
                    return names[this.clinicalSettings.ews_system] || 'EWS IHH';
                },

                getEWSSystemDescription() {
                    const descs = {
                        'ews_ihh': 'IHH standard scoring with 5 vital parameters: pulse, respiration rate, systolic BP, SpO2, and temperature. Uses Yellow Zone (Score 1) and Pink Zone (Score 2) triggers.',
                        'news2': 'Scores 6 vital parameters: respiratory rate, oxygen saturation, systolic blood pressure, pulse rate, level of consciousness, and temperature.',
                        'news': 'Original scoring system with 6 parameters, widely used internationally.',
                        'mews': 'Simplified 5-parameter scoring for rapid assessment.',
                        'pews': 'Pediatric-specific scoring with age-adjusted vital sign ranges.',
                        'custom': 'Hospital-defined scoring criteria and thresholds.'
                    };
                    return descs[this.clinicalSettings.ews_system] || '';
                },

                get visibleItems() {
                    return this.bedBoxItems
                        .filter(item => item.visible && !['mrn', 'ews', 'patient_details_button', 'admit_button', 'prebook_button'].includes(item.key));
                },

                toggleVisibility(key) {
                    const item = this.bedBoxItems.find(i => i.key === key);
                    if (item) {
                        item.visible = !item.visible;
                    }
                },

                togglePatientInfo(key) {
                    const item = this.patientInfoItems.find(i => i.key === key);
                    if (item) {
                        item.visible = !item.visible;
                    }
                },

                resetToDefaults() {
                    this.bedBoxItems = [
                        { key: 'patient_name', label: 'Patient Name', description: 'Full name of the patient', visible: true, order: 0 },
                        { key: 'consultant', label: 'Consultant Name', description: 'Attending consultant doctor', visible: true, order: 1 },
                        { key: 'nurse', label: 'Nurse on Duty', description: 'Nurse assigned from ward schedule', visible: true, order: 2 },
                        { key: 'admitted_duration', label: 'Admitted Duration', description: 'Days and hours since admission', visible: true, order: 3 },
                        { key: 'ews', label: 'EWS', description: 'Early Warning Score indicator', visible: true, order: 4 },
                        { key: 'mrn', label: 'MRN', description: 'Medical Record Number in header', visible: true, order: 5 },
                        { key: 'patient_details_button', label: 'Patient Details Button', description: 'Show/hide Patient Details button on occupied beds', visible: true, order: 6 },
                        { key: 'admit_button', label: 'Admit Patient Button', description: 'Show/hide Admit button on empty beds', visible: true, order: 7 },
                        { key: 'prebook_button', label: 'Prebook Button', description: 'Show/hide Prebook button on empty beds', visible: true, order: 8 },
                    ];
                    this.initSortable();
                },

                resetPatientInfoDefaults() {
                    this.patientInfoItems = this.patientInfoItems.map(item => ({ ...item, visible: true }));
                },

                addOption() {
                    if (this.newOption.value.trim() && this.newOption.label.trim()) {
                        // Check for duplicate value
                        const exists = this.clinicalOptions[this.activeOptionsTab].some(
                            opt => opt.value === this.newOption.value.trim()
                        );
                        if (!exists) {
                            this.clinicalOptions[this.activeOptionsTab].push({
                                value: this.newOption.value.trim().toLowerCase().replace(/\s+/g, '_'),
                                label: this.newOption.label.trim(),
                                color: this.newOption.colorClass
                            });
                            this.newOption.value = '';
                            this.newOption.label = '';
                            this.newOption.colorClass = 'bg-gray-100 text-gray-700';
                        }
                    }
                },

                removeOption(type, index) {
                    const option = this.clinicalOptions[type][index];
                    // Don't allow removing 'none' option
                    if (option && option.value !== 'none') {
                        this.clinicalOptions[type].splice(index, 1);
                    }
                },

                resetClinicalOptions() {
                    this.clinicalOptions = {
                        nursing_level: [
                            { value: 'none', label: 'None', color: 'bg-gray-100 text-gray-600' },
                            { value: 'level_1', label: 'Level 1', color: 'bg-green-100 text-green-700' },
                            { value: 'level_2', label: 'Level 2', color: 'bg-blue-100 text-blue-700' },
                            { value: 'level_3', label: 'Level 3', color: 'bg-yellow-100 text-yellow-700' },
                            { value: 'level_4', label: 'Level 4', color: 'bg-red-100 text-red-700' },
                        ],
                        diet_type: [
                            { value: 'npo', label: 'NPO (Nil By Mouth)', color: 'bg-red-100 text-red-700' },
                            { value: 'clear_fluid', label: 'Clear Fluid', color: 'bg-blue-100 text-blue-700' },
                            { value: 'full_fluid', label: 'Full Fluid', color: 'bg-cyan-100 text-cyan-700' },
                            { value: 'soft_diet', label: 'Soft Diet', color: 'bg-orange-100 text-orange-700' },
                            { value: 'regular', label: 'Regular', color: 'bg-green-100 text-green-700' },
                            { value: 'vegetarian', label: 'Vegetarian', color: 'bg-lime-100 text-lime-700' },
                            { value: 'diabetic', label: 'Diabetic', color: 'bg-purple-100 text-purple-700' },
                            { value: 'renal', label: 'Renal', color: 'bg-pink-100 text-pink-700' },
                            { value: 'low_salt', label: 'Low Salt', color: 'bg-amber-100 text-amber-700' },
                            { value: 'halal', label: 'Halal', color: 'bg-emerald-100 text-emerald-700' },
                            { value: 'kosher', label: 'Kosher', color: 'bg-indigo-100 text-indigo-700' },
                            { value: 'gluten_free', label: 'Gluten Free', color: 'bg-rose-100 text-rose-700' },
                        ],
                        fall_risk: [
                            { value: 'none', label: 'None', color: 'bg-gray-100 text-gray-600' },
                            { value: 'low', label: 'Low', color: 'bg-green-100 text-green-700' },
                            { value: 'moderate', label: 'Moderate', color: 'bg-yellow-100 text-yellow-700' },
                            { value: 'high', label: 'High', color: 'bg-orange-100 text-orange-700' },
                            { value: 'alert_active', label: 'FR Alert Active', color: 'bg-red-100 text-red-700' },
                        ],
                        isolation_type: [
                            { value: 'none', label: 'None', color: 'bg-gray-100 text-gray-600' },
                            { value: 'contact', label: 'Contact', color: 'bg-blue-100 text-blue-700' },
                            { value: 'droplet', label: 'Droplet', color: 'bg-cyan-100 text-cyan-700' },
                            { value: 'airborne', label: 'Airborne', color: 'bg-purple-100 text-purple-700' },
                            { value: 'protective', label: 'Protective', color: 'bg-green-100 text-green-700' },
                            { value: 'mrsa', label: 'MRSA', color: 'bg-orange-100 text-orange-700' },
                            { value: 'vre', label: 'VRE', color: 'bg-pink-100 text-pink-700' },
                            { value: 'cdiff', label: 'C.Diff', color: 'bg-amber-100 text-amber-700' },
                            { value: 'covid', label: 'COVID-19', color: 'bg-red-100 text-red-700' },
                            { value: 'tb', label: 'TB', color: 'bg-rose-100 text-rose-700' },
                        ],
                    };
                },

                getIcon(key) {
                    const icons = {
                        patient_name: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>',
                        consultant: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
                        nurse: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>',
                        admitted_duration: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>',
                        ews: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>',
                        mrn: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>',
                        admit_button: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>',
                        prebook_button: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>',
                    };
                    return icons[key] || '';
                },

                getPatientInfoIcon(key) {
                    const icons = {
                        nursing_level: '<path fill="currentColor" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>',
                        diet_type: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>',
                        fall_risk: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>',
                        isolation_type: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>',
                        allergies: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>',
                    };
                    return icons[key] || '';
                },

                getSampleValue(key) {
                    const values = {
                        patient_name: 'John Smith',
                        consultant: 'Dr. Sarah Johnson',
                        nurse: 'AM: Nurse Mary Wong',
                        admitted_duration: '3 days, 5 hours',
                    };
                    return values[key] || '';
                },

                initSortable() {
                    const el = document.getElementById('sortableList');
                    if (el && typeof Sortable !== 'undefined') {
                        Sortable.create(el, {
                            animation: 150,
                            handle: '.drag-handle',
                            ghostClass: 'sortable-ghost',
                            chosenClass: 'sortable-chosen',
                            onEnd: (evt) => {
                                const items = [...this.bedBoxItems];
                                const [movedItem] = items.splice(evt.oldIndex, 1);
                                items.splice(evt.newIndex, 0, movedItem);
                                this.bedBoxItems = items.map((item, index) => ({ ...item, order: index }));
                            }
                        });
                    }
                },

                init() {
                    this.$nextTick(() => {
                        this.initSortable();
                    });
                }
            };
        }
    </script>
</body>

</html>