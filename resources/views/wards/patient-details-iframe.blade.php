<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Details</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-br from-blue-50 via-white to-cyan-50 min-h-screen">
@php
    use App\Models\DietType;
    use App\Models\IsolationType;
    
    $patientTabs = [
        'info' => true,
        'additional' => true,
    ];
    $allergySource = $patient ? ($patient->allergies ?? []) : [];
    $allergyStrings = collect($allergySource)->map(function($a) {
        if (is_array($a)) {
            return $a['allergen'] ?? $a['allergen_code'] ?? json_encode($a);
        }
        return $a;
    })->values()->toArray();
    
    // Get display names from database tables
    $dietTypeDisplay = $patient && $patient->diet_type 
        ? DietType::getDisplayName($patient->diet_type) 
        : 'Regular diet';
    $isolationTypeDisplay = $patient && $patient->isolation_type && $patient->isolation_type !== 'none'
        ? IsolationType::getDisplayName($patient->isolation_type) 
        : 'None';
@endphp

<div class="p-6 max-w-6xl mx-auto"
     x-data='@json([
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

        <div x-show="activeTab === 'additional'" x-cloak
             x-data="{
                allergies: @json($allergyStrings),
                newAllergy: '',
                addAllergy() {},
                removeAllergy() {}
             }">
            <h3 class="text-lg font-semibold text-gray-800 mb-3">Patient Additional Info</h3>
            <p class="text-sm text-gray-600 mb-4">
                View clinical indicators and patient care information.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
                    <div class="text-xs uppercase text-gray-500">Nursing Level of Care</div>
                    <div class="mt-2">
                        @php $nl = $patient->nursing_level ?? 'none'; @endphp
                        <span class="inline-flex px-3 py-1 rounded-full text-sm font-semibold bg-blue-100 text-blue-800">
                            {{ str_replace('_', ' ', ucfirst($nl)) }}
                        </span>
                    </div>
                </div>
                <div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
                    <div class="text-xs uppercase text-gray-500">Diet Type</div>
                    <div class="mt-2">
                        <span class="inline-flex px-3 py-1 rounded-full text-sm font-semibold bg-cyan-100 text-cyan-800">
                            {{ $dietTypeDisplay }}
                        </span>
                    </div>
                </div>
                <div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
                    <div class="text-xs uppercase text-gray-500">Fall Risk</div>
                    <div class="mt-2">
                        <span class="inline-flex px-3 py-1 rounded-full text-sm font-semibold bg-amber-100 text-amber-800">
                            {{ str_replace('_', ' ', ucfirst($patient->fall_risk ?? 'none')) }}
                        </span>
                    </div>
                </div>
                <div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
                    <div class="text-xs uppercase text-gray-500">Isolation Precautions</div>
                    <div class="mt-2">
                        <span class="inline-flex px-3 py-1 rounded-full text-sm font-semibold bg-purple-100 text-purple-800">
                            {{ $isolationTypeDisplay }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="border-t pt-6 mt-6">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <div class="text-sm font-semibold text-gray-700">Medical Allergies</div>
                        <p class="text-xs text-gray-500">Recorded allergies for this patient</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <template x-for="(allergy, index) in allergies" :key="index">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-pink-100 text-pink-800">
                            <span x-text="allergy"></span>
                        </span>
                    </template>
                    <p x-show="allergies.length === 0" class="text-xs text-gray-400 italic">No allergies recorded</p>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
</div>
</body>
</html>

