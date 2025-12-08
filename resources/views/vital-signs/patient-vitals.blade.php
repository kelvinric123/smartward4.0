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
    </style>
</head>
<body class="bg-gray-50">
    <div class="p-4" x-data='{ 
        view: "list",
        chartInstance: null,
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

            <!-- View Toggle -->
            <div class="inline-flex rounded-md shadow-sm border border-gray-200 bg-white overflow-hidden text-xs">
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

        @if($patient)
            <!-- Admission Filter -->
            @if(count($admissions) > 0)
            <div class="mb-4">
                <form method="GET" action="{{ route('vital-signs.patient') }}" class="flex items-center space-x-2">
                    <input type="hidden" name="patient_id" value="{{ $patient->id }}">
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
                <div class="grid grid-cols-5 gap-3 mb-4">
                    <div class="bg-gradient-to-br from-red-50 to-red-100 border border-red-200 rounded-lg p-3 text-center">
                        <div class="text-xs text-red-600 font-semibold mb-1">Blood Pressure</div>
                        <div class="text-xl font-bold text-gray-900">
                            {{ $latest->blood_pressure ?? '-' }}
                        </div>
                        <div class="text-xs text-gray-500">mmHg</div>
                    </div>
                    <div class="bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200 rounded-lg p-3 text-center">
                        <div class="text-xs text-blue-600 font-semibold mb-1">Pulse Rate</div>
                        <div class="text-xl font-bold text-gray-900">{{ $latest->pulse_rate ?? '-' }}</div>
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
                            {{ $latest->spo2 ? $latest->spo2 . '%' : '-' }}
                        </div>
                        <div class="text-xs text-gray-500">Oxygen</div>
                    </div>
                    <div class="bg-gradient-to-br from-cyan-50 to-cyan-100 border border-cyan-200 rounded-lg p-3 text-center">
                        <div class="text-xs text-cyan-600 font-semibold mb-1">Resp. Rate</div>
                        <div class="text-xl font-bold text-gray-900">{{ $latest->respiratory_rate ?? '-' }}</div>
                        <div class="text-xs text-gray-500">/min</div>
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
                                    <th class="px-3 py-2 text-center font-semibold text-gray-600">Type</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-600">Admission</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @php $currentAdm = null; @endphp
                                @foreach($vitalSigns as $vital)
                                    @if($vital->admission_id !== $currentAdm)
                                        @php $currentAdm = $vital->admission_id; @endphp
                                        <tr class="bg-rose-50">
                                            <td colspan="8" class="px-3 py-2 text-xs font-bold text-rose-700">
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
                                            {{ $vital->pulse_rate ?? '-' }}
                                        </td>
                                        <td class="px-3 py-2 text-center font-bold text-orange-600">
                                            {{ $vital->temperature ? number_format($vital->temperature, 1) : '-' }}
                                        </td>
                                        <td class="px-3 py-2 text-center font-bold {{ $vital->spo2 && $vital->spo2 < 95 ? 'text-red-600' : 'text-green-600' }}">
                                            {{ $vital->spo2 ? $vital->spo2 . '%' : '-' }}
                                        </td>
                                        <td class="px-3 py-2 text-center font-bold text-cyan-600">
                                            {{ $vital->respiratory_rate ?? '-' }}
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-semibold {{ $vital->reading_type === 'full' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                                {{ ucfirst($vital->reading_type) }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-gray-500 text-[10px]">
                                            {{ Str::limit($vital->admission_id ?? 'General', 20) }}
                                        </td>
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
        @endif
    </div>
</body>
</html>
















