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
        
        /* IHH Chart Styles */
        .ihh-chart {
            font-size: 9px;
            border-collapse: collapse;
            width: 100%;
        }
        .ihh-chart th, .ihh-chart td {
            border: 1px solid #999;
            padding: 0;
            text-align: center;
            height: 14px;
            min-width: 24px;
        }
        .ihh-chart .section-header {
            background: #f0f0f0;
            font-weight: bold;
            text-align: left;
            padding: 2px 4px;
        }
        .ihh-chart .score-col {
            width: 20px;
            font-weight: bold;
        }
        /* Temperature zones - Score 0: White, Score 1: Orange, Score 2: Red */
        .ihh-temp-high2 { background: #f4cccc; }
        .ihh-temp-high1 { background: #fce5cd; }
        .ihh-temp-normal { background: #ffffff; }
        .ihh-temp-low1 { background: #fce5cd; }
        .ihh-temp-low2 { background: #f4cccc; }
        /* Blood Pressure zones - Score 0: White, Score 1: Orange, Score 2: Red */
        .ihh-bp-high2 { background: #f4cccc; }
        .ihh-bp-high1 { background: #fce5cd; }
        .ihh-bp-normal { background: #ffffff; }
        .ihh-bp-low1 { background: #fce5cd; }
        .ihh-bp-low2 { background: #f4cccc; }
        /* Pulse Rate zones - Score 0: White, Score 1: Orange, Score 2: Red */
        .ihh-pr-high2 { background: #f4cccc; }
        .ihh-pr-high1 { background: #fce5cd; }
        .ihh-pr-normal { background: #ffffff; }
        .ihh-pr-low1 { background: #fce5cd; }
        .ihh-pr-low2 { background: #f4cccc; }
        /* Respiration Rate zones - Score 0: White, Score 1: Orange, Score 2: Red */
        .ihh-rr-high2 { background: #f4cccc; }
        .ihh-rr-high1 { background: #fce5cd; }
        .ihh-rr-normal { background: #ffffff; }
        .ihh-rr-low1 { background: #fce5cd; }
        .ihh-rr-low2 { background: #f4cccc; }
        .ihh-marker {
            display: inline-block;
            width: 6px;
            height: 6px;
            background: #000;
            border-radius: 50%;
        }
        .ihh-marker-x {
            font-weight: bold;
            font-size: 10px;
        }
        .ihh-marker-systolic {
            font-weight: bold;
            font-size: 11px;
            line-height: 1;
            color: #000;
        }
        .ihh-marker-diastolic {
            font-weight: bold;
            font-size: 11px;
            line-height: 1;
            color: #000;
        }
        .ihh-label-col {
            width: 40px;
            text-align: right;
            padding-right: 3px !important;
            font-weight: bold;
            background: #f9f9f9;
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

        // Pre-calculate IHH vitals data for the chart (avoid inline closures in @json)
        $ihhVitalsData = [];
        if (($patientVitalsMode ?? 'demo') === 'real' && $patient) {
            $ihhVitalsData = \App\Models\VitalSign::where('patient_id', $patient->id)
                ->orderBy('recorded_at', 'desc')
                ->limit(100)
                ->get()
                ->map(function ($v) {
                    return [
                        'recorded_at' => $v->recorded_at->toISOString(),
                        'temperature' => $v->temperature,
                        'systolic_bp' => $v->systolic_bp,
                        'diastolic_bp' => $v->diastolic_bp,
                        'pulse_rate' => $v->pulse_rate,
                        'respiratory_rate' => $v->respiratory_rate,
                        'spo2' => $v->spo2,
                    ];
                })
                ->toArray();
        }
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
                            <div class="text-gray-500">RN</div>
                            <div class="font-medium text-gray-900">{{ $patient->rn ?? '-' }}</div>
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
                            <div class="font-medium text-gray-900">{{ $consultantName }}</div>
                        </div>
                        <div>
                            <div class="text-gray-500">Nurse</div>
                            <div class="font-medium text-gray-900">{{ $nurseName }}</div>
                        </div>
                        <div>
                            <div class="text-gray-500">Anaesthetist</div>
                            <div class="font-medium text-gray-900">{{ $anaesthetistName }}</div>
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
                    // Normalize allergies - convert to array of objects with standard structure
                    $allergyList = collect($patient->allergies ?? [])->map(function ($a) {
                        if (is_array($a)) {
                            // Use parsed allergen name, or fallback to code
                            $rawName = $a['allergen'] ?? $a['allergen_code'] ?? 'Unknown';
                            // If name contains caret (legacy data), take part after caret
                            $name = str_contains($rawName, '^') ? explode('^', $rawName)[1] ?? $rawName : $rawName;

                            return [
                                'name' => $name,
                                'status' => $a['status'] ?? 'Active', // Default to Active if not specified
                            ];
                        }
                        // Simple string legacy data
                        $rawName = $a;
                        $name = str_contains($rawName, '^') ? explode('^', $rawName)[1] ?? $rawName : $rawName;

                        return [
                            'name' => $name,
                            'status' => 'Active'
                        ];
                    })->values()->toArray();
                @endphp
                <div x-show="activeTab === 'additional'" x-cloak
                     x-data="{
                        allergies: @json($allergyList)
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
                                                {{ DietType::getDisplayName($dietCode) }}
                                            </span>
                                        @endforeach
                                    @else
                                        <span class="text-sm text-gray-400 italic">No diet types specified (Regular diet)</span>
                                    @endif
                                </div>
                            </div>

                            <!-- Fall Risk Alert (Read-only from ADT) -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 mr-2 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                        </svg>
                                        Fall Risk Alert
                                        <span class="ml-2 text-xs font-normal text-gray-400">(from ADT)</span>
                                    </div>
                                </label>
                                @php
                                    $fallRiskVal = $patient->fall_risk;
                                    $isFallRisk = $fallRiskVal === '1' || $fallRiskVal === 'yes' || $fallRiskVal === true;
                                    $fallRiskLabel = $isFallRisk ? 'Alert Active' : 'No Risk';
                                    $fallRiskColor = $isFallRisk ? 'red' : 'green';
                                    // Handle legacy/other values
                                    if ($fallRiskVal !== '0' && $fallRiskVal !== '1' && $fallRiskVal !== 'none' && !empty($fallRiskVal)) {
                                        $fallRiskLabel = ucfirst($fallRiskVal);
                                        $fallRiskColor = 'orange'; // Unknown non-empty
                                    } elseif (empty($fallRiskVal) || $fallRiskVal === 'none') {
                                        $fallRiskLabel = 'Not Assessed';
                                        $fallRiskColor = 'gray';
                                    }
                                @endphp
                                <div class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50 px-3 py-2 text-sm shadow-sm border">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $fallRiskColor }}-100 text-{{ $fallRiskColor }}-800">
                                        {{ $fallRiskLabel }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-gray-500">Patient fall risk (managed by ADT)</p>
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
                            <!-- Allergies Summary -->
                            <div class="mb-3" x-show="allergies.length > 0">
                                <p class="text-xs text-gray-600">
                                    <span class="font-medium" x-text="allergies.filter(a => a.status !== 'Resolved').length"></span>
                                    <span class="text-pink-600">Active</span>,
                                    <span class="font-medium" x-text="allergies.filter(a => a.status === 'Resolved').length"></span>
                                    <span class="text-green-600">Resolved</span>
                                </p>
                            </div>

                            <!-- Allergies List (Read-only) -->
                            <div class="flex flex-wrap gap-2" x-show="allergies.length > 0">
                                <template x-for="(allergy, index) in allergies" :key="index">
                                    <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium border"
                                          :class="allergy.status === 'Resolved' ? 'bg-green-100 text-green-800 border-green-200' : 'bg-pink-100 text-pink-800 border-pink-200'">
                                        <svg class="w-3 h-3 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                        </svg>
                                        <span x-text="allergy.name"></span>
                                        <span class="ml-1 text-xs font-semibold" x-text="allergy.status === 'Resolved' ? '(Resolved)' : ''"></span>
                                    </span>
                                </template>
                            </div>
                            <p x-show="allergies.length === 0" class="text-sm text-gray-400 italic">No allergies recorded</p>
                        </div>

                        <!-- Sugar Monitoring (HGT) -->
                        <div class="border-t pt-6">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 mr-2 text-teal-500" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 2c-1.1 0-2 .9-2 2v8c-2.2 1.2-3.5 3.5-3.5 6 0 3.6 2.9 6.5 6.5 6.5s6.5-2.9 6.5-6.5c0-2.5-1.3-4.8-3.5-6V4c0-1.1-.9-2-2-2zm-1 14.7c-1.3.5-2.2 1.8-2.2 3.3h6.4c0-1.5-.9-2.8-2.2-3.3V4h-2v12.7z"/>
                                    </svg>
                                    Sugar Monitoring (HGT)
                                </div>
                            </label>
                            <p class="text-xs text-gray-500 mb-3">Configure blood glucose monitoring frequency and record readings</p>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <!-- HGT Enabled Toggle -->
                                <div class="flex items-center">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="hgt_enabled" value="1" class="sr-only peer" {{ ($patient->hgt_enabled ?? false) ? 'checked' : '' }}>
                                        <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-teal-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-teal-500"></div>
                                        <span class="ms-3 text-sm font-medium text-gray-700">Enable HGT Monitoring</span>
                                    </label>
                                </div>

                                <!-- HGT Frequency -->
                                <div>
                                    <label for="hgt_frequency" class="block text-xs font-medium text-gray-600 mb-1">Frequency</label>
                                    <select id="hgt_frequency" name="hgt_frequency" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 text-sm">
                                        <option value="">Select frequency...</option>
                                        <option value="bd" {{ ($patient->hgt_frequency ?? '') === 'bd' ? 'selected' : '' }}>BD (Twice Daily)</option>
                                        <option value="tds" {{ ($patient->hgt_frequency ?? '') === 'tds' ? 'selected' : '' }}>TDS (Three Times Daily)</option>
                                        <option value="qid" {{ ($patient->hgt_frequency ?? '') === 'qid' ? 'selected' : '' }}>QID (Four Times Daily)</option>
                                        <option value="pid" {{ ($patient->hgt_frequency ?? '') === 'pid' ? 'selected' : '' }}>PRN (As Needed)</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Record New HGT Reading -->
                            <div class="bg-teal-50 border border-teal-200 rounded-lg p-3 mb-3">
                                <h5 class="text-xs font-semibold text-teal-700 mb-2">Record New HGT Reading</h5>
                                <div class="flex items-end gap-2">
                                    <div class="flex-1">
                                        <label for="hgt_value" class="block text-xs font-medium text-gray-600 mb-1">Value (mmol/L)</label>
                                        <input type="number" id="hgt_value" name="hgt_value" step="0.1" min="0" max="50" placeholder="e.g., 5.6" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 text-sm">
                                    </div>
                                    <button type="button" onclick="recordHgtReading()" class="px-3 py-2 bg-teal-600 text-white text-sm font-medium rounded-md hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500">
                                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                        Record
                                    </button>
                                </div>
                            </div>

                            <!-- Recent HGT Readings -->
                            @php
                                $recentHgtReadings = $patient->sugarReadings()->limit(5)->get();
                            @endphp
                            @if($recentHgtReadings->count() > 0)
                                <div class="mt-3">
                                    <h5 class="text-xs font-semibold text-gray-600 mb-2">Recent Readings</h5>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full text-xs border border-gray-200 rounded-lg overflow-hidden">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-2 py-1 text-left font-medium text-gray-600 border-b">Time</th>
                                                    <th class="px-2 py-1 text-left font-medium text-gray-600 border-b">Value</th>
                                                    <th class="px-2 py-1 text-left font-medium text-gray-600 border-b">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($recentHgtReadings as $reading)
                                                    @php
                                                        $statusColor = 'green';
                                                        $statusText = 'Normal';
                                                        if ($reading->value < 4.0) {
                                                            $statusColor = 'red';
                                                            $statusText = 'Low';
                                                        } elseif ($reading->value > 11.0) {
                                                            $statusColor = 'orange';
                                                            $statusText = 'High';
                                                        }
                                                    @endphp
                                                    <tr class="hover:bg-gray-50">
                                                        <td class="px-2 py-1 border-b">{{ $reading->recorded_at->format('M d H:i') }}</td>
                                                        <td class="px-2 py-1 border-b font-semibold text-{{ $statusColor }}-600">{{ $reading->value }} mmol/L</td>
                                                        <td class="px-2 py-1 border-b">
                                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-{{ $statusColor }}-100 text-{{ $statusColor }}-800">
                                                                {{ $statusText }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @else
                                <p class="text-xs text-gray-400 italic">No HGT readings recorded yet</p>
                            @endif
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
                                $fallRiskNum = ['0' => '0', '1' => '1', 'low' => '1', 'moderate' => '2', 'high' => '3', 'alert_active' => '4'];
                                $fallRiskColors = [
                                    '0' => 'bg-green-100 border-green-300 text-green-700',
                                    '1' => 'bg-red-100 border-red-300 text-red-700',
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
                                @php 
                                                                                                                                                                    $rawAllergies = $patient->allergies ?? [];
                                    $allAllergies = collect($rawAllergies)->map(function ($a) {
                                        return is_array($a) ? $a : ['status' => 'Active'];
                                    });
                                    $totalAllergies = $allAllergies->count();
                                    $activeAllergies = $allAllergies->filter(function ($a) {
                                        return ($a['status'] ?? 'Active') !== 'Resolved';
                                    })->count();
                                    $resolvedAllergies = $totalAllergies - $activeAllergies;

                                    $allergyColor = 'bg-gray-50 border-gray-200';
                                    $allergyText = 'text-gray-600';
                                    $allergyIcon = 'text-gray-400';

                                    if ($activeAllergies > 0) {
                                        $allergyColor = 'bg-pink-100 border-pink-300';
                                        $allergyText = 'text-pink-700';
                                        $allergyIcon = 'text-pink-600';
                                    } elseif ($resolvedAllergies > 0) {
                                        $allergyColor = 'bg-green-100 border-green-300'; // Green for resolved only
                                        $allergyText = 'text-green-700';
                                        $allergyIcon = 'text-green-600';
                                    }
                                @endphp
                                <div class="rounded-lg p-3 text-center border-2 {{ $allergyColor }}">
                                    <div class="flex justify-center mb-1">
                                        <svg class="w-5 h-5 {{ $allergyIcon }}" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 2L1 21h22L12 2zm0 3.5L19.5 19h-15L12 5.5zM11 10v4h2v-4h-2zm0 6v2h2v-2h-2z"/>
                                        </svg>
                                    </div>
                                    <div class="{{ $allergyText }} font-semibold text-[10px]">Allergies</div>
                                    <div class="text-gray-800 mt-0.5 font-bold">
                                        @if($activeAllergies > 0)
                                            {{ $activeAllergies }} <span class="text-[9px] font-normal text-gray-500">Active</span>
                                        @elseif($resolvedAllergies > 0)
                                            {{ $resolvedAllergies }} <span class="text-[9px] font-normal text-gray-500">Rsvd</span>
                                        @else
                                            -
                                        @endif
                                    </div>
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
                                    $tempValues = $realVitals->map(fn($v) => (float) $v->temperature)->toArray();
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
                                        class="px-3 py-1 font-semibold flex items-center space-x-1 border-r border-gray-200">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M4 19h16M5 16l4-6 4 4 6-10"/>
                                    </svg>
                                    <span>Graph</span>
                                </button>
                                <button type="button"
                                        @click="view = 'ihh'"
                                        :class="view === 'ihh' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                                        class="px-3 py-1 font-semibold flex items-center space-x-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    <span>IHH</span>
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

                        <template x-if="view === 'ihh'">
                            <div class="mt-2" x-data="{
                                ihhDate: new Date().toISOString().split('T')[0],
                                allVitals: @json($ihhVitalsData),
                                get filteredVitals() {
                                    const dateStart = new Date(this.ihhDate + 'T00:00:00');
                                    const dateEnd = new Date(this.ihhDate + 'T23:59:59');
                                    return this.allVitals.filter(v => {
                                        const d = new Date(v.recorded_at);
                                        return d >= dateStart && d <= dateEnd;
                                    }).sort((a, b) => new Date(a.recorded_at) - new Date(b.recorded_at));
                                },
                                get hasPrevious() {
                                    const dateStart = new Date(this.ihhDate + 'T00:00:00');
                                    return this.allVitals.some(v => new Date(v.recorded_at) < dateStart);
                                },
                                get hasNext() {
                                    const today = new Date().toISOString().split('T')[0];
                                    if (this.ihhDate >= today) return false;
                                    const dateEnd = new Date(this.ihhDate + 'T23:59:59');
                                    return this.allVitals.some(v => new Date(v.recorded_at) > dateEnd);
                                },
                                get isToday() {
                                    return this.ihhDate === new Date().toISOString().split('T')[0];
                                },
                                prevDay() {
                                    const d = new Date(this.ihhDate);
                                    d.setDate(d.getDate() - 1);
                                    this.ihhDate = d.toISOString().split('T')[0];
                                },
                                nextDay() {
                                    const d = new Date(this.ihhDate);
                                    d.setDate(d.getDate() + 1);
                                    const today = new Date().toISOString().split('T')[0];
                                    if (d.toISOString().split('T')[0] <= today) {
                                        this.ihhDate = d.toISOString().split('T')[0];
                                    }
                                },
                                goToday() {
                                    this.ihhDate = new Date().toISOString().split('T')[0];
                                },
                                formatDate(dateStr) {
                                    const d = new Date(dateStr);
                                    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
                                },
                                formatTime(isoStr) {
                                    const d = new Date(isoStr);
                                    return d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
                                },
                                formatDayMonth(isoStr) {
                                    const d = new Date(isoStr);
                                    return d.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit' });
                                }
                            }">
                                @if($vitalsMode === 'real')
                                    <!-- Date Navigation for IHH Chart -->
                                    <div class="flex items-center justify-between mb-2 bg-gray-50 rounded-lg p-1.5 border border-gray-200">
                                        <button type="button" 
                                                @click="prevDay()"
                                                :disabled="!hasPrevious"
                                                :class="hasPrevious ? 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-100 shadow-sm' : 'bg-gray-100 text-gray-400 cursor-not-allowed'"
                                                class="px-2 py-1 rounded text-xs font-medium transition-all">
                                            <svg class="w-3 h-3 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                            </svg>
                                            Prev
                                        </button>

                                        <div class="text-center">
                                            <div class="text-sm font-bold text-gray-800" x-text="formatDate(ihhDate)"></div>
                                            <div class="text-[10px] text-gray-500">
                                                <span x-show="isToday" class="text-blue-600 font-semibold">Today</span>
                                                <span x-show="!isToday" x-text="ihhDate"></span>
                                            </div>
                                        </div>

                                        <div class="flex items-center space-x-1">
                                            <button type="button" 
                                                    x-show="!isToday"
                                                    @click="goToday()"
                                                    class="px-2 py-1 rounded text-xs font-medium bg-blue-600 text-white hover:bg-blue-700 shadow-sm transition-all">
                                                Today
                                            </button>
                                            <button type="button" 
                                                    @click="nextDay()"
                                                    :disabled="!hasNext || isToday"
                                                    :class="(hasNext && !isToday) ? 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-100 shadow-sm' : 'bg-gray-100 text-gray-400 cursor-not-allowed'"
                                                    class="px-2 py-1 rounded text-xs font-medium transition-all">
                                                Next
                                                <svg class="w-3 h-3 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <template x-if="filteredVitals.length > 0">
                                        <div class="overflow-x-auto border border-gray-300 rounded-lg bg-white">
                                            <!-- Chart Header -->
                                            <div class="bg-gradient-to-r from-indigo-600 to-indigo-700 text-white p-2">
                                                <div class="flex items-center justify-between">
                                                    <div>
                                                        <h3 class="font-bold text-xs">MODIFIED CLINICAL CHART - EARLY WARNING SCORE (EWS)</h3>
                                                        <p class="text-[10px] text-indigo-200" x-text="formatDate(ihhDate)"></p>
                                                    </div>
                                                    <div class="text-right text-[10px]">
                                                        <span class="inline-flex items-center mr-2"><span class="w-2 h-2 bg-red-300 border border-red-400 mr-1"></span>2</span>
                                                        <span class="inline-flex items-center mr-2"><span class="w-2 h-2 bg-orange-200 border border-orange-300 mr-1"></span>1</span>
                                                        <span class="inline-flex items-center"><span class="w-2 h-2 bg-white border border-gray-400 mr-1"></span>0</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <table class="ihh-chart">
                                                <thead>
                                                    <tr class="bg-gray-100">
                                                        <th class="ihh-label-col" rowspan="2">TIME</th>
                                                        <th class="score-col" rowspan="2">S</th>
                                                        <template x-for="vital in filteredVitals" :key="vital.recorded_at">
                                                            <th class="text-[8px] px-1" x-text="formatDayMonth(vital.recorded_at)"></th>
                                                        </template>
                                                    </tr>
                                                    <tr class="bg-gray-50">
                                                        <template x-for="vital in filteredVitals" :key="vital.recorded_at + '_time'">
                                                            <th class="text-[8px] px-1" x-text="formatTime(vital.recorded_at)"></th>
                                                        </template>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <!-- Simplified view showing vital counts per day -->
                                                    <tr>
                                                        <td class="section-header" :colspan="2 + filteredVitals.length"><span class="text-indigo-700">Vitals Summary</span></td>
                                                    </tr>
                                                    <tr>
                                                        <td class="ihh-label-col">Temp</td>
                                                        <td class="score-col"></td>
                                                        <template x-for="vital in filteredVitals" :key="vital.recorded_at + '_temp'">
                                                            <td :class="vital.temperature >= 39 || vital.temperature <= 35 ? 'ihh-temp-high2' : (vital.temperature >= 38 || vital.temperature <= 35.9 ? 'ihh-temp-high1' : 'ihh-temp-normal')">
                                                                <span x-show="vital.temperature" class="text-[9px]" x-text="vital.temperature ? vital.temperature.toFixed(1) : ''"></span>
                                                            </td>
                                                        </template>
                                                    </tr>
                                                    <tr>
                                                        <td class="ihh-label-col">BP</td>
                                                        <td class="score-col"></td>
                                                        <template x-for="vital in filteredVitals" :key="vital.recorded_at + '_bp'">
                                                            <td :class="vital.systolic_bp > 200 || vital.systolic_bp <= 90 ? 'ihh-bp-high2' : (vital.systolic_bp >= 160 || vital.systolic_bp <= 100 ? 'ihh-bp-high1' : 'ihh-bp-normal')">
                                                                <span x-show="vital.systolic_bp" class="text-[9px]" x-text="vital.systolic_bp + '/' + vital.diastolic_bp"></span>
                                                            </td>
                                                        </template>
                                                    </tr>
                                                    <tr>
                                                        <td class="ihh-label-col">PR</td>
                                                        <td class="score-col"></td>
                                                        <template x-for="vital in filteredVitals" :key="vital.recorded_at + '_pr'">
                                                            <td :class="vital.pulse_rate > 120 || vital.pulse_rate <= 40 ? 'ihh-pr-high2' : ((vital.pulse_rate >= 100 && vital.pulse_rate <= 120) || (vital.pulse_rate >= 41 && vital.pulse_rate <= 59) ? 'ihh-pr-high1' : 'ihh-pr-normal')">
                                                                <span x-show="vital.pulse_rate" class="text-[9px]" x-text="vital.pulse_rate"></span>
                                                            </td>
                                                        </template>
                                                    </tr>
                                                    <tr>
                                                        <td class="ihh-label-col">RR</td>
                                                        <td class="score-col"></td>
                                                        <template x-for="vital in filteredVitals" :key="vital.recorded_at + '_rr'">
                                                            <td :class="vital.respiratory_rate > 25 || vital.respiratory_rate <= 8 ? 'ihh-rr-high2' : ((vital.respiratory_rate >= 21 && vital.respiratory_rate <= 25) || (vital.respiratory_rate >= 9 && vital.respiratory_rate <= 11) ? 'ihh-rr-high1' : 'ihh-rr-normal')">
                                                                <span x-show="vital.respiratory_rate" class="text-[9px]" x-text="vital.respiratory_rate"></span>
                                                            </td>
                                                        </template>
                                                    </tr>
                                                    <tr>
                                                        <td class="ihh-label-col">SpO2</td>
                                                        <td class="score-col"></td>
                                                        <template x-for="vital in filteredVitals" :key="vital.recorded_at + '_spo2'">
                                                            <td :class="vital.spo2 <= 91 ? 'ihh-temp-low2' : (vital.spo2 <= 95 ? 'ihh-temp-low1' : 'ihh-temp-normal')">
                                                                <span x-show="vital.spo2" class="text-[9px]" x-text="vital.spo2 + '%'"></span>
                                                            </td>
                                                        </template>
                                                    </tr>
                                                </tbody>
                                            </table>

                                            <div class="p-2 bg-gray-50 border-t text-[8px]">
                                                <div class="text-center text-gray-500">Monitor <b class="text-blue-600">4-6 hourly</b> | If abnormal, <b class="text-red-600">repeat in few mins</b></div>
                                            </div>
                                        </div>
                                    </template>

                                    <template x-if="filteredVitals.length === 0">
                                        <div class="text-center py-6 text-gray-500 border border-dashed border-gray-300 rounded-lg">
                                            <svg class="w-10 h-10 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                            <p class="text-sm">No vital signs on <span x-text="formatDate(ihhDate)"></span></p>
                                            <p class="text-xs text-gray-400 mt-1" x-show="hasPrevious">Use Previous to see earlier recordings</p>
                                            <p class="text-xs text-gray-400 mt-1" x-show="allVitals.length > 0 && !hasPrevious">Patient has <span x-text="allVitals.length"></span> recordings on other days</p>
                                            <p class="text-xs text-gray-400 mt-1" x-show="allVitals.length === 0">No vital signs recorded for this patient yet</p>
                                        </div>
                                    </template>
                                @else
                                    <!-- Demo IHH Chart -->
                                    <div class="overflow-x-auto border border-gray-300 rounded-lg bg-white">
                                        <div class="bg-gradient-to-r from-indigo-600 to-indigo-700 text-white p-2">
                                            <h3 class="font-bold text-xs">MODIFIED CLINICAL CHART - EARLY WARNING SCORE (EWS) - Demo</h3>
                                        </div>
                                        <div class="p-4 text-center text-gray-500">
                                            <svg class="w-12 h-12 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                            <p class="text-sm">IHH Chart requires real vital signs data.</p>
                                            <p class="text-xs text-gray-400 mt-1">Enable real data mode in Settings → Bed Box Config</p>
                                        </div>
                                    </div>
                                @endif
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

                        <div class="space-y-4">
                            <!-- Quick Location Buttons -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-2">Quick Location</label>
                                <div class="grid grid-cols-3 md:grid-cols-5 gap-2 text-xs">
                                    <button type="button"
                                            class="quick-loc-btn px-3 py-2 rounded-lg border-2 border-blue-200 text-blue-700 bg-blue-50 hover:bg-blue-100 hover:border-blue-400 transition-all font-medium"
                                            onclick="selectQuickLocation('Radiology', 'radiology', this)">
                                        <svg class="w-4 h-4 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/></svg>
                                        Radiology
                                    </button>
                                    <button type="button"
                                            class="quick-loc-btn px-3 py-2 rounded-lg border-2 border-purple-200 text-purple-700 bg-purple-50 hover:bg-purple-100 hover:border-purple-400 transition-all font-medium"
                                            onclick="selectQuickLocation('Surgery', 'surgery', this)">
                                        <svg class="w-4 h-4 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
                                        Surgery
                                    </button>
                                    <button type="button"
                                            class="quick-loc-btn px-3 py-2 rounded-lg border-2 border-red-200 text-red-700 bg-red-50 hover:bg-red-100 hover:border-red-400 transition-all font-medium"
                                            onclick="selectQuickLocation('Cath Lab', 'cath_lab', this)">
                                        <svg class="w-4 h-4 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                                        Cath Lab
                                    </button>
                                    <button type="button"
                                            class="quick-loc-btn px-3 py-2 rounded-lg border-2 border-cyan-200 text-cyan-700 bg-cyan-50 hover:bg-cyan-100 hover:border-cyan-400 transition-all font-medium"
                                            onclick="selectQuickLocation('Dialysis', 'dialysis', this)">
                                        <svg class="w-4 h-4 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                                        Dialysis
                                    </button>
                                    <button type="button"
                                            class="quick-loc-btn px-3 py-2 rounded-lg border-2 border-pink-200 text-pink-700 bg-pink-50 hover:bg-pink-100 hover:border-pink-400 transition-all font-medium"
                                            onclick="selectQuickLocation('Heart Centre', 'heart_centre', this)">
                                        <svg class="w-4 h-4 mx-auto mb-1" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                                        Heart Centre
                                    </button>
                                    <button type="button"
                                            class="quick-loc-btn px-3 py-2 rounded-lg border-2 border-teal-200 text-teal-700 bg-teal-50 hover:bg-teal-100 hover:border-teal-400 transition-all font-medium"
                                            onclick="selectQuickLocation('Lung Function Test', 'lung_function', this)">
                                        <svg class="w-4 h-4 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                        Lung Function
                                    </button>
                                    <button type="button"
                                            class="quick-loc-btn px-3 py-2 rounded-lg border-2 border-indigo-200 text-indigo-700 bg-indigo-50 hover:bg-indigo-100 hover:border-indigo-400 transition-all font-medium"
                                            onclick="selectQuickLocation('Specialist Clinic (SCC)', 'specialist_clinic', this)">
                                        <svg class="w-4 h-4 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                        SCC
                                    </button>
                                    <button type="button"
                                            class="quick-loc-btn px-3 py-2 rounded-lg border-2 border-orange-200 text-orange-700 bg-orange-50 hover:bg-orange-100 hover:border-orange-400 transition-all font-medium"
                                            onclick="selectQuickLocation('Rehab', 'rehab', this)">
                                        <svg class="w-4 h-4 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        Rehab
                                    </button>
                                    <button type="button"
                                            class="quick-loc-btn px-3 py-2 rounded-lg border-2 border-emerald-200 text-emerald-700 bg-emerald-50 hover:bg-emerald-100 hover:border-emerald-400 transition-all font-medium"
                                            onclick="selectQuickLocation('Endoscopy', 'endoscopy', this)">
                                        <svg class="w-4 h-4 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        Endoscopy
                                    </button>
                                    <button type="button"
                                            class="quick-loc-btn px-3 py-2 rounded-lg border-2 border-gray-200 text-gray-700 bg-gray-50 hover:bg-gray-100 hover:border-gray-400 transition-all font-medium"
                                            onclick="selectQuickLocation('', 'other', this); document.getElementById('movement_location').focus();">
                                        <svg class="w-4 h-4 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                        Other
                                    </button>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Destination Input -->
                                <div>
                                    <label for="movement_location" class="block text-xs font-semibold text-gray-700 mb-1">
                                        Destination / Location
                                    </label>
                                    <input type="text"
                                           id="movement_location"
                                           name="location"
                                           required
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                           placeholder="Select above or type here">
                                    <input type="hidden" id="movement_location_type" name="location_type" value="">
                                </div>

                                <!-- Date Picker -->
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                                        Scheduled Date
                                    </label>
                                    <input type="date"
                                           id="movement_date"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                           value="{{ now()->format('Y-m-d') }}">
                                </div>
                            </div>

                            <!-- Time Grid Picker -->
                            <div x-data="{ timePeriod: 'morning' }">
                                <label class="block text-xs font-semibold text-gray-700 mb-2">
                                    Scheduled Time <span class="text-gray-400 font-normal">(15-min intervals)</span>
                                </label>
                                <div class="border border-gray-200 rounded-lg p-3 bg-gray-50">
                                    <!-- Time Period Tabs -->
                                    <div class="flex gap-2 mb-3">
                                        <button type="button" 
                                                @click="timePeriod = 'morning'" 
                                                :class="timePeriod === 'morning' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100'"
                                                class="flex-1 px-3 py-2 rounded-lg text-xs font-semibold transition-all border border-gray-200">
                                            🌅 Morning
                                            <span class="block text-[10px] opacity-75">6AM - 12PM</span>
                                        </button>
                                        <button type="button" 
                                                @click="timePeriod = 'afternoon'" 
                                                :class="timePeriod === 'afternoon' ? 'bg-orange-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-100'"
                                                class="flex-1 px-3 py-2 rounded-lg text-xs font-semibold transition-all border border-gray-200">
                                            ☀️ Afternoon
                                            <span class="block text-[10px] opacity-75">12PM - 6PM</span>
                                        </button>
                                        <button type="button" 
                                                @click="timePeriod = 'evening'" 
                                                :class="timePeriod === 'evening' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100'"
                                                class="flex-1 px-3 py-2 rounded-lg text-xs font-semibold transition-all border border-gray-200">
                                            🌙 Evening
                                            <span class="block text-[10px] opacity-75">6PM - 12AM</span>
                                        </button>
                                    </div>

                                    <!-- Morning Times -->
                                    <div x-show="timePeriod === 'morning'" class="grid grid-cols-4 md:grid-cols-6 gap-2">
                                        @php
                                            $morningTimes = [];
                                            for ($h = 6; $h < 12; $h++) {
                                                foreach ([0, 15, 30, 45] as $m) {
                                                    $morningTimes[] = sprintf('%02d:%02d', $h, $m);
                                                }
                                            }
                                        @endphp
                                        @foreach($morningTimes as $time)
                                            <button type="button"
                                                    class="time-slot-btn px-2 py-2 rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-blue-100 hover:border-blue-400 hover:text-blue-700 transition-all text-sm font-medium"
                                                    onclick="selectTime('{{ $time }}', this)">
                                                {{ $time }}
                                            </button>
                                        @endforeach
                                    </div>

                                    <!-- Afternoon Times -->
                                    <div x-show="timePeriod === 'afternoon'" class="grid grid-cols-4 md:grid-cols-6 gap-2">
                                        @php
                                            $afternoonTimes = [];
                                            for ($h = 12; $h < 18; $h++) {
                                                foreach ([0, 15, 30, 45] as $m) {
                                                    $afternoonTimes[] = sprintf('%02d:%02d', $h, $m);
                                                }
                                            }
                                        @endphp
                                        @foreach($afternoonTimes as $time)
                                            <button type="button"
                                                    class="time-slot-btn px-2 py-2 rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-orange-100 hover:border-orange-400 hover:text-orange-700 transition-all text-sm font-medium"
                                                    onclick="selectTime('{{ $time }}', this)">
                                                {{ $time }}
                                            </button>
                                        @endforeach
                                    </div>

                                    <!-- Evening Times -->
                                    <div x-show="timePeriod === 'evening'" class="grid grid-cols-4 md:grid-cols-6 gap-2">
                                        @php
                                            $eveningTimes = [];
                                            for ($h = 18; $h < 24; $h++) {
                                                foreach ([0, 15, 30, 45] as $m) {
                                                    $eveningTimes[] = sprintf('%02d:%02d', $h, $m);
                                                }
                                            }
                                        @endphp
                                        @foreach($eveningTimes as $time)
                                            <button type="button"
                                                    class="time-slot-btn px-2 py-2 rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-indigo-100 hover:border-indigo-400 hover:text-indigo-700 transition-all text-sm font-medium"
                                                    onclick="selectTime('{{ $time }}', this)">
                                                {{ $time }}
                                            </button>
                                        @endforeach
                                    </div>

                                    <!-- Selected Time Display -->
                                    <div class="mt-3 flex items-center justify-between bg-white rounded-lg px-3 py-2 border border-gray-200">
                                        <span class="text-xs text-gray-500">Selected:</span>
                                        <span id="selected_time_display" class="text-sm font-bold text-blue-600">--:--</span>
                                    </div>
                                </div>
                                <!-- Hidden input for form submission -->
                                <input type="hidden" id="movement_time" value="">
                                <input type="hidden" id="movement_scheduled_at" name="scheduled_at" required>
                            </div>
                        </div>

                        <script>
                            function selectQuickLocation(location, type, btn) {
                                document.getElementById('movement_location').value = location;
                                document.getElementById('movement_location_type').value = type;

                                // Remove active state from all quick location buttons
                                document.querySelectorAll('.quick-loc-btn').forEach(b => {
                                    b.classList.remove('ring-2', 'ring-offset-2', 'ring-blue-500');
                                });

                                // Add active state to clicked button
                                btn.classList.add('ring-2', 'ring-offset-2', 'ring-blue-500');
                            }

                            function selectTime(time, btn) {
                                document.getElementById('movement_time').value = time;
                                document.getElementById('selected_time_display').textContent = time;

                                // Remove active state from all time buttons
                                document.querySelectorAll('.time-slot-btn').forEach(b => {
                                    b.classList.remove('bg-blue-600', 'bg-orange-500', 'bg-indigo-600', 'text-white', 'border-blue-600', 'border-orange-500', 'border-indigo-600');
                                });

                                // Add active state to clicked button
                                btn.classList.add('bg-blue-600', 'text-white', 'border-blue-600');

                                // Update hidden scheduled_at field
                                updateScheduledAt();
                            }

                            function updateScheduledAt() {
                                const date = document.getElementById('movement_date').value;
                                const time = document.getElementById('movement_time').value;
                                if (date && time) {
                                    document.getElementById('movement_scheduled_at').value = date + 'T' + time;
                                }
                            }

                            // Update scheduled_at when date changes
                            document.getElementById('movement_date').addEventListener('change', updateScheduledAt);
                        </script>

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

<script>
    function recordHgtReading() {
        const valueInput = document.getElementById('hgt_value');
        const value = parseFloat(valueInput.value);
        
        if (isNaN(value) || value <= 0 || value > 50) {
            alert('Please enter a valid HGT value between 0.1 and 50 mmol/L');
            return;
        }
        
        const patientId = {{ $patient->id }};
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || 
                         document.querySelector('input[name="_token"]')?.value;
        
        fetch('{{ route("ward.save-sugar-reading") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                patient_id: patientId,
                value: value
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Reload the page to show the new reading
                window.location.reload();
            } else {
                alert('Failed to record HGT reading: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error recording HGT:', error);
            alert('Failed to record HGT reading. Please try again.');
        });
    }
</script>
</body>
</html>


