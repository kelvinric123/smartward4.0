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
        $patientTabs = $patientDetailsTabs ?? [
            'info' => true,
            'additional' => true,
            'vitals' => true,
            'movement' => true,
            'referral' => true,
            'infusion' => true,
            'transfer' => true,
            'discharge' => true,
        ];
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
                        Vital Signs (Demo)
                    </button>
                    <button type="button"
                        @click="activeTab = 'movement'"
                        x-show="patientTabs.movement"
                        :class="activeTab === 'movement' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-2 px-3 border-b-2 font-medium">
                        Patient Movement
                    </button>
                    <button type="button"
                        @click="activeTab = 'referral'"
                        x-show="patientTabs.referral"
                        :class="activeTab === 'referral' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-2 px-3 border-b-2 font-medium">
                        Referral
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
                        allergies: @json($allergyStrings),
                        newAllergy: '',
                        addAllergy() {
                            if (this.newAllergy.trim() && !this.allergies.includes(this.newAllergy.trim())) {
                                this.allergies.push(this.newAllergy.trim());
                                this.newAllergy = '';
                            }
                        },
                        removeAllergy(index) {
                            this.allergies.splice(index, 1);
                        }
                     }">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Patient Additional Info</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        Manage clinical indicators and patient care information.
                    </p>

                    <form method="POST" action="{{ route('ward.update-patient-clinical') }}" class="space-y-6">
                        @csrf
                        <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                        <input type="hidden" name="active_tab" value="additional">
                        
                        <!-- Hidden field to store allergies array -->
                        <template x-for="(allergy, index) in allergies" :key="index">
                            <input type="hidden" name="allergies[]" :value="allergy">
                        </template>

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

                            <!-- Diet Type -->
                            <div>
                                <label for="diet_type" class="block text-sm font-semibold text-gray-700 mb-2">
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 mr-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                        </svg>
                                        Diet Type
                                    </div>
                                </label>
                                <select id="diet_type" name="diet_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                    @foreach($clinicalIndicatorOptions['diet_type'] ?? [] as $option)
                                        <option value="{{ $option['value'] }}" {{ ($patient->diet_type ?? 'regular') === $option['value'] ? 'selected' : '' }}>
                                            {{ $option['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-500">Patient dietary requirements</p>
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

                        <!-- Medical Allergies -->
                        <div class="border-t pt-6">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 mr-2 text-pink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                    </svg>
                                    Medical Allergies
                                </div>
                            </label>
                            <p class="text-xs text-gray-500 mb-3">Add known allergies for this patient</p>
                            
                            <!-- Add New Allergy -->
                            <div class="flex items-center space-x-2 mb-3">
                                <input type="text"
                                       x-model="newAllergy"
                                       @keydown.enter.prevent="addAllergy()"
                                       placeholder="Enter allergy (e.g., Penicillin, Latex)"
                                       class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                <button type="button"
                                        @click="addAllergy()"
                                        class="inline-flex items-center px-3 py-2 bg-pink-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-pink-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-pink-500">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    Add
                                </button>
                            </div>
                            
                            <!-- Current Allergies List -->
                            <div class="flex flex-wrap gap-2" x-show="allergies.length > 0">
                                <template x-for="(allergy, index) in allergies" :key="index">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-pink-100 text-pink-800">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                        </svg>
                                        <span x-text="allergy"></span>
                                        <button type="button" @click="removeAllergy(index)" class="ml-2 text-pink-600 hover:text-pink-800">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </span>
                                </template>
                            </div>
                            <p x-show="allergies.length === 0" class="text-xs text-gray-400 italic">No allergies recorded</p>
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
                            <div class="grid grid-cols-2 md:grid-cols-6 gap-3 text-xs">
                                <div class="rounded-lg p-3 text-center {{ ($patient->status === 'pending_discharge' || $patient->pending_discharge_at) ? 'bg-green-100 border-2 border-green-300' : 'bg-gray-50' }}">
                                    <div class="{{ ($patient->status === 'pending_discharge' || $patient->pending_discharge_at) ? 'text-green-700' : 'text-gray-600' }} font-semibold">Status</div>
                                    <div class="text-gray-800 mt-1 capitalize font-medium">
                                        @if($patient->status === 'pending_discharge' || $patient->pending_discharge_at)
                                            Pending DC
                                        @else
                                            {{ str_replace('_', ' ', $patient->status ?? 'Unknown') }}
                                        @endif
                                    </div>
                                </div>
                                <div class="bg-purple-50 rounded-lg p-3 text-center">
                                    <div class="text-purple-600 font-semibold">Nursing Level</div>
                                    <div class="text-gray-800 mt-1 capitalize">{{ str_replace('_', ' ', $patient->nursing_level ?? 'None') }}</div>
                                </div>
                                <div class="bg-red-50 rounded-lg p-3 text-center">
                                    <div class="text-red-600 font-semibold">Diet</div>
                                    <div class="text-gray-800 mt-1 capitalize">{{ str_replace('_', ' ', $patient->diet_type ?? 'Regular') }}</div>
                                </div>
                                <div class="bg-orange-50 rounded-lg p-3 text-center">
                                    <div class="text-orange-600 font-semibold">Fall Risk</div>
                                    <div class="text-gray-800 mt-1 capitalize">{{ str_replace('_', ' ', $patient->fall_risk ?? 'None') }}</div>
                                </div>
                                <div class="bg-yellow-50 rounded-lg p-3 text-center">
                                    <div class="text-yellow-700 font-semibold">Isolation</div>
                                    <div class="text-gray-800 mt-1 capitalize">{{ str_replace('_', ' ', $patient->isolation_type ?? 'None') }}</div>
                                </div>
                                <div class="bg-pink-50 rounded-lg p-3 text-center">
                                    <div class="text-pink-600 font-semibold">Allergies</div>
                                    <div class="text-gray-800 mt-1">{{ count($patient->allergies ?? []) }} recorded</div>
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

                <!-- Vital Signs Demo + Graph -->
                <div x-show="activeTab === 'vitals'" x-cloak
                     x-data="{
                        view: 'table',
                        chartInstance: null,
                        init() {
                            this.$watch('view', (val) => {
                                if (val === 'graph') {
                                    this.renderChart();
                                }
                            });
                        },
                        renderChart() {
                            if (this.chartInstance || typeof Chart === 'undefined') {
                                return;
                            }
                            const ctx = this.$refs.vitalsChart.getContext('2d');
                            const labels = ['Yesterday 20:00', 'Today 04:00', 'Today 08:00'];
                            const heartRate = [78, 84, 80];
                            const spo2 = [98, 97, 98];
                            const systolic = [116, 124, 118];
                            const diastolic = [74, 80, 76];
                            const temperature = [36.9, 37.2, 37.0];

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
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">Vital Signs (Demo)</h3>
                            <p class="text-sm text-gray-600">
                                Demo data only. Replace with live vitals integration later.
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
                        </div>
                    </template>

                    <template x-if="view === 'graph'">
                        <div class="mt-2 h-72 md:h-80">
                            <canvas x-ref="vitalsChart"></canvas>
                        </div>
                    </template>
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

                <!-- Referral -->
                <div x-show="activeTab === 'referral'" x-cloak x-data="{ referralType: 'consultant' }">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Referral</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        Use this tab to refer the patient to additional consultants or anaesthetists.
                        A patient can have more than one consultant or anaesthetist.
                    </p>

                    <!-- New Referral Form -->
                    <form method="POST" action="{{ route('ward.patient-referrals.store') }}" class="mb-6 space-y-4">
                        @csrf
                        <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                        <input type="hidden" name="active_tab" value="referral">

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">
                                    Referral Type
                                </label>
                                <div class="flex items-center space-x-4 text-xs">
                                    <label class="inline-flex items-center space-x-1 cursor-pointer">
                                        <input type="radio"
                                               class="text-blue-600 border-gray-300 focus:ring-blue-500"
                                               name="referral_type"
                                               value="consultant"
                                               x-model="referralType">
                                        <span>Consultant</span>
                                    </label>
                                    <label class="inline-flex items-center space-x-1 cursor-pointer">
                                        <input type="radio"
                                               class="text-blue-600 border-gray-300 focus:ring-blue-500"
                                               name="referral_type"
                                               value="anaesthetist"
                                               x-model="referralType">
                                        <span>Anaesthetist</span>
                                    </label>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">
                                    Target Consultant / Anaesthetist
                                </label>
                                <div x-show="referralType === 'consultant'" x-cloak>
                                    <select name="consultant_id"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                        <option value="">Select consultant...</option>
                                        @foreach($consultants as $consultant)
                                            <option value="{{ $consultant->id }}">
                                                {{ $consultant->name }} ({{ $consultant->specialty->name ?? 'No Specialty' }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div x-show="referralType === 'anaesthetist'" x-cloak>
                                    <select name="anaesthetist_id"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                        <option value="">Select anaesthetist...</option>
                                        @foreach($anaesthetists as $anaesthetist)
                                            <option value="{{ $anaesthetist->id }}">
                                                {{ $anaesthetist->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label for="referral_reason" class="block text-xs font-semibold text-gray-700 mb-1">
                                    Reason (optional)
                                </label>
                                <input type="text"
                                       id="referral_reason"
                                       name="reason"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                       placeholder="e.g. Second opinion, pre-op assessment">
                            </div>
                        </div>

                        <div>
                            <label for="referral_notes" class="block text-xs font-semibold text-gray-700 mb-1">
                                Notes (optional)
                            </label>
                            <textarea id="referral_notes"
                                      name="notes"
                                      rows="2"
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                      placeholder="Add any specific questions or instructions for the referred clinician"></textarea>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 4v16m8-8H4"/>
                                </svg>
                                Add Referral
                            </button>
                        </div>
                    </form>

                    <!-- Existing Referrals -->
                    <div class="space-y-3 text-sm">
                        <h4 class="font-semibold text-gray-800 flex items-center">
                            <svg class="w-4 h-4 mr-1 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            Existing Referrals
                        </h4>

                        @php
                            $referrals = $patient->referrals ?? collect();
                        @endphp

                        @if($referrals->count() === 0)
                            <div class="border border-dashed border-gray-300 rounded-lg p-4 text-xs text-gray-500">
                                No referrals recorded yet for this patient.
                            </div>
                        @else
                            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                                <table class="min-w-full text-xs">
                                    <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600 border-b">Type</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600 border-b">Referred To</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600 border-b">Reason</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600 border-b">Notes</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600 border-b">Status</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600 border-b">Created At</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($referrals as $referral)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-3 py-2 border-b align-top capitalize">
                                                {{ $referral->referral_type }}
                                            </td>
                                            <td class="px-3 py-2 border-b align-top">
                                                @if($referral->referral_type === 'consultant' && $referral->consultant)
                                                    <div class="font-semibold text-gray-900">
                                                        {{ $referral->consultant->name }}
                                                    </div>
                                                    <div class="text-[11px] text-gray-500">
                                                        {{ $referral->consultant->specialty->name ?? 'No Specialty' }}
                                                    </div>
                                                @elseif($referral->referral_type === 'anaesthetist' && $referral->anaesthetist)
                                                    <div class="font-semibold text-gray-900">
                                                        {{ $referral->anaesthetist->name }}
                                                    </div>
                                                    <div class="text-[11px] text-gray-500">
                                                        Anaesthetist
                                                    </div>
                                                @else
                                                    <span class="text-gray-400">—</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 border-b align-top max-w-xs">
                                                <div class="text-xs text-gray-800 whitespace-pre-line">
                                                    {{ $referral->reason ?? '-' }}
                                                </div>
                                            </td>
                                            <td class="px-3 py-2 border-b align-top max-w-xs">
                                                <div class="text-xs text-gray-700 whitespace-pre-line">
                                                    {{ $referral->notes ?? '-' }}
                                                </div>
                                            </td>
                                            <td class="px-3 py-2 border-b align-top">
                                                @php
                                                    $statusClasses = [
                                                        'active' => 'bg-green-100 text-green-800',
                                                        'completed' => 'bg-blue-100 text-blue-800',
                                                        'cancelled' => 'bg-gray-100 text-gray-800',
                                                    ];
                                                @endphp
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-[11px] font-semibold {{ $statusClasses[$referral->status] ?? 'bg-gray-100 text-gray-800' }}">
                                                    {{ ucfirst($referral->status) }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 border-b align-top">
                                                <div class="text-xs text-gray-700">
                                                    {{ $referral->created_at ? $referral->created_at->format('Y-m-d H:i') : '-' }}
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
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


