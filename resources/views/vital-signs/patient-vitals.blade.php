<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Vital Signs</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        
        /* IHH Chart Styles */
        .ihh-chart {
            font-size: 10px;
            border-collapse: collapse;
            width: 100%;
        }
        .ihh-chart th, .ihh-chart td {
            border: 1px solid #999;
            padding: 0;
            text-align: center;
            height: 16px;
            min-width: 28px;
        }
        .ihh-chart .section-header {
            background: #f0f0f0;
            font-weight: bold;
            text-align: left;
            padding: 2px 4px;
        }
        .ihh-chart .score-col {
            width: 24px;
            font-weight: bold;
        }
        /* Temperature zones - Score 0: White, Score 1: Orange, Score 2: Red */
        .ihh-temp-high2 { background: #f4cccc; } /* Score 2 - Red */
        .ihh-temp-high1 { background: #fce5cd; } /* Score 1 - Orange */
        .ihh-temp-normal { background: #ffffff; } /* Score 0 - White */
        .ihh-temp-low1 { background: #fce5cd; } /* Score 1 - Orange */
        .ihh-temp-low2 { background: #f4cccc; } /* Score 2 - Red */
        
        /* Blood Pressure zones - Score 0: White, Score 1: Orange, Score 2: Red */
        .ihh-bp-high2 { background: #f4cccc; } /* Score 2 - Red */
        .ihh-bp-high1 { background: #fce5cd; } /* Score 1 - Orange */
        .ihh-bp-normal { background: #ffffff; } /* Score 0 - White */
        .ihh-bp-low1 { background: #fce5cd; } /* Score 1 - Orange */
        .ihh-bp-low2 { background: #f4cccc; } /* Score 2 - Red */
        
        /* Pulse Rate zones - Score 0: White, Score 1: Orange, Score 2: Red */
        .ihh-pr-high2 { background: #f4cccc; } /* Score 2 - Red */
        .ihh-pr-high1 { background: #fce5cd; } /* Score 1 - Orange */
        .ihh-pr-normal { background: #ffffff; } /* Score 0 - White */
        .ihh-pr-low1 { background: #fce5cd; } /* Score 1 - Orange */
        .ihh-pr-low2 { background: #f4cccc; } /* Score 2 - Red */
        
        /* Respiration Rate zones - Score 0: White, Score 1: Orange, Score 2: Red */
        .ihh-rr-high2 { background: #f4cccc; } /* Score 2 - Red */
        .ihh-rr-high1 { background: #fce5cd; } /* Score 1 - Orange */
        .ihh-rr-normal { background: #ffffff; } /* Score 0 - White */
        .ihh-rr-low1 { background: #fce5cd; } /* Score 1 - Orange */
        .ihh-rr-low2 { background: #f4cccc; } /* Score 2 - Red */
        
        .ihh-marker {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #000;
            border-radius: 50%;
        }
        .ihh-marker-x {
            font-weight: bold;
            font-size: 12px;
        }
        .ihh-marker-systolic {
            font-weight: bold;
            font-size: 14px;
            line-height: 1;
            color: #000;
        }
        .ihh-marker-diastolic {
            font-weight: bold;
            font-size: 14px;
            line-height: 1;
            color: #000;
        }
        .ihh-bp-line {
            display: inline-block;
            width: 2px;
            height: 100%;
            background: #000;
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
        }
        .ihh-bp-cell {
            position: relative;
        }
        .ihh-label-col {
            width: 50px;
            text-align: right;
            padding-right: 4px !important;
            font-weight: bold;
            background: #f9f9f9;
        }
    </style>
</head>
<body class="bg-gray-50">
    @php
        // Values the edit form loads when a recorded reading is picked
        $editorPayload = fn ($vital) => [
            'id' => $vital->id,
            'recorded_at' => $vital->recorded_at?->format('Y-m-d\TH:i') ?? '',
            'systolic_bp' => $vital->systolic_bp ?? '',
            'diastolic_bp' => $vital->diastolic_bp ?? '',
            'pulse_rate' => $vital->pulse_rate ?? '',
            'temperature' => $vital->temperature ?? '',
            'spo2' => $vital->spo2 ?? '',
            'respiratory_rate' => $vital->respiratory_rate ?? '',
            'oxygen_delivery' => $vital->oxygen_delivery ?? '',
            'oxygen_flow_rate' => $vital->oxygen_flow_rate ?? '',
            'fio2_percent' => $vital->fio2_percent ?? '',
            'notes' => $vital->notes ?? '',
        ];
    @endphp
    <div class="p-4" x-data='{
        view: "ihh",
        chartInstance: null,
        editorMode: null,
        deleteId: null,
        passphrase: "",
        editorForm: {},
        blankForm() {
            return { id: null, recorded_at: "", systolic_bp: "", diastolic_bp: "", pulse_rate: "", temperature: "",
                     spo2: "", respiratory_rate: "", oxygen_delivery: "", oxygen_flow_rate: "", fio2_percent: "", notes: "" };
        },
        startAdd() {
            this.editorForm = this.blankForm();
            this.passphrase = "";
            this.deleteId = null;
            this.editorMode = "add";
        },
        startEdit(vital) {
            this.editorForm = Object.assign(this.blankForm(), vital);
            this.passphrase = "";
            this.deleteId = null;
            this.editorMode = "edit";
        },
        startDelete(id) {
            this.deleteId = id;
            this.passphrase = "";
            this.editorMode = "delete";
        },
        closeEditor() {
            this.editorMode = null;
            this.deleteId = null;
            this.passphrase = "";
        },
        onOxygen() {
            return this.editorForm.oxygen_delivery && this.editorForm.oxygen_delivery !== "room_air";
        },
        renderChart() {
            if (this.chartInstance) {
                this.chartInstance.destroy();
            }
            const ctx = this.$refs.vitalsChart?.getContext("2d");
            if (!ctx) return;
            
            const vitals = @json($vitalSigns->take(10)->reverse()->values());
            const labels = vitals.map(v => v.recorded_at ? new Date(v.recorded_at).toLocaleString("en-GB", { month: "short", day: "numeric", hour: "2-digit", minute: "2-digit" }) : "");
            
            this.chartInstance = new Chart(ctx, {
                type: "line",
                data: {
                    labels,
                    datasets: [
                        {
                            label: "Systolic BP",
                            data: vitals.map(v => v.systolic_bp),
                            borderColor: "#dc2626",
                            backgroundColor: "rgba(220, 38, 38, 0.1)",
                            tension: 0.3,
                        },
                        {
                            label: "Diastolic BP",
                            data: vitals.map(v => v.diastolic_bp),
                            borderColor: "#f97316",
                            backgroundColor: "rgba(249, 115, 22, 0.1)",
                            tension: 0.3,
                        },
                        {
                            label: "Pulse Rate",
                            data: vitals.map(v => v.pulse_rate),
                            borderColor: "#2563eb",
                            backgroundColor: "rgba(37, 99, 235, 0.1)",
                            tension: 0.3,
                        },
                        {
                            label: "SpO2",
                            data: vitals.map(v => v.spo2),
                            borderColor: "#16a34a",
                            backgroundColor: "rgba(22, 163, 74, 0.1)",
                            tension: 0.3,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: "index", intersect: false },
                    scales: {
                        y: { beginAtZero: false }
                    },
                    plugins: { legend: { position: "bottom" } }
                }
            });
        }
    }'>
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-xl font-bold text-gray-800 flex items-center">
                    <svg class="w-6 h-6 mr-2 text-rose-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"/>
                    </svg>
                    Vital Signs
                </h2>
                @if($patient)
                    <p class="text-sm text-gray-600 mt-1">
                        {{ $patient->name }} 
                        <span class="text-gray-400 mx-1">•</span>
                        MRN: <span class="font-semibold">{{ $patient->mrn }}</span>
                        @if($patient->bed_number)
                            <span class="text-gray-400 mx-1">•</span>
                            Bed: <span class="font-semibold">{{ $patient->bed_number }}</span>
                        @endif
                    </p>
                @else
                    <p class="text-sm text-red-500 mt-1">Patient not found.</p>
                @endif
            </div>

            <div class="flex items-center gap-2">
            @if($editable && $patient)
                <button type="button" @click="startAdd()"
                    class="inline-flex items-center px-3 py-1.5 rounded-md bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold shadow-sm">
                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Record Reading
                </button>
            @endif

            <!-- View Toggle -->
            <div class="inline-flex rounded-md shadow-sm border border-gray-200 bg-white overflow-hidden text-xs">
                <button type="button"
                        @click="view = 'ihh'"
                        :class="view === 'ihh' ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                        class="px-3 py-1 font-semibold flex items-center space-x-1 border-r border-gray-200">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span>IHH Chart</span>
                </button>
                <button type="button"
                        @click="view = 'list'"
                        :class="view === 'list' ? 'bg-gray-100 text-gray-900' : 'bg-white text-gray-600 hover:bg-gray-50'"
                        class="px-3 py-1 font-semibold border-r border-gray-200">
                    List
                </button>
                <button type="button"
                        @click="view = 'graph'; $nextTick(() => renderChart())"
                        :class="view === 'graph' ? 'bg-rose-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                        class="px-3 py-1 font-semibold flex items-center space-x-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 19h16M5 16l4-6 4 4 6-10"/>
                    </svg>
                    <span>Graph</span>
                </button>
            </div>
            </div>
        </div>

        @if (session('success'))
            <div class="mb-4 rounded-lg border-l-4 border-green-500 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-lg border-l-4 border-red-500 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">
                {{ session('error') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border-l-4 border-red-500 bg-red-50 px-4 py-3 text-sm text-red-800">
                <p class="font-medium mb-1">The reading was not saved:</p>
                <ul class="list-disc list-inside text-xs">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($patient)
            @if($editable)
                @include('vital-signs.partials.reading-editor', ['patient' => $patient])
            @endif

            <!-- Admission Filter -->
            @if(count($admissions) > 0)
            <div class="mb-4">
                <form method="GET" action="{{ route('vital-signs.patient') }}" class="flex items-center space-x-2">
                    <input type="hidden" name="patient_id" value="{{ $patient->id }}">@if($editable)<input type="hidden" name="edit" value="1">@endif
                    <label class="text-sm font-semibold text-gray-700">Filter by Admission:</label>
                    <select name="admission_id" onchange="this.form.submit()" class="rounded-lg border-gray-300 shadow-sm text-sm focus:border-rose-500 focus:ring-rose-500">
                        <option value="">All Admissions</option>
                        @foreach($admissions as $admission)
                            <option value="{{ $admission['id'] }}" {{ $selectedAdmissionId == $admission['id'] ? 'selected' : '' }}>
                                {{ $admission['label'] }}
                            </option>
                        @endforeach
                    </select>
                </form>
            </div>
            @endif

            <!-- Latest Vitals Summary -->
            @if($vitalSigns->count() > 0)
                @php $latest = $vitalSigns->first(); @endphp
                <div class="grid grid-cols-6 gap-3 mb-4">
                    <div class="bg-gradient-to-br from-red-50 to-red-100 border border-red-200 rounded-lg p-3 text-center">
                        <div class="text-xs text-red-600 font-semibold mb-1">Blood Pressure</div>
                        <div class="text-xl font-bold text-gray-900">
                            {{ $latest->blood_pressure ?? '-' }}
                        </div>
                        <div class="text-xs text-gray-500">mmHg</div>
                    </div>
                    <div class="bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200 rounded-lg p-3 text-center">
                        <div class="text-xs text-blue-600 font-semibold mb-1">Pulse Rate</div>
                        <div class="text-xl font-bold text-gray-900">{{ $latest->pulse_rate_display ?? '-' }}</div>
                        <div class="text-xs text-gray-500">bpm</div>
                    </div>
                    <div class="bg-gradient-to-br from-orange-50 to-orange-100 border border-orange-200 rounded-lg p-3 text-center">
                        <div class="text-xs text-orange-600 font-semibold mb-1">Temperature</div>
                        <div class="text-xl font-bold text-gray-900">
                            {{ $latest->temperature ? number_format($latest->temperature, 1) . '°C' : '-' }}
                        </div>
                        <div class="text-xs text-gray-500">Celsius</div>
                    </div>
                    <div class="bg-gradient-to-br from-green-50 to-green-100 border border-green-200 rounded-lg p-3 text-center">
                        <div class="text-xs text-green-600 font-semibold mb-1">SpO2</div>
                        <div class="text-xl font-bold {{ $latest->spo2 && $latest->spo2 < 95 ? 'text-red-600' : 'text-gray-900' }}">
                            {{ $latest->spo2_display ? $latest->spo2_display . '%' : '-' }}
                        </div>
                        <div class="text-xs text-gray-500">Oxygen</div>
                    </div>
                    <div class="bg-gradient-to-br from-cyan-50 to-cyan-100 border border-cyan-200 rounded-lg p-3 text-center">
                        <div class="text-xs text-cyan-600 font-semibold mb-1">Resp. Rate</div>
                        <div class="text-xl font-bold text-gray-900">{{ $latest->respiratory_rate ?? '-' }}</div>
                        <div class="text-xs text-gray-500">/min</div>
                    </div>
                    <div class="bg-gradient-to-br from-sky-50 to-sky-100 border border-sky-200 rounded-lg p-3 text-center">
                        <div class="text-xs text-sky-600 font-semibold mb-1">Oxygen</div>
                        <div class="text-base font-bold leading-tight {{ $latest->isOnOxygen() ? 'text-sky-700' : 'text-gray-900' }}">
                            {{ $latest->oxygenDeliveryLabel() ?? '-' }}
                        </div>
                        <div class="text-xs text-gray-500">
                            @if($latest->oxygen_flow_rate !== null)
                                {{ rtrim(rtrim(number_format($latest->oxygen_flow_rate, 1), '0'), '.') }} L/min
                            @elseif($latest->fio2_percent !== null)
                                FiO₂ {{ $latest->fio2_percent }}%
                            @elseif($latest->oxygen_delivery === \App\Models\VitalSign::OXYGEN_ROOM_AIR)
                                No supplemental O₂
                            @elseif($latest->oxygen_delivery)
                                On oxygen
                            @else
                                Not recorded
                            @endif
                        </div>
                    </div>
                </div>
                <div class="text-xs text-gray-500 mb-4">
                    Last recorded: {{ $latest->recorded_at->format('Y-m-d H:i') }} ({{ $latest->recorded_at->diffForHumans() }})
                </div>
            @endif

            <!-- List View -->
            <div x-show="view === 'list'" x-cloak>
                @if($vitalSigns->count() > 0)
                    <div class="overflow-x-auto border border-gray-200 rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200 text-xs">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-600">Date/Time</th>
                                    <th class="px-3 py-2 text-center font-semibold text-gray-600">BP</th>
                                    <th class="px-3 py-2 text-center font-semibold text-gray-600">PR</th>
                                    <th class="px-3 py-2 text-center font-semibold text-gray-600">Temp</th>
                                    <th class="px-3 py-2 text-center font-semibold text-gray-600">SpO2</th>
                                    <th class="px-3 py-2 text-center font-semibold text-gray-600">RR</th>
                                    <th class="px-3 py-2 text-center font-semibold text-gray-600">O₂</th>
                                    <th class="px-3 py-2 text-center font-semibold text-gray-600">Type</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-600">Admission</th>
                                    @if($editable)
                                        <th class="px-3 py-2 text-right font-semibold text-gray-600">Actions</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @php $currentAdm = null; @endphp
                                @foreach($vitalSigns as $vital)
                                    @if($vital->admission_id !== $currentAdm)
                                        @php $currentAdm = $vital->admission_id; @endphp
                                        <tr class="bg-rose-50">
                                            <td colspan="{{ $editable ? 10 : 9 }}" class="px-3 py-2 text-xs font-bold text-rose-700">
                                                <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
                                                </svg>
                                                {{ $vital->admission_id ?? 'General' }}
                                            </td>
                                        </tr>
                                    @endif
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 py-2">
                                            <div class="font-semibold">{{ $vital->recorded_at->format('Y-m-d') }}</div>
                                            <div class="text-gray-500">{{ $vital->recorded_at->format('H:i') }}</div>
                                        </td>
                                        <td class="px-3 py-2 text-center font-bold text-red-600">
                                            {{ $vital->blood_pressure ?? '-' }}
                                        </td>
                                        <td class="px-3 py-2 text-center font-bold text-blue-600">
                                            {{ $vital->pulse_rate_display ?? '-' }}
                                        </td>
                                        <td class="px-3 py-2 text-center font-bold text-orange-600">
                                            {{ $vital->temperature ? number_format($vital->temperature, 1) : '-' }}
                                        </td>
                                        <td class="px-3 py-2 text-center font-bold {{ $vital->spo2 && $vital->spo2 < 95 ? 'text-red-600' : 'text-green-600' }}">
                                            {{ $vital->spo2_display ? $vital->spo2_display . '%' : '-' }}
                                        </td>
                                        <td class="px-3 py-2 text-center font-bold text-cyan-600">
                                            {{ $vital->respiratory_rate ?? '-' }}
                                        </td>
                                        <td class="px-3 py-2 text-center whitespace-nowrap">
                                            @if($vital->oxygen_delivery)
                                                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-semibold {{ $vital->isOnOxygen() ? 'bg-sky-100 text-sky-700' : 'bg-gray-100 text-gray-600' }}"
                                                    title="{{ $vital->oxygenDeliveryLabel() }}">
                                                    {{ $vital->oxygenShortLabel() }}
                                                </span>
                                            @else
                                                <span class="text-gray-300">-</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-semibold {{ $vital->reading_type === 'full' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                                {{ ucfirst($vital->reading_type) }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-gray-500 text-[10px]">
                                            {{ Str::limit($vital->admission_id ?? 'General', 20) }}
                                        </td>
                                        @if($editable)
                                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                                @if($vital->isManualEntry())
                                                    <button type="button" @click="startEdit(@js($editorPayload($vital)))"
                                                        class="px-2 py-1 rounded bg-blue-50 text-blue-700 hover:bg-blue-100 font-semibold">Edit</button>
                                                    <button type="button" @click="startDelete({{ $vital->id }})"
                                                        class="px-2 py-1 rounded bg-red-50 text-red-700 hover:bg-red-100 font-semibold">Delete</button>
                                                @else
                                                    <span class="text-[10px] text-gray-400" title="Received from a monitor">From monitor</span>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="border border-dashed border-gray-300 rounded-lg p-8 text-center">
                        <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                        <p class="text-gray-500 font-medium">No vital signs recorded</p>
                        <p class="text-xs text-gray-400 mt-1">Vital signs will appear here once recorded</p>
                    </div>
                @endif
            </div>

            <!-- Graph View -->
            <div x-show="view === 'graph'" x-cloak>
                @if($vitalSigns->count() >= 2)
                    <div class="h-72 md:h-80 border border-gray-200 rounded-lg p-2 bg-white">
                        <canvas x-ref="vitalsChart"></canvas>
                    </div>
                @else
                    <div class="border border-dashed border-gray-300 rounded-lg p-8 text-center">
                        <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 19h16M5 16l4-6 4 4 6-10"/>
                        </svg>
                        <p class="text-gray-500 font-medium">Not enough data for graph</p>
                        <p class="text-xs text-gray-400 mt-1">At least 2 readings are required to display a trend</p>
                    </div>
                @endif
            </div>

            <!-- IHH Chart View (Modified Clinical Chart / Early Warning Score) -->
            <div x-show="view === 'ihh'" x-cloak>
                <!-- Date Navigation for IHH Chart -->
                <div class="flex items-center justify-between mb-3 bg-gray-50 rounded-lg p-2 border border-gray-200">
                    <form method="GET" action="{{ route('vital-signs.patient') }}" class="flex items-center space-x-2">
                        <input type="hidden" name="patient_id" value="{{ $patient->id }}">@if($editable)<input type="hidden" name="edit" value="1">@endif
                        @if($selectedAdmissionId)
                            <input type="hidden" name="admission_id" value="{{ $selectedAdmissionId }}">
                        @endif
                        <input type="hidden" name="date" value="{{ $selectedDate->copy()->subDay()->format('Y-m-d') }}">
                        <button type="submit" 
                                @if(!$hasPreviousDay) disabled @endif
                                class="px-3 py-1.5 rounded-md text-sm font-medium transition-all {{ $hasPreviousDay ? 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-100 shadow-sm' : 'bg-gray-100 text-gray-400 cursor-not-allowed' }}">
                            <svg class="w-4 h-4 inline -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                            Previous
                        </button>
                    </form>

                    <div class="text-center">
                        <div class="text-lg font-bold text-gray-800">{{ $selectedDate->format('d M Y') }}</div>
                        <div class="text-xs text-gray-500">
                            @if($selectedDate->isToday())
                                <span class="text-blue-600 font-semibold">Today</span>
                            @elseif($selectedDate->isYesterday())
                                Yesterday
                            @else
                                {{ $selectedDate->diffForHumans() }}
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center space-x-2">
                        @if(!$selectedDate->isToday())
                            <form method="GET" action="{{ route('vital-signs.patient') }}">
                                <input type="hidden" name="patient_id" value="{{ $patient->id }}">@if($editable)<input type="hidden" name="edit" value="1">@endif
                                @if($selectedAdmissionId)
                                    <input type="hidden" name="admission_id" value="{{ $selectedAdmissionId }}">
                                @endif
                                <button type="submit" 
                                        class="px-3 py-1.5 rounded-md text-sm font-medium bg-blue-600 text-white hover:bg-blue-700 shadow-sm transition-all">
                                    Today
                                </button>
                            </form>
                        @endif

                        <form method="GET" action="{{ route('vital-signs.patient') }}">
                            <input type="hidden" name="patient_id" value="{{ $patient->id }}">@if($editable)<input type="hidden" name="edit" value="1">@endif
                            @if($selectedAdmissionId)
                                <input type="hidden" name="admission_id" value="{{ $selectedAdmissionId }}">
                            @endif
                            <input type="hidden" name="date" value="{{ $selectedDate->copy()->addDay()->format('Y-m-d') }}">
                            <button type="submit" 
                                    @if(!$hasNextDay || $selectedDate->isToday()) disabled @endif
                                    class="px-3 py-1.5 rounded-md text-sm font-medium transition-all {{ ($hasNextDay && !$selectedDate->isToday()) ? 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-100 shadow-sm' : 'bg-gray-100 text-gray-400 cursor-not-allowed' }}">
                                Next
                                <svg class="w-4 h-4 inline -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>

                @php
                    // Filter vitals for the selected date only
                    $dayStart = $selectedDate->copy()->startOfDay();
                    $dayEnd = $selectedDate->copy()->endOfDay();
                    $chartVitals = $vitalSigns->filter(function($vital) use ($dayStart, $dayEnd) {
                        return $vital->recorded_at >= $dayStart && $vital->recorded_at <= $dayEnd;
                    })->sortBy('recorded_at')->values();
                @endphp

                @if($chartVitals->count() > 0)
                    <div class="overflow-x-auto border border-gray-300 rounded-lg bg-white">
                        <!-- Chart Header -->
                        <div class="bg-gradient-to-r from-blue-700 to-blue-800 text-white p-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-bold text-sm">MODIFIED CLINICAL CHART - EARLY WARNING SCORE (EWS)</h3>
                                    <p class="text-xs text-blue-200 mt-1">{{ $patient->name ?? 'Patient' }} | MRN: {{ $patient->mrn ?? '-' }} | {{ $selectedDate->format('d M Y') }}</p>
                                </div>
                                <div class="text-right text-xs">
                                    <div class="flex items-center space-x-3">
                                        <span class="flex items-center"><span class="w-3 h-3 bg-red-300 border border-red-400 mr-1"></span>Score 2</span>
                                        <span class="flex items-center"><span class="w-3 h-3 bg-orange-200 border border-orange-300 mr-1"></span>Score 1</span>
                                        <span class="flex items-center"><span class="w-3 h-3 bg-white border border-gray-400 mr-1"></span>Score 0</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <table class="ihh-chart">
                            <!-- Date/Time Row -->
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="ihh-label-col" rowspan="2">DATE/TIME</th>
                                    <th class="score-col" rowspan="2">Score</th>
                                    @foreach($chartVitals as $vital)
                                        <th class="text-[9px] px-1">{{ $vital->recorded_at->format('d/m') }}</th>
                                    @endforeach
                                </tr>
                                <tr class="bg-gray-50">
                                    @foreach($chartVitals as $vital)
                                        <th class="text-[9px] px-1">{{ $vital->recorded_at->format('H:i') }}</th>
                                    @endforeach
                                </tr>
                            </thead>

                            <tbody>
                                <!-- TEMPERATURE SECTION -->
                                <!-- Score 2: ≥39 or ≤35, Score 1: 38-38.9 or 35.1-35.9, Score 0: 36-37.9 -->
                                <tr>
                                    <td class="section-header" colspan="{{ 2 + count($chartVitals) }}">
                                        <span class="text-blue-700">Temperature °C</span>
                                    </td>
                                </tr>
                                @php
                                    $tempRanges = [
                                        ['min' => 40, 'max' => 42, 'label' => '40+', 'class' => 'ihh-temp-high2', 'score' => 2],
                                        ['min' => 39, 'max' => 39.9, 'label' => '39', 'class' => 'ihh-temp-high2', 'score' => 2],
                                        ['min' => 38, 'max' => 38.9, 'label' => '38', 'class' => 'ihh-temp-high1', 'score' => 1],
                                        ['min' => 37, 'max' => 37.9, 'label' => '37', 'class' => 'ihh-temp-normal', 'score' => 0],
                                        ['min' => 36, 'max' => 36.9, 'label' => '36', 'class' => 'ihh-temp-normal', 'score' => 0],
                                        ['min' => 35.1, 'max' => 35.9, 'label' => '35.1', 'class' => 'ihh-temp-low1', 'score' => 1],
                                        ['min' => 34, 'max' => 35, 'label' => '≤35', 'class' => 'ihh-temp-low2', 'score' => 2],
                                    ];
                                @endphp
                                @foreach($tempRanges as $range)
                                    <tr>
                                        <td class="ihh-label-col {{ $range['class'] }}">{{ $range['label'] }}</td>
                                        <td class="score-col {{ $range['class'] }}">{{ $range['score'] }}</td>
                                        @foreach($chartVitals as $vital)
                                            <td class="{{ $range['class'] }}">
                                                @if($vital->temperature && $vital->temperature >= $range['min'] && $vital->temperature <= $range['max'])
                                                    <span class="ihh-marker"></span><span class="text-[8px] ml-0.5">{{ number_format($vital->temperature, 1) }}</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach

                                <!-- BLOOD PRESSURE SECTION (Systolic ^ and Diastolic v) -->
                                <!-- Systolic Score 2: >200 or ≤90, Score 1: 160-199 or 91-100, Score 0: 101-159 -->
                                <tr>
                                    <td class="section-header" colspan="{{ 2 + count($chartVitals) }}">
                                        <span class="text-blue-700">Blood Pressure (mmHg)</span>
                                        <span class="text-gray-500 text-xs ml-2">▲ Systolic | ▼ Diastolic</span>
                                    </td>
                                </tr>
                                @php
                                    $bpRanges = [
                                        ['min' => 220, 'max' => 300, 'label' => '220+', 'class' => 'ihh-bp-high2', 'score' => 2],
                                        ['min' => 200, 'max' => 219, 'label' => '200', 'class' => 'ihh-bp-high2', 'score' => 2],
                                        ['min' => 180, 'max' => 199, 'label' => '180', 'class' => 'ihh-bp-high1', 'score' => 1],
                                        ['min' => 160, 'max' => 179, 'label' => '160', 'class' => 'ihh-bp-high1', 'score' => 1],
                                        ['min' => 140, 'max' => 159, 'label' => '140', 'class' => 'ihh-bp-normal', 'score' => 0],
                                        ['min' => 120, 'max' => 139, 'label' => '120', 'class' => 'ihh-bp-normal', 'score' => 0],
                                        ['min' => 101, 'max' => 119, 'label' => '101', 'class' => 'ihh-bp-normal', 'score' => 0],
                                        ['min' => 91, 'max' => 100, 'label' => '91', 'class' => 'ihh-bp-low1', 'score' => 1],
                                        ['min' => 80, 'max' => 90, 'label' => '≤90', 'class' => 'ihh-bp-low2', 'score' => 2],
                                        ['min' => 70, 'max' => 79, 'label' => '70', 'class' => 'ihh-bp-low2', 'score' => 2],
                                        ['min' => 60, 'max' => 69, 'label' => '60', 'class' => 'ihh-bp-low2', 'score' => 2],
                                        ['min' => 0, 'max' => 59, 'label' => '<60', 'class' => 'ihh-bp-low2', 'score' => 2],
                                    ];
                                @endphp
                                @foreach($bpRanges as $index => $range)
                                    <tr>
                                        <td class="ihh-label-col {{ $range['class'] }}">{{ $range['label'] }}</td>
                                        <td class="score-col {{ $range['class'] }}">{{ $range['score'] }}</td>
                                        @foreach($chartVitals as $vital)
                                            @php
                                                $hasSystolic = $vital->systolic_bp && $vital->systolic_bp >= $range['min'] && $vital->systolic_bp <= $range['max'];
                                                $hasDiastolic = $vital->diastolic_bp && $vital->diastolic_bp >= $range['min'] && $vital->diastolic_bp <= $range['max'];
                                                // Check if this row is between systolic and diastolic for vertical line
                                                $isBetween = false;
                                                if ($vital->systolic_bp && $vital->diastolic_bp) {
                                                    $isBetween = $range['max'] < $vital->systolic_bp && $range['min'] > $vital->diastolic_bp;
                                                }
                                            @endphp
                                            <td class="{{ $range['class'] }}" style="position: relative;">
                                                @if($hasSystolic)
                                                    <span class="ihh-marker-systolic">▲</span><span class="text-[8px] ml-0.5">{{ $vital->systolic_bp }}</span>
                                                @elseif($hasDiastolic)
                                                    <span class="ihh-marker-diastolic">▼</span><span class="text-[8px] ml-0.5">{{ $vital->diastolic_bp }}</span>
                                                @elseif($isBetween)
                                                    <span style="display:inline-block;width:2px;height:16px;background:#000;"></span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach

                                <!-- PULSE RATE SECTION -->
                                <!-- Score 2: >120 or ≤40, Score 1: 100-120 or 41-59, Score 0: 60-99 -->
                                <tr>
                                    <td class="section-header" colspan="{{ 2 + count($chartVitals) }}">
                                        <span class="text-blue-700">Pulse Rate (bpm)</span>
                                    </td>
                                </tr>
                                @php
                                    $prRanges = [
                                        ['min' => 140, 'max' => 250, 'label' => '140+', 'class' => 'ihh-pr-high2', 'score' => 2],
                                        ['min' => 121, 'max' => 139, 'label' => '>120', 'class' => 'ihh-pr-high2', 'score' => 2],
                                        ['min' => 100, 'max' => 120, 'label' => '100-120', 'class' => 'ihh-pr-high1', 'score' => 1],
                                        ['min' => 90, 'max' => 99, 'label' => '90', 'class' => 'ihh-pr-normal', 'score' => 0],
                                        ['min' => 80, 'max' => 89, 'label' => '80', 'class' => 'ihh-pr-normal', 'score' => 0],
                                        ['min' => 70, 'max' => 79, 'label' => '70', 'class' => 'ihh-pr-normal', 'score' => 0],
                                        ['min' => 60, 'max' => 69, 'label' => '60', 'class' => 'ihh-pr-normal', 'score' => 0],
                                        ['min' => 41, 'max' => 59, 'label' => '41-59', 'class' => 'ihh-pr-low1', 'score' => 1],
                                        ['min' => 0, 'max' => 40, 'label' => '≤40', 'class' => 'ihh-pr-low2', 'score' => 2],
                                    ];
                                @endphp
                                @foreach($prRanges as $range)
                                    <tr>
                                        <td class="ihh-label-col {{ $range['class'] }}">{{ $range['label'] }}</td>
                                        <td class="score-col {{ $range['class'] }}">{{ $range['score'] }}</td>
                                        @foreach($chartVitals as $vital)
                                            <td class="{{ $range['class'] }}">
                                                @if($vital->pulse_rate && $vital->pulse_rate >= $range['min'] && $vital->pulse_rate <= $range['max'])
                                                    <span class="ihh-marker"></span><span class="text-[8px] ml-0.5">{{ $vital->pulse_rate }}</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach

                                <!-- RESPIRATION RATE SECTION -->
                                <!-- Score 2: >25 or ≤8, Score 1: 21-24 or 9-11, Score 0: 12-20 -->
                                <tr>
                                    <td class="section-header" colspan="{{ 2 + count($chartVitals) }}">
                                        <span class="text-blue-700">Respiration Rate (breaths/min)</span>
                                    </td>
                                </tr>
                                @php
                                    $rrRanges = [
                                        ['min' => 30, 'max' => 60, 'label' => '30+', 'class' => 'ihh-rr-high2', 'score' => 2],
                                        ['min' => 26, 'max' => 29, 'label' => '>25', 'class' => 'ihh-rr-high2', 'score' => 2],
                                        ['min' => 21, 'max' => 25, 'label' => '21-24', 'class' => 'ihh-rr-high1', 'score' => 1],
                                        ['min' => 18, 'max' => 20, 'label' => '18-20', 'class' => 'ihh-rr-normal', 'score' => 0],
                                        ['min' => 15, 'max' => 17, 'label' => '15-17', 'class' => 'ihh-rr-normal', 'score' => 0],
                                        ['min' => 12, 'max' => 14, 'label' => '12-14', 'class' => 'ihh-rr-normal', 'score' => 0],
                                        ['min' => 9, 'max' => 11, 'label' => '9-11', 'class' => 'ihh-rr-low1', 'score' => 1],
                                        ['min' => 0, 'max' => 8, 'label' => '≤8', 'class' => 'ihh-rr-low2', 'score' => 2],
                                    ];
                                @endphp
                                @foreach($rrRanges as $range)
                                    <tr>
                                        <td class="ihh-label-col {{ $range['class'] }}">{{ $range['label'] }}</td>
                                        <td class="score-col {{ $range['class'] }}">{{ $range['score'] }}</td>
                                        @foreach($chartVitals as $vital)
                                            <td class="{{ $range['class'] }}">
                                                @if($vital->respiratory_rate && $vital->respiratory_rate >= $range['min'] && $vital->respiratory_rate <= $range['max'])
                                                    <span class="ihh-marker-x">×</span><span class="text-[8px] ml-0.5">{{ $vital->respiratory_rate }}</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach

                                <!-- SpO2 SECTION -->
                                <!-- Score 2: ≤91, Score 1: 92-95, Score 0: ≥96 -->
                                <tr>
                                    <td class="section-header" colspan="{{ 2 + count($chartVitals) }}">
                                        <span class="text-blue-700">SpO2 (%)</span>
                                    </td>
                                </tr>
                                @php
                                    $spo2Ranges = [
                                        ['min' => 96, 'max' => 100, 'label' => '≥96', 'class' => 'ihh-temp-normal', 'score' => 0],
                                        ['min' => 92, 'max' => 95, 'label' => '92-95', 'class' => 'ihh-temp-low1', 'score' => 1],
                                        ['min' => 0, 'max' => 91, 'label' => '≤91', 'class' => 'ihh-temp-low2', 'score' => 2],
                                    ];
                                @endphp
                                @foreach($spo2Ranges as $range)
                                    <tr>
                                        <td class="ihh-label-col {{ $range['class'] }}">{{ $range['label'] }}</td>
                                        <td class="score-col {{ $range['class'] }}">{{ $range['score'] }}</td>
                                        @foreach($chartVitals as $vital)
                                            <td class="{{ $range['class'] }}">
                                                @if($vital->spo2 && $vital->spo2 >= $range['min'] && $vital->spo2 <= $range['max'])
                                                    <span class="ihh-marker"></span><span class="text-[8px] ml-0.5">{{ $vital->spo2 }}%</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach

                                <!-- OXYGEN SECTION (recorded for reference; it does not change the EWS score) -->
                                <tr>
                                    <td class="section-header" colspan="{{ 2 + count($chartVitals) }}">
                                        <span class="text-blue-700">Oxygen Delivery</span>
                                        <span class="text-[9px] font-normal text-gray-500">(not scored)</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="ihh-label-col">O₂</td>
                                    <td class="score-col">-</td>
                                    @foreach($chartVitals as $vital)
                                        <td class="{{ $vital->isOnOxygen() ? 'ihh-temp-low1' : '' }}" title="{{ $vital->oxygenDeliveryLabel() }}">
                                            <span class="text-[8px] font-semibold">{{ $vital->oxygenShortLabel() ?? '' }}</span>
                                        </td>
                                    @endforeach
                                </tr>

                                <!-- TOTAL SCORE ROW -->
                                <tr class="bg-gray-200 font-bold">
                                    <td class="ihh-label-col bg-gray-300">TOTAL</td>
                                    <td class="score-col bg-gray-300">EWS</td>
                                    @foreach($chartVitals as $vital)
                                        @php
                                            $ewsScore = 0;
                                            
                                            // PULSE/HR: Score 2 = >120 or ≤40, Score 1 = 100-120 or 41-59
                                            $pr = $vital->pulse_rate;
                                            if ($pr !== null && $pr !== '' && is_numeric($pr)) {
                                                $pr = intval($pr);
                                                if ($pr > 120 || $pr <= 40) {
                                                    $ewsScore += 2;
                                                } elseif (($pr >= 100 && $pr <= 120) || ($pr >= 41 && $pr <= 59)) {
                                                    $ewsScore += 1;
                                                }
                                            }
                                            
                                            // RESPIRATION: Score 2 = >25 or ≤8, Score 1 = 21-24 or 9-11
                                            $rr = $vital->respiratory_rate;
                                            if ($rr !== null && $rr !== '' && is_numeric($rr)) {
                                                $rr = intval($rr);
                                                if ($rr > 25 || $rr <= 8) {
                                                    $ewsScore += 2;
                                                } elseif (($rr >= 21 && $rr <= 24) || ($rr >= 9 && $rr <= 11)) {
                                                    $ewsScore += 1;
                                                }
                                            }
                                            
                                            // BP SYSTOLIC: Score 2 = >200 or ≤90, Score 1 = 160-199 or 91-100
                                            $sbp = $vital->systolic_bp;
                                            if ($sbp !== null && $sbp !== '' && is_numeric($sbp)) {
                                                $sbp = intval($sbp);
                                                if ($sbp > 200 || $sbp <= 90) {
                                                    $ewsScore += 2;
                                                } elseif (($sbp >= 160 && $sbp <= 199) || ($sbp >= 91 && $sbp <= 100)) {
                                                    $ewsScore += 1;
                                                }
                                            }
                                            
                                            // SPO2: Score 2 = ≤91, Score 1 = 92-95
                                            $spo2 = $vital->spo2;
                                            if ($spo2 !== null && $spo2 !== '' && is_numeric($spo2)) {
                                                $spo2 = intval($spo2);
                                                if ($spo2 <= 91) {
                                                    $ewsScore += 2;
                                                } elseif ($spo2 >= 92 && $spo2 <= 95) {
                                                    $ewsScore += 1;
                                                }
                                            }
                                            
                                            // TEMPERATURE: Score 2 = ≥39 or ≤35, Score 1 = 38-38.9 or 35.1-35.9
                                            $temp = $vital->temperature;
                                            if ($temp !== null && $temp !== '' && is_numeric($temp)) {
                                                $temp = floatval($temp);
                                                if ($temp >= 39 || $temp <= 35) {
                                                    $ewsScore += 2;
                                                } elseif (($temp >= 38 && $temp <= 38.9) || ($temp >= 35.1 && $temp <= 35.9)) {
                                                    $ewsScore += 1;
                                                }
                                            }
                                            
                                            // Score class based on total
                                            $scoreClass = 'bg-green-500 text-white'; // 0-1 Normal
                                            if ($ewsScore >= 4) {
                                                $scoreClass = 'bg-red-500 text-white'; // ≥4 Urgent/Trigger
                                            } elseif ($ewsScore >= 2) {
                                                $scoreClass = 'bg-orange-400 text-white'; // 2-3 Warning
                                            }
                                        @endphp
                                        <td class="{{ $scoreClass }} font-bold">{{ $ewsScore }}</td>
                                    @endforeach
                                </tr>
                            </tbody>
                        </table>

                        <!-- Legend & Monitoring Note -->
                        <div class="p-3 bg-gray-50 border-t text-xs">
                            <div class="flex items-center justify-between mb-2">
                                <div class="text-gray-600">
                                    <strong>Legend:</strong>
                                    <span class="ml-2"><span class="ihh-marker inline-block align-middle"></span> Temp/PR/SpO2</span>
                                    <span class="ml-2"><span class="ihh-marker-systolic">▲</span> BP Systolic</span>
                                    <span class="ml-2"><span class="ihh-marker-diastolic">▼</span> BP Diastolic</span>
                                    <span class="ml-2"><span class="ihh-marker-x">×</span> Respiration</span>
                                </div>
                                <div class="text-gray-600">
                                    <strong>Score:</strong>
                                    <span class="ml-1 px-2 py-0.5 bg-white border border-gray-300 rounded">0 Normal</span>
                                    <span class="ml-1 px-2 py-0.5 bg-orange-200 text-orange-800 rounded">1 Warning</span>
                                    <span class="ml-1 px-2 py-0.5 bg-red-200 text-red-800 rounded">2 Trigger</span>
                                </div>
                            </div>
                            <div class="text-gray-500 text-center border-t pt-2 mt-2">
                                <strong>Monitoring:</strong> Take vitals every <span class="font-bold text-blue-600">4-6 hours</span>. 
                                If abnormal reading, <span class="font-bold text-red-600">repeat within few minutes</span> to confirm.
                            </div>
                        </div>
                    </div>
                @else
                    <div class="border border-dashed border-gray-300 rounded-lg p-8 text-center">
                        <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <p class="text-gray-500 font-medium">No vital signs on {{ $selectedDate->format('d M Y') }}</p>
                        <p class="text-xs text-gray-400 mt-1">
                            @if($hasPreviousDay)
                                Use the Previous button to see earlier recordings
                            @elseif($vitalSigns->count() > 0)
                                This patient has {{ $vitalSigns->count() }} recordings on other days
                            @else
                                No vital signs have been recorded for this patient yet
                            @endif
                        </p>
                    </div>
                @endif
            </div>
        @endif
    </div>
</body>
</html>





































