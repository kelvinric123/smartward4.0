<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-bold text-2xl text-gray-800 leading-tight">
                    Vital Signs Record
                </h2>
                <p class="text-sm text-gray-500 mt-1">Record and monitor patient vital signs</p>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Success/Error Messages -->
            @if(session('success'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>{{ session('error') }}</span>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Panel: Record New Vital Signs -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 bg-gradient-to-r from-rose-500 to-pink-500 text-white">
                            <h3 class="text-lg font-bold flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"/>
                                </svg>
                                Record Vital Signs
                            </h3>
                            <p class="text-sm text-white/80 mt-1">Enter patient vital sign readings</p>
                        </div>
                        
                        <form method="POST" action="{{ route('vital-signs.store') }}" class="p-6 space-y-4">
                            @csrf
                            
                            <!-- Patient Selection -->
                            <!-- Patient Selection -->
                            <div x-data="{
                                search: '',
                                open: false,
                                selectedId: '{{ old('patient_id', $patientId) }}',
                                patients: {{ Js::from($patients) }},
                                get filteredPatients() {
                                    if (this.search === '' && !this.selectedId) return this.patients;
                                    const term = this.search.toLowerCase();
                                    return this.patients.filter(p => 
                                        p.name.toLowerCase().includes(term) || 
                                        (p.mrn && p.mrn.toLowerCase().includes(term)) || 
                                        (p.rn && p.rn.toLowerCase().includes(term))
                                    );
                                },
                                selectPatient(patient) {
                                    this.selectedId = patient.id;
                                    this.search = patient.name;
                                    this.open = false;
                                },
                                init() {
                                    if (this.selectedId) {
                                        const p = this.patients.find(p => p.id == this.selectedId);
                                        if (p) {
                                            this.search = p.name;
                                        }
                                    }
                                    this.$watch('search', (value) => {
                                        if (!this.open && value !== '') {
                                            this.open = true;
                                        }
                                        if (value === '') {
                                            this.selectedId = '';
                                        }
                                    });
                                }
                            }" class="relative">
                                <label for="patient_search" class="block text-sm font-semibold text-gray-700 mb-1">
                                    Select Patient <span class="text-red-500">*</span>
                                </label>
                                <input type="hidden" name="patient_id" :value="selectedId">
                                
                                <div class="relative" @click.away="open = false">
                                    <div class="relative">
                                        <input 
                                            type="text" 
                                            id="patient_search"
                                            x-model="search"
                                            @focus="open = true"
                                            @click="open = true"
                                            @keydown.escape="open = false"
                                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm pl-10"
                                            placeholder="Search by Name, MRN, or RN..."
                                            autocomplete="off"
                                        >
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                            </svg>
                                        </div>
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer" x-show="search" @click="search = ''; selectedId = ''; open = true">
                                            <svg class="h-5 w-5 text-gray-400 hover:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </div>
                                    </div>
                                    
                                    <div 
                                        x-show="open" 
                                        x-transition
                                        class="absolute z-50 w-full mt-1 bg-white rounded-lg shadow-xl border border-gray-200 max-h-60 overflow-y-auto"
                                        style="display: none;"
                                    >
                                        <ul class="py-1">
                                            <template x-for="patient in filteredPatients" :key="patient.id">
                                                <li 
                                                    @click="selectPatient(patient)"
                                                    class="px-4 py-2 hover:bg-rose-50 cursor-pointer flex justify-between items-center group"
                                                    :class="{'bg-rose-50': selectedId == patient.id}"
                                                >
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900" x-text="patient.name"></div>
                                                        <div class="text-xs text-gray-500 flex items-center space-x-2">
                                                            <span x-show="patient.mrn">MRN: <span x-text="patient.mrn"></span></span>
                                                            <span x-show="patient.rn" class="text-gray-300">|</span>
                                                            <span x-show="patient.rn">RN: <span x-text="patient.rn"></span></span>
                                                        </div>
                                                    </div>
                                                    <span 
                                                        x-show="patient.status === 'admitted'" 
                                                        class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-800"
                                                    >
                                                        Admitted
                                                    </span>
                                                </li>
                                            </template>
                                            <li x-show="filteredPatients.length === 0" class="px-4 py-3 text-sm text-gray-500 text-center">
                                                No patients found
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <!-- Blood Pressure -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 mr-1 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                        </svg>
                                        Blood Pressure (SBP/DBP)
                                    </div>
                                </label>
                                <div class="flex items-center space-x-2">
                                    <input type="number" name="systolic_bp" id="systolic_bp" min="40" max="300" 
                                           class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm"
                                           placeholder="SBP">
                                    <span class="text-gray-500">/</span>
                                    <input type="number" name="diastolic_bp" id="diastolic_bp" min="20" max="200" 
                                           class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm"
                                           placeholder="DBP">
                                    <span class="text-xs text-gray-500">mmHg</span>
                                </div>
                            </div>

                            <!-- Pulse Rate -->
                            <div>
                                <label for="pulse_rate" class="block text-sm font-semibold text-gray-700 mb-1">
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 mr-1 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                        </svg>
                                        Pulse Rate
                                    </div>
                                </label>
                                <div class="flex items-center space-x-2">
                                    <input type="number" name="pulse_rate" id="pulse_rate" min="20" max="250" 
                                           class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm"
                                           placeholder="Heart rate">
                                    <span class="text-xs text-gray-500">bpm</span>
                                </div>
                            </div>

                            <!-- Temperature -->
                            <div>
                                <label for="temperature" class="block text-sm font-semibold text-gray-700 mb-1">
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 mr-1 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                        </svg>
                                        Temperature
                                    </div>
                                </label>
                                <div class="flex items-center space-x-2">
                                    <input type="number" name="temperature" id="temperature" min="30" max="45" step="0.1" 
                                           class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm"
                                           placeholder="Body temperature">
                                    <span class="text-xs text-gray-500">°C</span>
                                </div>
                            </div>

                            <!-- SpO2 -->
                            <div>
                                <label for="spo2" class="block text-sm font-semibold text-gray-700 mb-1">
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 mr-1 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        SpO2 (Oxygen Saturation)
                                    </div>
                                </label>
                                <div class="flex items-center space-x-2">
                                    <input type="number" name="spo2" id="spo2" min="50" max="100" 
                                           class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm"
                                           placeholder="Oxygen saturation">
                                    <span class="text-xs text-gray-500">%</span>
                                </div>
                            </div>

                            <!-- Respiratory Rate -->
                            <div>
                                <label for="respiratory_rate" class="block text-sm font-semibold text-gray-700 mb-1">
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 mr-1 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                        </svg>
                                        Respiratory Rate
                                    </div>
                                </label>
                                <div class="flex items-center space-x-2">
                                    <input type="number" name="respiratory_rate" id="respiratory_rate" min="5" max="60" 
                                           class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm"
                                           placeholder="Breaths per minute">
                                    <span class="text-xs text-gray-500">/min</span>
                                </div>
                            </div>

                            <!-- Reading Type -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Reading Type</label>
                                <div class="flex items-center space-x-4">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="radio" name="reading_type" value="single" checked class="text-rose-500 focus:ring-rose-500">
                                        <span class="ml-2 text-sm text-gray-700">Single Reading</span>
                                    </label>
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="radio" name="reading_type" value="full" class="text-rose-500 focus:ring-rose-500">
                                        <span class="ml-2 text-sm text-gray-700">Full Reading</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Notes -->
                            <div>
                                <label for="notes" class="block text-sm font-semibold text-gray-700 mb-1">Notes (Optional)</label>
                                <textarea name="notes" id="notes" rows="2" 
                                          class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm"
                                          placeholder="Any additional notes..."></textarea>
                            </div>

                            <!-- Submit Button -->
                            <button type="submit" class="w-full py-3 px-4 bg-gradient-to-r from-rose-500 to-pink-500 hover:from-rose-600 hover:to-pink-600 text-white font-bold rounded-lg shadow-lg transition-all flex items-center justify-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Record Vital Signs
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Right Panel: Vital Signs History -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 bg-gradient-to-r from-gray-700 to-gray-800 text-white">
                            <h3 class="text-lg font-bold flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                                Vital Signs History
                            </h3>
                            <p class="text-sm text-white/80 mt-1">View and search patient vital signs records</p>
                        </div>

                        <!-- Search & Filter -->
                        <div class="p-4 border-b border-gray-100 bg-gray-50">
                            <form method="GET" action="{{ route('vital-signs.index') }}" class="flex flex-wrap gap-3">
                                <div class="flex-1 min-w-[200px]">
                                    <input type="text" name="search" value="{{ $search }}" 
                                           class="w-full rounded-lg border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm"
                                           placeholder="Search by patient name or MRN...">
                                </div>
                                <div class="w-48">
                                    <select name="patient_id" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm" onchange="this.form.submit()">
                                        <option value="">All Patients</option>
                                        @foreach($patients as $patient)
                                            <option value="{{ $patient->id }}" {{ $patientId == $patient->id ? 'selected' : '' }}>
                                                {{ $patient->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                @if(count($admissions) > 0)
                                <div class="w-48">
                                    <select name="admission_id" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm" onchange="this.form.submit()">
                                        <option value="">All Admissions</option>
                                        @foreach($admissions as $admission)
                                            <option value="{{ $admission['id'] }}" {{ $admissionId == $admission['id'] ? 'selected' : '' }}>
                                                {{ $admission['label'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                @endif
                                <button type="submit" class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-800 transition-colors text-sm font-semibold">
                                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                    Search
                                </button>
                                @if($search || $patientId || $admissionId)
                                <a href="{{ route('vital-signs.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors text-sm font-semibold">
                                    Clear
                                </a>
                                @endif
                            </form>
                        </div>

                        <!-- Vital Signs Table -->
                        <div class="overflow-x-auto">
                            @if($vitalSigns->count() > 0)
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Patient</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            <div class="flex items-center justify-center">
                                                <svg class="w-3 h-3 mr-1 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"/>
                                                </svg>
                                                BP
                                            </div>
                                        </th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            <div class="flex items-center justify-center">
                                                <svg class="w-3 h-3 mr-1 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                                </svg>
                                                PR
                                            </div>
                                        </th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            <div class="flex items-center justify-center">
                                                <svg class="w-3 h-3 mr-1 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2z"/>
                                                </svg>
                                                Temp
                                            </div>
                                        </th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            <div class="flex items-center justify-center">
                                                <svg class="w-3 h-3 mr-1 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                SpO2
                                            </div>
                                        </th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            <div class="flex items-center justify-center">
                                                <svg class="w-3 h-3 mr-1 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12"/>
                                                </svg>
                                                RR
                                            </div>
                                        </th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Type</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Recorded</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    @php $currentAdmission = null; @endphp
                                    @foreach($vitalSigns as $vital)
                                        @if($vital->admission_id !== $currentAdmission)
                                            @php $currentAdmission = $vital->admission_id; @endphp
                                            <tr class="bg-gray-100">
                                                <td colspan="8" class="px-4 py-2">
                                                    <div class="flex items-center text-xs font-semibold text-gray-600">
                                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                                        </svg>
                                                        Admission: {{ $vital->admission_id ?? 'General' }}
                                                    </div>
                                                </td>
                                            </tr>
                                        @endif
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3">
                                                <div class="text-sm font-semibold text-gray-900">{{ $vital->patient->name ?? 'N/A' }}</div>
                                                <div class="text-xs text-gray-500">MRN: {{ $vital->patient->mrn ?? 'N/A' }}</div>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                @if($vital->systolic_bp && $vital->diastolic_bp)
                                                    <span class="font-bold text-red-600">{{ $vital->systolic_bp }}/{{ $vital->diastolic_bp }}</span>
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                @if($vital->pulse_rate)
                                                    <span class="font-bold text-blue-600">{{ $vital->pulse_rate }}</span>
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                @if($vital->temperature)
                                                    <span class="font-bold text-orange-600">{{ number_format($vital->temperature, 1) }}°C</span>
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                @if($vital->spo2)
                                                    <span class="font-bold {{ $vital->spo2 < 95 ? 'text-red-600' : 'text-green-600' }}">{{ $vital->spo2 }}%</span>
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                @if($vital->respiratory_rate)
                                                    <span class="font-bold text-cyan-600">{{ $vital->respiratory_rate }}</span>
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <span class="px-2 py-1 text-xs rounded-full font-semibold {{ $vital->reading_type === 'full' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                                                    {{ ucfirst($vital->reading_type) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="text-sm text-gray-900">{{ $vital->recorded_at->format('Y-m-d') }}</div>
                                                <div class="text-xs text-gray-500">{{ $vital->recorded_at->format('H:i') }}</div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            <!-- Pagination -->
                            <div class="px-4 py-3 border-t border-gray-100">
                                {{ $vitalSigns->links() }}
                            </div>
                            @else
                            <div class="p-8 text-center">
                                <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                </svg>
                                <p class="text-gray-500 font-medium">No vital signs recorded yet</p>
                                <p class="text-sm text-gray-400 mt-1">Start by selecting a patient and recording their vital signs</p>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>





































