@php
    $initials = fn ($name) => collect(preg_split('/\s+/', trim((string) $name)))
        ->filter()
        ->take(2)
        ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
        ->implode('');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-4">
            <div>
                <h2 class="font-bold text-2xl text-gray-800 leading-tight">
                    {{ __('Patients') }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    {{ $patients->total() }} {{ \Illuminate\Support\Str::plural('record', $patients->total()) }}
                    @if ($search !== '')
                        matching &ldquo;{{ $search }}&rdquo;
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <form method="GET" action="{{ route('patients.index') }}" class="flex flex-wrap items-center gap-2">
                    <input type="hidden" name="status" value="{{ $status }}">
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input type="hidden" name="dir" value="{{ $direction }}">
                    <div class="relative">
                        <input type="text" name="search" value="{{ $search }}"
                            placeholder="Search name, alias, MRN, RN or IC..." autocomplete="off"
                            class="pl-10 pr-4 py-2.5 bg-white border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm min-w-[260px]">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400 pointer-events-none">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z" />
                            </svg>
                        </span>
                    </div>
                    @if ($wards->count() > 1)
                        <select name="ward" onchange="this.form.submit()"
                            class="py-2.5 pl-3 pr-8 bg-white border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <option value="">All wards</option>
                            @foreach ($wards as $wardOption)
                                <option value="{{ $wardOption->id }}" @selected((string) $ward === (string) $wardOption->id)>
                                    {{ $wardOption->ward_name }}
                                </option>
                            @endforeach
                        </select>
                    @endif
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2.5 bg-gray-100 hover:bg-gray-200 border border-gray-300 rounded-lg font-medium text-sm text-gray-700 shadow-sm transition-colors">
                        Search
                    </button>
                    @if ($search !== '' || $ward)
                        <a href="{{ route('patients.index', ['status' => $status]) }}"
                            class="inline-flex items-center px-3 py-2.5 text-gray-500 hover:text-gray-700 transition-colors"
                            title="Clear filters">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </a>
                    @endif
                </form>
                <a href="{{ route('patients.create') }}"
                    class="inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 border border-transparent rounded-lg font-semibold text-sm text-white shadow-lg hover:shadow-xl transition-all duration-200 transform hover:-translate-y-0.5">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ __('Add Patient') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))
                <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-l-4 border-green-500 text-green-800 px-6 py-4 rounded-lg shadow-md"
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

            {{-- Status tabs --}}
            <nav aria-label="Patient status"
                class="flex gap-1 overflow-x-auto p-1.5 bg-white/90 backdrop-blur-sm shadow-lg rounded-2xl border border-blue-100">
                @foreach ($tabs as $key => $tab)
                    <a href="{{ request()->fullUrlWithQuery(['status' => $key, 'page' => null]) }}"
                        @class([
                            'inline-flex items-center gap-2 shrink-0 px-4 py-2.5 rounded-xl text-sm font-semibold transition-colors',
                            'bg-gradient-to-r from-blue-600 to-cyan-600 text-white shadow-md' => $status === $key,
                            'text-gray-600 hover:bg-blue-50 hover:text-blue-700' => $status !== $key,
                        ])>
                        {{ $tab['label'] }}
                        <span @class([
                            'px-1.5 py-0.5 rounded-full text-xs font-bold',
                            'bg-white/25 text-white' => $status === $key,
                            'bg-blue-100 text-blue-700' => $status !== $key,
                        ])>{{ $tab['count'] }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-blue-100">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-blue-100">
                        <thead>
                            <tr class="bg-gradient-to-r from-blue-50 to-cyan-50">
                                <x-patient.sort-header column="name" label="Patient" :sort="$sort" :direction="$direction" />
                                <th class="px-4 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">IC / Passport</th>
                                <th class="px-4 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Age / Gender</th>
                                <x-patient.sort-header column="ward" label="Ward / Bed" :sort="$sort" :direction="$direction" />
                                <x-patient.sort-header column="status" label="Status" :sort="$sort" :direction="$direction" />
                                <th class="px-4 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Payor</th>
                                <x-patient.sort-header column="admitted" label="Stay" :sort="$sort" :direction="$direction" />
                                <th class="px-4 py-4 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-blue-50">
                            @forelse ($patients as $patient)
                                <tr class="hover:bg-blue-50/60 transition-colors">
                                    <td class="px-4 py-4">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="w-10 h-10 shrink-0 rounded-xl bg-gradient-to-br from-blue-600 to-cyan-500 text-white text-sm font-bold flex items-center justify-center shadow-sm">
                                                {{ $initials($patient->name) ?: '?' }}
                                            </div>
                                            <div class="min-w-0">
                                                <a href="{{ route('patients.show', $patient) }}"
                                                    class="font-semibold text-gray-800 hover:text-blue-600 transition-colors">{{ $patient->name }}</a>
                                                @if ($patient->alias_name)
                                                    <p class="text-xs text-gray-500 italic">a.k.a. {{ $patient->alias_name }}</p>
                                                @endif
                                                <p class="text-xs text-gray-500 font-mono">{{ $patient->mrn }} &middot; {{ $patient->rn }}</p>
                                                @php $coe = collect($patient->coe_indicators ?? [])->values(); @endphp
                                                @if ($coe->isNotEmpty())
                                                    @foreach ($coe->take(2) as $indicator)
                                                        <span class="inline-block mt-1 mr-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-violet-100 text-violet-800 whitespace-nowrap">{{ $indicator }}</span>
                                                    @endforeach
                                                    @if ($coe->count() > 2)
                                                        <span class="inline-block mt-1 text-[11px] font-semibold text-gray-400">+{{ $coe->count() - 2 }}</span>
                                                    @endif
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <p class="text-sm text-gray-600 font-mono">{{ $patient->ic_passport }}</p>
                                        <p class="text-xs text-gray-400">{{ $patient->phone }}</p>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <span class="text-sm text-gray-600">{{ $patient->age !== null ? $patient->age . ' yrs' : '—' }}</span>
                                        @if ($patient->gender)
                                            <span @class([
                                                'ml-1 px-2 py-0.5 inline-flex text-xs font-semibold rounded-full',
                                                'bg-blue-100 text-blue-800' => $patient->gender === 'Male',
                                                'bg-pink-100 text-pink-800' => $patient->gender !== 'Male',
                                            ])>{{ $patient->gender }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4">
                                        <p class="text-sm text-gray-700">{{ $patient->ward?->ward_name ?? '—' }}</p>
                                        @if ($patient->bed_number)
                                            <p class="text-xs text-gray-400">Bed {{ $patient->bed_number }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full {{ $patient->statusBadgeClass() }}">
                                            {{ $patient->statusLabel() }}
                                        </span>
                                        @unless ($patient->is_active)
                                            <span class="block mt-1 text-xs font-semibold text-red-600">Inactive</span>
                                        @endunless
                                    </td>
                                    <td class="px-4 py-4">
                                        @if ($patient->payorStatusLabel())
                                            <span class="px-2.5 py-0.5 inline-flex text-xs font-semibold rounded-full {{ $patient->payorStatusBadgeClass() }}">
                                                {{ $patient->payorStatusLabel() }}
                                            </span>
                                        @else
                                            <span class="text-sm text-gray-300">—</span>
                                        @endif
                                        @if ($patient->payor_name)
                                            <p class="text-xs text-gray-400 mt-1 max-w-[140px] truncate">{{ $patient->payor_name }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        @if ($patient->admitted_at)
                                            <p class="text-sm text-gray-700">{{ $patient->admitted_at->format('d M Y') }}</p>
                                            <p class="text-xs text-gray-400">{{ $patient->lengthOfStayLabel() ?? 'Discharged' }}</p>
                                        @else
                                            <span class="text-sm text-gray-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="{{ route('patients.show', $patient) }}"
                                            class="inline-flex items-center px-3 py-1.5 bg-blue-100 hover:bg-blue-200 text-blue-700 rounded-lg transition-colors mr-2">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            View
                                        </a>
                                        <form action="{{ route('patients.deactivate', $patient) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit"
                                                class="inline-flex items-center px-3 py-1.5 bg-yellow-100 hover:bg-yellow-200 text-yellow-700 rounded-lg transition-colors mr-2">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                </svg>
                                                {{ $patient->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                        <form action="{{ route('patients.destroy', $patient) }}" method="POST" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button"
                                                onclick="confirmDelete(event, 'Are you sure you want to delete this item?')"
                                                class="inline-flex items-center px-3 py-1.5 bg-red-100 hover:bg-red-200 text-red-700 rounded-lg transition-colors">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center">
                                            <svg class="w-16 h-16 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                            <p class="text-gray-500 font-medium">No patients found</p>
                                            <p class="text-gray-400 text-sm mt-1">
                                                @if ($search !== '' || $ward || $status !== 'all')
                                                    Try a different search or status filter.
                                                @else
                                                    Get started by adding your first patient.
                                                @endif
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($patients->hasPages())
                    <div class="px-6 py-4 border-t border-blue-50">
                        {{ $patients->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
