@php
    $activeTab = request('tab') === 'clinical_indicators' ? 'clinical_indicators' : 'ward_types';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-bold text-2xl text-gray-800 leading-tight">
                {{ __('Ward Types') }}
            </h2>
            <p class="text-sm text-gray-500 mt-1">Ward types and the clinical indicators each one is scored against</p>
        </div>
    </x-slot>

    <div class="py-8" x-data="{ activeTab: '{{ $activeTab }}' }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-6 bg-gradient-to-r from-green-50 to-emerald-50 border-l-4 border-green-500 text-green-800 px-3 py-3 rounded-lg shadow-md"
                    role="alert">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="font-medium">{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-blue-100">
                <div class="p-8">

                    <!-- Tabs -->
                    <div class="mb-6 border-b border-gray-200 flex justify-between items-center">
                        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                            <button @click="activeTab = 'ward_types'"
                                :class="activeTab === 'ward_types' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                Ward Types
                                <span class="ml-1.5 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">{{ $wardTypes->total() }}</span>
                            </button>

                            <button @click="activeTab = 'clinical_indicators'"
                                :class="activeTab === 'clinical_indicators' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                Clinical Indicator
                                <span class="ml-1.5 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">{{ $clinicalIndicators->count() }}</span>
                            </button>
                        </nav>

                        <a x-show="activeTab === 'ward_types'" href="{{ route('ward-types.create') }}"
                            class="inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-brand-600 to-accent-600 hover:from-brand-700 hover:to-accent-700 border border-transparent rounded-lg font-semibold text-sm text-white shadow-lg hover:shadow-xl transition-all duration-200">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            {{ __('Add Ward Type') }}
                        </a>

                        <a x-show="activeTab === 'clinical_indicators'" x-cloak
                            href="{{ route('clinical-indicators.create') }}"
                            class="inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-brand-600 to-accent-600 hover:from-brand-700 hover:to-accent-700 border border-transparent rounded-lg font-semibold text-sm text-white shadow-lg hover:shadow-xl transition-all duration-200">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            {{ __('Add Clinical Indicator') }}
                        </a>
                    </div>

                    <!-- Ward Types tab -->
                    <div x-show="activeTab === 'ward_types'">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-blue-100">
                                <thead>
                                    <tr class="bg-gradient-to-r from-brand-50 to-accent-50">
                                        <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                            Code</th>
                                        <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                            Ward Type</th>
                                        <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                            Hospital</th>
                                        <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                            Clinical Indicator</th>
                                        <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                            Critical Care</th>
                                        <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                            Emergency</th>
                                        <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                            Status</th>
                                        <th class="px-3 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">
                                            Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-blue-50">
                                    @forelse ($wardTypes as $wardType)
                                        <tr class="hover:bg-blue-50 transition-colors">
                                            <td class="px-3 py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800">
                                                    {{ $wardType->code }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-3">
                                                <span class="font-semibold text-gray-800">{{ $wardType->name }}</span>
                                            </td>
                                            <td class="px-3 py-3 text-sm">
                                                @if ($wardType->isSystemType())
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                                        All hospitals
                                                    </span>
                                                @else
                                                    <span class="text-gray-600">{{ $wardType->hospital->name ?? '-' }}</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-3 text-sm">
                                                @forelse ($wardType->clinicalIndicators as $indicator)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-violet-100 text-violet-800 mr-1 mb-1"
                                                        title="{{ $indicator->name }}">
                                                        {{ $indicator->code }}
                                                    </span>
                                                @empty
                                                    <span class="text-gray-400">Not set</span>
                                                @endforelse
                                            </td>
                                            <td class="px-3 py-3 whitespace-nowrap text-sm">
                                                @if ($wardType->is_critical_care)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800"
                                                        title="Wards of this type are on the Critical Care Ward Dashboard">
                                                        Yes
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">No</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-3 whitespace-nowrap text-sm">
                                                @if ($wardType->is_emergency)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800"
                                                        title="Wards of this type are on Command Center V2 (ED)">
                                                        Yes
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">No</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-3 whitespace-nowrap">
                                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full {{ $wardType->is_active ? 'bg-gradient-to-r from-green-100 to-emerald-100 text-green-800 border border-green-200' : 'bg-gradient-to-r from-red-100 to-pink-100 text-red-800 border border-red-200' }}">
                                                    {{ $wardType->is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-3 whitespace-nowrap text-right text-sm font-medium">
                                                <div class="flex items-center justify-end gap-1.5">
                                                <a href="{{ route('ward-types.edit', $wardType) }}"
                                                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg transition-colors bg-blue-100 hover:bg-blue-200 text-blue-700"
                                                    title="Edit ward type">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                    <span class="sr-only">Edit</span>
                                                </a>
                                                <form action="{{ route('ward-types.toggle-active', $wardType) }}" method="POST">
                                                    @csrf
                                                    <button type="submit"
                                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg transition-colors bg-yellow-100 hover:bg-yellow-200 text-yellow-700"
                                                        title="{{ $wardType->is_active ? 'Deactivate' : 'Activate' }} ward type">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="{{ $wardType->is_active ? 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636' : 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' }}" />
                                                        </svg>
                                                        <span class="sr-only">{{ $wardType->is_active ? 'Deactivate' : 'Activate' }}</span>
                                                    </button>
                                                </form>
                                                <form action="{{ route('ward-types.destroy', $wardType) }}" method="POST">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button"
                                                        onclick="confirmDelete(event, 'Are you sure you want to delete this ward type? Wards using it will be left without a ward type.')"
                                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg transition-colors bg-red-100 hover:bg-red-200 text-red-700"
                                                        title="Delete ward type">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                        <span class="sr-only">Delete</span>
                                                    </button>
                                                </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="px-6 py-12 text-center">
                                                <div class="flex flex-col items-center">
                                                    <svg class="w-16 h-16 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                    </svg>
                                                    <p class="text-gray-500 font-medium">No ward types found</p>
                                                    <p class="text-gray-400 text-sm mt-1">Get started by adding your first ward type</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-6">
                            {{ $wardTypes->appends(['tab' => 'ward_types'])->links() }}
                        </div>
                    </div>

                    <!-- Clinical Indicator tab -->
                    <div x-show="activeTab === 'clinical_indicators'" x-cloak x-data="{ openRow: null, category: 'all' }">
                        @if ($indicatorGroups->count() > 1)
                            <div class="mb-5 flex flex-wrap items-center gap-2" role="group" aria-label="Show one category">
                                <button type="button" @click="category = 'all'" :aria-pressed="category === 'all'"
                                    :class="category === 'all' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-gray-200 bg-white text-gray-600 hover:border-blue-300 hover:text-blue-700'"
                                    class="inline-flex items-center gap-2 whitespace-nowrap rounded-full border px-3 py-1 text-xs font-semibold transition-colors">
                                    All
                                    <span class="font-medium text-gray-400">{{ $clinicalIndicators->count() }}</span>
                                </button>
                                @foreach ($indicatorGroups as $category => $indicators)
                                    @php
                                        $categoryKey = \Illuminate\Support\Str::slug($category);
                                    @endphp
                                    <button type="button" @click="category = '{{ $categoryKey }}'"
                                        :aria-pressed="category === '{{ $categoryKey }}'"
                                        :class="category === '{{ $categoryKey }}' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-gray-200 bg-white text-gray-600 hover:border-blue-300 hover:text-blue-700'"
                                        class="inline-flex items-center gap-2 whitespace-nowrap rounded-full border py-1 pl-1 pr-3 text-xs font-semibold transition-colors">
                                        <x-clinical-indicator-category-icon :category="$category" size="xs" />
                                        {{ $category }}
                                        <span class="font-medium text-gray-400">{{ $indicators->count() }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-blue-100">
                                <thead>
                                    <tr class="bg-gradient-to-r from-brand-50 to-accent-50">
                                        {{-- Fixed widths so the columns hold still when a category filter hides rows --}}
                                        <th class="w-28 px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                            Code</th>
                                        <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                            Clinical Indicator</th>
                                        <th class="w-36 px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                            Monitoring</th>
                                        <th class="w-24 px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider whitespace-nowrap">
                                            Used By</th>
                                        <th class="w-28 px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                            Status</th>
                                        <th class="w-44 px-3 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">
                                            Actions</th>
                                    </tr>
                                </thead>
                                @forelse ($indicatorGroups as $category => $indicators)
                                    <tbody class="bg-white divide-y divide-blue-50"
                                        x-show="category === 'all' || category === '{{ \Illuminate\Support\Str::slug($category) }}'">
                                        <tr class="bg-gray-50">
                                            <th scope="rowgroup" colspan="6" class="px-3 py-3 text-left font-normal">
                                                <div class="flex items-center gap-3">
                                                    <x-clinical-indicator-category-icon :category="$category" />
                                                    <div>
                                                        <div class="flex items-center gap-2">
                                                            <span class="text-sm font-bold text-gray-800">{{ $category }}</span>
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-white border border-gray-200 text-gray-600">
                                                                {{ $indicators->count() }}
                                                            </span>
                                                        </div>
                                                        @if (!empty(\App\Support\ClinicalIndicatorLibrary::CATEGORIES[$category]))
                                                            <p class="text-xs text-gray-500 mt-0.5">
                                                                {{ \App\Support\ClinicalIndicatorLibrary::CATEGORIES[$category] }}</p>
                                                        @endif
                                                    </div>
                                                </div>
                                            </th>
                                        </tr>
                                        @foreach ($indicators as $indicator)
                                            @php
                                                $definition = $indicator->definition();
                                                $hasDetail =
                                                    $definition &&
                                                    (!empty($definition['purpose']) ||
                                                        !empty($definition['items']) ||
                                                        !empty($definition['note']));
                                            @endphp
                                            <tr class="hover:bg-blue-50 transition-colors">
                                                <td class="px-3 py-3 whitespace-nowrap align-top">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-violet-100 text-violet-800">
                                                        {{ $indicator->code }}
                                                    </span>
                                                </td>
                                                <td class="px-3 py-3 align-top">
                                                    <div class="flex items-center gap-2">
                                                        <span class="font-semibold text-gray-800">{{ $indicator->name }}</span>
                                                        @if ($definition && !($definition['confirmed'] ?? false))
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 whitespace-nowrap">
                                                                Details to confirm
                                                            </span>
                                                        @endif
                                                    </div>
                                                    @if ($definition)
                                                        <p class="text-xs text-gray-500 mt-0.5">
                                                            {{ $definition['population'] ?? '' }}
                                                            @if (\App\Support\ClinicalIndicatorLibrary::takesReadings($definition))
                                                                @if (!empty($definition['population']))
                                                                    <span class="text-gray-300">&middot;</span>
                                                                @endif
                                                                Readings: {{ implode(', ', array_column($definition['items'], 'abbr')) }}
                                                            @elseif (\App\Support\ClinicalIndicatorLibrary::isScreen($definition))
                                                                @if (!empty($definition['population']))
                                                                    <span class="text-gray-300">&middot;</span>
                                                                @endif
                                                                Screen: {{ count($definition['items']) }} questions, risk from the most serious answer
                                                            @elseif ($definition['score_min'] !== null)
                                                                @if (!empty($definition['population']))
                                                                    <span class="text-gray-300">&middot;</span>
                                                                @endif
                                                                Score {{ $definition['score_min'] }} to {{ $definition['score_max'] }}
                                                            @endif
                                                        </p>
                                                    @elseif ($indicator->description)
                                                        <p class="text-xs text-gray-500 mt-0.5">{{ $indicator->description }}</p>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-3 text-xs align-top whitespace-nowrap">
                                                    @if ($indicator->isMonitored())
                                                        <div class="flex items-center gap-1.5 text-gray-700"
                                                            title="Suggested interval: shown as due once passed">
                                                            <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                                                            Every {{ \App\Support\ClinicalIndicatorMonitoring::formatInterval($indicator->monitoring_suggested_minutes) }}
                                                        </div>
                                                        <div class="mt-1 flex items-center gap-1.5 text-gray-700"
                                                            title="Warning level: shown as overdue once passed">
                                                            <span class="h-2 w-2 rounded-full bg-red-500"></span>
                                                            Warning {{ \App\Support\ClinicalIndicatorMonitoring::formatInterval($indicator->monitoring_warning_minutes) }}
                                                        </div>
                                                    @else
                                                        <span class="text-gray-400">Off</span>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-3 text-sm align-top whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                        {{ $indicator->ward_types_count }}
                                                    </span>
                                                </td>
                                                <td class="px-3 py-3 whitespace-nowrap align-top">
                                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full {{ $indicator->is_active ? 'bg-gradient-to-r from-green-100 to-emerald-100 text-green-800 border border-green-200' : 'bg-gradient-to-r from-red-100 to-pink-100 text-red-800 border border-red-200' }}">
                                                        {{ $indicator->is_active ? 'Active' : 'Inactive' }}
                                                    </span>
                                                </td>
                                                <td class="px-3 py-3 whitespace-nowrap text-right text-sm font-medium align-top">
                                                    <div class="flex items-center justify-end gap-1.5">
                                                        @if ($hasDetail)
                                                            <button type="button"
                                                                @click="openRow = openRow === {{ $indicator->id }} ? null : {{ $indicator->id }}"
                                                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg transition-colors bg-violet-100 hover:bg-violet-200 text-violet-700"
                                                                :title="openRow === {{ $indicator->id }} ? 'Hide details' : 'View details'"
                                                                title="View details">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                                </svg>
                                                                <span class="sr-only">View details</span>
                                                            </button>
                                                        @endif
                                                    <a href="{{ route('clinical-indicators.edit', $indicator) }}"
                                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg transition-colors bg-blue-100 hover:bg-blue-200 text-blue-700"
                                                        title="Edit clinical indicator">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                        <span class="sr-only">Edit</span>
                                                    </a>
                                                    <form action="{{ route('clinical-indicators.toggle-active', $indicator) }}" method="POST">
                                                        @csrf
                                                        <button type="submit"
                                                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg transition-colors bg-yellow-100 hover:bg-yellow-200 text-yellow-700"
                                                            title="{{ $indicator->is_active ? 'Deactivate' : 'Activate' }} clinical indicator">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="{{ $indicator->is_active ? 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636' : 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' }}" />
                                                            </svg>
                                                            <span class="sr-only">{{ $indicator->is_active ? 'Deactivate' : 'Activate' }}</span>
                                                        </button>
                                                    </form>
                                                        @unless ($indicator->isStandard())
                                                    <form action="{{ route('clinical-indicators.destroy', $indicator) }}" method="POST">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="button"
                                                            onclick="confirmDelete(event, 'Are you sure you want to delete this clinical indicator? Ward types using it will lose it.')"
                                                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg transition-colors bg-red-100 hover:bg-red-200 text-red-700"
                                                            title="Delete clinical indicator">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                            <span class="sr-only">Delete</span>
                                                        </button>
                                                    </form>
                                                        @endunless
                                                    </div>
                                                </td>
                                            </tr>

                                            @if ($definition)
                                                <tr x-show="openRow === {{ $indicator->id }}" x-cloak>
                                                    <td colspan="6" class="px-4 pb-6 pt-0 bg-blue-50/40">
                                                        <x-clinical-indicator-detail :definition="$definition" />
                                                    </td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    </tbody>
                                @empty
                                    <tbody class="bg-white">
                                        <tr>
                                            <td colspan="6" class="px-6 py-12 text-center">
                                                <div class="flex flex-col items-center">
                                                    <svg class="w-16 h-16 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                    </svg>
                                                    <p class="text-gray-500 font-medium">No clinical indicators found</p>
                                                    <p class="text-gray-400 text-sm mt-1">Get started by adding your first clinical indicator</p>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                @endforelse
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
