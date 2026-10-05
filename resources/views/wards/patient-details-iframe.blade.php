<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Details</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <x-theme-style />
</head>

<body class="bg-gradient-to-br from-blue-50 via-white to-cyan-50 min-h-screen">
    @php
        use App\Models\DietType;
        use App\Models\IsolationType;
        use App\Models\PatientCareProvider;

        $patientTabs = [
            'info' => true,
            'additional' => true,
            'careprovider' => true,
        ];
        $allergySource = $patient ? ($patient->allergies ?? []) : [];
        $allergyList = collect($allergySource)->map(function ($a) {
            if (is_array($a)) {
                $rawName = $a['allergen'] ?? $a['allergen_code'] ?? 'Unknown';
                $name = str_contains($rawName, '^') ? explode('^', $rawName)[1] ?? $rawName : $rawName;

                return [
                    'name' => $name,
                    'status' => $a['status'] ?? 'Active',
                ];
            }
            $rawName = $a;
            $name = str_contains($rawName, '^') ? explode('^', $rawName)[1] ?? $rawName : $rawName;

            return [
                'name' => $name,
                'status' => 'Active'
            ];
        })->values()->toArray();

        // Get display names from database tables
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

    <div class="p-6 max-w-6xl mx-auto" x-data='@json([
        "activeTab" => "info",
        "patientTabs" => $patientTabs,
    ])'>
        <div class="bg-white/80 backdrop-blur-sm border border-blue-100 shadow-lg rounded-2xl p-5">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Patient Details</h2>
                    @if($patient)
                        <p class="text-sm text-gray-600 mt-1 flex flex-wrap items-center gap-2">
                            <span class="font-semibold text-gray-800">{{ $patient->name }}</span>
                            <span class="text-gray-400">•</span>
                            <span>MRN: <span class="font-semibold">{{ $patient->mrn }}</span></span>
                            @if($patient->bed_number)
                                <span class="text-gray-400">•</span>
                                <span>Bed: <span class="font-semibold">{{ $patient->bed_number }}</span></span>
                            @endif
                            @if($patient->ward)
                                <span class="text-gray-400">•</span>
                                <span>Ward: <span class="font-semibold">{{ $patient->ward->ward_name }}</span></span>
                            @endif
                        </p>
                    @else
                        <p class="text-sm text-red-500 mt-1">Patient not found or inactive.</p>
                    @endif
                </div>
            </div>

            @if($patient)
                <div class="border-b border-gray-200 mt-4 mb-4">
                    <nav class="-mb-px flex space-x-4 text-sm" aria-label="Tabs">
                        <button type="button" @click="activeTab = 'info'" x-show="patientTabs.info"
                            :class="activeTab === 'info' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-2 px-3 border-b-2 font-medium">
                            Patient Info
                        </button>
                        <button type="button" @click="activeTab = 'additional'" x-show="patientTabs.additional"
                            :class="activeTab === 'additional' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-2 px-3 border-b-2 font-medium">
                            Patient Additional Info
                        </button>
                        <button type="button" @click="activeTab = 'careprovider'" x-show="patientTabs.careprovider"
                            :class="activeTab === 'careprovider' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-2 px-3 border-b-2 font-medium">
                            Care Provider
                        </button>
                    </nav>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-blue-100 p-5">
                    <div x-show="activeTab === 'info'" x-cloak>
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Patient Info</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                            @php
                                $infoRows = [
                                    ['label' => 'Name', 'value' => $patient->name],
                                    ['label' => 'MRN', 'value' => $patient->mrn],
                                    ['label' => 'RN', 'value' => $patient->rn ?? '-'],
                                    ['label' => 'Gender', 'value' => $patient->gender ?? '-'],
                                    ['label' => 'Age', 'value' => $patient->age ?? '-'],
                                    ['label' => 'Ward', 'value' => $patient->ward->ward_name ?? '-'],
                                    ['label' => 'Bed', 'value' => $patient->bed_number ?? '-'],
                                    ['label' => 'Consultant', 'value' => $patient->consultant->name ?? 'Not Assigned'],
                                    ['label' => 'Nurse', 'value' => $patient->nurse->name ?? 'Not Assigned'],
                                    ['label' => 'Anaesthetist', 'value' => $patient->anaesthetist->name ?? 'Not Assigned'],
                                    ['label' => 'Status', 'value' => ucfirst($patient->status ?? '-')],
                                    ['label' => 'Admitted At', 'value' => $patient->admitted_at ? $patient->admitted_at->format('Y-m-d H:i') : '-'],
                                    ['label' => 'Prebooked At', 'value' => $patient->booked_at ? $patient->booked_at->format('Y-m-d H:i') : '-'],
                                ];
                            @endphp
                            @foreach($infoRows as $row)
                                <div class="bg-gray-50 rounded-lg border border-gray-100 p-3">
                                    <div class="text-gray-500 text-xs uppercase tracking-wide">{{ $row['label'] }}</div>
                                    <div class="font-medium text-gray-900 mt-1">{{ $row['value'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div x-show="activeTab === 'additional'" x-cloak x-data="{
                                allergies: @json($allergyList)
                             }">
                        <h3 class="text-lg font-semibold text-gray-800 mb-3">Patient Additional Info</h3>
                        <p class="text-sm text-gray-600 mb-4">
                            View clinical indicators and patient care information. Diet types and allergies are managed by
                            the ADT system.
                        </p>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
                                <div class="text-xs uppercase text-gray-500">Nursing Level of Care</div>
                                <div class="mt-2">
                                    @php $nl = $patient->nursing_level ?? 'none'; @endphp
                                    <span
                                        class="inline-flex px-3 py-1 rounded-full text-sm font-semibold bg-blue-100 text-blue-800">
                                        {{ str_replace('_', ' ', ucfirst($nl)) }}
                                    </span>
                                </div>
                            </div>
                            <div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
                                <div class="text-xs uppercase text-gray-500">Diet Type <span
                                        class="normal-case text-gray-400">(from ADT)</span></div>
                                <div class="mt-2 flex flex-wrap gap-1">
                                    @if(count($dietTypesArray) > 0)
                                        @foreach($dietTypesArray as $dietCode)
                                            <span
                                                class="inline-flex px-3 py-1 rounded-full text-sm font-semibold bg-cyan-100 text-cyan-800">
                                                {{ \App\Models\DietType::getDisplayName($dietCode) }}
                                            </span>
                                        @endforeach
                                    @else
                                        <span
                                            class="inline-flex px-3 py-1 rounded-full text-sm font-semibold bg-gray-100 text-gray-600">
                                            Regular diet
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
                                <div class="text-xs uppercase text-gray-500">Fall Risk</div>
                                <div class="mt-2">
                                    <span
                                        class="inline-flex px-3 py-1 rounded-full text-sm font-semibold bg-amber-100 text-amber-800">
                                        {{ str_replace('_', ' ', ucfirst($patient->fall_risk ?? 'none')) }}
                                    </span>
                                </div>
                            </div>
                            <div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
                                <div class="text-xs uppercase text-gray-500">Isolation Precautions</div>
                                <div class="mt-2">
                                    <span
                                        class="inline-flex px-3 py-1 rounded-full text-sm font-semibold bg-purple-100 text-purple-800">
                                        {{ $isolationTypeDisplay }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="border-t pt-6 mt-6">
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <div class="text-sm font-semibold text-gray-700">Medical Allergies <span
                                            class="font-normal text-gray-400 text-xs">(from ADT)</span></div>
                                    <p class="text-xs text-gray-500">Allergies are managed by ADT system</p>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="(allergy, index) in allergies" :key="index">
                                    <span
                                        class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium border"
                                        :class="allergy.status === 'Resolved' ? 'bg-green-100 text-green-800 border-green-200' : 'bg-pink-100 text-pink-800 border-pink-200'">
                                        <svg class="w-3 h-3 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                        <span x-text="allergy.name"></span>
                                        <span class="ml-1 text-xs font-semibold"
                                            x-text="allergy.status === 'Resolved' ? '(Resolved)' : ''"></span>
                                    </span>
                                </template>
                                <p x-show="allergies.length === 0" class="text-sm text-gray-400 italic">No allergies
                                    recorded</p>
                            </div>
                        </div>
                    </div>

                    <!-- Care Provider Tab -->
                    <div x-show="activeTab === 'careprovider'" x-cloak>
                        <h3 class="text-lg font-semibold text-gray-800 mb-3">Care Provider</h3>
                        <p class="text-sm text-gray-600 mb-4">
                            Doctors assigned to this patient from ADT PV1 segment. This includes attending, referring, and
                            consulting doctors.
                        </p>

                        <div class="space-y-6">
                            <!-- Attending Doctors (PV1-7) -->
                            <div class="rounded-lg border border-blue-100 bg-blue-50/50 p-4">
                                <div class="flex items-center mb-3">
                                    <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    <h4 class="text-sm font-semibold text-blue-800">Attending Doctor <span
                                            class="font-normal text-blue-600">(PV1-7)</span></h4>
                                </div>
                                @if($attendingDoctors->count() > 0)
                                    <div class="space-y-2">
                                        @foreach($attendingDoctors as $provider)
                                            <div
                                                class="flex items-center justify-between bg-white rounded-lg p-3 border border-blue-100">
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
                                                    <span
                                                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd"
                                                                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                                clip-rule="evenodd" />
                                                        </svg>
                                                        Linked
                                                    </span>
                                                @else
                                                    <span
                                                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
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
                                    <svg class="w-5 h-5 text-purple-600 mr-2" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    <h4 class="text-sm font-semibold text-purple-800">Referring Doctor <span
                                            class="font-normal text-purple-600">(PV1-8)</span></h4>
                                </div>
                                @if($referringDoctors->count() > 0)
                                    <div class="space-y-2">
                                        @foreach($referringDoctors as $provider)
                                            <div
                                                class="flex items-center justify-between bg-white rounded-lg p-3 border border-purple-100">
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
                                                    <span
                                                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd"
                                                                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                                clip-rule="evenodd" />
                                                        </svg>
                                                        Linked
                                                    </span>
                                                @else
                                                    <span
                                                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
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
                                    <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                    <h4 class="text-sm font-semibold text-green-800">Consulting Doctor <span
                                            class="font-normal text-green-600">(PV1-9)</span></h4>
                                </div>
                                @if($consultingDoctors->count() > 0)
                                    <div class="space-y-2">
                                        @foreach($consultingDoctors as $provider)
                                            <div
                                                class="flex items-center justify-between bg-white rounded-lg p-3 border border-green-100">
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
                                                    <span
                                                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd"
                                                                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                                clip-rule="evenodd" />
                                                        </svg>
                                                        Linked
                                                    </span>
                                                @else
                                                    <span
                                                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
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
                                <svg class="w-5 h-5 text-gray-400 mr-2 mt-0.5" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <div class="text-sm text-gray-600">
                                    <p class="font-medium text-gray-700">About Care Providers from ADT</p>
                                    <p class="mt-1">Care providers are automatically assigned from HL7 ADT messages. Each
                                        doctor type corresponds to specific PV1 fields:</p>
                                    <ul class="mt-2 list-disc list-inside text-xs space-y-1">
                                        <li><strong>Attending Doctor (PV1-7):</strong> The primary physician responsible for
                                            the patient's care</li>
                                        <li><strong>Referring Doctor (PV1-8):</strong> The physician who referred the
                                            patient</li>
                                        <li><strong>Consulting Doctor (PV1-9):</strong> Additional specialists consulted for
                                            the patient's care</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</body>

</html>