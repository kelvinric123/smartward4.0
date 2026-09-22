@php
    $money = fn ($amount) => 'RM ' . number_format((float) $amount, 2);
    $updateUrl = route('patients.update', $patient);

    $initials = collect(preg_split('/\s+/', trim((string) $patient->name)))
        ->filter()
        ->take(2)
        ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
        ->implode('');

    $statusLabel = $patient->statusLabel();
    $statusClass = $patient->statusBadgeClass();
    $payorStatusLabel = $patient->payorStatusLabel();
    $payorStatusClass = $patient->payorStatusBadgeClass();
    $payorTypeLabel = \App\Models\Patient::PAYOR_TYPES[$patient->payor_type] ?? null;

    // Which tabs each section belongs to; "overview" shows them all side by side
    $sectionTabs = \App\Http\Controllers\PatientController::TAB_SECTIONS;
    $tabsShowing = fn (string $section) => collect($sectionTabs)
        ->filter(fn ($sections) => in_array($section, $sections, true))
        ->keys();
    $showExpr = fn (string $section) => '[' . $tabsShowing($section)->map(fn ($tab) => "'{$tab}'")->implode(',') . '].includes(tab)';
    $hiddenStyle = fn (string $section) => $tabsShowing($section)->contains($initialTab) ? '' : 'display:none';

    // Same classes the Alpine :class binding uses, so the first paint already matches the open tab
    $gridClass = match ($initialTab) {
        'overview' => 'grid grid-cols-1 lg:grid-cols-2 2xl:grid-cols-3 gap-6 items-start',
        'billing' => 'grid grid-cols-1 lg:grid-cols-2 gap-6 items-start max-w-6xl mx-auto',
        default => 'grid grid-cols-1 gap-6 max-w-4xl mx-auto',
    };

    $address = collect(is_array($patient->address) ? [
        $patient->address['street'] ?? null,
        trim(($patient->address['postal_code'] ?? '') . ' ' . ($patient->address['city'] ?? '')),
        $patient->address['state'] ?? null,
        $patient->address['country'] ?? null,
    ] : [])->filter()->implode(', ');

    $coeIndicators = $patient->coe_indicators ?? [];
    $selectedCoe = collect(old('_section') === 'medical' ? (array) old('coe_indicators', []) : $coeIndicators)
        ->filter(fn ($value) => is_string($value) && $value !== '')
        ->values()
        ->all();
    $coePickerOptions = collect($coeOptions)->merge($selectedCoe)->unique()->values()->all();

    // Allergies arrive from ADT either as plain strings or as objects with a status
    $allergies = collect($patient->allergies ?? [])->map(function ($allergy) {
        $raw = is_array($allergy) ? ($allergy['allergen'] ?? $allergy['allergen_code'] ?? 'Unknown') : (string) $allergy;
        return [
            'name' => str_contains($raw, '^') ? (explode('^', $raw)[1] ?? $raw) : $raw,
            'resolved' => is_array($allergy) && ($allergy['status'] ?? null) === 'Resolved',
        ];
    });

    $diets = collect($patient->diet_types ?? [])->filter()->map(fn ($code) => \App\Models\DietType::getDisplayName((string) $code));

    $nursingStyles = [
        'level_1' => 'bg-green-100 text-green-700',
        'level_2' => 'bg-blue-100 text-blue-700',
        'level_3' => 'bg-yellow-100 text-yellow-700',
        'level_4' => 'bg-red-100 text-red-700',
    ];
    $fallRiskStyles = [
        'low' => 'bg-green-100 text-green-700',
        'moderate' => 'bg-yellow-100 text-yellow-700',
        'high' => 'bg-orange-100 text-orange-700',
        'alert_active' => 'bg-red-100 text-red-700',
    ];
    $hasIsolation = $patient->isolation_type && $patient->isolation_type !== 'none';
    $isolationCritical = $hasIsolation && in_array(strtoupper($patient->isolation_type), ['COVID', 'TB', 'AIR', 'AIRBORNE']);
    $hgtFrequencies = ['bd' => 'BD (Twice Daily)', 'tds' => 'TDS (Three Times Daily)', 'qid' => 'QID (Four Times Daily)', 'pid' => 'PRN (As Needed)'];

    $los = $summary['los_days'] !== null
        ? $summary['los_days'] . ' ' . \Illuminate\Support\Str::plural('day', $summary['los_days']) . ' ' . $summary['los_hours'] . ' hrs'
        : null;
    $estimatedLos = $patient->estimated_length_of_stay
        ? $patient->estimated_length_of_stay . ' ' . \Illuminate\Support\Str::plural('day', $patient->estimated_length_of_stay)
        : null;
    $expected = $summary['expected_discharge'];
    $expectedRelative = $summary['expected_relative'];
    $expectedRelativeClass = match (true) {
        $expectedRelative === null => '',
        str_starts_with($expectedRelative, 'Overdue') => 'bg-red-100 text-red-700',
        in_array($expectedRelative, ['Today', 'Tomorrow']) => 'bg-amber-100 text-amber-800',
        default => 'bg-blue-100 text-blue-700',
    };

    $charges = $summary['charges'];
    $careProviders = $patient->activeCareProviders;
    $nurse = $patient->nurse ?? $patient->bed?->nurse;
    $careTeamCount = $careProviders->count() + ($patient->consultant ? 1 : 0) + ($patient->anaesthetist ? 1 : 0) + ($nurse ? 1 : 0);

    // Tab bar: label, icon and the little attention markers
    $activeAllergies = $allergies->where('resolved', false)->count();
    $payorNeedsAttention = in_array($patient->payor_status, ['pending', 'gl_requested', 'rejected'], true)
        || ($charges['gl_usage_pct'] !== null && $charges['gl_usage_pct'] > 100);

    $tabs = [
        'overview' => [
            'label' => 'Overview',
            'icon' => 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z',
        ],
        'details' => [
            'label' => 'Patient Details',
            'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
        ],
        'admission' => [
            'label' => 'Admission',
            'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
        ],
        'medical' => [
            'label' => 'Medical',
            'icon' => 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z',
            'badge' => $activeAllergies ?: null,
            'badgeTitle' => $activeAllergies . ' active ' . \Illuminate\Support\Str::plural('allergy', $activeAllergies),
        ],
        'billing' => [
            'label' => 'Payor & Charges',
            'icon' => 'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z',
            'alert' => $payorNeedsAttention,
        ],
        'care' => [
            'label' => 'Care Team',
            'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
            'badge' => $careTeamCount ?: null,
            'badgeTitle' => $careTeamCount . ' assigned',
        ],
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-4">
            <div>
                <h2 class="font-bold text-2xl text-gray-800 leading-tight">
                    {{ __('Patient Profile') }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    <a href="{{ route('patients.index') }}" class="hover:text-blue-600 transition-colors">Patients</a>
                    <span class="mx-1 text-gray-300">/</span>
                    <span class="text-gray-700">{{ $patient->name }}</span>
                </p>
            </div>
            <a href="{{ route('patients.index') }}"
                class="inline-flex items-center px-5 py-2.5 bg-gray-500 hover:bg-gray-600 border border-transparent rounded-lg font-semibold text-sm text-white shadow-lg hover:shadow-xl transition-all duration-200">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                {{ __('Back to Patients') }}
            </a>
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

            @if ($errors->any())
                <div class="bg-red-50 border-l-4 border-red-500 text-red-800 px-6 py-4 rounded-lg shadow-md" role="alert">
                    <span class="font-medium">Changes were not saved. Please correct the highlighted fields.</span>
                </div>
            @endif

            {{-- Patient banner with the key stay figures --}}
            <div class="bg-white/90 backdrop-blur-sm shadow-lg rounded-2xl border border-blue-100 overflow-hidden">
                <div class="h-1.5 bg-gradient-to-r from-blue-600 to-cyan-500"></div>
                <div class="p-6 flex flex-col md:flex-row md:items-center gap-5">
                    <div
                        class="w-16 h-16 shrink-0 rounded-2xl bg-gradient-to-br from-blue-600 to-cyan-500 text-white text-xl font-bold flex items-center justify-center shadow-md">
                        {{ $initials ?: '?' }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-2xl font-bold text-gray-900 break-words">{{ $patient->name }}</h1>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $statusClass }}">{{ $statusLabel }}</span>
                            @unless ($patient->is_active)
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800">Inactive Record</span>
                            @endunless
                        </div>
                        @if ($patient->alias_name)
                            <p class="mt-0.5 text-sm text-gray-500 italic">a.k.a. {{ $patient->alias_name }}</p>
                        @endif
                        <p class="mt-1 text-sm text-gray-500 font-mono break-words">
                            MRN {{ $patient->mrn }} &middot; RN {{ $patient->rn }} &middot; {{ $patient->ic_passport }}
                        </p>
                        <p class="mt-1 text-sm text-gray-600">
                            {{ $patient->gender ?? 'Gender not set' }}
                            &middot; {{ $patient->age !== null ? $patient->age . ' yrs' : 'Age not set' }}
                            @if ($patient->ward)
                                &middot; {{ $patient->ward->ward_name }}{{ $patient->bed_number ? ' / Bed ' . $patient->bed_number : '' }}
                            @endif
                        </p>
                        @if (count($coeIndicators))
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                @foreach ($coeIndicators as $coe)
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-violet-100 text-violet-800 border border-violet-200">
                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                        </svg>
                                        {{ $coe }}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="text-xs text-gray-400 md:text-right shrink-0">
                        Last updated<br>
                        <span class="text-gray-600 font-medium">{{ $patient->updated_at?->diffForHumans() ?? '—' }}</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-px bg-gray-100 border-t border-gray-100">
                    <div class="bg-white px-5 py-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Admission Date</p>
                        <p class="mt-1 text-lg font-bold text-gray-900">{{ $patient->admitted_at?->format('d M Y') ?? '—' }}</p>
                        <p class="text-xs text-gray-500">{{ $patient->admitted_at ? $patient->admitted_at->format('H:i') : 'Not admitted' }}</p>
                    </div>
                    <div class="bg-white px-5 py-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Length of Stay</p>
                        <p class="mt-1 text-lg font-bold text-gray-900">
                            @if ($summary['los_days'] !== null)
                                {{ $summary['los_days'] }} <span class="text-sm font-semibold text-gray-500">{{ \Illuminate\Support\Str::plural('day', $summary['los_days']) }}</span>
                                {{ $summary['los_hours'] }} <span class="text-sm font-semibold text-gray-500">hrs</span>
                            @else
                                &mdash;
                            @endif
                        </p>
                        <p class="text-xs text-gray-500">{{ $estimatedLos ? 'Estimated ' . $estimatedLos : 'No estimate' }}</p>
                    </div>
                    <div class="bg-white px-5 py-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Expected Discharge</p>
                        <p class="mt-1 text-lg font-bold text-gray-900">{{ $expected?->format('d M Y') ?? '—' }}</p>
                        @if ($expectedRelative)
                            <span class="inline-block mt-0.5 px-2 py-0.5 rounded-full text-xs font-semibold {{ $expectedRelativeClass }}">{{ $expectedRelative }}</span>
                        @else
                            <p class="text-xs text-gray-500">{{ $expected ? ($summary['expected_is_projected'] ? 'Projected from est. LOS' : $expected->format('H:i')) : 'Not set' }}</p>
                        @endif
                    </div>
                    <div class="bg-white px-5 py-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Payor / Insurance</p>
                        @if ($payorStatusLabel)
                            <span class="inline-block mt-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $payorStatusClass }}">{{ $payorStatusLabel }}</span>
                        @else
                            <p class="mt-1 text-lg font-bold text-gray-900">&mdash;</p>
                        @endif
                        <p class="mt-1 text-xs text-gray-500 truncate">{{ $patient->payor_name ?: ($payorTypeLabel ?? 'Not recorded') }}</p>
                    </div>
                    <div class="bg-white px-5 py-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Total Charges</p>
                        <p class="mt-1 text-lg font-bold text-gray-900">{{ $charges['total'] !== null ? $money($charges['total']) : '—' }}</p>
                        <p class="text-xs text-gray-500">
                            @if ($charges['balance'] === null)
                                Not recorded
                            @elseif ($charges['balance'] < 0)
                                Est. refund {{ $money(abs($charges['balance'])) }}
                            @else
                                Est. balance {{ $money($charges['balance']) }}
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            {{-- Tab bar + the sections it switches between --}}
            <div x-data="{
                    tab: @js($initialTab),
                    activeClass: 'bg-gradient-to-r from-blue-600 to-cyan-600 text-white shadow-md',
                    idleClass: 'text-gray-600 hover:bg-blue-50 hover:text-blue-700',
                    select(next) {
                        this.tab = next;
                        const url = new URL(window.location);
                        next === 'overview' ? url.searchParams.delete('tab') : url.searchParams.set('tab', next);
                        url.searchParams.delete('edit');
                        window.history.replaceState({}, '', url);
                    },
                }" class="space-y-6">

                <nav role="tablist" aria-label="Patient sections"
                    class="sticky top-0 z-20 flex gap-1 overflow-x-auto p-1.5 bg-white/95 backdrop-blur-sm shadow-lg rounded-2xl border border-blue-100">
                    @foreach ($tabs as $key => $meta)
                        <button type="button" role="tab" x-on:click="select(@js($key))"
                            :aria-selected="tab === @js($key)" :class="tab === @js($key) ? activeClass : idleClass"
                            class="inline-flex items-center gap-2 shrink-0 px-4 py-2.5 rounded-xl text-sm font-semibold transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $meta['icon'] }}" />
                            </svg>
                            {{ $meta['label'] }}
                            @if ($meta['badge'] ?? null)
                                <span title="{{ $meta['badgeTitle'] ?? '' }}"
                                    :class="tab === @js($key) ? 'bg-white/25 text-white' : 'bg-blue-100 text-blue-700'"
                                    class="px-1.5 py-0.5 rounded-full text-xs font-bold">{{ $meta['badge'] }}</span>
                            @elseif ($meta['alert'] ?? false)
                                <span title="Needs attention" class="w-2 h-2 rounded-full bg-amber-500"></span>
                            @endif
                        </button>
                    @endforeach
                </nav>

                <div class="{{ $gridClass }}"
                    :class="{
                        'grid grid-cols-1 lg:grid-cols-2 2xl:grid-cols-3 gap-6 items-start': tab === 'overview',
                        'grid grid-cols-1 lg:grid-cols-2 gap-6 items-start max-w-6xl mx-auto': tab === 'billing',
                        'grid grid-cols-1 gap-6 max-w-4xl mx-auto': !['overview', 'billing'].includes(tab),
                    }">

                {{-- Patient Details --}}
                <x-patient.section title="Patient Details" subtitle="Identity & contact" accent="blue" section="details"
                    :action="$updateUrl" :open="$openSection === 'details'"
                    x-show="{{ $showExpr('details') }}" style="{{ $hiddenStyle('details') }}">
                    <x-slot:icon>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </x-slot:icon>

                    <dl class="divide-y divide-gray-100">
                        <x-patient.field label="Full Name" :value="$patient->name" />
                        <x-patient.field label="Alias / Also Known As" :value="$patient->alias_name" />
                        <x-patient.field label="MRN" :value="$patient->mrn" mono />
                        <x-patient.field label="RN" :value="$patient->rn" mono />
                        <x-patient.field label="IC / Passport" :value="$patient->ic_passport" mono />
                        <x-patient.field label="Gender" :value="$patient->gender" />
                        <x-patient.field label="Age" :value="$patient->age !== null ? $patient->age . ' years' : null" />
                        <x-patient.field label="Date of Birth" :value="$patient->date_of_birth?->format('d M Y')" />
                        <x-patient.field label="Race" :value="$patient->race" />
                        <x-patient.field label="Religion" :value="$patient->religion" />
                        <x-patient.field label="Phone" :value="$patient->phone" />
                        <x-patient.field label="Address" :value="$address" />
                        <x-patient.field label="Record">
                            <span @class([
                                'px-2.5 py-0.5 rounded-full text-xs font-semibold',
                                'bg-green-100 text-green-800' => $patient->is_active,
                                'bg-red-100 text-red-800' => !$patient->is_active,
                            ])>{{ $patient->is_active ? 'Active' : 'Inactive' }}</span>
                        </x-patient.field>
                    </dl>

                    <x-slot:form>
                        <x-patient.input name="name" label="Full Name" :value="$patient->name" required />
                        <x-patient.input name="alias_name" label="Alias / Also Known As" :value="$patient->alias_name"
                            placeholder="Preferred or other name" />
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <x-patient.input name="mrn" label="MRN" :value="$patient->mrn" required />
                            <x-patient.input name="rn" label="RN" :value="$patient->rn" required />
                            <x-patient.input name="ic_passport" label="IC / Passport" :value="$patient->ic_passport" required />
                            <x-patient.input name="phone" label="Phone" :value="$patient->phone" required />
                            <x-patient.input name="gender" label="Gender" type="select" :value="$patient->gender"
                                :options="['Male' => 'Male', 'Female' => 'Female']" placeholder="Select gender" required />
                            <x-patient.input name="age" label="Age" type="number" :value="$patient->age" min="0" max="150" required />
                            <x-patient.input name="date_of_birth" label="Date of Birth" type="date"
                                :value="$patient->date_of_birth?->format('Y-m-d')" />
                            <x-patient.input name="race" label="Race" :value="$patient->race" />
                            <x-patient.input name="religion" label="Religion" :value="$patient->religion" />
                        </div>
                    </x-slot:form>
                </x-patient.section>

                {{-- Admission Details --}}
                <x-patient.section title="Admission Details" subtitle="Stay, ward & discharge plan" accent="cyan" section="admission"
                    :action="$updateUrl" :open="$openSection === 'admission'"
                    x-show="{{ $showExpr('admission') }}" style="{{ $hiddenStyle('admission') }}">
                    <x-slot:icon>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </x-slot:icon>

                    <dl class="divide-y divide-gray-100">
                        <x-patient.field label="Status">
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $statusClass }}">{{ $statusLabel }}</span>
                        </x-patient.field>
                        <x-patient.field label="Ward" :value="$patient->ward?->ward_name" />
                        <x-patient.field label="Bed" :value="$patient->bed_number" />
                        <x-patient.field label="Visit No." :value="$patient->visit_number" mono />
                        <x-patient.field label="Patient Class" :value="$patient->patient_class" />
                        <x-patient.field label="Prebooked" :value="$patient->booked_at?->format('d M Y, H:i')" />
                        <x-patient.field label="Admission Date" :value="$patient->admitted_at?->format('d M Y, H:i')" />
                        <x-patient.field label="Length of Stay" :value="$los" />
                        <x-patient.field label="Est. Length of Stay" :value="$estimatedLos" />
                        <x-patient.field label="Expected Discharge">
                            @if ($expected)
                                <span>{{ $expected->format($summary['expected_is_projected'] ? 'd M Y' : 'd M Y, H:i') }}</span>
                                @if ($summary['expected_is_projected'])
                                    <span class="block text-xs font-normal text-gray-400">Projected from est. LOS</span>
                                @endif
                                @if ($expectedRelative)
                                    <span class="block mt-1"><span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $expectedRelativeClass }}">{{ $expectedRelative }}</span></span>
                                @endif
                            @endif
                        </x-patient.field>
                        @if ($patient->pending_discharge_at)
                            <x-patient.field label="Pending Discharge Since" :value="$patient->pending_discharge_at->format('d M Y, H:i')" />
                        @endif
                        @if ($patient->discharged_at)
                            <x-patient.field label="Discharged" :value="$patient->discharged_at->format('d M Y, H:i')" />
                        @endif
                    </dl>

                    @php
                        $timeline = collect([
                            ['Prebooked', $patient->booked_at, 'bg-amber-500'],
                            ['Admitted', $patient->admitted_at, 'bg-green-500'],
                            ['Discharge planned', $patient->pending_discharge_at, 'bg-orange-500'],
                            ['Discharged', $patient->discharged_at, 'bg-gray-400'],
                        ])->filter(fn ($step) => $step[1] !== null);
                    @endphp
                    @if ($timeline->isNotEmpty())
                        <div class="mt-4 pt-4 border-t border-gray-100">
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-3">Timeline</p>
                            <ol class="relative border-l border-gray-200 ml-1.5 space-y-3">
                                @foreach ($timeline as [$label, $moment, $dotClass])
                                    <li class="ml-4">
                                        <span class="absolute -left-[5px] mt-1.5 w-2.5 h-2.5 rounded-full {{ $dotClass }}"></span>
                                        <p class="text-sm font-semibold text-gray-800">{{ $label }}</p>
                                        <p class="text-xs text-gray-500">{{ $moment->format('d M Y, H:i') }} &middot; {{ $moment->diffForHumans() }}</p>
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    @endif

                    <p class="mt-3 text-xs text-gray-400">Admission, transfer and discharge are handled from the Ward Dashboard.</p>

                    <x-slot:form>
                        <x-patient.input name="expected_discharge_at" label="Expected Discharge" type="datetime-local"
                            :value="$patient->expected_discharge_at?->format('Y-m-d\TH:i')" />
                        <x-patient.input name="estimated_length_of_stay" label="Estimated Length of Stay (days)" type="number"
                            :value="$patient->estimated_length_of_stay" min="0" max="3650" />
                        <p class="text-xs text-gray-400">Admission date, ward and bed are managed from the Ward Dashboard.</p>
                    </x-slot:form>
                </x-patient.section>

                {{-- Medical Info --}}
                <x-patient.section title="Medical Info" subtitle="COE programmes & clinical indicators" accent="rose" section="medical"
                    :action="$updateUrl" :open="$openSection === 'medical'"
                    x-show="{{ $showExpr('medical') }}" style="{{ $hiddenStyle('medical') }}">
                    <x-slot:icon>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                    </x-slot:icon>

                    <div class="space-y-4">
                        <div>
                            <p class="text-sm text-gray-500 mb-2">COE Indicators</p>
                            @forelse ($coeIndicators as $coe)
                                <span class="inline-flex items-center mr-1 mb-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-violet-100 text-violet-800 border border-violet-200">{{ $coe }}</span>
                            @empty
                                <p class="text-sm text-gray-400 italic">No COE programme</p>
                            @endforelse
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 mb-2">Allergies</p>
                            @forelse ($allergies as $allergy)
                                <span @class([
                                    'inline-flex items-center mr-1 mb-1 px-2.5 py-1 rounded-full text-xs font-semibold',
                                    'bg-red-100 text-red-800' => !$allergy['resolved'],
                                    'bg-gray-100 text-gray-500 line-through' => $allergy['resolved'],
                                ])>{{ $allergy['name'] }}</span>
                            @empty
                                <p class="text-sm text-gray-400 italic">No allergies recorded</p>
                            @endforelse
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 mb-2">Diet</p>
                            @forelse ($diets as $diet)
                                <span class="inline-flex items-center mr-1 mb-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-orange-100 text-orange-800">{{ $diet }}</span>
                            @empty
                                <p class="text-sm text-gray-400 italic">No diet recorded</p>
                            @endforelse
                            @foreach ($patient->feeding_routes ?? [] as $route)
                                <span class="inline-flex items-center mr-1 mb-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                                    {{ \App\Models\Patient::FEEDING_ROUTES[$route] ?? strtoupper($route) }}
                                </span>
                            @endforeach
                            @if ($patient->diet_orders)
                                <p class="mt-1 text-sm text-gray-700"><span class="font-semibold">Orders:</span> {{ $patient->diet_orders }}</p>
                            @endif
                        </div>
                        <dl class="divide-y divide-gray-100 border-t border-gray-100">
                            <x-patient.field label="Nursing Level">
                                @if ($patient->nursing_level && $patient->nursing_level !== 'none')
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $nursingStyles[$patient->nursing_level] ?? 'bg-gray-100 text-gray-700' }}">
                                        {{ \Illuminate\Support\Str::headline($patient->nursing_level) }}
                                    </span>
                                @endif
                            </x-patient.field>
                            <x-patient.field label="Fall Risk">
                                @if ($patient->fall_risk && $patient->fall_risk !== 'none')
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $fallRiskStyles[$patient->fall_risk] ?? 'bg-gray-100 text-gray-700' }}">
                                        {{ $patient->fall_risk === 'alert_active' ? 'FR Alert Active' : \Illuminate\Support\Str::headline($patient->fall_risk) }}
                                    </span>
                                @endif
                            </x-patient.field>
                            <x-patient.field label="Isolation">
                                @if ($hasIsolation)
                                    <span @class([
                                        'px-2.5 py-0.5 rounded-full text-xs font-semibold',
                                        'bg-red-100 text-red-700' => $isolationCritical,
                                        'bg-yellow-100 text-yellow-700' => !$isolationCritical,
                                    ])>{{ \App\Models\IsolationType::getDisplayName($patient->isolation_type) }}</span>
                                @endif
                            </x-patient.field>
                            <x-patient.field label="Glucose (HGT) Monitoring"
                                :value="$patient->hgt_enabled ? 'On' . ($patient->hgt_frequency ? ' · ' . ($hgtFrequencies[$patient->hgt_frequency] ?? strtoupper($patient->hgt_frequency)) : '') : 'Off'" />
                        </dl>
                    </div>

                    <x-slot:form>
                        <div x-data="{
                                initial: @js(array_values($selectedCoe)),
                                selected: @js(array_values($selectedCoe)),
                                options: @js($coePickerOptions),
                                custom: '',
                                toggle(item) {
                                    this.selected = this.selected.includes(item)
                                        ? this.selected.filter(i => i !== item)
                                        : [...this.selected, item];
                                },
                                add() {
                                    const item = this.custom.trim();
                                    if (!item) return;
                                    if (!this.options.includes(item)) this.options.push(item);
                                    if (!this.selected.includes(item)) this.selected.push(item);
                                    this.custom = '';
                                },
                            }"
                            x-on:section-reset.window="if ($event.detail === 'medical') { selected = [...initial]; custom = ''; }">
                            <p class="block text-xs font-semibold text-gray-600 mb-2">COE Indicators</p>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="item in options" :key="item">
                                    <button type="button" @click="toggle(item)"
                                        :class="selected.includes(item)
                                            ? 'bg-violet-600 border-violet-600 text-white'
                                            : 'bg-white border-gray-300 text-gray-700 hover:border-violet-400'"
                                        class="px-3 py-1.5 rounded-full border text-xs font-semibold transition-colors"
                                        x-text="item"></button>
                                </template>
                            </div>
                            <div class="flex gap-2 mt-3">
                                <input type="text" x-model="custom" @keydown.enter.prevent="add()" maxlength="100"
                                    placeholder="Add another COE programme…"
                                    class="flex-1 min-w-0 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <button type="button" @click="add()"
                                    class="px-3 py-2 bg-violet-50 hover:bg-violet-100 text-violet-700 rounded-lg text-sm font-semibold transition-colors">
                                    Add
                                </button>
                            </div>
                            <template x-for="item in selected" :key="'selected-' + item">
                                <input type="hidden" name="coe_indicators[]" :value="item">
                            </template>
                            @error('coe_indicators')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                            @error('coe_indicators.*')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <p class="text-xs text-gray-400">Allergies, diet and other clinical indicators are updated from the Ward Dashboard / ADT.</p>
                    </x-slot:form>
                </x-patient.section>

                {{-- Payor / Insurance --}}
                <x-patient.section title="Payor / Insurance" subtitle="Coverage & guarantee letter" accent="violet" section="payor"
                    :action="$updateUrl" :open="$openSection === 'payor'"
                    x-show="{{ $showExpr('payor') }}" style="{{ $hiddenStyle('payor') }}">
                    <x-slot:icon>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </x-slot:icon>

                    <dl class="divide-y divide-gray-100">
                        <x-patient.field label="Status">
                            @if ($payorStatusLabel)
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $payorStatusClass }}">{{ $payorStatusLabel }}</span>
                            @endif
                        </x-patient.field>
                        <x-patient.field label="Payor Type" :value="$payorTypeLabel" />
                        <x-patient.field label="Payor / Insurer" :value="$patient->payor_name" />
                        <x-patient.field label="Policy / Member No." :value="$patient->payor_policy_number" mono />
                        <x-patient.field label="GL Reference No." :value="$patient->payor_gl_number" mono />
                        <x-patient.field label="GL Amount" :value="$patient->payor_gl_amount !== null ? $money($patient->payor_gl_amount) : null" />
                    </dl>
                    @if ($patient->payor_remarks)
                        <div class="mt-3 p-3 rounded-lg bg-gray-50 border border-gray-100">
                            <p class="text-xs font-semibold text-gray-500 mb-1">Remarks</p>
                            <p class="text-sm text-gray-700 whitespace-pre-line">{{ $patient->payor_remarks }}</p>
                        </div>
                    @endif

                    <x-slot:form>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <x-patient.input name="payor_type" label="Payor Type" type="select" :value="$patient->payor_type"
                                :options="\App\Models\Patient::PAYOR_TYPES" />
                            <x-patient.input name="payor_status" label="Status" type="select" :value="$patient->payor_status"
                                :options="\App\Models\Patient::PAYOR_STATUSES" />
                        </div>
                        <x-patient.input name="payor_name" label="Payor / Insurer" :value="$patient->payor_name"
                            placeholder="e.g. insurer, company or agency name" />
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <x-patient.input name="payor_policy_number" label="Policy / Member No." :value="$patient->payor_policy_number" />
                            <x-patient.input name="payor_gl_number" label="GL Reference No." :value="$patient->payor_gl_number" />
                        </div>
                        <x-patient.input name="payor_gl_amount" label="GL Amount (RM)" type="number" :value="$patient->payor_gl_amount"
                            step="0.01" min="0" />
                        <x-patient.input name="payor_remarks" label="Remarks" type="textarea" :value="$patient->payor_remarks" />
                    </x-slot:form>
                </x-patient.section>

                {{-- Charges --}}
                <x-patient.section title="Charges" subtitle="Running bill summary" accent="emerald" section="charges"
                    :action="$updateUrl" :open="$openSection === 'charges'"
                    x-show="{{ $showExpr('charges') }}" style="{{ $hiddenStyle('charges') }}">
                    <x-slot:icon>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                    </x-slot:icon>

                    @if ($charges['total'] === null && $patient->deposit_paid === null)
                        <div class="py-6 text-center">
                            <p class="text-sm text-gray-500 font-medium">No charges recorded</p>
                            <p class="text-xs text-gray-400 mt-1">Use Edit to enter the current bill and deposit.</p>
                        </div>
                    @else
                        <div class="rounded-xl bg-gradient-to-br from-emerald-50 to-teal-50 border border-emerald-100 px-4 py-3">
                            <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Total Charges</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $charges['total'] !== null ? $money($charges['total']) : '—' }}</p>
                            @if ($patient->charges_updated_at)
                                <p class="text-xs text-gray-500">Updated {{ $patient->charges_updated_at->format('d M Y, H:i') }}</p>
                            @endif
                        </div>

                        <dl class="mt-2 divide-y divide-gray-100">
                            <x-patient.field label="Deposit Paid" :value="$money($charges['deposit'])" />
                            <x-patient.field label="GL Coverage">
                                @if ($charges['gl_amount'] !== null)
                                    @if ($charges['gl_approved'])
                                        {{ $money($charges['coverage']) }}
                                    @else
                                        <span class="font-normal text-gray-500">Not applied ({{ $payorStatusLabel ?? 'GL not approved' }})</span>
                                    @endif
                                @endif
                            </x-patient.field>
                            @if ($charges['balance'] !== null)
                                <x-patient.field :label="$charges['balance'] < 0 ? 'Est. Refund Due' : 'Est. Patient Balance'">
                                    <span @class([
                                        'text-amber-700' => $charges['balance'] > 0,
                                        'text-emerald-700' => $charges['balance'] <= 0,
                                    ])>{{ $money(abs($charges['balance'])) }}</span>
                                </x-patient.field>
                            @endif
                            <x-patient.field label="Average per Day" :value="$charges['per_day'] !== null ? $money($charges['per_day']) : null" />
                        </dl>

                        @if ($charges['gl_usage_pct'] !== null)
                            @php
                                $pct = $charges['gl_usage_pct'];
                                $barClass = $pct > 100 ? 'bg-red-500' : ($pct >= 80 ? 'bg-amber-500' : 'bg-emerald-500');
                            @endphp
                            <div class="mt-3">
                                <div class="flex justify-between text-xs font-semibold text-gray-600 mb-1.5">
                                    <span>GL utilisation</span>
                                    <span>{{ $pct }}%</span>
                                </div>
                                <div class="h-2.5 rounded-full bg-gray-100 overflow-hidden">
                                    <div class="h-full rounded-full {{ $barClass }}" style="width: {{ min(100, $pct) }}%"></div>
                                </div>
                                @if ($pct > 100)
                                    <p class="mt-1.5 text-xs font-medium text-red-600">
                                        Charges exceed the GL amount by {{ $money($charges['total'] - $charges['gl_amount']) }}.
                                    </p>
                                @endif
                            </div>
                        @endif
                    @endif

                    <x-slot:form>
                        <x-patient.input name="total_charges" label="Total Charges (RM)" type="number" :value="$patient->total_charges"
                            step="0.01" min="0" hint="Current running bill for this admission." />
                        <x-patient.input name="deposit_paid" label="Deposit Paid (RM)" type="number" :value="$patient->deposit_paid"
                            step="0.01" min="0" />
                        <p class="text-xs text-gray-400">The GL amount and status are set under Payor / Insurance.</p>
                    </x-slot:form>
                </x-patient.section>

                {{-- Care Team --}}
                <x-patient.section title="Care Team" subtitle="Doctors & nursing" accent="amber"
                    x-show="{{ $showExpr('care') }}" style="{{ $hiddenStyle('care') }}">
                    <x-slot:icon>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </x-slot:icon>

                    @if (!$patient->consultant && $careProviders->isEmpty() && !$patient->anaesthetist && !$nurse)
                        <div class="py-6 text-center">
                            <p class="text-sm text-gray-500 font-medium">No care team assigned</p>
                        </div>
                    @else
                        <ul class="divide-y divide-gray-100">
                            @if ($patient->consultant)
                                <li class="py-2.5 flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-800">{{ $patient->consultant->name }}</p>
                                        <p class="text-xs text-gray-500">{{ $patient->consultant->specialty?->name ?? 'Consultant' }}</p>
                                    </div>
                                    <span class="shrink-0 px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">Primary Consultant</span>
                                </li>
                            @endif
                            @foreach ($careProviders as $provider)
                                <li class="py-2.5 flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-800">{{ $provider->display_name }}</p>
                                        <p class="text-xs text-gray-500">{{ $provider->specialty ?? 'From ADT' }}</p>
                                    </div>
                                    <span class="shrink-0 px-2 py-0.5 rounded-full text-xs font-semibold {{ $provider->role_badge_class }}">{{ $provider->role_label }}</span>
                                </li>
                            @endforeach
                            @if ($patient->anaesthetist)
                                <li class="py-2.5 flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-800">{{ $patient->anaesthetist->name }}</p>
                                        <p class="text-xs text-gray-500">Anaesthesiology</p>
                                    </div>
                                    <span class="shrink-0 px-2 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-800">Anaesthetist</span>
                                </li>
                            @endif
                            @if ($nurse)
                                <li class="py-2.5 flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-800">{{ $nurse->name }}</p>
                                        <p class="text-xs text-gray-500">Nursing</p>
                                    </div>
                                    <span class="shrink-0 px-2 py-0.5 rounded-full text-xs font-semibold bg-teal-100 text-teal-800">Nurse</span>
                                </li>
                            @endif
                        </ul>
                    @endif
                </x-patient.section>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
