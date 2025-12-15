<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Details</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50">
    @php
        use App\Models\DietType;
        use App\Models\IsolationType;
        use App\Models\PatientCareProvider;
        
        $patientTabs = $patientDetailsTabs ?? [
            'info' => true,
            'additional' => true,
            'vitals' => true,
            'movement' => true,
            'careprovider' => true,
            'infusion' => true,
            'transfer' => true,
            'discharge' => true,
        ];
        
        // Map old 'referral' key to 'careprovider' for backwards compatibility
        if (isset($patientTabs['referral'])) {
            $patientTabs['careprovider'] = $patientTabs['referral'];
            unset($patientTabs['referral']);
        }
        
        // Get display names from database tables for clinical status summary
        $dietTypesArray = $patient && $patient->diet_types ? $patient->diet_types : [];
        $dietTypeDisplay = count($dietTypesArray) > 0 
            ? collect($dietTypesArray)->map(fn($dt) => DietType::getDisplayName($dt))->implode(', ')
            : 'Regular diet';
        $isolationTypeDisplay = $patient && $patient->isolation_type && $patient->isolation_type !== 'none'
            ? IsolationType::getDisplayName($patient->isolation_type) 
            : 'None';
        
        // Get care providers from ADT (grouped by role)
        $attendingDoctors = $patient ? $patient->activeCareProviders()->where('role', PatientCareProvider::ROLE_ATTENDING)->get() : collect();
        $referringDoctors = $patient ? $patient->activeCareProviders()->where('role', PatientCareProvider::ROLE_REFERRING)->get() : collect();
        $consultingDoctors = $patient ? $patient->activeCareProviders()->where('role', PatientCareProvider::ROLE_CONSULTING)->get() : collect();
    @endphp
    <div class="p-4" x-data='@json([
        "activeTab" => $activeTab ?? "info",
        "patientTabs" => $patientTabs,
    ])'>
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-xl font-bold text-gray-800">Patient Details</h2>
                @if($patient)
                    <p class="text-sm text-gray-600 mt-1">
                        {{ $patient->name }} 
                        <span class="text-gray-400 mx-1">•</span>
                        MRN: <span class="font-semibold">{{ $patient->mrn }}</span>
                        @if($patient->bed_number)
                            <span class="text-gray-400 mx-1">•</span>
                            Bed: <span class="font-semibold">{{ $patient->bed_number }}</span>
                        @endif
                        @if($patient->ward)
                            <span class="text-gray-400 mx-1">•</span>
                            Ward: <span class="font-semibold">{{ $patient->ward->ward_name }}</span>
                        @endif
                    </p>
                @else
                    <p class="text-sm text-red-500 mt-1">Patient not found or inactive.</p>
                @endif
            </div>
        </div>

        <!-- Success/Error Notifications inside iframe -->
        @if(session('success'))
            <div x-data="{ show: true }"
                 x-show="show"
                 x-init="setTimeout(() => show = false, 4000)"
                 class="fixed top-4 right-4 z-50 bg-green-500 text-white px-4 py-3 rounded-lg shadow-lg text-sm">
                <div class="flex items-center space-x-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>{{ session('success') }}</span>
                </div>
            </div>
        @endif

        @if(session('error'))
            <div x-data="{ show: true }"
                 x-show="show"
                 x-init="setTimeout(() => show = false, 5000)"
                 class="fixed top-4 right-4 z-50 bg-red-500 text-white px-4 py-3 rounded-lg shadow-lg text-sm">
                <div class="flex items-center space-x-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>{{ session('error') }}</span>
                </div>
            </div>
        @endif

        @if($patient)
            <!-- Tabs -->
            <div class="border-b border-gray-200 mb-4">
                <nav class="-mb-px flex flex-wrap space-x-4 text-sm" aria-label="Tabs">
                    <button type="button"
                        @click="activeTab = 'info'"
                        x-show="patientTabs.info"
                        :class="activeTab === 'info' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-2 px-3 border-b-2 font-medium">
                        Patient Info
                    </button>
                    <button type="button"
                        @click="activeTab = 'additional'"
                        x-show="patientTabs.additional"
                        :class="activeTab === 'additional' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-2 px-3 border-b-2 font-medium">
                        Patient Additional Info
                    </button>
                    <button type="button"
                        @click="activeTab = 'vitals'"
                        x-show="patientTabs.vitals"
                        :class="activeTab === 'vitals' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-2 px-3 border-b-2 font-medium">
                        Vital Signs
                    </button>
                    <button type="button"
                        @click="activeTab = 'movement'"
                        x-show="patientTabs.movement"
                        :class="activeTab === 'movement' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-2 px-3 border-b-2 font-medium">
                        Patient Movement
                    </button>
                    <button type="button"
                        @click="activeTab = 'careprovider'"
                        x-show="patientTabs.careprovider"
                        :class="activeTab === 'careprovider' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-2 px-3 border-b-2 font-medium">
                        Care Provider
                    </button>
                    <button type="button"
                        @click="activeTab = 'infusion'"
                        x-show="patientTabs.infusion"
                        :class="activeTab === 'infusion' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-2 px-3 border-b-2 font-medium">
                        Infusion Management
                    </button>
                    <button type="button"
                        @click="activeTab = 'transfer'"
                        x-show="patientTabs.transfer"
                        :class="activeTab === 'transfer' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-2 px-3 border-b-2 font-medium">
                        Transfer Bed
                    </button>
                    <button type="button"
                        @click="activeTab = 'discharge'"
                        x-show="patientTabs.discharge"
                        :class="activeTab === 'discharge' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-2 px-3 border-b-2 font-medium">
                        Discharge
                    </button>
                </nav>
            </div>

            <!-- Tab Panels -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <!-- Patient Info -->
                <div x-show="activeTab === 'info'" x-cloak>
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Patient Info</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <div class="text-gray-500">Name</div>
                            <div class="font-medium text-gray-900">{{ $patient->name }}</div>
                        </div>
                        <div>
                            <div class="text-gray-500">MRN</div>
                            <div class="font-medium text-gray-900">{{ $patient->mrn }}</div>
                        </div>
                        <div>
                            <div class="text-gray-500">Gender</div>
                            <div class="font-medium text-gray-900">{{ $patient->gender ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-gray-500">Age</div>
                            <div class="font-medium text-gray-900">{{ $patient->age ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-gray-500">Ward</div>
                            <div class="font-medium text-gray-900">{{ $patient->ward->ward_name ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-gray-500">Bed</div>
                            <div class="font-medium text-gray-900">{{ $patient->bed_number ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-gray-500">Consultant</div>
                            <div class="font-medium text-gray-900">{{ $patient->consultant->name ?? 'Not Assigned' }}</div>
                        </div>
                        <div>
                            <div class="text-gray-500">Nurse</div>
                            <div class="font-medium text-gray-900">{{ $patient->nurse->name ?? 'Not Assigned' }}</div>
                        </div>
                        <div>
                            <div class="text-gray-500">Anaesthetist</div>
                            <div class="font-medium text-gray-900">{{ $patient->anaesthetist->name ?? 'Not Assigned' }}</div>
                        </div>
                        <div>
                            <div class="text-gray-500">Status</div>
                            <div class="font-medium text-gray-900 capitalize">{{ $patient->status ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-gray-500">Admitted At</div>
                            <div class="font-medium text-gray-900">
                                {{ $patient->admitted_at ? $patient->admitted_at->format('Y-m-d H:i') : '-' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-gray-500">Prebooked At</div>
                            <div class="font-medium text-gray-900">
                                {{ $patient->booked_at ? $patient->booked_at->format('Y-m-d H:i') : '-' }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Patient Additional Info - Clinical Indicators -->
                @php
                    // Normalize allergies - convert object array to string array for UI
                    $allergyStrings = collect($patient->allergies ?? [])->map(function($a) {
                        if (is_array($a)) {
                            return $a['allergen'] ?? $a['allergen_code'] ?? json_encode($a);
                        }
                        return $a;
                    })->values()->toArray();
                @endphp
                <div x-show="activeTab === 'additional'" x-cloak
                     x-data="{
                        allergies: @json($allergyStrings)
                     }">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Patient Additional Info</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        View clinical indicators and patient care information. Diet types and allergies are managed by the ADT system.
                    </p>

                    <form method="POST" action="{{ route('ward.update-patient-clinical') }}" class="space-y-6">
                        @csrf
                        <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                        <input type="hidden" name="active_tab" value="additional">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Nursing Level of Care -->
                            <div>
                                <label for="nursing_level" class="block text-sm font-semibold text-gray-700 mb-2">
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 mr-2 text-purple-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"/>
                                        </svg>
                                        Nursing Level of Care
                                    </div>
                                </label>
                                <select id="nursing_level" name="nursing_level" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                    @foreach($clinicalIndicatorOptions['nursing_level'] ?? [] as $option)
                                        <option value="{{ $option['value'] }}" {{ ($patient->nursing_level ?? 'none') === $option['value'] ? 'selected' : '' }}>
                                            {{ $option['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-500">Patient care level classification</p>
                            </div>

                            <!-- Diet Types (Read-only from ADT) -->
                            <div class="md:col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 mr-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                        </svg>
                                        Diet Types
                                        <span class="ml-2 text-xs font-normal text-gray-400">(from ADT)</span>
                                    </div>
                                </label>
                                <p class="text-xs text-gray-500 mb-2">Diet types are managed by ADT system</p>
                                <div class="flex flex-wrap gap-2">
                                    @php
                                        $currentDietTypes = $patient->diet_types ?? [];
                                    @endphp
                                    @if(count($currentDietTypes) > 0)
                                        @foreach($currentDietTypes as $dietCode)
                                            <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium bg-cyan-100 text-cyan-800 border border-cyan-200">
                                                <svg class="w-3 h-3 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                {{ \App\Models\DietType::getDisplayName($dietCode) }}
                                            </span>
                                        @endforeach
                                    @else
                                        <span class="text-sm text-gray-400 italic">No diet types specified (Regular diet)</span>
                                    @endif
                                </div>
                            </div>

                            <!-- Fall Risk Alert -->
                            <div>
                                <label for="fall_risk" class="block text-sm font-semibold text-gray-700 mb-2">
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 mr-2 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                        </svg>
                                        Fall Risk Alert
                                    </div>
                                </label>
                                <select id="fall_risk" name="fall_risk" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                    @foreach($clinicalIndicatorOptions['fall_risk'] ?? [] as $option)
                                        <option value="{{ $option['value'] }}" {{ ($patient->fall_risk ?? 'none') === $option['value'] ? 'selected' : '' }}>
                                            {{ $option['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-500">Patient fall risk assessment</p>
                            </div>

                            <!-- Isolation Precautions -->
                            <div>
                                <label for="isolation_type" class="block text-sm font-semibold text-gray-700 mb-2">
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 mr-2 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                        </svg>
                                        Isolation Precautions
                                    </div>
                                </label>
                                <select id="isolation_type" name="isolation_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                    @foreach($clinicalIndicatorOptions['isolation_type'] ?? [] as $option)
                                        <option value="{{ $option['value'] }}" {{ ($patient->isolation_type ?? 'none') === $option['value'] ? 'selected' : '' }}>
                                            {{ $option['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-500">Infection control measures</p>
                            </div>
                        </div>

                        <!-- Medical Allergies (Read-only from ADT) -->
                        <div class="border-t pt-6">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 mr-2 text-pink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                    </svg>
                                    Medical Allergies
                                    <span class="ml-2 text-xs font-normal text-gray-400">(from ADT)</span>
                                </div>
                            </label>
                            <p class="text-xs text-gray-500 mb-3">Allergies are managed by ADT system</p>
                            
                            <!-- Allergies List (Read-only) -->
                            <div class="flex flex-wrap gap-2" x-show="allergies.length > 0">
                                <template x-for="(allergy, index) in allergies" :key="index">
                                    <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium bg-pink-100 text-pink-800 border border-pink-200">
                                        <svg class="w-3 h-3 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                        </svg>
                                        <span x-text="allergy"></span>
                                    </span>
                                </template>
                            </div>
                            <p x-show="allergies.length === 0" class="text-sm text-gray-400 italic">No allergies recorded</p>
                        </div>

                        <!-- Patient Status -->
                        @if($patient->status === 'pending_discharge' || $patient->pending_discharge_at)
                        <div class="border-t pt-6">
                            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                <div class="flex items-center">
                                    <svg class="w-6 h-6 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                    </svg>
                                    <div>
                                        <h4 class="text-sm font-bold text-green-800">Pending Discharge</h4>
                                        <p class="text-xs text-green-700">
                                            Patient is awaiting discharge
                                            @if($patient->pending_discharge_at)
                                                since {{ $patient->pending_discharge_at->format('d M Y, H:i') }}
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Current Clinical Status Summary -->
                        <div class="border-t pt-6">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3">Current Clinical Status</h4>
                            @php
                                $nursingLevelNum = ['level_1' => '1', 'level_2' => '2', 'level_3' => '3', 'level_4' => '4'];
                                $nursingLevelColors = [
                                    'level_1' => 'bg-green-100 border-green-300 text-green-700',
                                    'level_2' => 'bg-blue-100 border-blue-300 text-blue-700',
                                    'level_3' => 'bg-yellow-100 border-yellow-300 text-yellow-700',
                                    'level_4' => 'bg-red-100 border-red-300 text-red-700',
                                ];
                                $fallRiskNum = ['low' => '1', 'moderate' => '2', 'high' => '3', 'alert_active' => '4'];
                                $fallRiskColors = [
                                    'low' => 'bg-green-100 border-green-300 text-green-700',
                                    'moderate' => 'bg-yellow-100 border-yellow-300 text-yellow-700',
                                    'high' => 'bg-orange-100 border-orange-300 text-orange-700',
                                    'alert_active' => 'bg-red-100 border-red-300 text-red-700',
                                ];
                                $hasNbm = $patient->diet_types && collect($patient->diet_types)->map(fn($dt) => strtoupper($dt))->intersect(['NPO', 'NBM', 'NPD'])->isNotEmpty();
                            @endphp
                            <div class="grid grid-cols-2 md:grid-cols-6 gap-3 text-xs">
                                {{-- Status --}}
                                <div class="rounded-lg p-3 text-center border-2 {{ ($patient->status === 'pending_discharge' || $patient->pending_discharge_at) ? 'bg-green-100 border-green-300' : 'bg-gray-50 border-gray-200' }}">
                                    <div class="flex justify-center mb-1">
                                        <svg class="w-5 h-5 {{ ($patient->status === 'pending_discharge' || $patient->pending_discharge_at) ? 'text-green-600' : 'text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                        </svg>
                                    </div>
                                    <div class="{{ ($patient->status === 'pending_discharge' || $patient->pending_discharge_at) ? 'text-green-700' : 'text-gray-600' }} font-semibold text-[10px]">Status</div>
                                    <div class="text-gray-800 mt-0.5 capitalize font-bold">
                                        @if($patient->status === 'pending_discharge' || $patient->pending_discharge_at)
                                            Pending DC
                                        @else
                                            {{ str_replace('_', ' ', $patient->status ?? 'Unknown') }}
                                        @endif
                                    </div>
                                </div>

                                {{-- Nursing Level with icon and number --}}
                                @php $nl = $patient->nursing_level ?? 'none'; @endphp
                                <div class="rounded-lg p-3 text-center border-2 {{ $nursingLevelColors[$nl] ?? 'bg-gray-50 border-gray-200' }}">
                                    <div class="flex justify-center mb-1">
                                        <svg class="w-5 h-5 {{ isset($nursingLevelColors[$nl]) ? 'text-current' : 'text-gray-500' }}" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                        </svg>
                                    </div>
                                    <div class="font-semibold text-[10px]">Nursing Level</div>
                                    <div class="text-gray-800 mt-0.5 font-bold text-lg">{{ $nursingLevelNum[$nl] ?? '-' }}</div>
                                </div>

                                {{-- Diet - NBM indicator --}}
                                <div class="rounded-lg p-3 text-center border-2 {{ $hasNbm ? 'bg-red-100 border-red-300' : 'bg-cyan-50 border-cyan-200' }}">
                                    <div class="flex justify-center mb-1">
                                        @if($hasNbm)
                                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path d="M3 3v6c0 1 1 2 2 2h3c1 0 2-1 2-2V3M6 3v18"/>
                                            <line x1="2" y1="2" x2="22" y2="22" stroke-width="3"/>
                                            <path d="M15 3h4v6a3 3 0 01-3 3h-1M17 12v9"/>
                                        </svg>
                                        @else
                                        <svg class="w-5 h-5 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path d="M3 3v6c0 1 1 2 2 2h3c1 0 2-1 2-2V3M6 3v18M15 3h4v6a3 3 0 01-3 3h-1M17 12v9"/>
                                        </svg>
                                        @endif
                                    </div>
                                    <div class="{{ $hasNbm ? 'text-red-700' : 'text-cyan-700' }} font-semibold text-[10px]">Diet</div>
                                    <div class="text-gray-800 mt-0.5 capitalize text-[11px] font-medium">
                                        @if($hasNbm)
                                            <span class="text-red-700 font-bold">NBM</span>
                                        @else
                                            {{ \Illuminate\Support\Str::limit($dietTypeDisplay, 15) }}
                                        @endif
                                    </div>
                                </div>

                                {{-- Fall Risk with icon and number --}}
                                @php $fr = $patient->fall_risk ?? 'none'; @endphp
                                <div class="rounded-lg p-3 text-center border-2 {{ $fallRiskColors[$fr] ?? 'bg-gray-50 border-gray-200' }}">
                                    <div class="flex justify-center mb-1">
                                        <svg class="w-5 h-5 {{ isset($fallRiskColors[$fr]) ? 'text-current' : 'text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <circle cx="17" cy="4" r="2"/>
                                            <path d="M15 8l-3 4 4 2.5M12 12l-3.5-1.5M17 14.5l1.5 4.5M14 14.5l-2 5.5"/>
                                            <path d="M3 20h18" stroke-width="1.5"/>
                                        </svg>
                                    </div>
                                    <div class="font-semibold text-[10px]">Fall Risk</div>
                                    <div class="text-gray-800 mt-0.5 font-bold text-lg">{{ $fallRiskNum[$fr] ?? '-' }}</div>
                                </div>

                                {{-- Isolation with virus icon --}}
                                @php 
                                    $hasIsolation = $patient->isolation_type && $patient->isolation_type !== 'none';
                                    $criticalIsolations = ['covid', 'tb', 'airborne', 'COVID', 'TB', 'AIR'];
                                    $isCritical = $hasIsolation && (in_array($patient->isolation_type, $criticalIsolations) || in_array(strtoupper($patient->isolation_type), $criticalIsolations));
                                @endphp
                                <div class="rounded-lg p-3 text-center border-2 {{ $hasIsolation ? ($isCritical ? 'bg-red-100 border-red-300' : 'bg-yellow-100 border-yellow-300') : 'bg-gray-50 border-gray-200' }}">
                                    <div class="flex justify-center mb-1">
                                        <svg class="w-5 h-5 {{ $hasIsolation ? ($isCritical ? 'text-red-600' : 'text-yellow-600') : 'text-gray-400' }}" fill="currentColor" viewBox="0 0 24 24">
                                            <circle cx="12" cy="12" r="4"/>
                                            <circle cx="12" cy="3" r="1.5"/>
                                            <circle cx="12" cy="21" r="1.5"/>
                                            <circle cx="3" cy="12" r="1.5"/>
                                            <circle cx="21" cy="12" r="1.5"/>
                                            <circle cx="5.6" cy="5.6" r="1"/>
                                            <circle cx="18.4" cy="18.4" r="1"/>
                                            <circle cx="5.6" cy="18.4" r="1"/>
                                            <circle cx="18.4" cy="5.6" r="1"/>
                                        </svg>
                                    </div>
                                    <div class="{{ $hasIsolation ? ($isCritical ? 'text-red-700' : 'text-yellow-700') : 'text-gray-600' }} font-semibold text-[10px]">Isolation</div>
                                    <div class="text-gray-800 mt-0.5 capitalize text-[11px] font-medium">{{ $hasIsolation ? $isolationTypeDisplay : 'None' }}</div>
                                </div>

                                {{-- Allergies with warning icon --}}
                                @php $allergyCount = count($patient->allergies ?? []); @endphp
                                <div class="rounded-lg p-3 text-center border-2 {{ $allergyCount > 0 ? 'bg-pink-100 border-pink-300' : 'bg-gray-50 border-gray-200' }}">
                                    <div class="flex justify-center mb-1">
                                        <svg class="w-5 h-5 {{ $allergyCount > 0 ? 'text-pink-600' : 'text-gray-400' }}" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 2L1 21h22L12 2zm0 3.5L19.5 19h-15L12 5.5zM11 10v4h2v-4h-2zm0 6v2h2v-2h-2z"/>
                                        </svg>
                                    </div>
                                    <div class="{{ $allergyCount > 0 ? 'text-pink-700' : 'text-gray-600' }} font-semibold text-[10px]">Allergies</div>
                                    <div class="text-gray-800 mt-0.5 font-bold">{{ $allergyCount > 0 ? $allergyCount : '-' }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Save Clinical Indicators
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Vital Signs -->
                @php
                    $vitalsMode = $patientVitalsMode ?? 'demo';
                @endphp
                <div x-show="activeTab === 'vitals'" x-cloak
                     x-data="{
                        view: 'table',
                        chartInstance: null,
                        vitalsMode: '{{ $vitalsMode }}',
                        init() {
                            this.$watch('view', (val) => {
                                if (val === 'graph' && this.vitalsMode !== 'off') {
                                    this.renderChart();
                                }
                            });
                        },
                        renderChart() {
                            if (this.chartInstance || typeof Chart === 'undefined') {
                                return;
                            }
                            const ctx = this.$refs.vitalsChart.getContext('2d');
                            @if($vitalsMode === 'real')
                            // Real data - fetch from patient's vital signs
                            @php
                                $realVitals = \App\Models\VitalSign::where('patient_id', $patient->id ?? 0)
                                    ->orderBy('recorded_at', 'desc')
                                    ->limit(10)
                                    ->get()
                                    ->reverse();
                                $labels = $realVitals->map(fn($v) => $v->recorded_at->format('M d H:i'))->values()->toArray();
                                $heartRates = $realVitals->pluck('pulse_rate')->toArray();
                                $spo2Values = $realVitals->pluck('spo2')->toArray();
                                $systolicValues = $realVitals->pluck('systolic_bp')->toArray();
                                $diastolicValues = $realVitals->pluck('diastolic_bp')->toArray();
                                $tempValues = $realVitals->map(fn($v) => (float)$v->temperature)->toArray();
                            @endphp
                            const labels = @json($labels);
                            const heartRate = @json($heartRates);
                            const spo2 = @json($spo2Values);
                            const systolic = @json($systolicValues);
                            const diastolic = @json($diastolicValues);
                            const temperature = @json($tempValues);
                            @else
                            // Demo data
                            const labels = ['Yesterday 20:00', 'Today 04:00', 'Today 08:00'];
                            const heartRate = [78, 84, 80];
                            const spo2 = [98, 97, 98];
                            const systolic = [116, 124, 118];
                            const diastolic = [74, 80, 76];
                            const temperature = [36.9, 37.2, 37.0];
                            @endif

                            this.chartInstance = new Chart(ctx, {
                                type: 'line',
                                data: {
                                    labels,
                                    datasets: [
                                        {
                                            label: 'Heart Rate (bpm)',
                                            data: heartRate,
                                            borderColor: '#2563eb',
                                            backgroundColor: 'rgba(37, 99, 235, 0.1)',
                                            tension: 0.3,
                                        },
                                        {
                                            label: 'SpO₂ (%)',
                                            data: spo2,
                                            borderColor: '#16a34a',
                                            backgroundColor: 'rgba(22, 163, 74, 0.1)',
                                            tension: 0.3,
                                        },
                                        {
                                            label: 'Systolic BP (mmHg)',
                                            data: systolic,
                                            borderColor: '#dc2626',
                                            backgroundColor: 'rgba(220, 38, 38, 0.1)',
                                            tension: 0.3,
                                        },
                                        {
                                            label: 'Diastolic BP (mmHg)',
                                            data: diastolic,
                                            borderColor: '#f97316',
                                            backgroundColor: 'rgba(249, 115, 22, 0.1)',
                                            tension: 0.3,
                                        },
                                        {
                                            label: 'Temperature (°C)',
                                            data: temperature,
                                            borderColor: '#eab308',
                                            backgroundColor: 'rgba(234, 179, 8, 0.1)',
                                            tension: 0.3,
                                            yAxisID: 'y1',
                                        },
                                    ],
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    interaction: {
                                        mode: 'index',
                                        intersect: false,
                                    },
                                    scales: {
                                        y: {
                                            beginAtZero: false,
                                            title: {
                                                display: true,
                                                text: 'HR / BP / SpO₂',
                                            },
                                        },
                                        y1: {
                                            position: 'right',
                                            beginAtZero: false,
                                            grid: {
                                                drawOnChartArea: false,
                                            },
                                            title: {
                                                display: true,
                                                text: 'Temperature (°C)',
                                            },
                                        },
                                    },
                                    plugins: {
                                        legend: {
                                            position: 'bottom',
                                        },
                                    },
                                },
                            });
                        }
                     }">
                    @if($vitalsMode === 'off')
                        <!-- Vitals Disabled -->
                        <div class="flex flex-col items-center justify-center py-12 text-center">
                            <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mb-4">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                </svg>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-700 mb-2">Vital Signs Disabled</h3>
                            <p class="text-sm text-gray-500 max-w-sm">
                                Vital signs display has been turned off in settings. Contact your administrator or enable it in Settings → Bed Box Config.
                            </p>
                        </div>
                    @else
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800">
                                    Vital Signs
                                    @if($vitalsMode === 'demo')
                                        <span class="text-xs font-normal text-blue-600 bg-blue-100 px-2 py-0.5 rounded-full ml-2">Demo</span>
                                    @else
                                        <span class="text-xs font-normal text-green-600 bg-green-100 px-2 py-0.5 rounded-full ml-2">Real Data</span>
                                    @endif
                                </h3>
                                <p class="text-sm text-gray-600">
                                    @if($vitalsMode === 'demo')
                                        Demo data only. Configure real data in Settings → Bed Box Config.
                                    @else
                                        Displaying real vital signs from the database.
                                    @endif
                                </p>
                            </div>
                            <div class="inline-flex rounded-md shadow-sm border border-gray-200 bg-white overflow-hidden text-xs">
                                <button type="button"
                                        @click="view = 'table'"
                                        :class="view === 'table' ? 'bg-gray-100 text-gray-900' : 'bg-white text-gray-600 hover:bg-gray-50'"
                                        class="px-3 py-1 font-semibold border-r border-gray-200">
                                    Table
                                </button>
                                <button type="button"
                                        @click="view = 'graph'"
                                        :class="view === 'graph' ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                                        class="px-3 py-1 font-semibold flex items-center space-x-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M4 19h16M5 16l4-6 4 4 6-10"/>
                                    </svg>
                                    <span>Graph</span>
                                </button>
                            </div>
                        </div>

                        <template x-if="view === 'table'">
                            <div>
                                @if($vitalsMode === 'real')
                                    @php
                                        $latestVital = \App\Models\VitalSign::where('patient_id', $patient->id ?? 0)
                                            ->orderBy('recorded_at', 'desc')
                                            ->first();
                                        $recentVitals = \App\Models\VitalSign::where('patient_id', $patient->id ?? 0)
                                            ->orderBy('recorded_at', 'desc')
                                            ->limit(5)
                                            ->get();
                                    @endphp
                                    @if($latestVital)
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                            <div class="bg-blue-50 border border-blue-100 rounded-lg p-3">
                                                <div class="text-xs text-blue-600 font-semibold mb-1">Heart Rate</div>
                                                <div class="text-2xl font-bold text-gray-900">{{ $latestVital->pulse_rate ?? '-' }}</div>
                                                <div class="text-xs text-gray-500 mt-1">bpm</div>
                                            </div>
                                            <div class="bg-green-50 border border-green-100 rounded-lg p-3">
                                                <div class="text-xs text-green-600 font-semibold mb-1">SpO₂</div>
                                                <div class="text-2xl font-bold text-gray-900">{{ $latestVital->spo2 ? $latestVital->spo2 . '%' : '-' }}</div>
                                                <div class="text-xs text-gray-500 mt-1">{{ $latestVital->oxygen_therapy ?? 'room air' }}</div>
                                            </div>
                                            <div class="bg-red-50 border border-red-100 rounded-lg p-3">
                                                <div class="text-xs text-red-600 font-semibold mb-1">Blood Pressure</div>
                                                <div class="text-2xl font-bold text-gray-900">{{ ($latestVital->systolic_bp && $latestVital->diastolic_bp) ? $latestVital->systolic_bp . '/' . $latestVital->diastolic_bp : '-' }}</div>
                                                <div class="text-xs text-gray-500 mt-1">mmHg</div>
                                            </div>
                                            <div class="bg-yellow-50 border border-yellow-100 rounded-lg p-3">
                                                <div class="text-xs text-yellow-600 font-semibold mb-1">Temperature</div>
                                                <div class="text-2xl font-bold text-gray-900">{{ $latestVital->temperature ? $latestVital->temperature . '°C' : '-' }}</div>
                                                <div class="text-xs text-gray-500 mt-1">{{ $latestVital->temperature_site ?? '' }}</div>
                                            </div>
                                        </div>

                                        <div class="mt-4">
                                            <h4 class="text-sm font-semibold text-gray-800 mb-2">Recent Vitals</h4>
                                            <div class="overflow-x-auto">
                                                <table class="min-w-full text-xs border border-gray-200 rounded-lg overflow-hidden">
                                                    <thead class="bg-gray-50">
                                                        <tr>
                                                            <th class="px-2 py-2 text-left font-medium text-gray-600 border-b">Time</th>
                                                            <th class="px-2 py-2 text-left font-medium text-gray-600 border-b">HR</th>
                                                            <th class="px-2 py-2 text-left font-medium text-gray-600 border-b">BP</th>
                                                            <th class="px-2 py-2 text-left font-medium text-gray-600 border-b">SpO₂</th>
                                                            <th class="px-2 py-2 text-left font-medium text-gray-600 border-b">Temp</th>
                                                            <th class="px-2 py-2 text-left font-medium text-gray-600 border-b">RR</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($recentVitals as $vital)
                                                            <tr class="hover:bg-gray-50">
                                                                <td class="px-2 py-2 border-b">{{ $vital->recorded_at->format('M d H:i') }}</td>
                                                                <td class="px-2 py-2 border-b">{{ $vital->pulse_rate ? $vital->pulse_rate . ' bpm' : '-' }}</td>
                                                                <td class="px-2 py-2 border-b">{{ ($vital->systolic_bp && $vital->diastolic_bp) ? $vital->systolic_bp . '/' . $vital->diastolic_bp : '-' }}</td>
                                                                <td class="px-2 py-2 border-b">{{ $vital->spo2 ? $vital->spo2 . '%' : '-' }}</td>
                                                                <td class="px-2 py-2 border-b">{{ $vital->temperature ? $vital->temperature . '°C' : '-' }}</td>
                                                                <td class="px-2 py-2 border-b">{{ $vital->respiratory_rate ?? '-' }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    @else
                                        <div class="text-center py-8 text-gray-500">
                                            <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                            </svg>
                                            <p class="text-sm">No vital signs recorded for this patient yet.</p>
                                        </div>
                                    @endif
                                @else
                                    <!-- Demo Data -->
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                        <div class="bg-blue-50 border border-blue-100 rounded-lg p-3">
                                            <div class="text-xs text-blue-600 font-semibold mb-1">Heart Rate</div>
                                            <div class="text-2xl font-bold text-gray-900">82</div>
                                            <div class="text-xs text-gray-500 mt-1">bpm</div>
                                        </div>
                                        <div class="bg-green-50 border border-green-100 rounded-lg p-3">
                                            <div class="text-xs text-green-600 font-semibold mb-1">SpO₂</div>
                                            <div class="text-2xl font-bold text-gray-900">97%</div>
                                            <div class="text-xs text-gray-500 mt-1">room air</div>
                                        </div>
                                        <div class="bg-red-50 border border-red-100 rounded-lg p-3">
                                            <div class="text-xs text-red-600 font-semibold mb-1">Blood Pressure</div>
                                            <div class="text-2xl font-bold text-gray-900">122/78</div>
                                            <div class="text-xs text-gray-500 mt-1">mmHg</div>
                                        </div>
                                        <div class="bg-yellow-50 border border-yellow-100 rounded-lg p-3">
                                            <div class="text-xs text-yellow-600 font-semibold mb-1">Temperature</div>
                                            <div class="text-2xl font-bold text-gray-900">37.1°C</div>
                                            <div class="text-xs text-gray-500 mt-1">oral</div>
                                        </div>
                                    </div>

                                    <div class="mt-4">
                                        <h4 class="text-sm font-semibold text-gray-800 mb-2">Recent Vitals (Demo)</h4>
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full text-xs border border-gray-200 rounded-lg overflow-hidden">
                                                <thead class="bg-gray-50">
                                                    <tr>
                                                        <th class="px-2 py-2 text-left font-medium text-gray-600 border-b">Time</th>
                                                        <th class="px-2 py-2 text-left font-medium text-gray-600 border-b">HR</th>
                                                        <th class="px-2 py-2 text-left font-medium text-gray-600 border-b">BP</th>
                                                        <th class="px-2 py-2 text-left font-medium text-gray-600 border-b">SpO₂</th>
                                                        <th class="px-2 py-2 text-left font-medium text-gray-600 border-b">Temp</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr class="hover:bg-gray-50">
                                                        <td class="px-2 py-2 border-b">Today 08:00</td>
                                                        <td class="px-2 py-2 border-b">80 bpm</td>
                                                        <td class="px-2 py-2 border-b">118/76</td>
                                                        <td class="px-2 py-2 border-b">98%</td>
                                                        <td class="px-2 py-2 border-b">37.0°C</td>
                                                    </tr>
                                                    <tr class="hover:bg-gray-50">
                                                        <td class="px-2 py-2 border-b">Today 04:00</td>
                                                        <td class="px-2 py-2 border-b">84 bpm</td>
                                                        <td class="px-2 py-2 border-b">124/80</td>
                                                        <td class="px-2 py-2 border-b">97%</td>
                                                        <td class="px-2 py-2 border-b">37.2°C</td>
                                                    </tr>
                                                    <tr class="hover:bg-gray-50">
                                                        <td class="px-2 py-2 border-b">Yesterday 20:00</td>
                                                        <td class="px-2 py-2 border-b">78 bpm</td>
                                                        <td class="px-2 py-2 border-b">116/74</td>
                                                        <td class="px-2 py-2 border-b">98%</td>
                                                        <td class="px-2 py-2 border-b">36.9°C</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </template>

                        <template x-if="view === 'graph'">
                            <div class="mt-2 h-72 md:h-80">
                                <canvas x-ref="vitalsChart"></canvas>
                            </div>
                        </template>
                    @endif
                </div>

                <!-- Patient Movement -->
                <div x-show="activeTab === 'movement'" x-cloak>
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Patient Movement</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        Use this tab to schedule and send the patient to procedures outside the ward
                        (for example Radiology, Surgery, etc.).
                    </p>

                    <!-- New Movement Form -->
                    <form method="POST" action="{{ route('ward.patient-movements.store') }}" class="mb-6 space-y-4">
                        @csrf
                        <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                        <input type="hidden" name="active_tab" value="movement">

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Quick Location</label>
                                <div class="flex flex-wrap gap-2 text-xs">
                                    <button type="button"
                                            class="px-2 py-1 rounded border border-blue-200 text-blue-700 bg-blue-50 hover:bg-blue-100"
                                            onclick="document.getElementById('movement_location').value='Radiology'; document.getElementById('movement_location_type').value='radiology';">
                                        Radiology
                                    </button>
                                    <button type="button"
                                            class="px-2 py-1 rounded border border-purple-200 text-purple-700 bg-purple-50 hover:bg-purple-100"
                                            onclick="document.getElementById('movement_location').value='Surgery'; document.getElementById('movement_location_type').value='surgery';">
                                        Surgery
                                    </button>
                                    <button type="button"
                                            class="px-2 py-1 rounded border border-gray-200 text-gray-700 bg-gray-50 hover:bg-gray-100"
                                            onclick="document.getElementById('movement_location').focus(); document.getElementById('movement_location_type').value='other';">
                                        Other (type below)
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label for="movement_location" class="block text-xs font-semibold text-gray-700 mb-1">
                                    Destination / Location
                                </label>
                                <input type="text"
                                       id="movement_location"
                                       name="location"
                                       required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                       placeholder="e.g. Radiology, OR 3, Cath Lab">
                                <input type="hidden" id="movement_location_type" name="location_type" value="">
                            </div>

                            <div>
                                <label for="movement_scheduled_at" class="block text-xs font-semibold text-gray-700 mb-1">
                                    Scheduled Date &amp; Time
                                </label>
                                <input type="datetime-local"
                                       id="movement_scheduled_at"
                                       name="scheduled_at"
                                       required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                            </div>
                        </div>

                        <div>
                            <label for="movement_notes" class="block text-xs font-semibold text-gray-700 mb-1">
                                Notes (optional)
                            </label>
                            <textarea id="movement_notes"
                                      name="notes"
                                      rows="2"
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                      placeholder="E.g. CT brain, fasting from 06:00, escort required"></textarea>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 4v16m8-8H4"/>
                                </svg>
                                Schedule Movement
                            </button>
                        </div>
                    </form>

                    <!-- Existing & Upcoming Movements -->
                    <div class="space-y-4 text-sm">
                        <h4 class="font-semibold text-gray-800 flex items-center">
                            <svg class="w-4 h-4 mr-1 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Movement Timeline
                        </h4>

                        @php
                            $movements = $patient->movements ?? collect();
                        @endphp

                        @if($movements->count() === 0)
                            <div class="border border-dashed border-gray-300 rounded-lg p-4 text-xs text-gray-500">
                                No movement records yet for this patient.
                            </div>
                        @else
                            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                                <table class="min-w-full text-xs">
                                    <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600 border-b">Location</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600 border-b">Scheduled</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600 border-b">Status</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600 border-b">Sent / Returned</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600 border-b">Notes</th>
                                        <th class="px-3 py-2 text-right font-medium text-gray-600 border-b">Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($movements as $movement)
                                        @php
                                            $isUpcoming = $movement->status === 'scheduled' && $movement->scheduled_at && $movement->scheduled_at->greaterThanOrEqualTo(now());
                                            $isCurrent = $movement->status === 'sent' && !$movement->returned_at;
                                        @endphp
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-3 py-2 border-b align-top">
                                                <div class="font-semibold text-gray-900">{{ $movement->location }}</div>
                                                @if($movement->location_type)
                                                    <div class="text-[11px] text-gray-500 uppercase tracking-wide">
                                                        {{ $movement->location_type }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 border-b align-top">
                                                {{ $movement->scheduled_at ? $movement->scheduled_at->format('Y-m-d H:i') : '-' }}
                                            </td>
                                            <td class="px-3 py-2 border-b align-top">
                                                @php
                                                    $statusClasses = [
                                                        'scheduled' => 'bg-yellow-100 text-yellow-800',
                                                        'sent' => 'bg-orange-100 text-orange-800',
                                                        'returned' => 'bg-green-100 text-green-800',
                                                        'cancelled' => 'bg-gray-100 text-gray-800',
                                                    ];
                                                    $statusLabel = ucfirst($movement->status);
                                                @endphp
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-[11px] font-semibold {{ $statusClasses[$movement->status] ?? 'bg-gray-100 text-gray-800' }}">
                                                    {{ $statusLabel }}
                                                </span>
                                                @if($isCurrent)
                                                    <div class="mt-1">
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-[11px] font-bold bg-orange-500 text-white animate-pulse">
                                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                            </svg>
                                                            OUTSIDE
                                                        </span>
                                                    </div>
                                                @endif
                                                @if($isUpcoming)
                                                    <div class="mt-1 text-[11px] text-orange-600">
                                                        Reminder 15 minutes before scheduled time on ward dashboard.
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 border-b align-top">
                                                <div>
                                                    <span class="text-gray-500 text-[11px]">Sent:</span>
                                                    <span class="ml-1 text-xs text-gray-800">
                                                        {{ $movement->sent_at ? $movement->sent_at->format('Y-m-d H:i') : '-' }}
                                                    </span>
                                                </div>
                                                <div>
                                                    <span class="text-gray-500 text-[11px]">Returned:</span>
                                                    <span class="ml-1 text-xs text-gray-800">
                                                        {{ $movement->returned_at ? $movement->returned_at->format('Y-m-d H:i') : '-' }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-3 py-2 border-b align-top max-w-xs">
                                                <div class="text-xs text-gray-700 whitespace-pre-line">
                                                    {{ $movement->notes ?? '-' }}
                                                </div>
                                            </td>
                                            <td class="px-3 py-2 border-b align-top text-right space-y-1">
                                                @if($movement->status === 'scheduled')
                                                    <form method="POST"
                                                          action="{{ route('ward.patient-movements.send', $movement) }}">
                                                        @csrf
                                                        <input type="hidden" name="active_tab" value="movement">
                                                        <button type="submit"
                                                                class="inline-flex items-center px-2 py-1 bg-blue-600 text-white text-[11px] font-semibold rounded hover:bg-blue-700">
                                                            Send Patient
                                                        </button>
                                                    </form>
                                                @elseif($movement->status === 'sent' && !$movement->returned_at)
                                                    <form method="POST"
                                                          action="{{ route('ward.patient-movements.return', $movement) }}">
                                                        @csrf
                                                        <input type="hidden" name="active_tab" value="movement">
                                                        <button type="submit"
                                                                class="inline-flex items-center px-2 py-1 bg-green-600 text-white text-[11px] font-semibold rounded hover:bg-green-700">
                                                            Mark Returned
                                                        </button>
                                                    </form>
                                                @else
                                                    <span class="text-[11px] text-gray-400">No actions</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Care Provider Tab -->
                <div x-show="activeTab === 'careprovider'" x-cloak>
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Care Provider</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        Doctors assigned to this patient from ADT PV1 segment. This includes attending, referring, and consulting doctors.
                    </p>

                    <div class="space-y-6">
                        <!-- Attending Doctors (PV1-7) -->
                        <div class="rounded-lg border border-blue-100 bg-blue-50/50 p-4">
                            <div class="flex items-center mb-3">
                                <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                <h4 class="text-sm font-semibold text-blue-800">Attending Doctor <span class="font-normal text-blue-600">(PV1-7)</span></h4>
                            </div>
                            @if($attendingDoctors->count() > 0)
                                <div class="space-y-2">
                                    @foreach($attendingDoctors as $provider)
                                        <div class="flex items-center justify-between bg-white rounded-lg p-3 border border-blue-100">
                                            <div>
                                                <div class="font-medium text-gray-900">{{ $provider->display_name }}</div>
                                                <div class="text-xs text-gray-500">
                                                    Code: {{ $provider->doctor_code }}
                                                    @if($provider->specialty)
                                                        • {{ $provider->specialty }}
                                                    @endif
                                                </div>
                                            </div>
                                            @if($provider->isLinked())
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                    </svg>
                                                    Linked
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                                    ADT Only
                                                </span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm text-blue-600 italic">No attending doctor assigned from ADT</p>
                            @endif
                        </div>

                        <!-- Referring Doctors (PV1-8) -->
                        <div class="rounded-lg border border-purple-100 bg-purple-50/50 p-4">
                            <div class="flex items-center mb-3">
                                <svg class="w-5 h-5 text-purple-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                                <h4 class="text-sm font-semibold text-purple-800">Referring Doctor <span class="font-normal text-purple-600">(PV1-8)</span></h4>
                            </div>
                            @if($referringDoctors->count() > 0)
                                <div class="space-y-2">
                                    @foreach($referringDoctors as $provider)
                                        <div class="flex items-center justify-between bg-white rounded-lg p-3 border border-purple-100">
                                            <div>
                                                <div class="font-medium text-gray-900">{{ $provider->display_name }}</div>
                                                <div class="text-xs text-gray-500">
                                                    Code: {{ $provider->doctor_code }}
                                                    @if($provider->specialty)
                                                        • {{ $provider->specialty }}
                                                    @endif
                                                </div>
                                            </div>
                                            @if($provider->isLinked())
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                    </svg>
                                                    Linked
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                                    ADT Only
                                                </span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm text-purple-600 italic">No referring doctor assigned from ADT</p>
                            @endif
                        </div>

                        <!-- Consulting Doctors (PV1-9) -->
                        <div class="rounded-lg border border-green-100 bg-green-50/50 p-4">
                            <div class="flex items-center mb-3">
                                <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                                <h4 class="text-sm font-semibold text-green-800">Consulting Doctor <span class="font-normal text-green-600">(PV1-9)</span></h4>
                            </div>
                            @if($consultingDoctors->count() > 0)
                                <div class="space-y-2">
                                    @foreach($consultingDoctors as $provider)
                                        <div class="flex items-center justify-between bg-white rounded-lg p-3 border border-green-100">
                                            <div>
                                                <div class="font-medium text-gray-900">{{ $provider->display_name }}</div>
                                                <div class="text-xs text-gray-500">
                                                    Code: {{ $provider->doctor_code }}
                                                    @if($provider->specialty)
                                                        • {{ $provider->specialty }}
                                                    @endif
                                                </div>
                                            </div>
                                            @if($provider->isLinked())
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                    </svg>
                                                    Linked
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                                    ADT Only
                                                </span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm text-green-600 italic">No consulting doctor assigned from ADT</p>
                            @endif
                        </div>
                    </div>

                    <!-- Info note about ADT -->
                    <div class="mt-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-gray-400 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <div class="text-sm text-gray-600">
                                <p class="font-medium text-gray-700">About Care Providers from ADT</p>
                                <p class="mt-1">Care providers are automatically assigned from HL7 ADT messages. Each doctor type corresponds to specific PV1 fields:</p>
                                <ul class="mt-2 list-disc list-inside text-xs space-y-1">
                                    <li><strong>Attending Doctor (PV1-7):</strong> The primary physician responsible for the patient's care</li>
                                    <li><strong>Referring Doctor (PV1-8):</strong> The physician who referred the patient</li>
                                    <li><strong>Consulting Doctor (PV1-9):</strong> Additional specialists consulted for the patient's care</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Infusion Management -->
                <div x-show="activeTab === 'infusion'" x-cloak>
                    <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                        </svg>
                        Infusion Management
                    </h3>
                    <p class="text-sm text-gray-600 mb-3">
                        Real-time infusion pump monitoring for this patient. Data is received from infusion pump gateways via HL7 integration.
                    </p>
                    
                    <div class="mt-2">
                        <iframe src="{{ route('ward.patient-infusions') }}?patient_id={{ $patient->id }}" 
                                class="w-full h-[450px] border-0 rounded-lg bg-gray-50"
                                title="Patient Infusions">
                        </iframe>
                    </div>
                </div>

                <!-- Transfer Bed -->
                <div x-show="activeTab === 'transfer'" x-cloak>
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Transfer Bed</h3>
                    @if($patient->status === 'discharged')
                        <p class="text-sm text-red-600 mb-3">
                            This patient is already discharged and cannot be transferred.
                        </p>
                    @else
                        <p class="text-sm text-gray-600 mb-4">
                            Move this patient to a different bed (and optionally ward). The current bed will be freed.
                        </p>

                        <div class="mb-3 text-xs text-gray-600">
                            <div>Current Ward: <span class="font-semibold">{{ $patient->ward->ward_name ?? '-' }}</span></div>
                            <div>Current Bed: <span class="font-semibold">{{ $patient->bed_number ?? '-' }}</span></div>
                        </div>

                        <form method="POST" action="{{ route('ward.transfer-bed') }}" class="space-y-4">
                            @csrf
                            <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                            <input type="hidden" name="active_tab" value="transfer">

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
                                <div>
                                    <label for="transfer_ward_id" class="block text-xs font-semibold text-gray-700 mb-1">
                                        Target Ward
                                    </label>
                                    <select id="transfer_ward_id"
                                            name="ward_id"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                        @foreach($wards as $ward)
                                            <option value="{{ $ward->id }}"
                                                {{ $patient->ward_id === $ward->id ? 'selected' : '' }}>
                                                {{ $ward->ward_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label for="transfer_bed_number" class="block text-xs font-semibold text-gray-700 mb-1">
                                        Target Bed Number
                                    </label>
                                    <input type="text"
                                           id="transfer_bed_number"
                                           name="bed_number"
                                           required
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                           placeholder="e.g. B05">
                                    <p class="mt-1 text-[11px] text-gray-500">
                                        Use an existing bed number in the selected ward.
                                    </p>
                                </div>

                                <div>
                                    <label for="transfer_reason" class="block text-xs font-semibold text-gray-700 mb-1">
                                        Reason (optional)
                                    </label>
                                    <input type="text"
                                           id="transfer_reason"
                                           name="reason"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                           placeholder="e.g. Closer to nurses station">
                                </div>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit"
                                        class="inline-flex items-center px-4 py-2 bg-amber-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3v-1M9 5V4a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                    </svg>
                                    Transfer Bed
                                </button>
                            </div>
                        </form>
                    @endif
                </div>

                <!-- Discharge -->
                <div x-show="activeTab === 'discharge'" x-cloak>
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Discharge</h3>
                    @if($patient->status === 'discharged')
                        <p class="text-sm text-green-700 mb-3">
                            This patient is already discharged from the ward.
                        </p>
                    @else
                        <p class="text-sm text-gray-600 mb-4">
                            Discharge this patient from the ward. The current bed will be freed and the patient
                            will no longer appear as admitted on the ward dashboard.
                        </p>

                        <div class="mb-3 text-xs text-gray-600">
                            <div>Status: <span class="font-semibold capitalize">{{ $patient->status }}</span></div>
                            <div>Ward: <span class="font-semibold">{{ $patient->ward->ward_name ?? '-' }}</span></div>
                            <div>Bed: <span class="font-semibold">{{ $patient->bed_number ?? '-' }}</span></div>
                        </div>

                        <form method="POST" action="{{ route('ward.discharge-patient') }}" class="space-y-4">
                            @csrf
                            <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                            <input type="hidden" name="active_tab" value="discharge">

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
                                <div>
                                    <label for="discharge_reason" class="block text-xs font-semibold text-gray-700 mb-1">
                                        Discharge Reason (optional)
                                    </label>
                                    <input type="text"
                                           id="discharge_reason"
                                           name="discharge_reason"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 text-sm"
                                           placeholder="e.g. Discharged home, transferred to ICU">
                                </div>

                                <div>
                                    <label for="discharged_at" class="block text-xs font-semibold text-gray-700 mb-1">
                                        Discharge Date &amp; Time (optional)
                                    </label>
                                    <input type="datetime-local"
                                           id="discharged_at"
                                           name="discharged_at"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 text-sm">
                                    <p class="mt-1 text-[11px] text-gray-500">
                                        Leave blank to use the current date &amp; time.
                                    </p>
                                </div>
                            </div>

                            <div>
                                <label for="discharge_notes" class="block text-xs font-semibold text-gray-700 mb-1">
                                    Notes (optional)
                                </label>
                                <textarea id="discharge_notes"
                                          name="discharge_notes"
                                          rows="2"
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 text-sm"
                                          placeholder="Add any discharge summary, follow-up instructions, etc."></textarea>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit"
                                        class="inline-flex items-center px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3v-1M9 5V4a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                    </svg>
                                    Confirm Discharge
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        @endif
    </div>
</body>
</html>


