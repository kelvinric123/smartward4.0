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
    @include('components.autofill-guard')
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
            'io' => true,
            'medications' => false,
            'movement' => true,
            'careprovider' => true,
            'anaesthetist' => true,
            'nurses' => true,
            'infusion' => true,
            'transfer' => true,
            'discharge' => true,
            'discharge_summary' => true,
            'nursing_plan' => true,
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

        // Consultant tab: care providers (ADT + manually added) grouped by role. Anaesthetists
        // are listed on their own tab, so providers linked to one are left out here.
        $attendingDoctors = $patient ? $patient->activeCareProviders()->where('role', PatientCareProvider::ROLE_ATTENDING)->whereNull('anaesthetist_id')->get() : collect();
        $referringDoctors = $patient ? $patient->activeCareProviders()->where('role', PatientCareProvider::ROLE_REFERRING)->whereNull('anaesthetist_id')->get() : collect();
        $consultingDoctors = $patient ? $patient->activeCareProviders()->where('role', PatientCareProvider::ROLE_CONSULTING)->whereNull('anaesthetist_id')->get() : collect();

        // Consultants selectable when adding a care provider by hand
        $assignableConsultants = \App\Models\Consultant::where('is_active', true)
            ->with('specialty')
            ->orderBy('name')
            ->get();

        // Anaesthetist tab: care providers linked to an anaesthetist (from ADT or added here),
        // plus the anaesthetist on the patient record (set on admission or by ADT)
        $anaesthetistTeam = collect();
        $assignableAnaesthetists = collect();
        if ($patient && ($patientTabs['anaesthetist'] ?? false)) {
            $anaesthetistProviders = $patient->activeCareProviders()
                ->whereNotNull('anaesthetist_id')
                ->with('anaesthetist')
                ->orderBy('assigned_at')
                ->get();

            $anaesthetistTeam = $anaesthetistProviders->map(fn ($provider) => [
                'name' => $provider->display_name,
                'code' => $provider->anaesthetist?->personnel_code ?? $provider->doctor_code,
                'phone' => $provider->anaesthetist?->phone,
                'source' => $provider->source === PatientCareProvider::SOURCE_MANUAL ? 'manual' : 'adt',
                'role' => $provider->role_label,
                'provider' => $provider,
            ]);

            if ($patient->anaesthetist && !$anaesthetistProviders->contains('anaesthetist_id', $patient->anaesthetist_id)) {
                $anaesthetistTeam->prepend([
                    'name' => $patient->anaesthetist->name,
                    'code' => $patient->anaesthetist->personnel_code,
                    'phone' => $patient->anaesthetist->phone,
                    'source' => 'record',
                    'role' => null,
                    'provider' => null,
                ]);
            }

            $assignableAnaesthetists = \App\Models\Anaesthetist::where('is_active', true)->orderBy('name')->get();
        }

        // Nurses tab: straight from the ward roster for the patient's bed
        $nurseRoster = $patient && ($patientTabs['nurses'] ?? false)
            ? \App\Services\PatientRoster::forPatient($patient)
            : null;

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
                    @if(($patientTabs['io'] ?? false) && ($fluidBalance ?? null))
                        @php
                            // Worst flag on the I/O chart: over the limit, low urine, overload signs, weight gain
                            $ioAlertLevel = $fluidBalance['status']['alerts'][0]['level'] ?? null;
                        @endphp
                        <button type="button"
                            @click="activeTab = 'io'"
                            :class="activeTab === 'io' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-2 px-3 border-b-2 font-medium">
                            I/O Chart
                            @if(in_array($ioAlertLevel, ['critical', 'warning'], true))
                                <span class="ml-1 inline-block h-2 w-2 rounded-full align-middle {{ $ioAlertLevel === 'critical' ? 'bg-red-500' : 'bg-amber-400' }}"
                                    title="{{ $fluidBalance['status']['alerts'][0]['title'] }}"></span>
                            @endif
                        </button>
                    @endif
                    @if($patientTabs['medications'] ?? false)
                        @php
                            $overdueMedicationCount = ($medicationOrders ?? collect())
                                ->filter(fn ($order) => $order->dueState() === 'overdue')
                                ->count();
                        @endphp
                        <button type="button"
                            @click="activeTab = 'medications'"
                            :class="activeTab === 'medications' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-2 px-3 border-b-2 font-medium">
                            Medications
                            @if($overdueMedicationCount > 0)
                                <span class="ml-1 inline-flex items-center justify-center min-w-[1.25rem] px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-red-600 text-white"
                                    title="{{ $overdueMedicationCount }} overdue">{{ $overdueMedicationCount }}</span>
                            @endif
                        </button>
                    @endif
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
                        Consultant
                    </button>
                    <button type="button"
                        @click="activeTab = 'anaesthetist'"
                        x-show="patientTabs.anaesthetist"
                        :class="activeTab === 'anaesthetist' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-2 px-3 border-b-2 font-medium">
                        Anaesthetist
                    </button>
                    <button type="button"
                        @click="activeTab = 'nurses'"
                        x-show="patientTabs.nurses"
                        :class="activeTab === 'nurses' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-2 px-3 border-b-2 font-medium">
                        Nurses
                    </button>
                    <button type="button"
                        @click="activeTab = 'infusion'"
                        x-show="patientTabs.infusion"
                        :class="activeTab === 'infusion' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-2 px-3 border-b-2 font-medium">
                        Infusion Management
                    </button>
                    <button type="button"
                        @click="activeTab = 'transfusion'"
                        :class="activeTab === 'transfusion' ? 'border-red-500 text-red-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-2 px-3 border-b-2 font-medium">
                        Blood Transfusion
                    </button>
                    @php
                        // Loaded once here for the badge, and reused by the tab (wards.partials.consultant-orders)
                        $consultantOrderTab = \App\Models\ConsultantOrder::tabFor($patient);
                        $openOrderCount = $consultantOrderTab['open']->count();
                        $openStatOrderCount = $consultantOrderTab['open']->where('urgency', 'stat')->count();
                    @endphp
                    <button type="button"
                        @click="activeTab = 'orders'"
                        :class="activeTab === 'orders' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-2 px-3 border-b-2 font-medium">
                        Consultant Orders
                        @if($openOrderCount > 0)
                            <span class="ml-1 inline-flex items-center justify-center min-w-[1.25rem] px-1.5 py-0.5 rounded-full text-[10px] font-bold {{ $openStatOrderCount > 0 ? 'bg-red-600 text-white' : 'bg-indigo-100 text-indigo-800' }}"
                                title="{{ $openOrderCount }} open{{ $openStatOrderCount > 0 ? ', ' . $openStatOrderCount . ' STAT' : '' }}">{{ $openOrderCount }}</span>
                        @endif
                    </button>
                    @if($patientTabs['nursing_plan'] ?? false)
                        @php
                            // Loaded once here for the badge, and reused by the tab (wards.partials.nursing-plan)
                            $nursingPlanTab = [
                                'care_plan' => \App\Services\NursingPlan\NursingCarePlan::forPatient($patient),
                                'shift' => \App\Services\NursingPlan\ShiftTasks::forPatient($patient),
                            ];
                            $shiftOverdueCount = $nursingPlanTab['shift']['counts']['overdue'];
                            $carePlanDueCount = $nursingPlanTab['care_plan']['due_evaluations'];
                        @endphp
                        <button type="button"
                            @click="activeTab = 'nursing_plan'"
                            :class="activeTab === 'nursing_plan' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-2 px-3 border-b-2 font-medium">
                            Nursing Plan
                            @if($shiftOverdueCount > 0)
                                <span class="ml-1 inline-flex items-center justify-center min-w-[1.25rem] px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-red-600 text-white"
                                    title="{{ $shiftOverdueCount }} overdue this shift">{{ $shiftOverdueCount }}</span>
                            @elseif($carePlanDueCount > 0)
                                <span class="ml-1 inline-flex items-center justify-center min-w-[1.25rem] px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 text-indigo-800"
                                    title="{{ $carePlanDueCount }} to evaluate this shift">{{ $carePlanDueCount }}</span>
                            @endif
                        </button>
                    @endif
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
                    @if($patientTabs['discharge_summary'] ?? false)
                        <button type="button"
                            @click="activeTab = 'discharge_summary'"
                            :class="activeTab === 'discharge_summary' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-2 px-3 border-b-2 font-medium">
                            Discharge Summary
                        </button>
                    @endif

                    {{-- One tab per clinical indicator bound to this ward's ward type --}}
                    @foreach ($wardClinicalIndicators ?? [] as $indicator)
                        <button type="button"
                            @click="activeTab = 'indicator-{{ $indicator['id'] }}'"
                            :class="activeTab === 'indicator-{{ $indicator['id'] }}' ? 'border-violet-500 text-violet-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-2 px-3 border-b-2 font-medium"
                            title="{{ $indicator['name'] }}{{ $indicator['monitoring'] ? ' - ' . $indicator['monitoring']['label'] : '' }}">
                            {{ $indicator['code'] }}
                            @if (in_array($indicator['monitoring']['state'] ?? null, ['due', 'overdue'], true))
                                <span class="ml-1 inline-block h-2 w-2 rounded-full align-middle {{ $indicator['monitoring']['state'] === 'overdue' ? 'bg-red-500' : 'bg-amber-400' }}"></span>
                            @endif
                        </button>
                    @endforeach
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

                    // Same list plus each entry exactly as stored, so an edit keeps ADT entries (and their status) intact
                    $allergyEntries = collect($patient->allergies ?? [])->values()->map(fn ($raw, $i) => $allergyList[$i] + [
                        'raw' => json_encode($raw),
                    ])->all();

                    // Where each field is maintained (Settings > Patient Additional Info)
                    $readOnlyInfo = (bool) ($additionalInfoReadOnly ?? false);
                    $sourceOf = fn (string $field) => $infoSources[$field] ?? \App\Services\PatientInfoSources::FIELDS[$field][1];
                    $fromAdt = fn (string $field) => $sourceOf($field) === \App\Services\PatientInfoSources::ADT;
                    $canEdit = fn (string $field) => !$fromAdt($field) && !$readOnlyInfo;
                    $adtFields = collect(\App\Services\PatientInfoSources::FIELDS)
                        ->filter(fn ($meta, $field) => $fromAdt($field))
                        ->map(fn ($meta) => $meta[0])
                        ->values();
                    $optionLabel = function (string $group, $value) use ($clinicalIndicatorOptions) {
                        foreach ($clinicalIndicatorOptions[$group] ?? [] as $option) {
                            if ((string) $option['value'] === (string) $value) {
                                return $option['label'];
                            }
                        }
                        return null;
                    };
                @endphp
                <div x-show="activeTab === 'additional'" x-cloak
                     x-data="{
                        allergies: @json($allergyList)
                     }">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Patient Additional Info</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        View clinical indicators and patient care information.
                        @if($adtFields->isNotEmpty())
                            {{ $adtFields->implode(', ') }} {{ $adtFields->count() === 1 ? 'is' : 'are' }} managed by the ADT system.
                        @endif
                    </p>

                    @if($additionalInfoReadOnly ?? false)
                        <div class="mb-4 p-3 bg-orange-50 border border-orange-200 rounded-lg">
                            <div class="flex items-center text-sm text-orange-700">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                                <span class="font-medium">Read Only Mode</span>
                                <span class="ml-1">- Clinical indicators cannot be modified.</span>
                            </div>
                        </div>
                    @endif

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
                                        @if($fromAdt('nursing_level'))
                                            <span class="ml-2 text-xs font-normal text-gray-400">(from ADT)</span>
                                        @endif
                                    </div>
                                </label>
                                @if($canEdit('nursing_level'))
                                    <select id="nursing_level" name="nursing_level" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                        @foreach($clinicalIndicatorOptions['nursing_level'] ?? [] as $option)
                                            <option value="{{ $option['value'] }}" {{ ($patient->nursing_level ?? 'none') === $option['value'] ? 'selected' : '' }}>
                                                {{ $option['label'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                @else
                                    <div class="mt-1 block w-full rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm shadow-sm">
                                        {{ $optionLabel('nursing_level', $patient->nursing_level ?? 'none') ?? \Illuminate\Support\Str::headline($patient->nursing_level ?? 'none') }}
                                    </div>
                                @endif
                                <p class="mt-1 text-xs text-gray-500">Patient care level classification</p>
                            </div>

                            <!-- Diet -->
                            <div class="md:col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 mr-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                        </svg>
                                        Diet
                                        @if($fromAdt('diet'))
                                            <span class="ml-2 text-xs font-normal text-gray-400">(from ADT)</span>
                                        @endif
                                    </div>
                                </label>
                                @php
                                    $currentDietCodes = collect($patient->diet_types ?? [])->map(fn ($code) => strtoupper((string) $code));
                                    $isNbm = $currentDietCodes->intersect(\App\Models\Patient::NBM_DIET_CODES)->isNotEmpty();
                                    $currentRoutes = $patient->feeding_routes ?? [];
                                @endphp
                                @if($canEdit('diet'))
                                    @php
                                        $selectedDiets = $currentDietCodes->reject(fn ($code) => in_array($code, \App\Models\Patient::NBM_DIET_CODES, true))->values()->all();
                                        // Codes on the patient that are no longer in the active list still get a box, so saving never drops them
                                        $dietChoices = $dietTypeOptions->mapWithKeys(fn ($diet) => [$diet->code => $diet->name]);
                                        foreach ($selectedDiets as $code) {
                                            if (!$dietChoices->has($code)) {
                                                $dietChoices[$code] = DietType::getDisplayName($code);
                                            }
                                        }
                                    @endphp
                                    <input type="hidden" name="diet_managed" value="1">
                                    <div class="space-y-4 rounded-lg border border-gray-200 bg-gray-50/60 p-4">
                                        <!-- Nil By Mouth -->
                                        <label class="flex items-center justify-between gap-4 rounded-lg border border-red-100 bg-white px-3 py-2 cursor-pointer">
                                            <span>
                                                <span class="block text-sm font-semibold text-gray-800">Nil By Mouth (NBM)</span>
                                                <span class="block text-xs text-gray-500">No food or drink by mouth</span>
                                            </span>
                                            <input type="checkbox" name="nbm" value="1" @checked($isNbm)
                                                class="h-5 w-5 rounded border-gray-300 text-red-600 focus:ring-red-500">
                                        </label>

                                        <!-- Tube / parenteral feeding -->
                                        <div>
                                            <p class="text-xs font-semibold text-gray-600 mb-2">Tube / Parenteral Feeding</p>
                                            <div class="flex flex-wrap gap-2">
                                                @foreach(\App\Models\Patient::FEEDING_ROUTES as $routeValue => $routeLabel)
                                                    <label class="inline-flex items-center gap-2 rounded-full border border-gray-300 bg-white px-3 py-1.5 text-sm cursor-pointer hover:border-blue-400">
                                                        <input type="checkbox" name="feeding_routes[]" value="{{ $routeValue }}" @checked(in_array($routeValue, $currentRoutes, true))
                                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                        {{ $routeLabel }}
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>

                                        <!-- Diet type -->
                                        <div x-data="{ search: '' }">
                                            <div class="flex items-center justify-between gap-3 mb-2">
                                                <p class="text-xs font-semibold text-gray-600">Diet Type</p>
                                                <input type="search" x-model="search" placeholder="Filter diets..." autocomplete="off"
                                                    class="w-44 rounded-md border-gray-300 text-xs py-1 focus:border-blue-500 focus:ring-blue-500">
                                            </div>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-1 max-h-48 overflow-y-auto rounded-md border border-gray-200 bg-white p-2">
                                                @foreach($dietChoices as $dietCode => $dietName)
                                                    <label class="flex items-center gap-2 rounded px-2 py-1 text-sm cursor-pointer hover:bg-gray-50"
                                                        x-show="search === '' || $el.textContent.toLowerCase().includes(search.toLowerCase())">
                                                        <input type="checkbox" name="diet_types[]" value="{{ $dietCode }}" @checked(in_array($dietCode, $selectedDiets, true))
                                                            class="rounded border-gray-300 text-cyan-600 focus:ring-cyan-500">
                                                        <span>{{ $dietName }} <span class="text-[10px] text-gray-400">{{ $dietCode }}</span></span>
                                                    </label>
                                                @endforeach
                                            </div>
                                            <p class="mt-1 text-xs text-gray-400">No diet type selected means a regular diet.</p>
                                        </div>

                                        <!-- Diet orders -->
                                        <div>
                                            <label for="diet_orders" class="block text-xs font-semibold text-gray-600 mb-1">Diet Orders</label>
                                            <textarea id="diet_orders" name="diet_orders" rows="2" maxlength="1000"
                                                placeholder="e.g. Clear fluids until 6pm, NBM from midnight for OT"
                                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">{{ $patient->diet_orders }}</textarea>
                                        </div>
                                    </div>
                                @else
                                    <p class="text-xs text-gray-500 mb-2">{{ $fromAdt('diet') ? 'Diet types are managed by ADT system' : 'Read only' }}</p>
                                    <div class="flex flex-wrap gap-2">
                                        @if($currentDietCodes->isNotEmpty())
                                            @foreach($currentDietCodes as $dietCode)
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
                                        @foreach($currentRoutes as $routeValue)
                                            <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium bg-indigo-100 text-indigo-800 border border-indigo-200">
                                                {{ \App\Models\Patient::FEEDING_ROUTES[$routeValue] ?? strtoupper($routeValue) }}
                                            </span>
                                        @endforeach
                                    </div>
                                    @if($patient->diet_orders)
                                        <p class="mt-2 text-sm text-gray-700"><span class="font-semibold">Diet orders:</span> {{ $patient->diet_orders }}</p>
                                    @endif
                                @endif
                            </div>

                            <!-- Fall Risk Alert -->
                            <div>
                                <label for="fall_risk" class="block text-sm font-semibold text-gray-700 mb-2">
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 mr-2 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                        </svg>
                                        Fall Risk Alert
                                        @if($fromAdt('fall_risk'))
                                            <span class="ml-2 text-xs font-normal text-gray-400">(from ADT)</span>
                                        @endif
                                    </div>
                                </label>
                                @if($canEdit('fall_risk'))
                                    @php
                                        // ADT sends 1 / 0; show those as the matching ward option
                                        $fallCurrent = match ((string) $patient->fall_risk) {
                                            '1', 'yes' => 'alert_active',
                                            '0', '' => 'none',
                                            default => (string) $patient->fall_risk,
                                        };
                                    @endphp
                                    <select id="fall_risk" name="fall_risk" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                        @foreach($clinicalIndicatorOptions['fall_risk'] ?? [] as $option)
                                            <option value="{{ $option['value'] }}" @selected($fallCurrent === (string) $option['value'])>{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    @php
                                        $fallRiskVal = $patient->fall_risk;
                                        $isFallRisk = $fallRiskVal === '1' || $fallRiskVal === 'yes' || $fallRiskVal === true;
                                        $fallRiskLabel = $isFallRisk ? 'Alert Active' : 'No Risk';
                                        $fallRiskColor = $isFallRisk ? 'red' : 'green';
                                        // Handle legacy/other values
                                        if ($fallRiskVal !== '0' && $fallRiskVal !== '1' && $fallRiskVal !== 'none' && !empty($fallRiskVal)) {
                                            $fallRiskLabel = $optionLabel('fall_risk', $fallRiskVal) ?? ucfirst($fallRiskVal);
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
                                @endif
                                <p class="mt-1 text-xs text-gray-500">Patient fall risk{{ $fromAdt('fall_risk') ? ' (managed by ADT)' : '' }}</p>
                            </div>

                            <!-- Isolation Precautions -->
                            <div>
                                <label for="isolation_type" class="block text-sm font-semibold text-gray-700 mb-2">
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 mr-2 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                        </svg>
                                        Isolation Precautions
                                        @if($fromAdt('isolation'))
                                            <span class="ml-2 text-xs font-normal text-gray-400">(from ADT)</span>
                                        @endif
                                    </div>
                                </label>
                                @if($canEdit('isolation'))
                                    <select id="isolation_type" name="isolation_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                        @foreach($clinicalIndicatorOptions['isolation_type'] ?? [] as $option)
                                            <option value="{{ $option['value'] }}" {{ ($patient->isolation_type ?? 'none') === $option['value'] ? 'selected' : '' }}>
                                                {{ $option['label'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                @else
                                    <div class="mt-1 block w-full rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm shadow-sm">
                                        @if($patient->isolation_type && $patient->isolation_type !== 'none')
                                            {{ $optionLabel('isolation_type', $patient->isolation_type) ?? IsolationType::getDisplayName($patient->isolation_type) }}
                                        @else
                                            {{ $optionLabel('isolation_type', 'none') ?? 'No Isolation Precaution' }}
                                        @endif
                                    </div>
                                @endif
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
                                    @if($fromAdt('allergies'))
                                        <span class="ml-2 text-xs font-normal text-gray-400">(from ADT)</span>
                                    @endif
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

                            @if($canEdit('allergies'))
                                <input type="hidden" name="allergies_managed" value="1">
                                <div x-data="{ entries: @js($allergyEntries) }">
                                    <div class="flex flex-wrap gap-2">
                                        <template x-for="(entry, index) in entries" :key="index">
                                            <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium border"
                                                  :class="entry.status === 'Resolved' ? 'bg-green-100 text-green-800 border-green-200' : 'bg-pink-100 text-pink-800 border-pink-200'">
                                                <span x-text="entry.name"></span>
                                                <span class="ml-1 text-xs font-semibold" x-text="entry.status === 'Resolved' ? '(Resolved)' : ''"></span>
                                                <button type="button" @click="entries.splice(index, 1)" title="Remove"
                                                    class="ml-2 -mr-1 rounded-full px-1 text-current opacity-60 hover:opacity-100">&times;</button>
                                                <input type="hidden" name="allergies_kept[]" :value="entry.raw">
                                            </span>
                                        </template>
                                        <p x-show="entries.length === 0" class="text-sm text-gray-400 italic">No allergies recorded</p>
                                    </div>
                                    <div class="mt-3 max-w-md">
                                        <label for="new_allergy" class="block text-xs font-semibold text-gray-600 mb-1">Add allergy</label>
                                        <input type="text" id="new_allergy" name="new_allergy" maxlength="100" autocomplete="off"
                                            placeholder="e.g. Penicillin (saved with the form)"
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-pink-500 focus:ring-pink-500 text-sm">
                                        <p class="mt-1 text-xs text-gray-400">Removed allergies and the new one are applied when you save.</p>
                                    </div>
                                </div>
                            @else
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
                            @endif
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
                                    <label class="inline-flex items-center {{ ($additionalInfoReadOnly ?? false) ? 'cursor-not-allowed opacity-60' : 'cursor-pointer' }}">
                                        <input type="checkbox" name="hgt_enabled" value="1" class="sr-only peer" {{ ($patient->hgt_enabled ?? false) ? 'checked' : '' }} @disabled($additionalInfoReadOnly ?? false)>
                                        <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-teal-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-teal-500"></div>
                                        <span class="ms-3 text-sm font-medium text-gray-700">Enable HGT Monitoring</span>
                                    </label>
                                </div>

                                <!-- HGT Frequency -->
                                <div>
                                    <label for="hgt_frequency" class="block text-xs font-medium text-gray-600 mb-1">Frequency</label>
                                    <select id="hgt_frequency" name="hgt_frequency" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 text-sm {{ ($additionalInfoReadOnly ?? false) ? 'bg-gray-100 cursor-not-allowed' : '' }}" @disabled($additionalInfoReadOnly ?? false)>
                                        <option value="">Select frequency...</option>
                                        <option value="bd" {{ ($patient->hgt_frequency ?? '') === 'bd' ? 'selected' : '' }}>BD (Twice Daily)</option>
                                        <option value="tds" {{ ($patient->hgt_frequency ?? '') === 'tds' ? 'selected' : '' }}>TDS (Three Times Daily)</option>
                                        <option value="qid" {{ ($patient->hgt_frequency ?? '') === 'qid' ? 'selected' : '' }}>QID (Four Times Daily)</option>
                                        <option value="pid" {{ ($patient->hgt_frequency ?? '') === 'pid' ? 'selected' : '' }}>PRN (As Needed)</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Record New HGT Reading -->
                            @if(!($additionalInfoReadOnly ?? false))
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
                            @endif

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

                        @if(!($additionalInfoReadOnly ?? false))
                            <div class="flex justify-end">
                                <button type="submit"
                                        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Save Clinical Indicators
                                </button>
                            </div>
                        @endif
                    </form>
                </div>

                <!-- Vital Signs: the same panel the ward dashboard opens, with recording enabled -->
                @php
                    // 'off' hides the tab content; everything else shows the real recorded vitals
                    $vitalsHidden = ($patientVitalsMode ?? 'real') === 'off';
                @endphp
                <div x-show="activeTab === 'vitals'" x-cloak>
                    @if($vitalsHidden)
                        <div class="p-6 text-center text-gray-500 border border-dashed border-gray-300 rounded-lg">
                            <p class="text-sm">Vital signs are hidden.</p>
                            <p class="text-xs text-gray-400 mt-1">Turn them back on under Settings &rarr; Patient Details Config.</p>
                        </div>
                    @elseif($patient)
                        <iframe src="{{ route('vital-signs.patient', ['patient_id' => $patient->id, 'edit' => 1]) }}"
                            class="w-full h-[640px] border border-gray-200 rounded-lg bg-white"
                            title="Patient Vital Signs"></iframe>
                    @else
                        <div class="p-4 text-sm text-red-500">No patient selected.</div>
                    @endif
                </div>

                {{-- I/O chart: intake and output, the fluid plan and signs of overload --}}
                @if(($patientTabs['io'] ?? false) && ($fluidBalance ?? null))
                    <div x-show="activeTab === 'io'" x-cloak>
                        @include('wards.partials.fluid-balance', ['chart' => $fluidBalance])
                    </div>
                @endif

                {{-- Medication monitoring: only for users who switched it on in Settings --}}
                @if($patientTabs['medications'] ?? false)
                    <div x-show="activeTab === 'medications'" x-cloak>
                        @include('wards.partials.medication-monitoring', [
                            'medicationOrders' => $medicationOrders ?? collect(),
                            'medicationFormulary' => $medicationFormulary ?? collect(),
                        ])
                    </div>
                @endif

                <!-- Patient Movement -->
                <div x-show="activeTab === 'movement'" x-cloak>
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Patient Movement</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        Use this tab to schedule and send the patient to procedures outside the ward
                        (for example Radiology, Surgery, etc.).
                    </p>

                    @if($additionalInfoReadOnly ?? false)
                        <div class="mb-4 p-3 bg-orange-50 border border-orange-200 rounded-lg">
                            <div class="flex items-center text-sm text-orange-700">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                                <span class="font-medium">Read Only Mode</span>
                                <span class="ml-1">- Scheduling new movements is disabled.</span>
                            </div>
                        </div>
                    @endif

                    <!-- New Movement Form -->
                    @if(!($additionalInfoReadOnly ?? false))
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
                    @endif

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
                                                @if(!($additionalInfoReadOnly ?? false))
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
                                                        {{-- Completed/Cancelled --}}
                                                    @endif
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

                {{-- Consultant tab (settings key 'careprovider', formerly "Care Provider") --}}
                <div x-show="activeTab === 'careprovider'" x-cloak>
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Consultant</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        Consultants assigned to this patient from the ADT PV1 segment, plus any added here by hand.
                        Everyone listed below is shown to the patient in the bedside patient app. Anaesthetists are
                        listed on the Anaesthetist tab.
                    </p>

                    <!-- Add a consultant manually -->
                    <div class="mb-6 rounded-lg border border-gray-200 bg-white p-4">
                        <h4 class="text-sm font-semibold text-gray-800 mb-1">Add a consultant</h4>
                        <p class="text-xs text-gray-500 mb-3">
                            For a consultant brought in outside the ADT feed. They appear in the patient's care team immediately.
                        </p>
                        <form method="POST" action="{{ route('ward.care-providers.store') }}"
                              class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                            @csrf
                            <input type="hidden" name="patient_id" value="{{ $patient->id }}">

                            <div class="md:col-span-1">
                                <label for="cp_consultant_id" class="block text-xs font-semibold text-gray-700 mb-1">
                                    Consultant
                                </label>
                                <select id="cp_consultant_id" name="consultant_id" required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                    <option value="">Select a consultant…</option>
                                    @foreach($assignableConsultants as $consultant)
                                        <option value="{{ $consultant->id }}">
                                            {{ $consultant->name }}@if($consultant->specialty) — {{ $consultant->specialty->name }}@endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="md:col-span-1">
                                <label for="cp_role" class="block text-xs font-semibold text-gray-700 mb-1">Role</label>
                                <select id="cp_role" name="role" required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                    <option value="consulting">Consulting Doctor</option>
                                    <option value="attending">Attending Doctor</option>
                                    <option value="referring">Referring Doctor</option>
                                </select>
                            </div>

                            <div class="md:col-span-1">
                                <button type="submit"
                                        class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    Add to care team
                                </button>
                            </div>
                        </form>
                    </div>

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
                                            <div class="flex items-center gap-3">
                                                @if($provider->source === PatientCareProvider::SOURCE_MANUAL)
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                                        Added manually
                                                    </span>
                                                @elseif($provider->isLinked())
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

                                                @if($provider->source === PatientCareProvider::SOURCE_MANUAL)
                                                    @include('wards.partials.care-provider-remove', ['provider' => $provider, 'tab' => 'careprovider'])
                                                @endif
                                            </div>
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
                                            <div class="flex items-center gap-3">
                                                @if($provider->source === PatientCareProvider::SOURCE_MANUAL)
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                                        Added manually
                                                    </span>
                                                @elseif($provider->isLinked())
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

                                                @if($provider->source === PatientCareProvider::SOURCE_MANUAL)
                                                    @include('wards.partials.care-provider-remove', ['provider' => $provider, 'tab' => 'careprovider'])
                                                @endif
                                            </div>
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
                                            <div class="flex items-center gap-3">
                                                @if($provider->source === PatientCareProvider::SOURCE_MANUAL)
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                                        Added manually
                                                    </span>
                                                @elseif($provider->isLinked())
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

                                                @if($provider->source === PatientCareProvider::SOURCE_MANUAL)
                                                    @include('wards.partials.care-provider-remove', ['provider' => $provider, 'tab' => 'careprovider'])
                                                @endif
                                            </div>
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
                                <p class="font-medium text-gray-700">About consultants from ADT</p>
                                <p class="mt-1">Consultants are assigned automatically from HL7 ADT messages. Each doctor type corresponds to a PV1 field (a doctor who is an anaesthetist is listed on the Anaesthetist tab instead):</p>
                                <ul class="mt-2 list-disc list-inside text-xs space-y-1">
                                    <li><strong>Attending Doctor (PV1-7):</strong> The primary physician responsible for the patient's care</li>
                                    <li><strong>Referring Doctor (PV1-8):</strong> The physician who referred the patient</li>
                                    <li><strong>Consulting Doctor (PV1-9):</strong> Additional specialists consulted for the patient's care</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Anaesthetist tab --}}
                @if($patientTabs['anaesthetist'] ?? false)
                    @php
                        $pv1Fields = [
                            PatientCareProvider::ROLE_ATTENDING => 'PV1-7',
                            PatientCareProvider::ROLE_REFERRING => 'PV1-8',
                            PatientCareProvider::ROLE_CONSULTING => 'PV1-9',
                        ];
                    @endphp
                    <div x-show="activeTab === 'anaesthetist'" x-cloak>
                        <h3 class="text-lg font-semibold text-gray-800 mb-3">Anaesthetist</h3>
                        <p class="text-sm text-gray-600 mb-4">
                            Anaesthetists on this patient's care team: from ADT when a PV1 doctor is an anaesthetist, the one
                            recorded on the patient, and any added here by hand. They are shown in the bedside patient app.
                        </p>

                        <!-- Add an anaesthetist manually -->
                        <div class="mb-6 rounded-lg border border-gray-200 bg-white p-4">
                            <h4 class="text-sm font-semibold text-gray-800 mb-1">Add an anaesthetist</h4>
                            <p class="text-xs text-gray-500 mb-3">
                                For example for a pre-operative review or the pain team. They appear in the patient's care team immediately.
                            </p>
                            <form method="POST" action="{{ route('ward.care-providers.store-anaesthetist') }}"
                                  class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                                @csrf
                                <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                                <input type="hidden" name="active_tab" value="anaesthetist">

                                <div class="md:col-span-2">
                                    <label for="an_anaesthetist_id" class="block text-xs font-semibold text-gray-700 mb-1">
                                        Anaesthetist
                                    </label>
                                    <select id="an_anaesthetist_id" name="anaesthetist_id" required
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                        <option value="">Select an anaesthetist…</option>
                                        @foreach($assignableAnaesthetists as $anaesthetistOption)
                                            <option value="{{ $anaesthetistOption->id }}">
                                                {{ $anaesthetistOption->name }}@if($anaesthetistOption->personnel_code) — {{ $anaesthetistOption->personnel_code }}@endif
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="md:col-span-1">
                                    <button type="submit"
                                            class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                        Add to care team
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="rounded-lg border border-teal-100 bg-teal-50/50 p-4">
                            <div class="flex items-center mb-3">
                                <svg class="w-5 h-5 text-teal-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                <h4 class="text-sm font-semibold text-teal-800">Anaesthetists</h4>
                            </div>
                            @if($anaesthetistTeam->isNotEmpty())
                                <div class="space-y-2">
                                    @foreach($anaesthetistTeam as $member)
                                        <div class="flex items-center justify-between gap-3 bg-white rounded-lg p-3 border border-teal-100">
                                            <div class="min-w-0">
                                                <div class="font-medium text-gray-900">{{ $member['name'] }}</div>
                                                <div class="text-xs text-gray-500">
                                                    @if($member['code'])
                                                        Code: {{ $member['code'] }}
                                                    @endif
                                                    @if($member['phone'])
                                                        • Phone: {{ $member['phone'] }}
                                                    @endif
                                                    @if($member['source'] === 'adt' && $member['provider'])
                                                        • {{ $member['role'] }} ({{ $pv1Fields[$member['provider']->role] ?? 'PV1' }})
                                                    @elseif($member['source'] === 'record')
                                                        • Recorded on the patient (admission or ADT)
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-3 shrink-0">
                                                @if($member['source'] === 'manual')
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                                        Added manually
                                                    </span>
                                                    @include('wards.partials.care-provider-remove', ['provider' => $member['provider'], 'tab' => 'anaesthetist'])
                                                @elseif($member['source'] === 'adt')
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        From ADT
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                                        Patient record
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm text-teal-700 italic">No anaesthetist assigned.</p>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Nurses tab: straight from the ward roster, which is where nurses are assigned --}}
                @if(($patientTabs['nurses'] ?? false) && $nurseRoster)
                    @php
                        $rosterBed = $nurseRoster['bed'];
                        $onDuty = $nurseRoster['current'];
                        $initials = fn ($name) => \Illuminate\Support\Str::upper(collect(preg_split('/\s+/', trim((string) $name)))
                            ->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode(''));
                    @endphp
                    <div x-show="activeTab === 'nurses'" x-cloak>
                        <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800">Nurses</h3>
                                <p class="text-sm text-gray-600 mt-1">
                                    @if($rosterBed)
                                        From the ward roster for bed <span class="font-semibold">{{ $rosterBed->bed_number }}</span>.
                                    @endif
                                    Nurses are assigned in the roster, so changes are made there.
                                </p>
                            </div>
                            @if($rosterBed)
                                <a href="{{ route('ward.schedule', ['ward_id' => $rosterBed->ward_id, 'date' => now()->toDateString()]) }}"
                                   target="_blank" rel="noopener"
                                   class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-md shadow-sm hover:bg-gray-50">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    Open roster
                                    <svg class="w-3.5 h-3.5 ml-1.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                    </svg>
                                </a>
                            @endif
                        </div>

                        @if(!$rosterBed)
                            <div class="p-6 text-center text-gray-500 border border-dashed border-gray-300 rounded-lg">
                                <p class="text-sm">This patient is not in a bed, so there is no roster entry.</p>
                            </div>
                        @else
                            {{-- Who is looking after the bed right now --}}
                            @if($onDuty)
                                <div class="mb-5 flex flex-wrap items-center gap-3 rounded-xl border p-4 {{ $onDuty['nurse'] ? 'border-pink-200 bg-pink-50' : 'border-amber-200 bg-amber-50' }}">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-sm font-bold {{ $onDuty['nurse'] ? 'bg-pink-500 text-white' : 'bg-amber-200 text-amber-800' }}">
                                        {{ $onDuty['nurse'] ? $initials($onDuty['nurse']->name) : '?' }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-[11px] font-bold uppercase tracking-wider {{ $onDuty['nurse'] ? 'text-pink-700' : 'text-amber-700' }}">
                                            On duty now &middot; {{ $onDuty['name'] }} shift ({{ $onDuty['time'] }})
                                        </div>
                                        @if($onDuty['nurse'])
                                            <div class="text-base font-semibold text-gray-900">{{ $onDuty['nurse']->name }}</div>
                                            <div class="text-xs text-gray-600">
                                                {{ $onDuty['nurse']->designation }}
                                                @if($onDuty['nurse']->taggingNurses->isNotEmpty())
                                                    &middot; Tagging: {{ $onDuty['nurse']->taggingNurses->pluck('name')->implode(', ') }}
                                                @endif
                                            </div>
                                        @else
                                            <div class="text-base font-semibold text-amber-800">No nurse rostered to this bed</div>
                                            <div class="text-xs text-amber-700">Assign one in the roster.</div>
                                        @endif
                                    </div>
                                    @if($onDuty['team_leader'])
                                        <div class="text-right text-xs text-gray-600">
                                            Team leader
                                            <div class="text-sm font-semibold text-gray-800">{{ $onDuty['team_leader']->name }}</div>
                                        </div>
                                    @endif
                                </div>
                            @endif

                            @if($patient->nurse)
                                <div class="mb-5 rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700">
                                    Primary nurse on the patient record:
                                    <span class="font-semibold text-gray-900">{{ $patient->nurse->name }}</span>
                                </div>
                            @endif

                            {{-- Every shift of today and tomorrow --}}
                            @foreach($nurseRoster['days'] as $day)
                                <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                                    {{ $day['label'] }}
                                    <span class="font-normal normal-case tracking-normal text-gray-500">&middot; {{ $day['date']->format('D, d M Y') }}</span>
                                </h4>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
                                    @foreach($day['shifts'] as $shift)
                                        <div class="rounded-lg border p-3 {{ $shift['is_current'] ? 'border-pink-300 bg-pink-50/60 ring-1 ring-pink-200' : 'border-gray-200 bg-white' }}">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="text-xs font-bold text-gray-700">{{ $shift['code'] }} &middot; {{ $shift['name'] }}</span>
                                                @if($shift['is_current'])
                                                    <span class="px-1.5 py-0.5 rounded-full bg-pink-600 text-white text-[10px] font-bold">NOW</span>
                                                @endif
                                            </div>
                                            <div class="text-[11px] text-gray-500">{{ $shift['time'] }}</div>
                                            @if($shift['nurse'])
                                                <div class="mt-2 text-sm font-semibold text-gray-900">{{ $shift['nurse']->name }}</div>
                                                @if($shift['nurse']->designation)
                                                    <div class="text-xs text-gray-500">{{ $shift['nurse']->designation }}</div>
                                                @endif
                                                @if($shift['nurse']->taggingNurses->isNotEmpty())
                                                    <div class="mt-1 text-[11px] text-purple-700">
                                                        <span class="font-semibold">Tagging:</span>
                                                        {{ $shift['nurse']->taggingNurses->pluck('name')->implode(', ') }}
                                                    </div>
                                                @endif
                                            @else
                                                <div class="mt-2 text-sm italic text-gray-400">Not rostered</div>
                                            @endif
                                            @if($shift['team_leader'])
                                                <div class="mt-2 pt-2 border-t border-gray-100 text-[11px] text-gray-600">
                                                    Team leader: <span class="font-semibold text-gray-800">{{ $shift['team_leader']->name }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        @endif
                    </div>
                @endif

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
                <!-- Blood Transfusion -->
                <div x-show="activeTab === 'transfusion'" x-cloak>
                @php
                    $running = $bloodTransfusions->where('status', 'in_progress');
                    $pending = $bloodTransfusions->where('status', 'pending');
                    $finished = $bloodTransfusions->whereIn('status', ['completed', 'stopped']);
                    $openExceptions = $bloodTransfusions
                        ->reject(fn ($t) => $t->isFinished())
                        ->flatMap(fn ($t) => collect($t->exceptions())
                            ->map(fn ($e) => $e + ['unit' => $t->unit_number]));
                    $levelOrder = ['critical' => 0, 'warning' => 1, 'info' => 2];
                    $openExceptions = $openExceptions->sortBy(fn ($e) => $levelOrder[$e['level']] ?? 9)->values();
                    $bt = \App\Models\BloodTransfusion::class;
                @endphp
                <div x-data="{
                    now: Date.now(),
                    adding: {{ $bloodTransfusions->isEmpty() ? 'true' : 'false' }},
                    init() { setInterval(() => this.now = Date.now(), 1000); },
                    mins(from) { return Math.max(0, Math.floor((this.now - from) / 60000)); },
                    until(to) { return Math.max(0, Math.ceil((to - this.now) / 60000)); },
                    hhmm(total) {
                        const h = Math.floor(total / 60), m = total % 60;
                        return h > 0 ? h + 'h ' + String(m).padStart(2, '0') + 'm' : m + 'm';
                    },
                }">
                    {{-- Header: always in reach, however many units are open --}}
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <div class="flex items-center gap-2">
                            <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                                Blood Transfusion
                            </h3>
                            @if ($running->isNotEmpty())
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-red-600 text-white">
                                    {{ $running->count() }} running
                                </span>
                            @endif
                            @if ($pending->isNotEmpty())
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-200 text-gray-700">
                                    {{ $pending->count() }} pre-start
                                </span>
                            @endif
                        </div>

                        <button type="button" @click="adding = !adding"
                            class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-md shadow-sm transition-colors"
                            :class="adding ? 'bg-gray-200 text-gray-700 hover:bg-gray-300' : 'bg-blue-600 text-white hover:bg-blue-700'">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    :d="adding ? 'M6 18L18 6M6 6l12 12' : 'M12 4v16m8-8H4'" />
                            </svg>
                            <span x-text="adding ? 'Cancel' : 'Add unit'">Add unit</span>
                        </button>
                    </div>

                    {{-- Add form, opening in place at the top so it is never a scroll away --}}
                    <div x-show="adding" x-cloak class="mb-4">
                        <div x-data="{
                            presets: @js($bt::PRODUCT_PRESETS),
                            product: '{{ $bt::PRODUCT_TYPES[0] }}',
                            volume: {{ $bt::PRODUCT_PRESETS[$bt::PRODUCT_TYPES[0]]['volume_ml'] }},
                            minutes: {{ $bt::PRODUCT_PRESETS[$bt::PRODUCT_TYPES[0]]['minutes'] }},
                            touched: false,
                            applyPreset() {
                                const p = this.presets[this.product];
                                if (p) { this.volume = p.volume_ml; this.minutes = p.minutes; this.touched = false; }
                            },
                            bump(field, delta, min, max) {
                                this[field] = Math.min(max, Math.max(min, Number(this[field] || 0) + delta));
                                this.touched = true;
                            },
                            clamp(field, min, max) {
                                const n = Number(this[field]);
                                this[field] = isNaN(n) ? min : Math.min(max, Math.max(min, Math.round(n)));
                            },
                            get preset() { return this.presets[this.product] || null; },
                            get rate() { return this.minutes > 0 ? Math.round(this.volume / (this.minutes / 60) * 10) / 10 : null; },
                            get durationFits() {
                                const p = this.preset;
                                return !p || (this.minutes >= p.min_minutes && this.minutes <= p.max_minutes);
                            },
                            get durationMessage() {
                                const p = this.preset;
                                if (!p) return null;
                                if (this.minutes < p.min_minutes)
                                    return this.product + ' is normally given over at least ' + p.min_minutes + ' min. ' + this.minutes + ' min is faster than usual.';
                                if (this.minutes > p.max_minutes)
                                    return this.product + ' is normally finished within ' + p.max_minutes + ' min. ' + this.minutes + ' min is longer than usual.';
                                return null;
                            },
                        }" class="rounded-xl border border-blue-200 bg-blue-50/40 p-4">
                            <form method="POST" action="{{ route('ward.blood-transfusions.store') }}">
                                @csrf
                                <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                                <input type="hidden" name="active_tab" value="transfusion">

                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Unit number <span class="text-red-500">*</span></label>
                                        <input type="text" name="unit_number" required maxlength="64"
                                            class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Product type <span class="text-red-500">*</span></label>
                                        <select name="product_type" required x-model="product" @change="applyPreset()"
                                            class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                            @foreach ($bt::PRODUCT_TYPES as $product)
                                                <option value="{{ $product }}">{{ $product }}</option>
                                            @endforeach
                                        </select>
                                        <p class="mt-1 text-[11px] text-blue-700" x-text="preset ? preset.note : ''"></p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Crossmatch reference</label>
                                        <input type="text" name="crossmatch_reference" maxlength="64"
                                            class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                    </div>

                                    {{-- Volume stepper --}}
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Volume (mL)</label>
                                        <div class="flex items-stretch rounded-lg border border-gray-300 bg-white overflow-hidden">
                                            <button type="button" @click="bump('volume', -{{ $bt::VOLUME_STEP }}, {{ $bt::VOLUME_MIN }}, {{ $bt::VOLUME_MAX }})"
                                                class="w-11 shrink-0 flex items-center justify-center text-gray-600 hover:bg-gray-100 active:bg-gray-200 text-xl font-semibold"
                                                aria-label="Decrease volume">&minus;</button>
                                            <input type="number" name="volume_ml" x-model.number="volume"
                                                @change="clamp('volume', {{ $bt::VOLUME_MIN }}, {{ $bt::VOLUME_MAX }}); touched = true"
                                                min="{{ $bt::VOLUME_MIN }}" max="{{ $bt::VOLUME_MAX }}"
                                                class="flex-1 min-w-0 border-0 text-center text-sm font-semibold focus:ring-0">
                                            <button type="button" @click="bump('volume', {{ $bt::VOLUME_STEP }}, {{ $bt::VOLUME_MIN }}, {{ $bt::VOLUME_MAX }})"
                                                class="w-11 shrink-0 flex items-center justify-center text-gray-600 hover:bg-gray-100 active:bg-gray-200 text-xl font-semibold"
                                                aria-label="Increase volume">+</button>
                                        </div>
                                        <p class="mt-1 text-[11px] text-gray-500">Steps of {{ $bt::VOLUME_STEP }} mL</p>
                                    </div>

                                    {{-- Duration stepper --}}
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Planned duration (min)</label>
                                        <div class="flex items-stretch rounded-lg border bg-white overflow-hidden"
                                            :class="durationFits ? 'border-gray-300' : 'border-amber-400'">
                                            <button type="button" @click="bump('minutes', -{{ $bt::MINUTES_STEP }}, {{ $bt::MINUTES_MIN }}, {{ $bt::MAX_RUNNING_MINUTES }})"
                                                class="w-11 shrink-0 flex items-center justify-center text-gray-600 hover:bg-gray-100 active:bg-gray-200 text-xl font-semibold"
                                                aria-label="Decrease duration">&minus;</button>
                                            <input type="number" name="prescribed_minutes" x-model.number="minutes"
                                                @change="clamp('minutes', {{ $bt::MINUTES_MIN }}, {{ $bt::MAX_RUNNING_MINUTES }}); touched = true"
                                                min="{{ $bt::MINUTES_MIN }}" max="{{ $bt::MAX_RUNNING_MINUTES }}"
                                                class="flex-1 min-w-0 border-0 text-center text-sm font-semibold focus:ring-0">
                                            <button type="button" @click="bump('minutes', {{ $bt::MINUTES_STEP }}, {{ $bt::MINUTES_MIN }}, {{ $bt::MAX_RUNNING_MINUTES }})"
                                                class="w-11 shrink-0 flex items-center justify-center text-gray-600 hover:bg-gray-100 active:bg-gray-200 text-xl font-semibold"
                                                aria-label="Increase duration">+</button>
                                        </div>
                                        <p class="mt-1 text-[11px] text-gray-500">
                                            Steps of {{ $bt::MINUTES_STEP }} min, up to the {{ $bt::MAX_RUNNING_MINUTES }} min limit
                                        </p>
                                    </div>

                                    {{-- Resulting rate --}}
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Resulting rate</label>
                                        <div class="rounded-lg border border-gray-200 bg-white px-3 py-2">
                                            <span class="text-lg font-bold text-gray-800" x-text="rate === null ? '-' : rate"></span>
                                            <span class="text-xs text-gray-500">mL/hr</span>
                                        </div>
                                        <p class="mt-1 text-[11px] text-gray-500" x-text="volume + ' mL over ' + hhmm(minutes)"></p>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Unit blood group</label>
                                        <select name="unit_blood_group"
                                            class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                            <option value="">Not recorded</option>
                                            @foreach ($bt::BLOOD_GROUPS as $group)
                                                <option value="{{ $group }}">{{ $group }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Patient blood group</label>
                                        <select name="patient_blood_group"
                                            class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                            <option value="">Not recorded</option>
                                            @foreach ($bt::BLOOD_GROUPS as $group)
                                                <option value="{{ $group }}">{{ $group }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Unit expires at</label>
                                        <input type="datetime-local" name="unit_expires_at"
                                            class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                    <div class="sm:col-span-2 lg:col-span-3">
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Notes</label>
                                        <input type="text" name="notes" maxlength="1000"
                                            class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                </div>

                                {{-- Tell the nurse when what they set does not fit the product --}}
                                <div x-show="!durationFits" x-cloak
                                    class="mt-3 flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2">
                                    <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <div class="text-xs text-amber-800">
                                        <span class="font-semibold">Check the duration</span>
                                        <div class="mt-0.5" x-text="durationMessage"></div>
                                        <button type="button" @click="applyPreset()"
                                            class="mt-1 underline font-semibold">Use the usual figures</button>
                                    </div>
                                </div>

                                <div class="mt-3 flex justify-end gap-2">
                                    <button type="button" @click="adding = false"
                                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-md hover:bg-gray-50">
                                        Cancel
                                    </button>
                                    <button type="submit"
                                        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-blue-700">
                                        Register unit
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {{-- Exception alerts, worst first, across every open unit --}}
                    @if ($openExceptions->isNotEmpty())
                        <div class="mb-4 space-y-2">
                            @foreach ($openExceptions as $exception)
                                @php
                                    $tone = match ($exception['level']) {
                                        'critical' => 'border-red-300 bg-red-50 text-red-800',
                                        'warning' => 'border-amber-300 bg-amber-50 text-amber-800',
                                        default => 'border-blue-200 bg-blue-50 text-blue-800',
                                    };
                                @endphp
                                <div class="flex items-start gap-2 rounded-lg border px-3 py-2 {{ $tone }}">
                                    <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <div class="text-xs">
                                        <span class="font-semibold">{{ $exception['title'] }}</span>
                                        <span class="opacity-60">&middot; unit {{ $exception['unit'] }}</span>
                                        <div class="mt-0.5">{{ $exception['detail'] }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Running units: time based monitoring --}}
                    @foreach ($running as $transfusion)
                        @php
                            $end = $transfusion->predictedEndAt();
                            $limit = $transfusion->expiresRunningAt();
                        @endphp
                        <div class="mb-4 rounded-xl border border-red-200 bg-red-50/40 p-4"
                            x-data="{
                                started: {{ $transfusion->started_at->getTimestampMs() }},
                                end: {{ $end ? $end->getTimestampMs() : 'null' }},
                                limit: {{ $limit ? $limit->getTimestampMs() : 'null' }},
                                planned: {{ $transfusion->prescribed_minutes ?: 0 }},
                                get elapsed() { return this.mins(this.started); },
                                get percent() { return this.planned ? Math.min(100, Math.round(this.elapsed / this.planned * 100)) : 0; },
                                get overdue() { return this.end !== null && this.now > this.end; },
                                get breached() { return this.limit !== null && this.now > this.limit; },
                            }">
                            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                                <div>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-red-600 text-white">
                                        RUNNING
                                    </span>
                                    <span class="ml-2 font-semibold text-gray-800">{{ $transfusion->unit_number }}</span>
                                    <span class="text-sm text-gray-600">&middot; {{ $transfusion->product_type }}</span>
                                    @if ($transfusion->unit_blood_group)
                                        <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-800">
                                            {{ $transfusion->unit_blood_group }}
                                        </span>
                                    @endif
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ $transfusion->volume_ml ? $transfusion->volume_ml . ' mL' : 'Volume not recorded' }}
                                </div>
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                                <div>
                                    <div class="text-xs text-gray-500">Started</div>
                                    <div class="font-semibold text-gray-800">{{ $transfusion->started_at->format('d M H:i') }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500">Current rate</div>
                                    <div class="font-semibold text-gray-800">
                                        {{ $transfusion->rateMlPerHour() ? $transfusion->rateMlPerHour() . ' mL/hr' : '-' }}
                                    </div>
                                    <div class="text-[11px] text-gray-400">prescribed</div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500">Predicted end</div>
                                    <div class="font-semibold" :class="overdue ? 'text-red-700' : 'text-gray-800'">
                                        {{ $end ? $end->format('d M H:i') : '-' }}
                                    </div>
                                    <div class="text-[11px]" :class="overdue ? 'text-red-600' : 'text-gray-400'"
                                        x-text="end === null ? '' : (overdue ? hhmm(mins(end)) + ' overdue' : hhmm(until(end)) + ' remaining')"></div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500">Elapsed</div>
                                    <div class="font-semibold" :class="breached ? 'text-red-700' : 'text-gray-800'"
                                        x-text="hhmm(elapsed)"></div>
                                    <div class="text-[11px]" :class="breached ? 'text-red-600 font-semibold' : 'text-gray-400'"
                                        x-text="breached ? 'past the 4 hour limit' : 'limit ' + '{{ $limit ? $limit->format('H:i') : '-' }}'"></div>
                                </div>
                            </div>

                            @if ($transfusion->prescribed_minutes)
                                <div class="mt-3">
                                    <div class="h-2 w-full rounded-full bg-gray-200 overflow-hidden">
                                        <div class="h-2 rounded-full transition-all"
                                            :class="overdue ? 'bg-red-500' : 'bg-emerald-500'"
                                            :style="'width: ' + percent + '%'"></div>
                                    </div>
                                    <div class="mt-1 text-[11px] text-gray-500" x-text="percent + '% of the planned ' + hhmm(planned)"></div>
                                </div>
                            @endif

                            <form method="POST" action="{{ route('ward.blood-transfusions.finish', $transfusion) }}"
                                class="mt-3 flex flex-wrap items-end gap-2">
                                @csrf
                                <input type="hidden" name="active_tab" value="transfusion">
                                <div class="flex-1 min-w-[12rem]">
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Reason (only if stopping early)</label>
                                    <input type="text" name="stop_reason" maxlength="255"
                                        class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500"
                                        placeholder="e.g. suspected reaction">
                                </div>
                                <button type="submit" name="outcome" value="completed"
                                    class="inline-flex items-center px-4 py-2 bg-emerald-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-emerald-700">
                                    Complete
                                </button>
                                <button type="submit" name="outcome" value="stopped"
                                    class="inline-flex items-center px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-red-700">
                                    Stop
                                </button>
                            </form>
                        </div>
                    @endforeach

                    {{-- Pending units: the pre-start check, one step at a time --}}
                    @foreach ($pending as $transfusion)
                        @php $steps = $transfusion->checklistSteps(); @endphp
                        <div class="mb-4 rounded-xl border border-gray-200 bg-white p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                                <div>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-gray-200 text-gray-700">
                                        PRE-START
                                    </span>
                                    <span class="ml-2 font-semibold text-gray-800">{{ $transfusion->unit_number }}</span>
                                    <span class="text-sm text-gray-600">&middot; {{ $transfusion->product_type }}</span>
                                </div>
                                <div class="flex items-center gap-2 text-xs text-gray-500">
                                    <span>{{ $transfusion->groupSummary() }}</span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full font-semibold
                                        {{ $transfusion->checksComplete() ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600' }}">
                                        Step {{ $transfusion->completedStepCount() }} of 4
                                    </span>
                                </div>
                            </div>

                            <ol class="space-y-2">
                                @foreach ($steps as $step)
                                    <li class="flex items-start gap-3 rounded-lg border px-3 py-2.5
                                        {{ $step['done'] ? 'border-emerald-200 bg-emerald-50' : ($step['isNext'] ? 'border-blue-300 bg-blue-50/50' : 'border-gray-200 bg-gray-50 opacity-60') }}">
                                        <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold
                                            {{ $step['done'] ? 'bg-emerald-600 text-white' : ($step['isNext'] ? 'bg-blue-600 text-white' : 'bg-gray-300 text-gray-600') }}">
                                            @if ($step['done'])
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                                </svg>
                                            @else
                                                {{ $step['number'] }}
                                            @endif
                                        </span>
                                        <span class="flex-1 min-w-0">
                                            <span class="block text-sm font-medium text-gray-800">{{ $step['label'] }}</span>
                                            <span class="block text-xs text-gray-500">{{ $step['detail'] }}</span>
                                            @if ($step['problem'])
                                                <span class="mt-1 inline-flex items-center rounded px-1.5 py-0.5 text-[11px] font-semibold bg-red-100 text-red-800">
                                                    {{ $step['problem'] }}
                                                </span>
                                            @endif
                                        </span>
                                        <span class="shrink-0">
                                            @if ($step['isNext'])
                                                <form method="POST" action="{{ route('ward.blood-transfusions.checklist', $transfusion) }}">
                                                    @csrf
                                                    <input type="hidden" name="active_tab" value="transfusion">
                                                    <input type="hidden" name="step" value="{{ $step['key'] }}">
                                                    <input type="hidden" name="action" value="confirm">
                                                    <button type="submit"
                                                        class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white text-xs font-semibold rounded-md shadow-sm hover:bg-blue-700">
                                                        Confirm
                                                    </button>
                                                </form>
                                            @elseif ($step['canUndo'])
                                                <form method="POST" action="{{ route('ward.blood-transfusions.checklist', $transfusion) }}">
                                                    @csrf
                                                    <input type="hidden" name="active_tab" value="transfusion">
                                                    <input type="hidden" name="step" value="{{ $step['key'] }}">
                                                    <input type="hidden" name="action" value="undo">
                                                    <button type="submit"
                                                        class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 text-gray-600 text-xs font-semibold rounded-md hover:bg-gray-50">
                                                        Undo
                                                    </button>
                                                </form>
                                            @elseif ($step['done'])
                                                <span class="text-xs font-semibold text-emerald-700">Done</span>
                                            @else
                                                <span class="text-xs text-gray-400">Locked</span>
                                            @endif
                                        </span>
                                    </li>
                                @endforeach
                            </ol>

                            <form method="POST" action="{{ route('ward.blood-transfusions.start', $transfusion) }}"
                                class="mt-3 pt-3 border-t border-gray-100 flex flex-wrap items-center justify-between gap-2">
                                @csrf
                                <input type="hidden" name="active_tab" value="transfusion">
                                <span class="text-xs {{ $transfusion->canStart() ? 'text-emerald-700 font-semibold' : 'text-gray-500' }}">
                                    @if ($transfusion->canStart())
                                        All four steps confirmed. Ready to start.
                                    @elseif (!$transfusion->checksComplete())
                                        {{ 4 - $transfusion->completedStepCount() }} step(s) left before this unit can start.
                                    @else
                                        Resolve the flagged problems before starting.
                                    @endif
                                    @if ($transfusion->checked_at)
                                        <span class="font-normal text-gray-400">
                                            &middot; last action {{ $transfusion->checked_at->format('H:i') }}
                                            @if ($transfusion->checkedBy)
                                                by {{ $transfusion->checkedBy->name }}
                                            @endif
                                        </span>
                                    @endif
                                </span>
                                <button type="submit" @disabled(!$transfusion->canStart())
                                    class="inline-flex items-center px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-red-700 disabled:bg-gray-300 disabled:cursor-not-allowed">
                                    Start transfusion
                                </button>
                            </form>
                        </div>
                    @endforeach

                    {{-- Finished units --}}
                    @if ($finished->isNotEmpty())
                        <div class="mt-4">
                            <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Previous units</h4>
                            <div class="overflow-x-auto rounded-lg border border-gray-200">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Unit</th>
                                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Product</th>
                                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Started</th>
                                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Finished</th>
                                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Took</th>
                                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Outcome</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-100">
                                        @foreach ($finished as $transfusion)
                                            <tr>
                                                <td class="px-3 py-2 font-medium text-gray-800">{{ $transfusion->unit_number }}</td>
                                                <td class="px-3 py-2 text-gray-600">{{ $transfusion->product_type }}</td>
                                                <td class="px-3 py-2 text-gray-600 whitespace-nowrap">
                                                    {{ $transfusion->started_at?->format('d M H:i') ?? '-' }}</td>
                                                <td class="px-3 py-2 text-gray-600 whitespace-nowrap">
                                                    {{ $transfusion->completed_at?->format('d M H:i') ?? '-' }}</td>
                                                <td class="px-3 py-2 text-gray-600 whitespace-nowrap">
                                                    {{ $transfusion->elapsedMinutes() !== null ? $transfusion->elapsedMinutes() . ' min' : '-' }}</td>
                                                <td class="px-3 py-2">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                                        {{ $transfusion->status === 'completed' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }}">
                                                        {{ ucfirst($transfusion->status) }}
                                                    </span>
                                                    @if ($transfusion->stop_reason)
                                                        <span class="block text-[11px] text-gray-500 mt-0.5">{{ $transfusion->stop_reason }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <p class="mt-4 text-[11px] text-gray-400">
                        The checks here support the bedside procedure, they do not replace it. Group compatibility is
                        only asserted for red cell products; everything else is left for manual confirmation.
                    </p>
                </div>
                </div>

                <!-- Consultant Orders -->
                @include('wards.partials.consultant-orders')

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
                            Discharge this patient now, or schedule it for later so the ward can plan ahead.
                            A scheduled discharge keeps the patient in the bed and shows the expected date
                            in the bedside patient app.
                        </p>

                        <div class="mb-3 text-xs text-gray-600">
                            <div>Status: <span class="font-semibold capitalize">{{ str_replace('_', ' ', $patient->status) }}</span></div>
                            <div>Ward: <span class="font-semibold">{{ $patient->ward->ward_name ?? '-' }}</span></div>
                            <div>Bed: <span class="font-semibold">{{ $patient->bed_number ?? '-' }}</span></div>
                        </div>

                        @if($patient->status === 'pending_discharge' && $patient->expected_discharge_at)
                            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-semibold text-amber-800">Discharge already scheduled</p>
                                        <p class="text-sm text-amber-700 mt-1">
                                            Expected {{ $patient->expected_discharge_at->format('l, j M Y \a\t H:i') }}
                                        </p>
                                        <p class="text-xs text-amber-600 mt-1">
                                            The patient sees this date in their app. Confirm the discharge below when they actually leave.
                                        </p>
                                    </div>
                                    <form method="POST" action="{{ route('ward.cancel-scheduled-discharge') }}"
                                          onsubmit="return confirm('Cancel the scheduled discharge and set this patient back to admitted?');">
                                        @csrf
                                        <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                                        <button type="submit"
                                                class="whitespace-nowrap px-3 py-1.5 bg-white border border-amber-300 text-amber-800 text-xs font-semibold rounded-md hover:bg-amber-100">
                                            Cancel schedule
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endif

                        <div x-data="{ mode: '{{ $patient->status === 'pending_discharge' ? 'now' : 'schedule' }}' }">
                            <!-- Immediate vs scheduled -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-5">
                                <button type="button" @click="mode = 'now'"
                                        :class="mode === 'now' ? 'border-red-500 bg-red-50 ring-2 ring-red-200' : 'border-gray-200 bg-white hover:border-gray-300'"
                                        class="text-left rounded-lg border p-4 transition">
                                    <div class="flex items-center">
                                        <span class="inline-flex w-4 h-4 rounded-full border-2 mr-2 items-center justify-center"
                                              :class="mode === 'now' ? 'border-red-600' : 'border-gray-300'">
                                            <span x-show="mode === 'now'" class="w-2 h-2 rounded-full bg-red-600"></span>
                                        </span>
                                        <span class="text-sm font-semibold text-gray-800">Discharge immediately</span>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500 pl-6">
                                        The patient is leaving now. Frees the bed straight away.
                                    </p>
                                </button>

                                <button type="button" @click="mode = 'schedule'"
                                        :class="mode === 'schedule' ? 'border-blue-500 bg-blue-50 ring-2 ring-blue-200' : 'border-gray-200 bg-white hover:border-gray-300'"
                                        class="text-left rounded-lg border p-4 transition">
                                    <div class="flex items-center">
                                        <span class="inline-flex w-4 h-4 rounded-full border-2 mr-2 items-center justify-center"
                                              :class="mode === 'schedule' ? 'border-blue-600' : 'border-gray-300'">
                                            <span x-show="mode === 'schedule'" class="w-2 h-2 rounded-full bg-blue-600"></span>
                                        </span>
                                        <span class="text-sm font-semibold text-gray-800">Schedule a discharge</span>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500 pl-6">
                                        Plan a future date. Patient keeps the bed and sees the date in their app.
                                    </p>
                                </button>
                            </div>

                            <!-- Scheduled discharge -->
                            <form x-show="mode === 'schedule'" x-cloak
                                  method="POST" action="{{ route('ward.schedule-discharge') }}" class="space-y-4">
                                @csrf
                                <input type="hidden" name="patient_id" value="{{ $patient->id }}">

                                <div>
                                    <label for="expected_discharge_at" class="block text-xs font-semibold text-gray-700 mb-1">
                                        Expected Discharge Date &amp; Time
                                    </label>
                                    <input type="datetime-local"
                                           id="expected_discharge_at"
                                           name="expected_discharge_at"
                                           required
                                           value="{{ $patient->expected_discharge_at?->format('Y-m-d\TH:i') }}"
                                           class="mt-1 block w-full md:w-1/2 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                    <p class="mt-1 text-[11px] text-gray-500">
                                        Shown to the patient as their expected going-home date.
                                    </p>
                                </div>

                                <div>
                                    <label for="schedule_discharge_notes" class="block text-xs font-semibold text-gray-700 mb-1">
                                        Notes (optional)
                                    </label>
                                    <textarea id="schedule_discharge_notes"
                                              name="discharge_notes"
                                              rows="2"
                                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                              placeholder="e.g. Pending final review by consultant"></textarea>
                                </div>

                                <div class="flex justify-end">
                                    <button type="submit"
                                            class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        Schedule Discharge
                                    </button>
                                </div>
                            </form>

                        <form x-show="mode === 'now'" x-cloak
                              method="POST" action="{{ route('ward.discharge-patient') }}" class="space-y-4">
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
                        </div>
                    @endif
                </div>

                {{-- Discharge Summary (loaded when the tab is first opened) --}}
                @include('wards.partials.discharge-summary-tab')

                {{-- Nursing Plan: this shift's tasks and the nursing care plan --}}
                @include('wards.partials.nursing-plan')

                {{-- One panel per clinical indicator bound to this ward's ward type --}}
                @foreach ($wardClinicalIndicators ?? [] as $indicator)
                    @if (\App\Support\ClinicalIndicatorLibrary::takesReadings($indicator['definition']))
                        {{-- Monitor readings (the hemodynamic numerics) are typed in rather than scored --}}
                        @include('wards.partials.clinical-indicator-readings')
                        @continue
                    @endif
                    @php
                        $definition = $indicator['definition'];
                        $items = $definition['items'] ?? [];
                        $bands = $definition['bands'] ?? [];
                        $latest = $indicator['latest'];
                    @endphp
                    <div x-show="activeTab === 'indicator-{{ $indicator['id'] }}'" x-cloak>
                    <div x-data="{
                        scorable: {{ $indicator['scorable'] ? 'true' : 'false' }},
                        items: @js(array_fill(0, max(count($items), 1), '')),
                        manualScore: '',
                        bands: @js(array_values($bands)),
                        showReference: false,
                        get total() {
                            if (this.scorable) {
                                if (this.items.some(v => v === '' || v === null)) return null;
                                return this.items.reduce((sum, v) => sum + Number(v), 0);
                            }
                            return this.manualScore === '' ? null : Number(this.manualScore);
                        },
                        get band() {
                            if (this.total === null) return null;
                            return this.bands.find(b =>
                                (b.min === null || this.total >= b.min) &&
                                (b.max === null || this.total <= b.max)) || null;
                        },
                        get bandClass() {
                            const tone = this.band ? this.band.tone : null;
                            if (tone === 'high') return 'bg-red-100 text-red-800 border-red-200';
                            if (tone === 'moderate') return 'bg-amber-100 text-amber-800 border-amber-200';
                            if (tone === 'low') return 'bg-green-100 text-green-800 border-green-200';
                            return 'bg-gray-100 text-gray-600 border-gray-200';
                        },
                    }">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <h3 class="text-lg font-semibold text-gray-800">{{ $indicator['name'] }}</h3>
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-violet-100 text-violet-800">
                                    {{ $indicator['code'] }}
                                </span>
                            </div>
                            @if ($latest)
                                <div class="text-right">
                                    <div class="text-xs text-gray-500">Last scored</div>
                                    <div class="text-sm font-semibold text-gray-800">
                                        {{ $latest->score }}
                                        @if ($latest->breakdown())
                                            <span class="font-normal text-gray-500">({{ $latest->breakdown() }})</span>
                                        @endif
                                        @if ($latest->band_label)
                                            <span
                                                class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                                {{ $latest->band_tone === 'high' ? 'bg-red-100 text-red-800' : ($latest->band_tone === 'moderate' ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800') }}">
                                                {{ $latest->band_label }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-gray-400">
                                        {{ $latest->recorded_at->format('d M Y H:i') }}
                                        @if ($latest->recordedBy)
                                            &middot; {{ $latest->recordedBy->name }}
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>

                        @if ($indicator['monitoring'])
                            @php
                                $monitoring = $indicator['monitoring'];
                                $monitoringStyles = [
                                    'overdue' => ['box' => 'border-red-200 bg-red-50 text-red-800', 'badge' => 'bg-red-600 text-white', 'word' => 'Overdue'],
                                    'due' => ['box' => 'border-amber-200 bg-amber-50 text-amber-800', 'badge' => 'bg-amber-400 text-white', 'word' => 'Due'],
                                ];
                                $monitoringStyle = $monitoringStyles[$monitoring['state']] ?? null;
                            @endphp
                            <div class="mb-3 flex items-center gap-2 rounded-lg border px-3 py-2 text-sm {{ $monitoringStyle['box'] ?? 'border-gray-200 bg-gray-50 text-gray-600' }}">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                @if ($monitoringStyle)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $monitoringStyle['badge'] }}">
                                        {{ $monitoringStyle['word'] }}
                                    </span>
                                @endif
                                <span>
                                    <span class="font-semibold">Reassess every {{ $monitoring['interval'] }}</span>
                                    &middot; {{ $monitoring['state'] === 'ok' ? $monitoring['label'] : ucfirst($monitoring['history']) }}
                                </span>
                            </div>
                        @endif

                        @if ($definition)
                            <form method="POST" action="{{ route('ward.clinical-indicator-score.store') }}"
                                class="rounded-xl border border-gray-200 bg-white p-4">
                                @csrf
                                <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                                <input type="hidden" name="clinical_indicator_id" value="{{ $indicator['id'] }}">
                                <input type="hidden" name="active_tab" value="indicator-{{ $indicator['id'] }}">

                                @if ($indicator['scorable'])
                                    <div class="divide-y divide-gray-100 rounded-lg border border-gray-200 overflow-hidden">
                                        @foreach ($items as $index => $item)
                                            <div class="px-3 py-2.5 flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-4">
                                                <label for="ci{{ $indicator['id'] }}_item{{ $index }}"
                                                    class="text-sm font-medium text-gray-800 sm:w-72 sm:shrink-0">
                                                    {{ $item['name'] }}
                                                </label>
                                                <select id="ci{{ $indicator['id'] }}_item{{ $index }}"
                                                    name="item_scores[{{ $index }}]"
                                                    x-model="items[{{ $index }}]"
                                                    class="flex-1 rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                                    <option value="">Select...</option>
                                                    @foreach ($item['options'] as $option)
                                                        <option value="{{ $option['value'] }}">
                                                            {{ $option['label'] }} ({{ $option['value'] }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-4">
                                        <label for="ci{{ $indicator['id'] }}_score"
                                            class="text-sm font-medium text-gray-800 sm:w-72 sm:shrink-0">
                                            Score
                                            @if ($definition['score_min'] !== null)
                                                <span class="font-normal text-gray-500">({{ $definition['score_min'] }} to
                                                    {{ $definition['score_max'] }})</span>
                                            @endif
                                        </label>
                                        <input type="number" id="ci{{ $indicator['id'] }}_score" name="score"
                                            x-model="manualScore" min="0"
                                            @if ($definition['score_min'] !== null) min="{{ $definition['score_min'] }}" max="{{ $definition['score_max'] }}" @endif
                                            class="w-32 rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                @endif

                                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                                    <div class="flex-1">
                                        <label for="ci{{ $indicator['id'] }}_notes"
                                            class="block text-xs font-semibold text-gray-700 mb-1">Notes (optional)</label>
                                        <input type="text" id="ci{{ $indicator['id'] }}_notes" name="notes" maxlength="1000"
                                            class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500"
                                            placeholder="Anything worth recording with this score">
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <div class="text-right">
                                            <div class="text-xs text-gray-500">Total</div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-2xl font-bold text-gray-800"
                                                    x-text="total === null ? '-' : total">-</span>
                                                <span x-show="band" x-cloak
                                                    class="inline-flex items-center px-2.5 py-1 rounded-lg border text-xs font-semibold"
                                                    :class="bandClass" x-text="band ? band.label : ''"></span>
                                            </div>
                                        </div>
                                        <button type="submit"
                                            class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:bg-gray-300 disabled:cursor-not-allowed"
                                            :disabled="total === null">
                                            Save score
                                        </button>
                                    </div>
                                </div>
                            </form>
                        @else
                            <div class="rounded-xl border border-gray-200 bg-gray-50 p-6">
                                <p class="text-sm text-gray-600">
                                    This scale was added for your hospital, so it has no content in the shared library
                                    yet and cannot be scored here.
                                </p>
                            </div>
                        @endif

                        @if ($indicator['history']->isNotEmpty())
                            <div class="mt-4">
                                <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Recent scores</h4>
                                <div class="overflow-x-auto rounded-lg border border-gray-200">
                                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">When</th>
                                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Score</th>
                                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Band</th>
                                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">By</th>
                                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-100">
                                            @foreach ($indicator['history'] as $score)
                                                <tr>
                                                    <td class="px-3 py-2 whitespace-nowrap text-gray-600">
                                                        {{ $score->recorded_at->format('d M H:i') }}</td>
                                                    <td class="px-3 py-2 font-semibold text-gray-800 whitespace-nowrap">
                                                        {{ $score->score }}
                                                        @if ($score->breakdown())
                                                            <span class="ml-1 text-xs font-normal text-gray-500">{{ $score->breakdown() }}</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-3 py-2 whitespace-nowrap">
                                                        @if ($score->band_label)
                                                            <span
                                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                                                {{ $score->band_tone === 'high' ? 'bg-red-100 text-red-800' : ($score->band_tone === 'moderate' ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800') }}">
                                                                {{ $score->band_label }}
                                                            </span>
                                                        @else
                                                            <span class="text-gray-400">-</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-3 py-2 text-gray-600 whitespace-nowrap">
                                                        {{ $score->recordedBy->name ?? '-' }}</td>
                                                    <td class="px-3 py-2 text-gray-600">{{ $score->notes ?: '-' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        @if ($definition)
                            <div class="mt-4">
                                <button type="button" @click="showReference = !showReference"
                                    class="inline-flex items-center text-xs font-medium text-blue-600 hover:text-blue-800">
                                    <svg class="w-3.5 h-3.5 mr-1 transition-transform" :class="showReference ? 'rotate-180' : ''"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                    <span x-text="showReference ? 'Hide scale details' : 'Show scale details'">Show scale
                                        details</span>
                                </button>
                                <div x-show="showReference" x-cloak class="mt-2">
                                    <x-clinical-indicator-detail :definition="$definition" />
                                </div>
                            </div>
                        @endif
                    </div>
                    </div>
                @endforeach
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


