<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-bold text-2xl text-gray-800 leading-tight">{{ __('Dashboard Settings') }}</h2>
                <p class="text-sm text-gray-500 mt-1">Configure "{{ $commandCentre->name }}" dashboard display</p>
            </div>
            <a href="{{ route('patient-flow-command-centres.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 transition-all">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-6 bg-gradient-to-r from-green-50 to-emerald-50 border-l-4 border-green-500 text-green-800 px-6 py-4 rounded-lg shadow-md" role="alert">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span class="font-medium">{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-blue-100">
                <div class="p-8">
                    <form method="POST" action="{{ route('patient-flow-command-centres.settings.update', $commandCentre) }}">
                        @csrf

                        {{-- Display Sections --}}
                        <div class="mb-8">
                            <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wider mb-4 flex items-center">
                                <svg class="w-4 h-4 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                                Dashboard Sections
                            </h3>
                            <div class="space-y-3">
                                <label class="flex items-center justify-between p-3 rounded-lg border border-gray-200 bg-white hover:bg-blue-50 transition-colors cursor-pointer">
                                    <div><span class="text-sm font-medium text-gray-800">Summary Cards</span><p class="text-xs text-gray-500">Show overview cards at the top (wards, beds, occupancy)</p></div>
                                    <input type="checkbox" name="show_summary_cards" value="1" {{ $current['show_summary_cards'] ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                </label>
                                <label class="flex items-center justify-between p-3 rounded-lg border border-gray-200 bg-white hover:bg-blue-50 transition-colors cursor-pointer">
                                    <div><span class="text-sm font-medium text-gray-800">Bed-Level Details</span><p class="text-xs text-gray-500">Show individual bed/patient activity below the summary table</p></div>
                                    <input type="checkbox" name="show_bed_details" value="1" {{ $current['show_bed_details'] ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                </label>
                            </div>
                        </div>

                        {{-- Patient Flow Sections --}}
                        <div class="mb-8">
                            <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wider mb-4 flex items-center">
                                <svg class="w-4 h-4 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                                Patient Flow Sections
                            </h3>
                            <p class="text-xs text-gray-500 mb-3">Choose which patient flow categories to display in bed details</p>
                            <div class="space-y-3">
                                <label class="flex items-center justify-between p-3 rounded-lg border border-blue-200 bg-blue-50/50 hover:bg-blue-50 transition-colors cursor-pointer">
                                    <div class="flex items-center"><div class="w-3 h-3 rounded-full bg-blue-500 mr-3"></div><span class="text-sm font-medium text-gray-800">Admitting</span></div>
                                    <input type="checkbox" name="show_admitting_section" value="1" {{ $current['show_admitting_section'] ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                </label>
                                <label class="flex items-center justify-between p-3 rounded-lg border border-amber-200 bg-amber-50/50 hover:bg-amber-50 transition-colors cursor-pointer">
                                    <div class="flex items-center"><div class="w-3 h-3 rounded-full bg-amber-500 mr-3"></div><span class="text-sm font-medium text-gray-800">Pending Discharge</span></div>
                                    <input type="checkbox" name="show_pending_discharge_section" value="1" {{ $current['show_pending_discharge_section'] ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                </label>
                                <label class="flex items-center justify-between p-3 rounded-lg border border-teal-200 bg-teal-50/50 hover:bg-teal-50 transition-colors cursor-pointer">
                                    <div class="flex items-center"><div class="w-3 h-3 rounded-full bg-teal-500 mr-3"></div><span class="text-sm font-medium text-gray-800">Discharged Today</span></div>
                                    <input type="checkbox" name="show_discharged_section" value="1" {{ $current['show_discharged_section'] ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                </label>
                                <label class="flex items-center justify-between p-3 rounded-lg border border-purple-200 bg-purple-50/50 hover:bg-purple-50 transition-colors cursor-pointer">
                                    <div class="flex items-center"><div class="w-3 h-3 rounded-full bg-purple-500 mr-3"></div><span class="text-sm font-medium text-gray-800">Prebooked</span></div>
                                    <input type="checkbox" name="show_prebooked_section" value="1" {{ $current['show_prebooked_section'] ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                </label>
                            </div>
                        </div>

                        {{-- Privacy --}}
                        <div class="mb-8">
                            <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wider mb-4 flex items-center">
                                <svg class="w-4 h-4 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                Privacy & Data
                            </h3>
                            <div class="space-y-3">
                                <label class="flex items-center justify-between p-3 rounded-lg border border-gray-200 bg-white hover:bg-blue-50 transition-colors cursor-pointer">
                                    <div><span class="text-sm font-medium text-gray-800">Show Patient Name</span><p class="text-xs text-gray-500">Display patient name in bed details (may have privacy implications)</p></div>
                                    <input type="checkbox" name="show_patient_name" value="1" {{ $current['show_patient_name'] ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                </label>
                                <label class="flex items-center justify-between p-3 rounded-lg border border-gray-200 bg-white hover:bg-blue-50 transition-colors cursor-pointer">
                                    <div><span class="text-sm font-medium text-gray-800">Show Consultant</span><p class="text-xs text-gray-500">Display consultant/doctor name in bed details</p></div>
                                    <input type="checkbox" name="show_consultant" value="1" {{ $current['show_consultant'] ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                </label>
                            </div>
                        </div>

                        {{-- Refresh Interval --}}
                        <div class="mb-8">
                            <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wider mb-4 flex items-center">
                                <svg class="w-4 h-4 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Refresh Interval
                            </h3>
                            <div class="flex items-center gap-3">
                                <input type="number" name="refresh_interval" value="{{ $current['refresh_interval'] }}" min="10" max="300" class="w-24 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <span class="text-sm text-gray-500">seconds (10–300)</span>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-4 pt-6 border-t border-gray-200">
                            <a href="{{ route('patient-flow-command-centres.index') }}" class="inline-flex items-center px-5 py-2.5 bg-gray-100 hover:bg-gray-200 border border-gray-300 rounded-lg font-semibold text-sm text-gray-700 transition-all">Cancel</a>
                            <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 border border-transparent rounded-lg font-semibold text-sm text-white shadow-lg hover:shadow-xl transition-all">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
