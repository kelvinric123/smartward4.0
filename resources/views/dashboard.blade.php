<x-app-layout>
    @php
        $hospital = \App\Models\Hospital::first();
        $logoUrl = $hospital && $hospital->logo_path ? \Illuminate\Support\Facades\Storage::url($hospital->logo_path) : asset('phkl_new.png');
    @endphp
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-bold text-2xl text-gray-800 leading-tight">
                    {{ __('Dashboard') }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">Welcome back, {{ Auth::user()->name }}! Here's your hospital
                    overview.</p>
            </div>
            <div class="text-sm text-gray-500">
                {{ date('l, F j, Y') }}
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Hospitals Card -->
                <div
                    class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl shadow-lg p-6 text-white transform hover:scale-105 transition-all duration-200 hover:shadow-xl">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-white/20 rounded-xl backdrop-blur-sm">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                        <span class="text-3xl font-bold">{{ \App\Models\Hospital::count() }}</span>
                    </div>
                    <h3 class="text-lg font-semibold mb-1">Hospitals</h3>
                    <p class="text-blue-100 text-sm">Registered facilities</p>
                </div>

                <!-- Consultants Card -->
                <div
                    class="bg-gradient-to-br from-cyan-500 to-teal-500 rounded-2xl shadow-lg p-6 text-white transform hover:scale-105 transition-all duration-200 hover:shadow-xl">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-white/20 rounded-xl backdrop-blur-sm">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        <span class="text-3xl font-bold">{{ \App\Models\Consultant::count() }}</span>
                    </div>
                    <h3 class="text-lg font-semibold mb-1">Consultants</h3>
                    <p class="text-cyan-100 text-sm">Active specialists</p>
                </div>

                <!-- Nurses Card -->
                <div
                    class="bg-gradient-to-br from-purple-500 to-pink-500 rounded-2xl shadow-lg p-6 text-white transform hover:scale-105 transition-all duration-200 hover:shadow-xl">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-white/20 rounded-xl backdrop-blur-sm">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <span class="text-3xl font-bold">{{ \App\Models\Nurse::count() }}</span>
                    </div>
                    <h3 class="text-lg font-semibold mb-1">Nurses</h3>
                    <p class="text-purple-100 text-sm">Nursing staff</p>
                </div>

                <!-- Anaesthetists Card -->
                <div
                    class="bg-gradient-to-br from-orange-500 to-red-500 rounded-2xl shadow-lg p-6 text-white transform hover:scale-105 transition-all duration-200 hover:shadow-xl">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-white/20 rounded-xl backdrop-blur-sm">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <span class="text-3xl font-bold">{{ \App\Models\Anaesthetist::count() }}</span>
                    </div>
                    <h3 class="text-lg font-semibold mb-1">Anaesthetists</h3>
                    <p class="text-orange-100 text-sm">Anesthesia specialists</p>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Specialties List -->
                <div class="lg:col-span-2">
                    <div
                        class="bg-white/90 backdrop-blur-sm rounded-2xl shadow-lg overflow-hidden border border-blue-100">
                        <div class="px-6 py-4 bg-gradient-to-r from-blue-50 to-cyan-50 border-b border-blue-100">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-bold text-gray-800">Specialties Overview</h3>
                                <a href="{{ route('specialties.index') }}"
                                    class="text-sm text-blue-600 hover:text-blue-800 font-medium transition-colors">View
                                    All →</a>
                            </div>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @foreach(\App\Models\Specialty::limit(6)->get() as $specialty)
                                    <div
                                        class="flex items-center p-4 bg-gradient-to-br from-blue-50 to-cyan-50 rounded-xl border border-blue-100 hover:shadow-md transition-all">
                                        <div class="p-2 bg-blue-500 rounded-lg text-white mr-4">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                            </svg>
                                        </div>
                                        <div class="flex-1">
                                            <h4 class="font-semibold text-gray-800">{{ $specialty->name }}</h4>
                                            <p class="text-xs text-gray-500 mt-1">
                                                {{ \App\Models\Consultant::where('specialty_id', $specialty->id)->count() }}
                                                Consultants</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions & Hospital Info -->
                <div class="space-y-6">
                    <!-- Hospital Info -->
                    <div
                        class="bg-white/90 backdrop-blur-sm rounded-2xl shadow-lg overflow-hidden border border-blue-100">
                        <div class="px-6 py-4 bg-gradient-to-r from-blue-50 to-cyan-50 border-b border-blue-100">
                            <h3 class="text-lg font-bold text-gray-800">Hospital Information</h3>
                        </div>
                        <div class="p-6">
                            @php
                                $hospital = \App\Models\Hospital::first();
                            @endphp
                            @if($hospital)
                                <div class="text-center mb-4">
                                    <div class="bg-blue-600 rounded-xl p-2 inline-block mb-3">
                                        <img src="{{ $logoUrl }}" alt="Hospital Logo" class="h-16 w-auto mx-auto">
                                    </div>
                                    <h4 class="font-bold text-gray-800">{{ $hospital->name }}</h4>
                                </div>
                                <div class="space-y-3 text-sm">
                                    <div class="flex items-start">
                                        <svg class="w-5 h-5 text-blue-500 mr-2 flex-shrink-0" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span class="text-gray-600">{{ $hospital->address }}</span>
                                    </div>
                                    <div class="flex items-center">
                                        <svg class="w-5 h-5 text-blue-500 mr-2" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                        </svg>
                                        <span class="text-gray-600">{{ $hospital->phone }}</span>
                                    </div>
                                    <div class="flex items-center">
                                        <svg class="w-5 h-5 text-blue-500 mr-2" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                        <span class="text-gray-600">{{ $hospital->email }}</span>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div
                        class="bg-white/90 backdrop-blur-sm rounded-2xl shadow-lg overflow-hidden border border-blue-100">
                        <div class="px-6 py-4 bg-gradient-to-r from-blue-50 to-cyan-50 border-b border-blue-100">
                            <h3 class="text-lg font-bold text-gray-800">Quick Actions</h3>
                        </div>
                        <div class="p-6 space-y-3">
                            <a href="{{ route('consultants.create') }}"
                                class="flex items-center p-3 bg-gradient-to-r from-blue-500 to-cyan-500 hover:from-blue-600 hover:to-cyan-600 text-white rounded-xl transition-all shadow-md hover:shadow-lg">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4" />
                                </svg>
                                <span class="font-medium">Add Consultant</span>
                            </a>
                            <a href="{{ route('nurses.create') }}"
                                class="flex items-center p-3 bg-gradient-to-r from-purple-500 to-pink-500 hover:from-purple-600 hover:to-pink-600 text-white rounded-xl transition-all shadow-md hover:shadow-lg">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4" />
                                </svg>
                                <span class="font-medium">Add Nurse</span>
                            </a>
                            <a href="{{ route('specialties.create') }}"
                                class="flex items-center p-3 bg-gradient-to-r from-green-500 to-teal-500 hover:from-green-600 hover:to-teal-600 text-white rounded-xl transition-all shadow-md hover:shadow-lg">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4" />
                                </svg>
                                <span class="font-medium">Add Specialty</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>