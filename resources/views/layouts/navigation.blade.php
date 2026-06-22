@php
    $openSection = null;
    $hospital = \App\Models\Hospital::first();
    $logoUrl = $hospital && $hospital->navbar_logo_path ? \Illuminate\Support\Facades\Storage::url($hospital->navbar_logo_path) : ($hospital && $hospital->logo_path ? \Illuminate\Support\Facades\Storage::url($hospital->logo_path) : asset('phkl_new.png'));

    if (request()->routeIs('patients.*')) {
        $openSection = 'patient';
    } else if (request()->routeIs('hospitals.*') || request()->routeIs('specialties.*') || request()->routeIs('consultants.*') || request()->routeIs('anaesthetists.*') || request()->routeIs('nurses.*') || request()->routeIs('diet-types.*') || request()->routeIs('isolation-types.*') || request()->routeIs('users.*') || request()->routeIs('patient-flow-command-centres.*')) {
        $openSection = 'admin';
    } elseif (request()->routeIs('wards.*') || request()->routeIs('beds.*')) {
        $openSection = 'wardManagement';
    } elseif (request()->routeIs('ward.schedule')) {
        $openSection = 'schedule';
    } elseif (request()->routeIs('vital-signs.*')) {
        $openSection = 'vitalSign';
    } elseif (request()->routeIs('ldap.*') || request()->routeIs('vital-sign-integration.*') || request()->routeIs('infusion-integration.*') || request()->routeIs('adt.*') || request()->routeIs('ecg.index') || request()->routeIs('ekad.*') || request()->routeIs('integration.demo.*')) {
        $openSection = 'integration';
    } elseif (request()->routeIs('user-activities.*')) {
        $openSection = 'appLogs';
    } elseif (request()->routeIs('command-center.*')) {
        $openSection = 'commandCenter';
    }
@endphp

<nav x-data="{ 
    sidebarOpen: true,
    openSection: @js($openSection),
    isSectionOpen(section) { return this.openSection === section; },
    toggleSection(section) { this.openSection = this.openSection === section ? null : section; }
}" @toggle-sidebar.window="sidebarOpen = $event.detail.open"
    class="bg-gradient-to-br from-blue-600 to-cyan-500 border-r border-blue-400 transition-all duration-300 min-h-screen flex flex-col flex-shrink-0 shadow-xl"
    :class="sidebarOpen ? 'w-64' : 'w-20'">
    <!-- Logo & Toggle -->
    <div
        class="shrink-0 flex items-center justify-between px-4 py-4 border-b border-blue-400/30 h-16 backdrop-blur-sm bg-white/10">
        <a href="{{ route('dashboard') }}" x-show="sidebarOpen" x-transition class="flex items-center">
            <img src="{{ $logoUrl }}" alt="Hospital Logo" class="h-10 w-auto">
        </a>
        <button @click="sidebarOpen = !sidebarOpen"
            class="p-2 rounded-lg hover:bg-white/20 transition-colors text-white">
            <svg x-show="sidebarOpen" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
            </svg>
            <svg x-show="!sidebarOpen" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
            </svg>
        </button>
    </div>

    <!-- Navigation Links -->
    <div class="flex-1 px-2 py-4 space-y-2 overflow-y-auto">
        <!-- Dashboard -->
        @if(!Auth::user()->hasRole(App\Models\User::ROLE_WARD_DASHBOARD))
            <a href="{{ route('dashboard') }}"
                class="flex items-center px-3 py-2.5 rounded-lg text-white transition-all {{ request()->routeIs('dashboard') ? 'bg-white/25 shadow-lg' : 'hover:bg-white/10' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                <span x-show="sidebarOpen" x-transition class="ml-3 font-medium">Dashboard</span>
            </a>
        @endif

        <!-- Command Center (Superadmin / Hospital Admin / IT Admin) -->
        @if(Auth::user()->isSuperadmin() || Auth::user()->hasRole(App\Models\User::ROLE_HOSPITAL_ADMIN) || Auth::user()->hasRole(App\Models\User::ROLE_IT_ADMIN))
            <a href="{{ route('command-center.index') }}"
                class="flex items-center px-3 py-2.5 rounded-lg text-white transition-all {{ request()->routeIs('command-center.*') ? 'bg-white/25 shadow-lg' : 'hover:bg-white/10' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                </svg>
                <span x-show="sidebarOpen" x-transition class="ml-3 font-medium">Command Center</span>
            </a>
        @endif


        <!-- Patient Section -->
        @if(!Auth::user()->hasRole(App\Models\User::ROLE_WARD_DASHBOARD) && !Auth::user()->hasRole(App\Models\User::ROLE_NURSE) && !Auth::user()->hasRole(App\Models\User::ROLE_NURSE_HEAD) && !Auth::user()->hasRole(App\Models\User::ROLE_USER))
            <div class="pt-2">
                <button @click="toggleSection('patient')"
                    class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-white transition-all hover:bg-white/10">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <span x-show="sidebarOpen" x-transition class="ml-3 font-medium">Patient</span>
                    </div>
                    <svg x-show="sidebarOpen" :class="{'rotate-180': isSectionOpen('patient')}"
                        class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-show="isSectionOpen('patient') && sidebarOpen" x-transition
                    class="mt-2 ml-4 space-y-1 border-l-2 border-white/30 pl-2">
                    <a href="{{ route('patients.index') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-white text-sm transition-all {{ request()->routeIs('patients.*') ? 'bg-white/25 shadow-lg' : 'hover:bg-white/10' }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                        <span class="ml-2">Patient List</span>
                    </a>
                </div>
            </div>
        @endif


        <!-- Admin Management Section -->
        @if(!Auth::user()->hasRole(App\Models\User::ROLE_WARD_DASHBOARD) && !Auth::user()->hasRole(App\Models\User::ROLE_NURSE) && !Auth::user()->hasRole(App\Models\User::ROLE_NURSE_HEAD) && !Auth::user()->hasRole(App\Models\User::ROLE_USER))
            <div class="pt-2">
                <button @click="toggleSection('admin')"
                    class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-white transition-all hover:bg-white/10">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        <span x-show="sidebarOpen" x-transition class="ml-3 font-medium">Admin Management</span>
                    </div>
                    <svg x-show="sidebarOpen" :class="{'rotate-180': isSectionOpen('admin')}"
                        class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-show="isSectionOpen('admin') && sidebarOpen" x-transition
                    class="mt-2 ml-4 space-y-1 border-l-2 border-white/30 pl-2">

                    @if(Auth::user()->isSuperadmin() || Auth::user()->hasRole(App\Models\User::ROLE_HOSPITAL_ADMIN) || Auth::user()->hasRole(App\Models\User::ROLE_IT_ADMIN))
                        <a href="{{ route('hospitals.index') }}"
                            class="flex items-center px-3 py-2 rounded-lg text-white text-sm transition-all {{ request()->routeIs('hospitals.*') ? 'bg-white/25 shadow-lg' : 'hover:bg-white/10' }}">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            <span class="ml-2">Hospital</span>
                        </a>
                    @endif

                    @if(Auth::user()->isSuperadmin() || Auth::user()->hasRole(App\Models\User::ROLE_HOSPITAL_ADMIN) || Auth::user()->hasRole(App\Models\User::ROLE_IT_ADMIN))
                        <a href="{{ route('specialties.index') }}"
                            class="flex items-center px-3 py-2 rounded-lg text-white text-sm transition-all {{ request()->routeIs('specialties.*') ? 'bg-white/25 shadow-lg' : 'hover:bg-white/10' }}">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                            <span class="ml-2">Specialties</span>
                        </a>
                    @endif


                    @if(Auth::user()->isSuperadmin() || Auth::user()->hasRole(App\Models\User::ROLE_HOSPITAL_ADMIN) || Auth::user()->hasRole(App\Models\User::ROLE_IT_ADMIN))
                        <a href="{{ route('consultants.index') }}"
                            class="flex items-center px-3 py-2 rounded-lg text-white text-sm transition-all {{ request()->routeIs('consultants.*') ? 'bg-white/25 shadow-lg' : 'hover:bg-white/10' }}">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <span class="ml-2">Consultants</span>
                        </a>
                    @endif


                    @if(Auth::user()->isSuperadmin() || Auth::user()->hasRole(App\Models\User::ROLE_HOSPITAL_ADMIN) || Auth::user()->hasRole(App\Models\User::ROLE_IT_ADMIN))
                        <a href="{{ route('anaesthetists.index') }}"
                            class="flex items-center px-3 py-2 rounded-lg text-white text-sm transition-all {{ request()->routeIs('anaesthetists.*') ? 'bg-white/25 shadow-lg' : 'hover:bg-white/10' }}">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span class="ml-2">Anaesthetist</span>
                        </a>
                    @endif


                    <a href="{{ route('nurses.index') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-white text-sm transition-all {{ request()->routeIs('nurses.*') ? 'bg-white/25 shadow-lg' : 'hover:bg-white/10' }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span class="ml-2">Nurses</span>
                    </a>

                    @if(Auth::user()->isSuperadmin() || Auth::user()->hasRole(App\Models\User::ROLE_HOSPITAL_ADMIN) || Auth::user()->hasRole(App\Models\User::ROLE_IT_ADMIN))
                        <a href="{{ route('users.index') }}"
                            class="flex items-center px-3 py-2 rounded-lg text-white text-sm transition-all {{ request()->routeIs('users.*') ? 'bg-white/25 shadow-lg' : 'hover:bg-white/10' }}">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <span class="ml-2">Users Management</span>
                        </a>
                    @endif

                    @if(Auth::user()->isSuperadmin() || Auth::user()->hasRole(App\Models\User::ROLE_HOSPITAL_ADMIN) || Auth::user()->hasRole(App\Models\User::ROLE_IT_ADMIN))
                        <a href="{{ route('patient-flow-command-centres.index') }}"
                            class="flex items-center px-3 py-2 rounded-lg text-white text-sm transition-all {{ request()->routeIs('patient-flow-command-centres.*') ? 'bg-white/25 shadow-lg' : 'hover:bg-white/10' }}">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
                            </svg>
                            <span class="ml-2">Patient Flow Command Centre</span>
                        </a>
                    @endif


                    @if(Auth::user()->isSuperadmin() || Auth::user()->hasRole(App\Models\User::ROLE_HOSPITAL_ADMIN) || Auth::user()->hasRole(App\Models\User::ROLE_IT_ADMIN))
                        <div class="border-t border-white/20 my-2 mx-2"></div>
                        <p class="px-3 py-1 text-xs text-white/60 font-medium uppercase tracking-wider">Patient Additional Field
                        </p>

                        <a href="{{ route('diet-types.index') }}"
                            class="flex items-center px-3 py-2 rounded-lg text-white text-sm transition-all {{ request()->routeIs('diet-types.*') || request()->routeIs('isolation-types.*') ? 'bg-white/25 shadow-lg' : 'hover:bg-white/10' }}">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                            </svg>
                            <span class="ml-2">Diet Types & Isolation</span>
                        </a>
                    @endif

                </div>
            </div>
        @endif


        <!-- Ward Management Section -->
        @if(!Auth::user()->hasRole(App\Models\User::ROLE_WARD_DASHBOARD) && !Auth::user()->hasRole(App\Models\User::ROLE_NURSE) && !Auth::user()->hasRole(App\Models\User::ROLE_NURSE_HEAD) && !Auth::user()->hasRole(App\Models\User::ROLE_USER))
            <div class="pt-2">
                <button @click="toggleSection('wardManagement')"
                    class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-white transition-all hover:bg-white/10">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                        <span x-show="sidebarOpen" x-transition class="ml-3 font-medium">Ward Management</span>
                    </div>
                    <svg x-show="sidebarOpen" :class="{'rotate-180': isSectionOpen('wardManagement')}"
                        class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-show="isSectionOpen('wardManagement') && sidebarOpen" x-transition
                    class="mt-2 ml-4 space-y-1 border-l-2 border-white/30 pl-2">
                    <a href="{{ route('wards.index') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-white text-sm transition-all {{ request()->routeIs('wards.*') ? 'bg-white/25 shadow-lg' : 'hover:bg-white/10' }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        <span class="ml-2">Wards</span>
                    </a>

                    <a href="{{ route('beds.index') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-white text-sm transition-all {{ request()->routeIs('beds.*') ? 'bg-white/25 shadow-lg' : 'hover:bg-white/10' }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                        <span class="ml-2">Beds</span>
                    </a>
                </div>
            </div>
        @endif


        <!-- Schedule Section -->
        <div class="pt-2">
            <button @click="toggleSection('schedule')"
                class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-white transition-all hover:bg-white/10 {{ request()->routeIs('ward.schedule') ? 'bg-white/20' : '' }}">
                <div class="flex items-center">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 7V5a3 3 0 013-3h2a3 3 0 013 3v2m4 0H4a2 2 0 00-2 2v9a3 3 0 003 3h14a3 3 0 003-3v-9a2 2 0 00-2-2z" />
                    </svg>
                    <span x-show="sidebarOpen" x-transition class="ml-3 font-medium">Schedule</span>
                </div>
                <svg x-show="sidebarOpen" :class="{'rotate-180': isSectionOpen('schedule')}"
                    class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <div x-show="isSectionOpen('schedule') && sidebarOpen" x-transition
                class="mt-2 ml-4 space-y-1 border-l-2 border-white/30 pl-2">
                <a href="{{ route('ward.schedule') }}"
                    class="flex items-center px-3 py-2 rounded-lg text-white text-sm transition-all {{ request()->routeIs('ward.schedule') ? 'bg-white/25 shadow-lg' : 'hover:bg-white/10' }}">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 7h18M3 12h18M3 17h18" />
                    </svg>
                    <span class="ml-2">Ward Schedule</span>
                </a>
            </div>
        </div>


        <!-- Vital Sign Section -->
        @if(!Auth::user()->hasRole(App\Models\User::ROLE_WARD_DASHBOARD))
            <div class="pt-2">
                <button @click="toggleSection('vitalSign')"
                    class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-white transition-all hover:bg-white/10 {{ request()->routeIs('vital-signs.*') ? 'bg-white/20' : '' }}">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z"
                                clip-rule="evenodd" />
                        </svg>
                        <span x-show="sidebarOpen" x-transition class="ml-3 font-medium">Vital Sign</span>
                    </div>
                    <svg x-show="sidebarOpen" :class="{'rotate-180': isSectionOpen('vitalSign')}"
                        class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-show="isSectionOpen('vitalSign') && sidebarOpen" x-transition
                    class="mt-2 ml-4 space-y-1 border-l-2 border-white/30 pl-2">
                    <a href="{{ route('vital-signs.index') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-white text-sm transition-all {{ request()->routeIs('vital-signs.index') ? 'bg-white/25 shadow-lg' : 'hover:bg-white/10' }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                        <span class="ml-2">Record</span>
                    </a>
                </div>
            </div>
        @endif


        <!-- Ward Dashboard Section -->
        <div class="pt-2">
            <a href="{{ route('ward.dashboard') }}"
                class="flex items-center px-3 py-2.5 rounded-lg text-white transition-all {{ request()->routeIs('ward.dashboard') ? 'bg-white/25 shadow-lg' : 'hover:bg-white/10' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <span x-show="sidebarOpen" x-transition class="ml-3 font-medium">Ward Dashboard</span>
            </a>
        </div>

        <!-- Integration Section -->
        @if(!Auth::user()->hasRole(App\Models\User::ROLE_WARD_DASHBOARD) && !Auth::user()->hasRole(App\Models\User::ROLE_NURSE) && !Auth::user()->hasRole(App\Models\User::ROLE_NURSE_HEAD) && !Auth::user()->hasRole(App\Models\User::ROLE_USER))
            <div class="pt-2">
                <button @click="toggleSection('integration')"
                    class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-white transition-all hover:bg-white/10 {{ request()->routeIs('ldap.*') || request()->routeIs('vital-sign-integration.*') || request()->routeIs('infusion-integration.*') || request()->routeIs('adt.*') || request()->routeIs('ecg.index') || request()->routeIs('ekad.*') || request()->routeIs('integration.demo.*') ? 'bg-white/20' : '' }}">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z" />
                        </svg>
                        <span x-show="sidebarOpen" x-transition class="ml-3 font-medium">Integration</span>
                    </div>
                    <svg x-show="sidebarOpen" :class="{'rotate-180': isSectionOpen('integration')}"
                        class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-show="isSectionOpen('integration') && sidebarOpen" x-transition
                    class="mt-2 ml-4 space-y-1 border-l-2 border-white/30 pl-2">
                    <a href="{{ route('ldap.index') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-white text-sm transition-all {{ request()->routeIs('ldap.*') ? 'bg-white/25 shadow-lg' : 'hover:bg-white/10' }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                        </svg>
                        <span class="ml-2">LDAP Integration</span>
                    </a>

                    <a href="{{ route('vital-sign-integration.index') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-white text-sm transition-all {{ request()->routeIs('vital-sign-integration.*') ? 'bg-white/25 shadow-lg' : 'hover:bg-white/10' }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z"
                                clip-rule="evenodd" />
                        </svg>
                        <span class="ml-2">Vital Sign Integration</span>
                    </a>

                    <a href="{{ route('infusion-integration.index') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-white text-sm transition-all {{ request()->routeIs('infusion-integration.*') ? 'bg-white/25 shadow-lg' : 'hover:bg-white/10' }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
                        <span class="ml-2">Infusion Integration</span>
                    </a>

                    <a href="{{ route('adt.index') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-white text-sm transition-all {{ request()->routeIs('adt.index') ? 'bg-white/25 shadow-lg' : 'hover:bg-white/10' }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                        <span class="ml-2">ADT Config</span>
                    </a>

                    <a href="{{ route('adt.test') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-white text-sm transition-all {{ request()->routeIs('adt.test') ? 'bg-white/25 shadow-lg' : 'hover:bg-white/10' }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        <span class="ml-2">ADT Test</span>
                    </a>

                    <a href="{{ route('ecg.index') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-white text-sm transition-all {{ request()->routeIs('ecg.index') ? 'bg-white/25 shadow-lg' : 'hover:bg-white/10' }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                        <span class="ml-2">ECG Admin</span>
                    </a>

                    <a href="{{ route('ekad.index') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-white text-sm transition-all {{ request()->routeIs('ekad.*') ? 'bg-white/25 shadow-lg' : 'hover:bg-white/10' }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <span class="ml-2">EKad (E-Ink)</span>
                    </a>

                    <a href="{{ route('integration.demo.index') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-white text-sm transition-all {{ request()->routeIs('integration.demo.*') ? 'bg-white/25 shadow-lg' : 'hover:bg-white/10' }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
                        <span class="ml-2">Demo</span>
                    </a>
                </div>
            </div>
        @endif

        <!-- Application Logs Section -->
        @if(!Auth::user()->hasRole(App\Models\User::ROLE_WARD_DASHBOARD) && !Auth::user()->hasRole(App\Models\User::ROLE_NURSE) && !Auth::user()->hasRole(App\Models\User::ROLE_NURSE_HEAD) && !Auth::user()->hasRole(App\Models\User::ROLE_USER))
            <div class="pt-2">
                <button @click="toggleSection('appLogs')"
                    class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-white transition-all hover:bg-white/10 {{ request()->routeIs('user-activities.*') ? 'bg-white/20' : '' }}">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span x-show="sidebarOpen" x-transition class="ml-3 font-medium">Application Logs</span>
                    </div>
                    <svg x-show="sidebarOpen" :class="{'rotate-180': isSectionOpen('appLogs')}"
                        class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-show="isSectionOpen('appLogs') && sidebarOpen" x-transition
                    class="mt-2 ml-4 space-y-1 border-l-2 border-white/30 pl-2">
                    <a href="{{ route('user-activities.index') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-white text-sm transition-all {{ request()->routeIs('user-activities.*') ? 'bg-white/25 shadow-lg' : 'hover:bg-white/10' }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="ml-2">User Activities</span>
                    </a>
                </div>
            </div>
        @endif

    </div>

    <!-- User Profile & Footer (Bottom) -->
    <div class="border-t border-blue-400/30 backdrop-blur-sm bg-white/10">
        <div x-show="sidebarOpen" x-transition class="p-4">
            <div class="flex items-center mb-4">
                <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center text-white font-bold">
                    {{ substr(Auth::user()->name, 0, 1) }}
                </div>
                <div class="ml-3 overflow-hidden">
                    <div class="font-medium text-sm text-white truncate">{{ Auth::user()->name }}</div>
                    <div class="text-xs text-blue-100 truncate">{{ Auth::user()->email }}</div>
                </div>
            </div>
            <div class="space-y-1">
                <a href="{{ route('profile.edit') }}"
                    class="flex items-center px-3 py-2 rounded-lg text-white text-sm hover:bg-white/10 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <span class="ml-2">Profile</span>
                </a>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                        class="w-full flex items-center px-3 py-2 rounded-lg text-white text-sm hover:bg-white/10 transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        <span class="ml-2">Log Out</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Footer -->
        <div x-show="sidebarOpen" x-transition
            class="px-4 py-3 bg-white/10 backdrop-blur-sm border-t border-blue-400/30">
            <div class="text-center">
                <p class="text-xs text-white/90 font-medium">Developed by</p>
                <a href="https://qmed.asia" target="_blank"
                    class="inline-flex items-center mt-1 hover:opacity-80 transition-opacity">
                    <img src="{{ asset('logo_qmed.png') }}" alt="Qmed" class="h-6 w-auto">
                </a>
                <p class="text-xs text-white/70 mt-1">© 2025 All Rights Reserved</p>
            </div>
        </div>
    </div>
</nav>