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
        <div class="w-full px-4 sm:px-6 lg:px-8">
            <!-- Success/Error Messages -->
            @if(session('success'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
                    class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
                    class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ session('error') }}</span>
                    </div>
                </div>
            @endif

            <!-- Full Width Stacked Layout -->
            <div class="space-y-6">
                <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 bg-gradient-to-r from-rose-500 to-pink-500 text-white">
                        <div class="flex flex-col lg:flex-row lg:justify-between lg:items-start gap-4">
                            <div>
                                <h3 class="text-lg font-bold flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    Record Vital Signs
                                </h3>
                                <p class="text-sm text-white/80 mt-1">Enter patient vital sign readings</p>
                            </div>
                            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                                <!-- Active Bindings Display -->
                                @if(isset($activeBindings) && $activeBindings->count() > 0)
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($activeBindings as $binding)
                                            <div
                                                class="flex items-center bg-white/20 backdrop-blur-sm rounded-lg px-3 py-2 text-sm">
                                                <div class="flex items-center mr-2">
                                                    <div class="h-2 w-2 rounded-full bg-emerald-400 mr-2 animate-pulse"></div>
                                                    <span class="font-medium">{{ $binding->apiUser->name ?? 'Gateway' }}</span>
                                                    <svg class="w-3 h-3 mx-1 text-white/60" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M9 5l7 7-7 7" />
                                                    </svg>
                                                    <span>{{ $binding->nurse->name ?? 'Nurse' }}</span>
                                                </div>
                                                <form action="{{ route('vital-sign-integration.unbind', $binding) }}"
                                                    method="POST" class="inline"
                                                    >
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button" onclick="confirmDelete(event, 'Are you sure you want to delete this item?')" class="ml-2 p-1 hover:bg-white/20 rounded transition"
                                                        title="Unbind">
                                                        <svg class="w-4 h-4 text-white/80 hover:text-white" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                    </button>
                                                </form>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                <!-- Notification Toggle Button -->
                                <button type="button" @click="$dispatch('toggle-notifications')"
                                    x-data="{ enabled: localStorage.getItem('vital_sign_notifications_enabled') !== 'false' }"
                                    @notification-toggled.window="enabled = $event.detail"
                                    class="px-4 py-2.5 bg-white rounded-lg font-bold shadow-lg transition-all flex items-center whitespace-nowrap"
                                    :class="enabled ? 'text-emerald-600 hover:bg-emerald-50' : 'text-gray-400 hover:bg-gray-50'">

                                    <!-- Enabled Icon -->
                                    <svg x-show="enabled" class="w-5 h-5 mr-2" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                    </svg>

                                    <!-- Disabled Icon -->
                                    <svg x-show="!enabled" class="w-5 h-5 mr-2" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24" style="display: none;">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"
                                            clip-rule="evenodd" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" />
                                    </svg>

                                    <span x-text="enabled ? 'Notifications ON' : 'Notifications OFF'"></span>
                                </button>

                                <!-- Prominent Bind Gateway Button -->
                                <button type="button" @click="$dispatch('open-bind-modal')"
                                    class="px-4 py-2.5 bg-white text-rose-600 hover:bg-rose-50 rounded-lg font-bold shadow-lg transition-all flex items-center whitespace-nowrap">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                    </svg>
                                    Bind Gateway
                                </button>
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('vital-signs.store') }}" class="p-6">
                        @csrf

                        <!-- Horizontal Form Layout -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8 gap-4 items-end">
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
                            }" class="relative xl:col-span-2">
                                <label for="patient_search" class="block text-sm font-semibold text-gray-700 mb-1">
                                    Select Patient <span class="text-red-500">*</span>
                                </label>
                                <input type="hidden" name="patient_id" :value="selectedId">

                                <div class="relative" @click.away="open = false">
                                    <div class="relative">
                                        <input type="text" id="patient_search" x-model="search" @focus="open = true"
                                            @click="open = true" @keydown.escape="open = false"
                                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm pl-10"
                                            placeholder="Search by Name, MRN, or RN..." autocomplete="off">
                                        <div
                                            class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                            </svg>
                                        </div>
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer"
                                            x-show="search" @click="search = ''; selectedId = ''; open = true">
                                            <svg class="h-5 w-5 text-gray-400 hover:text-gray-600" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </div>
                                    </div>

                                    <div x-show="open" x-transition
                                        class="absolute z-50 w-full mt-1 bg-white rounded-lg shadow-xl border border-gray-200 max-h-60 overflow-y-auto"
                                        style="display: none;">
                                        <ul class="py-1">
                                            <template x-for="patient in filteredPatients" :key="patient.id">
                                                <li @click="selectPatient(patient)"
                                                    class="px-4 py-2 hover:bg-rose-50 cursor-pointer flex justify-between items-center group"
                                                    :class="{'bg-rose-50': selectedId == patient.id}">
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900"
                                                            x-text="patient.name"></div>
                                                        <div class="text-xs text-gray-500 flex items-center space-x-2">
                                                            <span x-show="patient.mrn">MRN: <span
                                                                    x-text="patient.mrn"></span></span>
                                                            <span x-show="patient.rn" class="text-gray-300">|</span>
                                                            <span x-show="patient.rn">RN: <span
                                                                    x-text="patient.rn"></span></span>
                                                        </div>
                                                    </div>
                                                    <span x-show="patient.status === 'admitted'"
                                                        class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-800">
                                                        Admitted
                                                    </span>
                                                </li>
                                            </template>
                                            <li x-show="filteredPatients.length === 0"
                                                class="px-4 py-3 text-sm text-gray-500 text-center">
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
                                        <svg class="w-4 h-4 mr-1 text-red-500" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                        </svg>
                                        BP (SBP/DBP)
                                    </div>
                                </label>
                                <div class="flex items-center space-x-1">
                                    <input type="number" name="systolic_bp" id="systolic_bp" min="40" max="300"
                                        class="block w-16 rounded-lg border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm"
                                        placeholder="SBP">
                                    <span class="text-gray-500">/</span>
                                    <input type="number" name="diastolic_bp" id="diastolic_bp" min="20" max="200"
                                        class="block w-16 rounded-lg border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm"
                                        placeholder="DBP">
                                </div>
                            </div>

                            <!-- Pulse Rate -->
                            <div>
                                <label for="pulse_rate" class="block text-sm font-semibold text-gray-700 mb-1">
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 mr-1 text-blue-500" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M13 10V3L4 14h7v7l9-11h-7z" />
                                        </svg>
                                        Pulse
                                    </div>
                                </label>
                                <input type="number" name="pulse_rate" id="pulse_rate" min="20" max="250"
                                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm"
                                    placeholder="bpm">
                            </div>

                            <!-- Temperature -->
                            <div>
                                <label for="temperature" class="block text-sm font-semibold text-gray-700 mb-1">
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 mr-1 text-orange-500" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2z" />
                                        </svg>
                                        Temp
                                    </div>
                                </label>
                                <input type="number" name="temperature" id="temperature" min="30" max="45" step="0.1"
                                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm"
                                    placeholder="°C">
                            </div>

                            <!-- SpO2 -->
                            <div>
                                <label for="spo2" class="block text-sm font-semibold text-gray-700 mb-1">
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 mr-1 text-green-500" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        SpO2
                                    </div>
                                </label>
                                <input type="number" name="spo2" id="spo2" min="50" max="100"
                                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm"
                                    placeholder="%">
                            </div>

                            <!-- Respiratory Rate -->
                            <div>
                                <label for="respiratory_rate" class="block text-sm font-semibold text-gray-700 mb-1">
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 mr-1 text-cyan-500" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12" />
                                        </svg>
                                        RR
                                    </div>
                                </label>
                                <input type="number" name="respiratory_rate" id="respiratory_rate" min="5" max="60"
                                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm"
                                    placeholder="/min">
                            </div>

                            <!-- Submit Button -->
                            <button type="submit"
                                class="h-[38px] px-6 bg-gradient-to-r from-rose-500 to-pink-500 hover:from-rose-600 hover:to-pink-600 text-white font-bold rounded-lg shadow-lg transition-all flex items-center justify-center whitespace-nowrap">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7" />
                                </svg>
                                Record
                            </button>
                        </div>

                        <!-- Second Row: Reading Type & Notes -->
                        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <!-- Reading Type -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Reading Type</label>
                                <div class="flex items-center space-x-4">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="radio" name="reading_type" value="single" checked
                                            class="text-rose-500 focus:ring-rose-500">
                                        <span class="ml-2 text-sm text-gray-700">Single</span>
                                    </label>
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="radio" name="reading_type" value="full"
                                            class="text-rose-500 focus:ring-rose-500">
                                        <span class="ml-2 text-sm text-gray-700">Full</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Notes -->
                            <div class="lg:col-span-3">
                                <label for="notes" class="block text-sm font-semibold text-gray-700 mb-1">Notes
                                    (Optional)</label>
                                <input type="text" name="notes" id="notes"
                                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm"
                                    placeholder="Any additional notes...">
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Bottom Section: Vital Signs History (Full Width) -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 bg-gradient-to-r from-gray-700 to-gray-800 text-white">
                        <h3 class="text-lg font-bold flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
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
                                    placeholder="Search by patient name or MRN..." autocomplete="off">
                            </div>
                            <div class="w-48">
                                <select name="patient_id"
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm"
                                    onchange="this.form.submit()">
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
                                    <select name="admission_id"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 text-sm"
                                        onchange="this.form.submit()">
                                        <option value="">All Admissions</option>
                                        @foreach($admissions as $admission)
                                            <option value="{{ $admission['id'] }}" {{ $admissionId == $admission['id'] ? 'selected' : '' }}>
                                                {{ $admission['label'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <button type="submit"
                                class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-800 transition-colors text-sm font-semibold">
                                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                Search
                            </button>
                            @if($search || $patientId || $admissionId)
                                <a href="{{ route('vital-signs.index') }}"
                                    class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors text-sm font-semibold">
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
                                        <th
                                            class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Patient</th>
                                        <th
                                            class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            <div class="flex items-center justify-center">
                                                <svg class="w-3 h-3 mr-1 text-red-500" fill="currentColor"
                                                    viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd"
                                                        d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z"
                                                        clip-rule="evenodd" />
                                                </svg>
                                                BP
                                            </div>
                                        </th>
                                        <th
                                            class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            <div class="flex items-center justify-center">
                                                <svg class="w-3 h-3 mr-1 text-blue-500" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                </svg>
                                                PR
                                            </div>
                                        </th>
                                        <th
                                            class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            <div class="flex items-center justify-center">
                                                <svg class="w-3 h-3 mr-1 text-orange-500" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2z" />
                                                </svg>
                                                Temp
                                            </div>
                                        </th>
                                        <th
                                            class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            <div class="flex items-center justify-center">
                                                <svg class="w-3 h-3 mr-1 text-green-500" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                SpO2
                                            </div>
                                        </th>
                                        <th
                                            class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            <div class="flex items-center justify-center">
                                                <svg class="w-3 h-3 mr-1 text-cyan-500" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12" />
                                                </svg>
                                                RR
                                            </div>
                                        </th>
                                        <th
                                            class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Type</th>
                                        <th
                                            class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Recorded</th>
                                        <th
                                            class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Gateway</th>
                                        <th
                                            class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Operator</th>
                                        <th
                                            class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    @php $currentAdmission = null; @endphp
                                    @foreach($vitalSigns as $vital)
                                        @if($vital->admission_id !== $currentAdmission)
                                            @php $currentAdmission = $vital->admission_id; @endphp
                                            <tr class="bg-gray-100">
                                                <td colspan="10" class="px-4 py-2">
                                                    <div class="flex items-center text-xs font-semibold text-gray-600">
                                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                                        </svg>
                                                        Admission: {{ $vital->admission_id ?? 'General' }}
                                                    </div>
                                                </td>
                                            </tr>
                                        @endif
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3">
                                                <div class="text-sm font-semibold text-gray-900">
                                                    {{ $vital->patient->name ?? 'N/A' }}
                                                </div>
                                                <div class="text-xs text-gray-500">MRN: {{ $vital->patient->mrn ?? 'N/A' }}
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                @if($vital->systolic_bp && $vital->diastolic_bp)
                                                    <span
                                                        class="font-bold text-red-600">{{ $vital->systolic_bp }}/{{ $vital->diastolic_bp }}</span>
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                @if($vital->pulse_rate)
                                                    <span class="font-bold text-blue-600">{{ $vital->pulse_rate_display }}</span>
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                @if($vital->temperature)
                                                    <span
                                                        class="font-bold text-orange-600">{{ number_format($vital->temperature, 1) }}°C</span>
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                @if($vital->spo2)
                                                    <span
                                                        class="font-bold {{ $vital->spo2 < 95 ? 'text-red-600' : 'text-green-600' }}">{{ $vital->spo2_display }}%</span>
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
                                                <span
                                                    class="px-2 py-1 text-xs rounded-full font-semibold {{ $vital->reading_type === 'full' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                                                    {{ ucfirst($vital->reading_type) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="text-sm text-gray-900">{{ $vital->recorded_at->format('Y-m-d') }}
                                                </div>
                                                <div class="text-xs text-gray-500">{{ $vital->recorded_at->format('H:i') }}
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                @if($vital->gatewayDevice)
                                                    <div class="flex items-center text-sm font-medium text-emerald-700 bg-emerald-50 px-2 py-1 rounded border border-emerald-100 w-fit"
                                                        title="{{ $vital->gatewayDevice->gateway_id }}{{ $vital->gatewayDevice->ward?->ward_name ? ' · ' . $vital->gatewayDevice->ward->ward_name : '' }}">
                                                        <svg class="w-3 h-3 mr-1.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                                                        </svg>
                                                        {{ $vital->gatewayDevice->name }}
                                                    </div>
                                                @elseif($vital->gateway_id)
                                                    <div class="text-sm text-gray-700">{{ $vital->gateway_id }}</div>
                                                    <div class="text-xs text-gray-400">unregistered</div>
                                                @else
                                                    <span class="text-sm text-gray-400">Manual</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3">
                                                @if($vital->operator)
                                                    <div
                                                        class="flex items-center text-sm font-medium text-rose-700 bg-rose-50 px-2 py-1 rounded border border-rose-100 w-fit">
                                                        <svg class="w-3 h-3 mr-1.5 text-rose-500" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                                        </svg>
                                                        {{ $vital->operator->name }}
                                                    </div>
                                                @else
                                                    <span class="text-sm text-gray-500">-</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <form action="{{ route('vital-signs.destroy', $vital) }}" method="POST"
                                                    >
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button" onclick="confirmDelete(event, 'Are you sure you want to delete this item?')" class="text-red-500 hover:text-red-700 transition"
                                                        title="Delete Vital Sign">
                                                        <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                </form>
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
                                <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                </svg>
                                <p class="text-gray-500 font-medium">No vital signs recorded yet</p>
                                <p class="text-sm text-gray-400 mt-1">Start by selecting a patient and recording their vital
                                    signs</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bind Gateway Modal -->
    <div x-data="{ 
        open: false,
        scannerInput: '',
        scannerStatus: '',
        scannerError: false,
        selectedGatewayId: '',
        cameraActive: false,
        html5QrCode: null,
        gateways: {{ Js::from($gateways) }},
        handleScan() {
            const searchTerm = this.scannerInput.trim().toLowerCase();
            if (!searchTerm) {
                this.scannerStatus = '';
                this.scannerError = false;
                return;
            }
            
            // Find gateway by name (case-insensitive)
            const match = this.gateways.find(g => 
                g.name.toLowerCase() === searchTerm || 
                g.name.toLowerCase().includes(searchTerm)
            );
            
            if (match) {
                this.selectedGatewayId = match.id;
                document.getElementById('api_user_id').value = match.id;
                this.scannerStatus = 'Found: ' + match.name;
                this.scannerError = false;
            } else {
                this.scannerStatus = 'No gateway found matching: ' + this.scannerInput;
                this.scannerError = true;
            }
        },
        resetScanner() {
            this.scannerInput = '';
            this.scannerStatus = '';
            this.scannerError = false;
            this.stopCamera();
        },
        async startCamera() {
            if (this.cameraActive) {
                this.stopCamera();
                return;
            }
            
            this.cameraActive = true;
            
            // Load html5-qrcode if not already loaded
            if (typeof Html5Qrcode === 'undefined') {
                const script = document.createElement('script');
                script.src = 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js';
                script.onload = () => this.initCamera();
                document.head.appendChild(script);
            } else {
                this.initCamera();
            }
        },
        initCamera() {
            const self = this;
            this.html5QrCode = new Html5Qrcode('qr-reader');
            
            this.html5QrCode.start(
                { facingMode: 'environment' },
                {
                    fps: 10,
                    qrbox: { width: 250, height: 250 }
                },
                (decodedText) => {
                    // Success callback
                    self.scannerInput = decodedText;
                    self.handleScan();
                    self.stopCamera();
                },
                (errorMessage) => {
                    // Error callback - ignore, just means no QR found yet
                }
            ).catch((err) => {
                console.error('Camera error:', err);
                self.scannerStatus = 'Camera access denied or unavailable';
                self.scannerError = true;
                self.cameraActive = false;
            });
        },
        stopCamera() {
            if (this.html5QrCode) {
                this.html5QrCode.stop().catch(err => console.log('Stop error:', err));
                this.html5QrCode = null;
            }
            this.cameraActive = false;
        }
    }" @open-bind-modal.window="open = true; $nextTick(() => { $refs.scannerInput.focus(); resetScanner(); })"
        @keydown.escape.window="open = false; stopCamera();" class="relative z-[60]" aria-labelledby="modal-title"
        role="dialog" aria-modal="true" x-show="open" style="display: none;">

        <div x-show="open" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

        <div class="fixed inset-0 z-10 overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div x-show="open" x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg"
                    @click.away="open = false">
                    <form action="{{ route('vital-sign-integration.bind') }}" method="POST">
                        @csrf
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div
                                    class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-rose-100 sm:mx-0 sm:h-10 sm:w-10">
                                    <svg class="h-6 w-6 text-rose-600" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                                    </svg>
                                </div>
                                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                    <h3 class="text-base font-semibold leading-6 text-gray-900" id="modal-title">Bind
                                        Gateway to Operator</h3>
                                    <div class="mt-4 space-y-4">
                                        <!-- Barcode/QR Scanner Input -->
                                        <div>
                                            <label for="scanner_input" class="block text-sm font-medium text-gray-700">
                                                <div class="flex items-center">
                                                    <svg class="h-4 w-4 mr-1.5 text-rose-500" fill="none"
                                                        viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h2M4 12h2m10 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                                    </svg>
                                                    Scan Gateway Barcode/QR
                                                </div>
                                            </label>

                                            <!-- Camera Scan Button -->
                                            <button type="button" @click="startCamera()"
                                                :class="cameraActive ? 'bg-red-600 hover:bg-red-700' : 'bg-rose-600 hover:bg-rose-700'"
                                                class="mt-2 w-full flex items-center justify-center px-4 py-3 text-white font-semibold rounded-lg shadow-md transition-colors">
                                                <template x-if="!cameraActive">
                                                    <span class="flex items-center">
                                                        <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24"
                                                            stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        </svg>
                                                        Scan with Camera
                                                    </span>
                                                </template>
                                                <template x-if="cameraActive">
                                                    <span class="flex items-center">
                                                        <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24"
                                                            stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                        Stop Camera
                                                    </span>
                                                </template>
                                            </button>

                                            <!-- Camera Preview Area -->
                                            <div x-show="cameraActive" x-transition
                                                class="mt-3 rounded-lg overflow-hidden border-2 border-rose-300 bg-black">
                                                <div id="qr-reader" style="width: 100%;"></div>
                                            </div>

                                            <!-- Manual Input -->
                                            <div class="mt-3 relative">
                                                <input type="text" id="scanner_input" x-ref="scannerInput"
                                                    x-model="scannerInput" @keydown.enter.prevent="handleScan()"
                                                    @input.debounce.300ms="handleScan()"
                                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 sm:text-sm pl-10"
                                                    placeholder="Or type gateway name..." autocomplete="off">
                                                <div
                                                    class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24"
                                                        stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                                    </svg>
                                                </div>
                                            </div>
                                            <!-- Scanner Status Message -->
                                            <p x-show="scannerStatus" x-text="scannerStatus"
                                                :class="scannerError ? 'text-red-600' : 'text-green-600'"
                                                class="mt-1 text-sm font-medium"></p>
                                            <p class="mt-1 text-xs text-gray-400">Use camera to scan QR code, or type
                                                gateway name and press Enter</p>
                                        </div>

                                        <div class="border-t border-gray-200 pt-4">
                                            <p class="text-xs text-gray-500 mb-2">Or select manually:</p>
                                        </div>

                                        <div>
                                            <label for="api_user_id"
                                                class="block text-sm font-medium text-gray-700">Select Gateway</label>
                                            <select name="api_user_id" id="api_user_id" x-model="selectedGatewayId"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 sm:text-sm"
                                                required>
                                                @foreach($gateways as $gateway)
                                                    <option value="{{ $gateway->id }}">{{ $gateway->name }}
                                                        ({{ $gateway->username }})</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div>
                                            <label for="nurse_id" class="block text-sm font-medium text-gray-700">Select
                                                Nurse (Operator)</label>
                                            <select name="nurse_id" id="nurse_id"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 sm:text-sm"
                                                required>
                                                @foreach($nurses as $nurse)
                                                    <option value="{{ $nurse->id }}">{{ $nurse->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div>
                                            <label for="duration_hours"
                                                class="block text-sm font-medium text-gray-700">Duration (Hours)</label>
                                            <input type="number" name="duration_hours" id="duration_hours" min="1"
                                                max="24" value="8"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 sm:text-sm"
                                                required>
                                            <p class="mt-1 text-xs text-gray-500">The binding will automatically expire
                                                after this duration.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                            <button type="submit"
                                class="inline-flex w-full justify-center rounded-md bg-rose-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-rose-500 sm:ml-3 sm:w-auto">Bind</button>
                            <button type="button" @click="open = false"
                                class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

</x-app-layout>

<!-- Vital Sign Notification System -->
<div x-data="vitalSignNotifications()" x-init="init()" @toggle-notifications.window="toggleNotifications()"
    class="relative z-[100]">
    <!-- Large Central Notification Overlay -->
    <template x-for="(notification, index) in notifications" :key="notification.id">
        <div class="fixed inset-0 z-[100] flex items-start justify-center pt-20 px-4 sm:px-6 pointer-events-none">

            <!-- Transparent Backdrop (Click to dismiss) -->
            <div x-show="true" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                class="fixed inset-0 pointer-events-auto" @click="removeNotification(index)"></div>

            <!-- Notification Card -->
            <div x-show="true" x-transition:enter="transform ease-out duration-300 transition"
                x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
                x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="pointer-events-auto w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-black/5 border-t-8 border-emerald-500">

                <div class="p-6">
                    <div class="flex items-start">
                        <div class="flex-shrink-0 pt-1">
                            <div
                                class="h-12 w-12 rounded-full bg-emerald-100 flex items-center justify-center animate-pulse">
                                <svg class="h-8 w-8 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                </svg>
                            </div>
                        </div>
                        <div class="ml-5 flex-1 w-0">
                            <!-- Header -->
                            <div class="flex items-center justify-between">
                                <h3 class="text-xl font-bold text-gray-900">New Vital Sign Recorded</h3>
                                <p class="text-xs font-medium text-gray-500 bg-gray-100 px-2 py-1 rounded-md"
                                    x-text="notification.recorded_at"></p>
                            </div>

                            <!-- Source Info -->
                            <p class="mt-1 text-sm text-gray-600">
                                Source: <span class="font-semibold text-emerald-600"
                                    x-text="notification.source || notification.gateway"></span>
                            </p>

                            <!-- Patient Info -->
                            <div class="mt-4 bg-gray-50 rounded-lg p-3 border border-gray-100">
                                <p class="text-lg font-bold text-gray-900" x-text="notification.patient_name"></p>
                                <p class="text-sm text-gray-500" x-show="notification.patient_mrn"
                                    x-text="'MRN: ' + notification.patient_mrn"></p>
                            </div>

                            <!-- Vitals Grid -->
                            <div class="mt-4 grid grid-cols-2 gap-4">
                                <div x-show="notification.systolic_bp"
                                    class="bg-red-50 p-3 rounded-lg border border-red-100 text-center">
                                    <span class="block text-xs uppercase text-red-600 font-bold tracking-wider">Blood
                                        Pressure</span>
                                    <span class="block text-2xl font-bold text-gray-900"
                                        x-text="notification.systolic_bp + '/' + notification.diastolic_bp"></span>
                                    <span class="text-xs text-gray-500">mmHg</span>
                                </div>
                                <div x-show="notification.pulse_rate"
                                    class="bg-blue-50 p-3 rounded-lg border border-blue-100 text-center">
                                    <span class="block text-xs uppercase text-blue-600 font-bold tracking-wider">Heart
                                        Rate</span>
                                    <span class="block text-2xl font-bold text-gray-900"
                                        x-text="notification.pulse_rate"></span>
                                    <span class="text-xs text-gray-500">bpm</span>
                                </div>
                                <div x-show="notification.spo2"
                                    class="bg-green-50 p-3 rounded-lg border border-green-100 text-center">
                                    <span
                                        class="block text-xs uppercase text-green-600 font-bold tracking-wider">SpO2</span>
                                    <span class="block text-2xl font-bold text-gray-900"
                                        x-text="notification.spo2 + '%'"></span>
                                    <span class="text-xs text-gray-500">Saturation</span>
                                </div>
                                <div x-show="notification.temperature"
                                    class="bg-orange-50 p-3 rounded-lg border border-orange-100 text-center">
                                    <span
                                        class="block text-xs uppercase text-orange-600 font-bold tracking-wider">Temp</span>
                                    <span class="block text-2xl font-bold text-gray-900"
                                        x-text="notification.temperature + '°C'"></span>
                                    <span class="text-xs text-gray-500">Celsius</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer / Actions -->
                    <div class="mt-6 flex justify-end">
                        <button type="button" @click="removeNotification(index)"
                            class="inline-flex items-center rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 transition-all">
                            Acknowledge
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>



<script>
    // Persistent audio context for notifications
    let notificationAudioContext = null;

    function getAudioContext() {
        if (!notificationAudioContext) {
            notificationAudioContext = new (window.AudioContext || window.webkitAudioContext)();
        }
        // Resume if suspended (required after user interaction)
        if (notificationAudioContext.state === 'suspended') {
            notificationAudioContext.resume();
        }
        return notificationAudioContext;
    }

    function playDingDong() {
        try {
            const ctx = getAudioContext();
            const now = ctx.currentTime;

            // First tone - "Ding" (higher pitch)
            const osc1 = ctx.createOscillator();
            const gain1 = ctx.createGain();
            osc1.connect(gain1);
            gain1.connect(ctx.destination);
            osc1.frequency.value = 880; // A5
            osc1.type = 'sine';
            gain1.gain.setValueAtTime(0.4, now);
            gain1.gain.exponentialRampToValueAtTime(0.01, now + 0.3);
            osc1.start(now);
            osc1.stop(now + 0.3);

            // Second tone - "Dong" (lower pitch)
            const osc2 = ctx.createOscillator();
            const gain2 = ctx.createGain();
            osc2.connect(gain2);
            gain2.connect(ctx.destination);
            osc2.frequency.value = 659; // E5
            osc2.type = 'sine';
            gain2.gain.setValueAtTime(0.4, now + 0.2);
            gain2.gain.exponentialRampToValueAtTime(0.01, now + 1.2); // Longer decay
            osc2.start(now + 0.2);
            osc2.stop(now + 1.2);
        } catch (e) {
            console.log('Audio notification not available:', e);
        }
    }

    // Request notification permission on page load
    document.addEventListener('DOMContentLoaded', function () {
        if ('Notification' in window) {
            if (Notification.permission === 'default') {
                Notification.requestPermission().then(permission => {
                    console.log('Notification permission:', permission);
                });
            }
        }

        // Initialize audio context on first user interaction to allow subsequent sounds
        const initAudio = () => {
            getAudioContext();
            document.removeEventListener('click', initAudio);
            document.removeEventListener('touchstart', initAudio);
        };
        document.addEventListener('click', initAudio, { once: true });
        document.addEventListener('touchstart', initAudio, { once: true });
    });

    function vitalSignNotifications() {
        return {
            notifications: [],
            lastCheck: '{{ $serverTime ?? now()->toIso8601String() }}',
            pollInterval: null,
            seenIds: new Set(),
            notificationsEnabled: true,

            init() {
                // Load preference
                this.notificationsEnabled = localStorage.getItem('vital_sign_notifications_enabled') !== 'false';

                // Start polling
                this.startPolling();
            },

            toggleNotifications() {
                this.notificationsEnabled = !this.notificationsEnabled;
                localStorage.setItem('vital_sign_notifications_enabled', this.notificationsEnabled);

                // Broadcast event to update button state
                window.dispatchEvent(new CustomEvent('notification-toggled', {
                    detail: this.notificationsEnabled
                }));
            },

            startPolling() {
                // Poll every 5 seconds (more frequent for "live" feel)
                this.pollInterval = setInterval(() => this.checkForNewVitals(), 5000);
                // Also check immediately
                this.checkForNewVitals();
            },

            async checkForNewVitals() {
                // Skip check if notifications are disabled
                if (!this.notificationsEnabled) return;

                try {
                    const response = await fetch(`{{ route('vital-signs.check-new') }}?since=${encodeURIComponent(this.lastCheck)}`);
                    const data = await response.json();

                    if (data.success && data.count > 0) {
                        data.new_vitals.forEach(vital => {
                            // Avoid duplicate notifications
                            if (!this.seenIds.has(vital.id)) {
                                this.seenIds.add(vital.id);
                                this.showNotification(vital);
                            }
                        });
                    }

                    // Update last check timestamp
                    this.lastCheck = data.timestamp;
                } catch (error) {
                    console.error('Failed to check for new vital signs:', error);
                }
            },

            showNotification(vital) {
                // Double check enabled state
                if (!this.notificationsEnabled) return;

                // Add to notifications queue
                this.notifications.push(vital);

                // Play ding-dong notification sound
                playDingDong();

                // Show browser notification if permitted
                if ('Notification' in window && Notification.permission === 'granted') {
                    const bpText = vital.systolic_bp ? `BP: ${vital.systolic_bp}/${vital.diastolic_bp}` : '';
                    const hrText = vital.pulse_rate ? `HR: ${vital.pulse_rate}` : '';

                    new Notification(`New Vital Sign - ${vital.source || vital.gateway}`, {
                        body: `${vital.patient_name}\n${[bpText, hrText].filter(Boolean).join(' | ')}`,
                        icon: '/favicon.ico',
                        tag: `vital-${vital.id}`,
                    });
                }
            },

            removeNotification(index) {
                this.notifications.splice(index, 1);
            }
        }
    }
</script>