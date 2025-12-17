<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-bold text-2xl text-gray-800 leading-tight">
                    {{ __('Anaesthetist Details') }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">View anaesthetist information</p>
            </div>
            <div class="flex space-x-3">
                <a href="{{ route('anaesthetists.edit', $anaesthetist) }}" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 border border-transparent rounded-lg font-semibold text-sm text-white shadow-md hover:shadow-lg transition-all duration-200">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    {{ __('Edit') }}
                </a>
                <a href="{{ route('anaesthetists.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-500 hover:bg-gray-600 border border-transparent rounded-lg font-semibold text-sm text-white shadow-md hover:shadow-lg transition-all duration-200">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    {{ __('Back to List') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-blue-100">
                <div class="p-8">
                    <!-- Header Section -->
                    <div class="flex items-center mb-8 pb-6 border-b border-gray-200">
                        <div class="p-4 bg-gradient-to-br from-orange-100 to-amber-100 rounded-2xl mr-6">
                            <svg class="w-12 h-12 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-gray-800">{{ $anaesthetist->name }}</h3>
                            <span class="inline-flex items-center px-3 py-1 mt-2 rounded-full text-sm font-medium {{ $anaesthetist->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $anaesthetist->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                    </div>

                    <!-- Details Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Personnel Code (ADT) -->
                        <div class="bg-gradient-to-br from-amber-50 to-orange-50 rounded-xl p-5 border border-amber-100">
                            <div class="flex items-center mb-2">
                                <svg class="w-5 h-5 text-amber-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                </svg>
                                <span class="text-sm font-semibold text-amber-700 uppercase tracking-wide">Personnel Code (ADT)</span>
                            </div>
                            <p class="text-xl font-bold text-gray-800">{{ $anaesthetist->personnel_code ?? '-' }}</p>
                        </div>

                        <!-- Registration Number -->
                        <div class="bg-gradient-to-br from-blue-50 to-cyan-50 rounded-xl p-5 border border-blue-100">
                            <div class="flex items-center mb-2">
                                <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                                </svg>
                                <span class="text-sm font-semibold text-blue-700 uppercase tracking-wide">Registration Number</span>
                            </div>
                            <p class="text-xl font-bold text-gray-800">{{ $anaesthetist->registration_number }}</p>
                        </div>

                        <!-- Email -->
                        <div class="bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl p-5 border border-green-100">
                            <div class="flex items-center mb-2">
                                <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                <span class="text-sm font-semibold text-green-700 uppercase tracking-wide">Email</span>
                            </div>
                            <p class="text-lg text-gray-800">{{ $anaesthetist->email ?? '-' }}</p>
                        </div>

                        <!-- Phone -->
                        <div class="bg-gradient-to-br from-indigo-50 to-violet-50 rounded-xl p-5 border border-indigo-100">
                            <div class="flex items-center mb-2">
                                <svg class="w-5 h-5 text-indigo-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                </svg>
                                <span class="text-sm font-semibold text-indigo-700 uppercase tracking-wide">Phone</span>
                            </div>
                            <p class="text-lg text-gray-800">{{ $anaesthetist->phone ?? '-' }}</p>
                        </div>

                        <!-- Years of Experience -->
                        <div class="bg-gradient-to-br from-teal-50 to-cyan-50 rounded-xl p-5 border border-teal-100 md:col-span-2">
                            <div class="flex items-center mb-2">
                                <svg class="w-5 h-5 text-teal-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span class="text-sm font-semibold text-teal-700 uppercase tracking-wide">Years of Experience</span>
                            </div>
                            <p class="text-xl font-bold text-gray-800">{{ $anaesthetist->years_of_experience ?? '-' }} {{ $anaesthetist->years_of_experience ? 'years' : '' }}</p>
                        </div>
                    </div>

                    <!-- Qualifications -->
                    @if($anaesthetist->qualifications)
                    <div class="mt-6 bg-gradient-to-br from-gray-50 to-slate-50 rounded-xl p-5 border border-gray-200">
                        <div class="flex items-center mb-3">
                            <svg class="w-5 h-5 text-gray-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <span class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Qualifications</span>
                        </div>
                        <p class="text-gray-800 whitespace-pre-line">{{ $anaesthetist->qualifications }}</p>
                    </div>
                    @endif

                    <!-- Timestamps -->
                    <div class="mt-6 pt-6 border-t border-gray-200 flex justify-between text-sm text-gray-500">
                        <span>Created: {{ $anaesthetist->created_at->format('M d, Y H:i') }}</span>
                        <span>Last Updated: {{ $anaesthetist->updated_at->format('M d, Y H:i') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>









