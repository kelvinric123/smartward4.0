<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-bold text-2xl text-gray-800 leading-tight">
                    {{ __('ADT Message Test') }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">Send test ADT messages to the HL7 listener</p>
            </div>
            <a href="{{ route('adt.index') }}" 
               class="inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-lg transition-all">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to ADT Config
            </a>
        </div>
    </x-slot>

    <div class="py-8" x-data="adtTester()">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            <!-- Connection Settings -->
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-indigo-100">
                <div class="p-6 border-b border-indigo-100 bg-gradient-to-r from-indigo-50 to-purple-50">
                    <div class="flex items-center">
                        <div class="p-3 bg-indigo-600 rounded-xl mr-4">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800">Connection Settings</h3>
                            <p class="text-sm text-gray-500">Configure the HL7 listener endpoint</p>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Host / IP Address</label>
                            <input type="text" x-model="host" 
                                   class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono"
                                   placeholder="localhost">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Port</label>
                            <input type="number" x-model="port" 
                                   class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono"
                                   placeholder="3000">
                        </div>
                        <div class="flex items-end">
                            <button @click="testConnection()" 
                                    :disabled="testingConnection"
                                    class="w-full inline-flex items-center justify-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:bg-indigo-400 text-white font-medium rounded-lg transition-colors">
                                <svg x-show="!testingConnection" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                <svg x-show="testingConnection" class="w-4 h-4 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <span x-text="testingConnection ? 'Testing...' : 'Test Connection'"></span>
                            </button>
                        </div>
                    </div>
                    <div x-show="connectionStatus" x-transition class="mt-4">
                        <div :class="connectionStatus === 'online' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'" 
                             class="border rounded-lg px-4 py-3 flex items-center">
                            <svg x-show="connectionStatus === 'online'" class="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <svg x-show="connectionStatus === 'offline'" class="w-5 h-5 mr-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span x-text="connectionMessage"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Message Type Selection -->
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-teal-100">
                <div class="p-6 border-b border-teal-100 bg-gradient-to-r from-teal-50 to-cyan-50">
                    <div class="flex items-center">
                        <div class="p-3 bg-teal-600 rounded-xl mr-4">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800">Message Type</h3>
                            <p class="text-sm text-gray-500">Select the ADT event type to send</p>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3">
                        @foreach($sampleMessages as $code => $sample)
                            <button @click="selectSampleMessage('{{ $code }}')"
                                    :class="selectedType === '{{ $code }}' ? 'ring-2 ring-teal-500 bg-teal-50 border-teal-300' : 'hover:bg-gray-50 border-gray-200'"
                                    class="text-center p-3 rounded-xl border transition-all">
                                <span class="block text-lg font-bold 
                                    {{ $code === 'A01' ? 'text-green-600' : '' }}
                                    {{ $code === 'A02' ? 'text-blue-600' : '' }}
                                    {{ $code === 'A03' ? 'text-red-600' : '' }}
                                    {{ $code === 'A08' ? 'text-yellow-600' : '' }}
                                    {{ in_array($code, ['A11', 'A13', 'A25']) ? 'text-purple-600' : '' }}
                                    {{ $code === 'A16' ? 'text-orange-600' : '' }}">
                                    {{ $code }}
                                </span>
                                <span class="text-xs text-gray-500">{{ explode(' - ', $sample['name'])[1] ?? '' }}</span>
                            </button>
                        @endforeach
                    </div>
                    <div x-show="selectedType" x-transition class="mt-4 p-4 bg-gray-50 rounded-xl">
                        <p class="text-sm text-gray-700" x-text="sampleMessages[selectedType]?.description"></p>
                    </div>
                </div>
            </div>

            <!-- Parameter Editor -->
            <div x-show="selectedType" x-transition class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-purple-100">
                <div class="p-6 border-b border-purple-100 bg-gradient-to-r from-purple-50 to-pink-50">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="p-3 bg-purple-600 rounded-xl mr-4">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800">Message Parameters</h3>
                                <p class="text-sm text-gray-500">Modify parameters to customize the message</p>
                            </div>
                        </div>
                        <button @click="resetParameters()" 
                                class="px-3 py-1.5 text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors">
                            Reset to Default
                        </button>
                    </div>
                </div>
                <div class="p-6 space-y-6">
                    <!-- MSH Segment Parameters -->
                    <div>
                        <h4 class="text-sm font-bold text-gray-700 mb-3 flex items-center">
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-indigo-100 text-indigo-700 text-xs font-bold mr-2">MSH</span>
                            Message Header
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Sending Application</label>
                                <input type="text" x-model="params.sendingApp" @input="generateMessage()"
                                       class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 font-mono">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Sending Facility</label>
                                <input type="text" x-model="params.sendingFacility" @input="generateMessage()"
                                       class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 font-mono">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Receiving Application</label>
                                <input type="text" x-model="params.receivingApp" @input="generateMessage()"
                                       class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 font-mono">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Message Control ID</label>
                                <input type="text" x-model="params.messageControlId" @input="generateMessage()"
                                       class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 font-mono">
                            </div>
                        </div>
                    </div>

                    <!-- PID Segment Parameters -->
                    <div>
                        <h4 class="text-sm font-bold text-gray-700 mb-3 flex items-center">
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-green-100 text-green-700 text-xs font-bold mr-2">PID</span>
                            Patient Identification
                        </h4>
                        
                        <!-- Patient Selection Dropdown -->
                        <div class="mb-4 p-4 bg-green-50 rounded-xl border border-green-200">
                            <div class="flex items-center justify-between">
                                <div class="flex-1 mr-4">
                                    <label class="block text-xs font-medium text-green-700 mb-1">
                                        <span class="inline-flex items-center">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                            </svg>
                                            Select Patient to Auto-fill
                                        </span>
                                    </label>
                                    <select x-model="params.selectedPatient" @change="onPatientChange()"
                                            class="w-full text-sm rounded-lg border-green-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                                        <option value="">-- Select Patient (Optional) --</option>
                                        @foreach($patients as $patient)
                                            <option value="{{ $patient['id'] }}">
                                                {{ $patient['mrn'] }} - {{ $patient['name'] }} 
                                                @if($patient['status'] === 'admitted') (Admitted) @elseif($patient['status'] === 'pending_discharge') (Pending Discharge) @else (Pre-book) @endif
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <button @click="clearPatientSelection()" 
                                        x-show="params.selectedPatient"
                                        class="px-3 py-1.5 text-sm bg-green-100 hover:bg-green-200 text-green-700 rounded-lg transition-colors"
                                        title="Clear patient selection">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                            <p class="mt-2 text-xs text-green-600">Selecting a patient will auto-fill the PID, PV1, and Care Team fields below.</p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">MRN</label>
                                <input type="text" x-model="params.mrn" @input="generateMessage()"
                                       class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 font-mono">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">IC/Passport (PP^Number)</label>
                                <input type="text" x-model="params.icPassport" @input="generateMessage()"
                                       class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 font-mono">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Patient Name</label>
                                <input type="text" x-model="params.patientName" @input="generateMessage()"
                                       class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Date of Birth (YYYYMMDD)</label>
                                <input type="text" x-model="params.dob" @input="generateMessage()"
                                       class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 font-mono"
                                       placeholder="19920409">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Gender</label>
                                <select x-model="params.gender" @change="generateMessage()"
                                        class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                    <option value="M">Male</option>
                                    <option value="F">Female</option>
                                    <option value="O">Other</option>
                                    <option value="U">Unknown</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Phone</label>
                                <input type="text" x-model="params.phone" @input="generateMessage()"
                                       class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 font-mono">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Address</label>
                                <input type="text" x-model="params.address" @input="generateMessage()"
                                       class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Nationality</label>
                                <input type="text" x-model="params.nationality" @input="generateMessage()"
                                       class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 font-mono">
                            </div>
                        </div>
                    </div>

                    <!-- PV1 Segment Parameters -->
                    <div>
                        <h4 class="text-sm font-bold text-gray-700 mb-3 flex items-center">
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-blue-100 text-blue-700 text-xs font-bold mr-2">PV1</span>
                            Patient Visit
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Patient Class</label>
                                <select x-model="params.patientClass" @change="generateMessage()"
                                        class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                    <option value="I">Inpatient</option>
                                    <option value="O">Outpatient</option>
                                    <option value="E">Emergency</option>
                                    <option value="P">Preadmit</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Ward</label>
                                <select x-model="params.selectedWard" @change="onWardChange()"
                                        class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                    <option value="">-- Select Ward --</option>
                                    @foreach($wards as $ward)
                                        <option value="{{ $ward->id }}|{{ $ward->ward_code }}|{{ $ward->ward_name }}">
                                            {{ $ward->ward_code }} - {{ $ward->ward_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Room/Bed</label>
                                <select x-model="params.selectedBed" @change="onBedChange()"
                                        class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                    <option value="">-- Select Bed --</option>
                                    <template x-for="bed in filteredBeds" :key="bed.id">
                                        <option :value="bed.id + '|' + bed.bed_id" x-text="bed.bed_display_name || bed.bed_number"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Visit Number</label>
                                <input type="text" x-model="params.visitNumber" @input="generateMessage()"
                                       class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 font-mono"
                                       placeholder="PHKL25IP11000009">
                            </div>
                            <div class="lg:col-span-2">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Ward Code / Name (Auto-filled)</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="text" x-model="params.wardCode" @input="generateMessage()"
                                           class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 font-mono bg-gray-50"
                                           placeholder="WWC7">
                                    <input type="text" x-model="params.wardName" @input="generateMessage()"
                                           class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 bg-gray-50"
                                           placeholder="WARD C7 (EXECUTIVE WARD)">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Bed Code (Auto-filled)</label>
                                <input type="text" x-model="params.bedCode" @input="generateMessage()"
                                       class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 font-mono bg-gray-50"
                                       placeholder="C706">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Admit DateTime</label>
                                <input type="text" x-model="params.admitDateTime" @input="generateMessage()"
                                       class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 font-mono"
                                       placeholder="20251117080000">
                            </div>
                        </div>
                    </div>

                    <!-- Care Team Section -->
                    <div>
                        <h4 class="text-sm font-bold text-gray-700 mb-3 flex items-center">
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-emerald-100 text-emerald-700 text-xs font-bold mr-2">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            </span>
                            Care Team
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <!-- Doctor/Consultant -->
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">
                                    <span class="inline-flex items-center">
                                        <svg class="w-3 h-3 mr-1 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                        </svg>
                                        Attending Doctor
                                    </span>
                                </label>
                                <select x-model="params.selectedDoctor" @change="onDoctorChange()"
                                        class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                    <option value="">-- Select Doctor --</option>
                                    @foreach($consultants as $consultant)
                                        <option value="{{ $consultant->personnel_code }}|{{ $consultant->name }}">
                                            {{ $consultant->personnel_code }} - {{ $consultant->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="lg:col-span-2">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Doctor Code / Name (Auto-filled)</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="text" x-model="params.doctorCode" @input="generateMessage()"
                                           class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 font-mono bg-gray-50"
                                           placeholder="DALEXLHR">
                                    <input type="text" x-model="params.doctorName" @input="generateMessage()"
                                           class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 bg-gray-50"
                                           placeholder="ALEX LEOW HWONG RUEY">
                                </div>
                            </div>

                            <!-- Anaesthetist -->
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">
                                    <span class="inline-flex items-center">
                                        <svg class="w-3 h-3 mr-1 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                        </svg>
                                        Anaesthetist
                                    </span>
                                </label>
                                <select x-model="params.selectedAnaesthetist" @change="onAnaesthetistChange()"
                                        class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                    <option value="">-- Select Anaesthetist --</option>
                                    @foreach($anaesthetists as $anaesthetist)
                                        <option value="{{ $anaesthetist->personnel_code }}|{{ $anaesthetist->name }}">
                                            {{ $anaesthetist->personnel_code }} - {{ $anaesthetist->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="lg:col-span-2">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Anaesthetist Code / Name (Auto-filled)</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="text" x-model="params.anaesthetistCode" @input="generateMessage()"
                                           class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 font-mono bg-gray-50"
                                           placeholder="ANAEST01">
                                    <input type="text" x-model="params.anaesthetistName" @input="generateMessage()"
                                           class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 bg-gray-50"
                                           placeholder="Anaesthetist Name">
                                </div>
                            </div>

                            <!-- Nurse -->
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">
                                    <span class="inline-flex items-center">
                                        <svg class="w-3 h-3 mr-1 text-pink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                        </svg>
                                        Primary Nurse
                                    </span>
                                </label>
                                <select x-model="params.selectedNurse" @change="onNurseChange()"
                                        class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                    <option value="">-- Select Nurse --</option>
                                    @foreach($nurses as $nurse)
                                        <option value="{{ $nurse->personnel_code }}|{{ $nurse->name }}">
                                            {{ $nurse->personnel_code }} - {{ $nurse->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="lg:col-span-2">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Nurse Code / Name (Auto-filled)</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="text" x-model="params.nurseCode" @input="generateMessage()"
                                           class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 font-mono bg-gray-50"
                                           placeholder="NURSE01">
                                    <input type="text" x-model="params.nurseName" @input="generateMessage()"
                                           class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 bg-gray-50"
                                           placeholder="Nurse Name">
                                </div>
                            </div>

                            <!-- Referring Doctor (PV1-8) -->
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">
                                    <span class="inline-flex items-center">
                                        <svg class="w-3 h-3 mr-1 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"/>
                                        </svg>
                                        Referring Doctor (PV1-8)
                                    </span>
                                </label>
                                <select x-model="params.selectedReferringDoctor" @change="onReferringDoctorChange()"
                                        class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                    <option value="">-- Select Referring Doctor --</option>
                                    @foreach($consultants as $consultant)
                                        <option value="{{ $consultant->personnel_code }}|{{ $consultant->name }}">
                                            {{ $consultant->personnel_code }} - {{ $consultant->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="lg:col-span-2">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Referring Doctor Code / Name (Auto-filled)</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="text" x-model="params.referringDoctorCode" @input="generateMessage()"
                                           class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 font-mono bg-gray-50"
                                           placeholder="DREF01">
                                    <input type="text" x-model="params.referringDoctorName" @input="generateMessage()"
                                           class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 bg-gray-50"
                                           placeholder="Referring Doctor Name">
                                </div>
                            </div>

                            <!-- Consulting Doctor (PV1-9) -->
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">
                                    <span class="inline-flex items-center">
                                        <svg class="w-3 h-3 mr-1 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                                        </svg>
                                        Consulting Doctor (PV1-9)
                                    </span>
                                </label>
                                <select x-model="params.selectedConsultingDoctor" @change="onConsultingDoctorChange()"
                                        class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                    <option value="">-- Select Consulting Doctor --</option>
                                    @foreach($consultants as $consultant)
                                        <option value="{{ $consultant->personnel_code }}|{{ $consultant->name }}">
                                            {{ $consultant->personnel_code }} - {{ $consultant->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="lg:col-span-2">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Consulting Doctor Code / Name (Auto-filled)</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="text" x-model="params.consultingDoctorCode" @input="generateMessage()"
                                           class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 font-mono bg-gray-50"
                                           placeholder="DCONS01">
                                    <input type="text" x-model="params.consultingDoctorName" @input="generateMessage()"
                                           class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 bg-gray-50"
                                           placeholder="Consulting Doctor Name">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- A08 Specific: Diet & Isolation -->
                    <div x-show="selectedType === 'A08'" x-transition>
                        <h4 class="text-sm font-bold text-gray-700 mb-3 flex items-center">
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-yellow-100 text-yellow-700 text-xs font-bold mr-2">EXT</span>
                            A08 Extended Parameters
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="lg:col-span-1">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Diet Type(s) - Select Multiple</label>
                                <select x-model="params.selectedDietTypes" @change="updateDietTypeString()" multiple
                                       class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500"
                                       style="min-height: 120px;">
                                    @foreach($dietTypes as $diet)
                                        <option value="{{ $diet->code }}^{{ $diet->name }}">{{ $diet->code }} - {{ $diet->name }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-400">Hold Ctrl/Cmd to select multiple diet types</p>
                            </div>
                            <div class="lg:col-span-1">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Diet Type String (Auto-generated)</label>
                                <input type="text" x-model="params.dietType" @input="generateMessage()"
                                       class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 bg-gray-50"
                                       placeholder="DMD, REGD^DIABETIC DIET, REGULAR DIET">
                                <p class="mt-1 text-xs text-gray-400">Format: CODE1, CODE2^NAME1, NAME2</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Isolation Type</label>
                                <select x-model="params.selectedIsolation" @change="updateIsolationFromSelect()"
                                       class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                    <option value="">-- Select Isolation --</option>
                                    @foreach($isolationTypes as $isolation)
                                        <option value="{{ $isolation->code }}|{{ $isolation->name }}">{{ $isolation->code }} - {{ $isolation->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Isolation Code</label>
                                <input type="text" x-model="params.isolationCode" @input="generateMessage()"
                                       class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 font-mono"
                                       placeholder="CI">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Isolation Description</label>
                                <input type="text" x-model="params.isolationDesc" @input="generateMessage()"
                                       class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500"
                                       placeholder="Contact Isolation">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Next of Kin Name</label>
                                <input type="text" x-model="params.nokName" @input="generateMessage()"
                                       class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500"
                                       placeholder="AHMAD">
                            </div>
                        </div>
                    </div>

                    <!-- A03 Specific: Discharge DateTime -->
                    <div x-show="selectedType === 'A03'" x-transition>
                        <h4 class="text-sm font-bold text-gray-700 mb-3 flex items-center">
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded bg-red-100 text-red-700 text-xs font-bold mr-2">EXT</span>
                            A03 Extended Parameters
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Discharge DateTime</label>
                                <input type="text" x-model="params.dischargeDateTime" @input="generateMessage()"
                                       class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 font-mono"
                                       placeholder="20251117160000">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Message Editor -->
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-orange-100">
                <div class="p-6 border-b border-orange-100 bg-gradient-to-r from-orange-50 to-amber-50">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="p-3 bg-orange-600 rounded-xl mr-4">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800">HL7 Message</h3>
                                <p class="text-sm text-gray-500">Generated message (or paste your own custom message)</p>
                            </div>
                        </div>
                        <div class="flex space-x-2">
                            <label class="flex items-center text-sm text-gray-600">
                                <input type="checkbox" x-model="manualMode" class="rounded border-gray-300 text-orange-600 mr-2">
                                Manual Edit Mode
                            </label>
                            <button @click="formatMessage()" 
                                    class="px-3 py-1.5 text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors"
                                    title="Format message">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/>
                                </svg>
                            </button>
                            <button @click="clearMessage()" 
                                    class="px-3 py-1.5 text-sm bg-red-100 hover:bg-red-200 text-red-700 rounded-lg transition-colors"
                                    title="Clear message">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    <textarea x-model="message" 
                              @input="manualMode = true"
                              rows="12"
                              class="w-full rounded-lg border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500 font-mono text-sm bg-gray-900 text-green-400"
                              placeholder="Select a message type above or paste your HL7 ADT message here..."></textarea>
                    <div class="mt-4 flex items-center justify-between">
                        <div class="text-sm text-gray-500">
                            <span x-text="message ? message.split(/\\r|\\n/).filter(s => s.trim()).length : 0"></span> segments
                            <span x-show="manualMode" class="ml-2 text-orange-600">(Manual mode - parameters won't update message)</span>
                        </div>
                        <button @click="sendMessage()" 
                                :disabled="sending || !message.trim()"
                                class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 disabled:from-gray-400 disabled:to-gray-500 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transition-all">
                            <svg x-show="!sending" class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                            </svg>
                            <svg x-show="sending" class="w-5 h-5 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <span x-text="sending ? 'Sending...' : 'Send Message'"></span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Response Section -->
            <div x-show="lastResponse" x-transition class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-gray-200">
                <div class="p-6" :class="lastResponse?.success ? 'bg-gradient-to-r from-green-50 to-emerald-50' : 'bg-gradient-to-r from-red-50 to-pink-50'">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div :class="lastResponse?.success ? 'bg-green-600' : 'bg-red-600'" class="p-3 rounded-xl mr-4">
                                <svg x-show="lastResponse?.success" class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <svg x-show="!lastResponse?.success" class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800" x-text="lastResponse?.success ? 'Message Sent' : 'Send Failed'"></h3>
                                <p class="text-sm text-gray-600" x-text="lastResponse?.message"></p>
                            </div>
                        </div>
                        <div x-show="lastResponse?.bytes_sent" class="text-right">
                            <span class="text-sm text-gray-500">Bytes sent:</span>
                            <span class="ml-1 font-mono text-sm font-bold text-gray-700" x-text="lastResponse?.bytes_sent"></span>
                        </div>
                    </div>
                    <div x-show="lastResponse?.success" class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-xl">
                        <div class="flex items-center text-sm text-blue-700">
                            <svg class="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span>Check the <a href="{{ route('adt.index') }}" class="font-semibold underline hover:text-blue-800">ADT Config page</a> logs for processing results.</span>
                        </div>
                    </div>
                    <div x-show="lastResponse?.error" class="mt-4">
                        <pre class="bg-gray-900 text-red-400 rounded-lg p-4 text-sm overflow-x-auto font-mono whitespace-pre-wrap" x-text="lastResponse?.error"></pre>
                    </div>
                </div>
            </div>

            <!-- Help Section -->
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-slate-200">
                <div class="p-6 border-b border-slate-200 bg-gradient-to-r from-slate-50 to-gray-50">
                    <div class="flex items-center">
                        <div class="p-3 bg-slate-600 rounded-xl mr-4">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800">Quick Reference</h3>
                            <p class="text-sm text-gray-500">ADT Event Types</p>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="p-3 bg-green-50 rounded-lg border border-green-200">
                            <span class="font-bold text-green-700">A01</span>
                            <span class="text-sm text-green-600 ml-2">Admit Patient</span>
                            <p class="text-xs text-gray-500 mt-1">Admits patient to a bed/ward</p>
                        </div>
                        <div class="p-3 bg-blue-50 rounded-lg border border-blue-200">
                            <span class="font-bold text-blue-700">A02</span>
                            <span class="text-sm text-blue-600 ml-2">Transfer</span>
                            <p class="text-xs text-gray-500 mt-1">Moves patient to new location</p>
                        </div>
                        <div class="p-3 bg-red-50 rounded-lg border border-red-200">
                            <span class="font-bold text-red-700">A03</span>
                            <span class="text-sm text-red-600 ml-2">Discharge</span>
                            <p class="text-xs text-gray-500 mt-1">Ends visit, releases bed</p>
                        </div>
                        <div class="p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                            <span class="font-bold text-yellow-700">A08</span>
                            <span class="text-sm text-yellow-600 ml-2">Update Info</span>
                            <p class="text-xs text-gray-500 mt-1">Updates demographics, diet, isolation</p>
                        </div>
                        <div class="p-3 bg-purple-50 rounded-lg border border-purple-200">
                            <span class="font-bold text-purple-700">A11</span>
                            <span class="text-sm text-purple-600 ml-2">Cancel Admit</span>
                            <p class="text-xs text-gray-500 mt-1">Reverses A01 admission</p>
                        </div>
                        <div class="p-3 bg-purple-50 rounded-lg border border-purple-200">
                            <span class="font-bold text-purple-700">A13</span>
                            <span class="text-sm text-purple-600 ml-2">Cancel Discharge</span>
                            <p class="text-xs text-gray-500 mt-1">Reverses A03 discharge</p>
                        </div>
                        <div class="p-3 bg-orange-50 rounded-lg border border-orange-200">
                            <span class="font-bold text-orange-700">A16</span>
                            <span class="text-sm text-orange-600 ml-2">Pending Discharge</span>
                            <p class="text-xs text-gray-500 mt-1">Flags patient for discharge</p>
                        </div>
                        <div class="p-3 bg-purple-50 rounded-lg border border-purple-200">
                            <span class="font-bold text-purple-700">A25</span>
                            <span class="text-sm text-purple-600 ml-2">Cancel Pending</span>
                            <p class="text-xs text-gray-500 mt-1">Reverses A16 pending discharge</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function adtTester() {
            return {
                host: '{{ $defaultHost }}',
                port: {{ $defaultPort }},
                message: '',
                selectedType: null,
                sending: false,
                testingConnection: false,
                connectionStatus: null,
                connectionMessage: '',
                lastResponse: null,
                manualMode: false,
                
                sampleMessages: @json($sampleMessages),
                
                // Database data for dropdowns
                allBeds: @json($beds),
                allPatients: @json($patients),
                
                // Default parameters
                defaultParams: {
                    // MSH
                    sendingApp: 'CEREBRALPLUS',
                    sendingFacility: 'PHKL',
                    receivingApp: 'IWARD',
                    messageControlId: '50690.0',
                    // PID
                    selectedPatient: '',
                    mrn: '3300746940',
                    icPassport: 'PP^N7356938',
                    patientName: 'TEST SST PATIENT',
                    dob: '19920409',
                    gender: 'M',
                    phone: '0^06128764',
                    address: 'Test^^Ayer Hitam^Johor^N/A^MYS',
                    nationality: 'IND',
                    // PV1 Location
                    patientClass: 'I',
                    selectedWard: '',
                    selectedBed: '',
                    wardCode: 'WWC7',
                    bedCode: 'C706',
                    wardName: 'WARD C7 (EXECUTIVE WARD)',
                    visitNumber: 'PHKL25IP11000009',
                    admitDateTime: '',
                    // Care Team
                    selectedDoctor: '',
                    doctorCode: 'DALEXLHR',
                    doctorName: 'ALEX LEOW HWONG RUEY',
                    selectedAnaesthetist: '',
                    anaesthetistCode: '',
                    anaesthetistName: '',
                    selectedNurse: '',
                    nurseCode: '',
                    nurseName: '',
                    // Referring Doctor (PV1-8)
                    selectedReferringDoctor: '',
                    referringDoctorCode: '',
                    referringDoctorName: '',
                    // Consulting Doctor (PV1-9)
                    selectedConsultingDoctor: '',
                    consultingDoctorCode: '',
                    consultingDoctorName: '',
                    // A08 specific
                    selectedDietTypes: [],
                    dietType: 'DMD, REGD^DIABETIC DIET, REGULAR DIET',
                    selectedIsolation: '',
                    isolationCode: 'CI',
                    isolationDesc: 'Contact Isolation',
                    nokName: 'AHMAD',
                    // A03 specific
                    dischargeDateTime: '',
                },
                
                params: {},

                // Computed property for filtered beds based on selected ward
                get filteredBeds() {
                    if (!this.params.selectedWard) {
                        return this.allBeds;
                    }
                    const parts = this.params.selectedWard.split('|');
                    const wardId = parseInt(parts[0]);
                    return this.allBeds.filter(bed => bed.ward_id === wardId);
                },

                init() {
                    this.resetParameters();
                    this.testConnection();
                },
                
                resetParameters() {
                    this.params = { ...this.defaultParams };
                    this.params.selectedDietTypes = [];
                    this.params.selectedIsolation = '';
                    this.params.selectedWard = '';
                    this.params.selectedBed = '';
                    this.params.selectedDoctor = '';
                    this.params.selectedAnaesthetist = '';
                    this.params.selectedNurse = '';
                    this.params.selectedPatient = '';
                    this.params.selectedReferringDoctor = '';
                    this.params.selectedConsultingDoctor = '';
                    this.params.referringDoctorCode = '';
                    this.params.referringDoctorName = '';
                    this.params.consultingDoctorCode = '';
                    this.params.consultingDoctorName = '';
                    // Set current datetime
                    const now = new Date();
                    const datetime = now.getFullYear().toString() +
                        (now.getMonth() + 1).toString().padStart(2, '0') +
                        now.getDate().toString().padStart(2, '0') +
                        now.getHours().toString().padStart(2, '0') +
                        now.getMinutes().toString().padStart(2, '0') +
                        now.getSeconds().toString().padStart(2, '0');
                    this.params.admitDateTime = datetime;
                    this.params.dischargeDateTime = datetime;
                    this.params.messageControlId = (50690 + Math.floor(Math.random() * 1000)).toFixed(1);
                    
                    if (this.selectedType) {
                        this.generateMessage();
                    }
                },

                // Ward selection handler
                onWardChange() {
                    if (!this.params.selectedWard) {
                        this.params.wardCode = '';
                        this.params.wardName = '';
                    } else {
                        const parts = this.params.selectedWard.split('|');
                        if (parts.length >= 3) {
                            this.params.wardCode = parts[1];
                            this.params.wardName = parts[2];
                        }
                    }
                    // Reset bed selection when ward changes
                    this.params.selectedBed = '';
                    this.params.bedCode = '';
                    this.generateMessage();
                },

                // Bed selection handler
                onBedChange() {
                    if (!this.params.selectedBed) {
                        this.params.bedCode = '';
                    } else {
                        const parts = this.params.selectedBed.split('|');
                        if (parts.length >= 2) {
                            this.params.bedCode = parts[1];
                        }
                    }
                    this.generateMessage();
                },

                // Doctor selection handler
                onDoctorChange() {
                    if (!this.params.selectedDoctor) {
                        this.params.doctorCode = '';
                        this.params.doctorName = '';
                    } else {
                        const parts = this.params.selectedDoctor.split('|');
                        if (parts.length >= 2) {
                            this.params.doctorCode = parts[0];
                            this.params.doctorName = parts[1];
                        }
                    }
                    this.generateMessage();
                },

                // Anaesthetist selection handler
                onAnaesthetistChange() {
                    if (!this.params.selectedAnaesthetist) {
                        this.params.anaesthetistCode = '';
                        this.params.anaesthetistName = '';
                    } else {
                        const parts = this.params.selectedAnaesthetist.split('|');
                        if (parts.length >= 2) {
                            this.params.anaesthetistCode = parts[0];
                            this.params.anaesthetistName = parts[1];
                        }
                    }
                    this.generateMessage();
                },

                // Nurse selection handler
                onNurseChange() {
                    if (!this.params.selectedNurse) {
                        this.params.nurseCode = '';
                        this.params.nurseName = '';
                    } else {
                        const parts = this.params.selectedNurse.split('|');
                        if (parts.length >= 2) {
                            this.params.nurseCode = parts[0];
                            this.params.nurseName = parts[1];
                        }
                    }
                    this.generateMessage();
                },

                // Patient selection handler - auto-fills PID, PV1, and Care Team fields
                onPatientChange() {
                    if (!this.params.selectedPatient) {
                        return;
                    }
                    
                    const patientId = parseInt(this.params.selectedPatient);
                    const patient = this.allPatients.find(p => p.id === patientId);
                    
                    if (!patient) {
                        return;
                    }
                    
                    // Auto-fill PID fields
                    this.params.mrn = patient.mrn || '';
                    this.params.icPassport = patient.ic_passport ? 'PP^' + patient.ic_passport : '';
                    this.params.patientName = patient.name || '';
                    this.params.dob = patient.date_of_birth || '';
                    this.params.gender = patient.gender || 'U';
                    this.params.phone = patient.phone ? '0^' + patient.phone : '';
                    this.params.address = patient.address || '';
                    this.params.nationality = patient.race || 'MYS';
                    
                    // Auto-fill PV1 fields
                    this.params.visitNumber = patient.visit_number || '';
                    if (patient.admitted_at) {
                        this.params.admitDateTime = patient.admitted_at;
                    }
                    
                    // Auto-fill ward and bed
                    if (patient.ward_code) {
                        this.params.wardCode = patient.ward_code;
                        this.params.wardName = patient.ward_name || '';
                        // Try to auto-select the ward in dropdown
                        const wardOption = Array.from(document.querySelectorAll('select[x-model="params.selectedWard"] option')).find(opt => opt.value.includes(patient.ward_code));
                        if (wardOption) {
                            this.params.selectedWard = wardOption.value;
                        }
                    }
                    if (patient.bed_code) {
                        this.params.bedCode = patient.bed_code;
                    }
                    
                    // Auto-fill Care Team - Attending Doctor
                    if (patient.consultant_code) {
                        this.params.doctorCode = patient.consultant_code;
                        this.params.doctorName = patient.consultant_name || '';
                        this.params.selectedDoctor = patient.consultant_code + '|' + (patient.consultant_name || '');
                    }
                    
                    // Auto-fill Care Team - Nurse
                    if (patient.nurse_code) {
                        this.params.nurseCode = patient.nurse_code;
                        this.params.nurseName = patient.nurse_name || '';
                        this.params.selectedNurse = patient.nurse_code + '|' + (patient.nurse_name || '');
                    }
                    
                    // Auto-fill Care Team - Anaesthetist
                    if (patient.anaesthetist_code) {
                        this.params.anaesthetistCode = patient.anaesthetist_code;
                        this.params.anaesthetistName = patient.anaesthetist_name || '';
                        this.params.selectedAnaesthetist = patient.anaesthetist_code + '|' + (patient.anaesthetist_name || '');
                    }
                    
                    this.generateMessage();
                },

                // Clear patient selection
                clearPatientSelection() {
                    this.params.selectedPatient = '';
                    this.generateMessage();
                },

                // Referring Doctor selection handler (PV1-8)
                onReferringDoctorChange() {
                    if (!this.params.selectedReferringDoctor) {
                        this.params.referringDoctorCode = '';
                        this.params.referringDoctorName = '';
                    } else {
                        const parts = this.params.selectedReferringDoctor.split('|');
                        if (parts.length >= 2) {
                            this.params.referringDoctorCode = parts[0];
                            this.params.referringDoctorName = parts[1];
                        }
                    }
                    this.generateMessage();
                },

                // Consulting Doctor selection handler (PV1-9)
                onConsultingDoctorChange() {
                    if (!this.params.selectedConsultingDoctor) {
                        this.params.consultingDoctorCode = '';
                        this.params.consultingDoctorName = '';
                    } else {
                        const parts = this.params.selectedConsultingDoctor.split('|');
                        if (parts.length >= 2) {
                            this.params.consultingDoctorCode = parts[0];
                            this.params.consultingDoctorName = parts[1];
                        }
                    }
                    this.generateMessage();
                },

                updateDietTypeString() {
                    if (this.params.selectedDietTypes.length === 0) {
                        this.params.dietType = '';
                        this.generateMessage();
                        return;
                    }
                    
                    // Parse selected values (format: "CODE^NAME")
                    const codes = [];
                    const names = [];
                    
                    this.params.selectedDietTypes.forEach(item => {
                        const parts = item.split('^');
                        if (parts.length >= 2) {
                            codes.push(parts[0]);
                            names.push(parts[1]);
                        }
                    });
                    
                    // Format: "CODE1, CODE2^NAME1, NAME2"
                    this.params.dietType = codes.join(', ') + '^' + names.join(', ');
                    this.generateMessage();
                },

                updateIsolationFromSelect() {
                    if (!this.params.selectedIsolation) {
                        this.params.isolationCode = '';
                        this.params.isolationDesc = '';
                        this.generateMessage();
                        return;
                    }
                    
                    const parts = this.params.selectedIsolation.split('|');
                    if (parts.length >= 2) {
                        this.params.isolationCode = parts[0];
                        this.params.isolationDesc = parts[1];
                    }
                    this.generateMessage();
                },

                selectSampleMessage(type) {
                    this.selectedType = type;
                    this.manualMode = false;
                    
                    // Clear selections and use manual codes as fallback
                    this.params.selectedWard = '';
                    this.params.selectedBed = '';
                    this.params.selectedDoctor = '';
                    this.params.selectedPatient = '';
                    this.params.selectedReferringDoctor = '';
                    this.params.selectedConsultingDoctor = '';
                    this.params.referringDoctorCode = '';
                    this.params.referringDoctorName = '';
                    this.params.consultingDoctorCode = '';
                    this.params.consultingDoctorName = '';
                    
                    // Update ward/bed based on message type
                    if (type === 'A01' || type === 'A11') {
                        this.params.wardCode = 'WWC7';
                        this.params.bedCode = 'C706';
                        this.params.wardName = 'WARD C7 (EXECUTIVE WARD)';
                        this.params.doctorCode = 'DALEXLHR';
                        this.params.doctorName = 'ALEX LEOW HWONG RUEY';
                    } else {
                        this.params.wardCode = 'WWD6';
                        this.params.bedCode = 'D610';
                        this.params.wardName = 'WARD D6 (MEDICAL \\& SURGICAL)';
                        this.params.doctorCode = 'DKAMJIT';
                        this.params.doctorName = 'KAMALJIT KAUR D/O HARBAN SINGH';
                    }
                    
                    this.generateMessage();
                },
                
                generateMessage() {
                    if (this.manualMode || !this.selectedType) return;
                    
                    const type = this.selectedType;
                    const p = this.params;
                    const now = new Date();
                    const datetime = now.getFullYear().toString() +
                        (now.getMonth() + 1).toString().padStart(2, '0') +
                        now.getDate().toString().padStart(2, '0') +
                        now.getHours().toString().padStart(2, '0') +
                        now.getMinutes().toString().padStart(2, '0') +
                        now.getSeconds().toString().padStart(2, '0');
                    
                    let segments = [];
                    
                    // MSH Segment
                    segments.push(`MSH|^~\\&|${p.sendingApp}|${p.sendingFacility}|${p.receivingApp}|${p.receivingApp}|${datetime}||ADT^${type}^ADT_${type}|${p.messageControlId}|T|2.4`);
                    
                    // EVN Segment
                    segments.push(`EVN|${type}|${datetime}||||`);
                    
                    // PID Segment
                    segments.push(`PID|1||${p.mrn}^^^^MR|${p.icPassport}|${p.patientName}||${p.dob}|${p.gender}||00|${p.address}|MYS|${p.phone}|||0|99||||||||||||${p.nationality}|`);
                    
                    // Build care team components for PV1
                    // PV1-7: Attending Doctor, PV1-8: Referring Doctor, PV1-9: Consulting Doctor
                    const attendingDoc = p.doctorCode ? `${p.doctorCode}^${p.doctorName}` : '';
                    const referringDoc = p.referringDoctorCode ? `${p.referringDoctorCode}^${p.referringDoctorName}` : '';
                    const consultingDoc = p.consultingDoctorCode ? `${p.consultingDoctorCode}^${p.consultingDoctorName}` : '';
                    
                    // PV1 Segment - varies by type
                    // Format: PV1|1|Class|Location|||WardInfo|AttDoc(7)|RefDoc(8)|ConsDoc(9)|...
                    let pv1 = `PV1|1|${p.patientClass}|${p.wardCode}^${p.bedCode}^${p.bedCode}|||${p.wardCode}^${p.wardName}|${attendingDoc}|${referringDoc}|${consultingDoc}|||||||||||||${p.visitNumber}|15^4^1^C000020027~15^1^99^|`;
                    
                    if (type === 'A03') {
                        // Add discharge datetime for A03
                        pv1 += `||||||||||||||||||||||${p.admitDateTime}|${p.dischargeDateTime}||||||||`;
                    } else if (type === 'A08') {
                        // Add diet type for A08
                        pv1 += `|||||||||||||||||||${p.dietType}|||||||||`;
                    } else {
                        pv1 += `||||||||||||||||||||||||||${p.admitDateTime}|`;
                    }
                    segments.push(pv1);
                    
                    // NK1 Segment (for A01, A08)
                    if (type === 'A01') {
                        segments.push('NK1|1||||||||||||||||||||||||||||||||||||||');
                    } else if (type === 'A08') {
                        segments.push(`NK1|1|^${p.nokName}|40^Son|${p.address}|60123456789||||||||||||||||||||||||||||||||||||111103149999`);
                        // RMI Segment for isolation
                        if (p.isolationCode) {
                            segments.push(`RMI|0|||${p.isolationCode}^${p.isolationDesc}||||||||||||||||||||||||||||||||||`);
                        }
                    }
                    
                    this.message = segments.join('\n');
                },

                formatMessage() {
                    this.message = this.message
                        .replace(/\r\n/g, '\n')
                        .replace(/\r/g, '\n')
                        .split('\n')
                        .filter(line => line.trim())
                        .join('\n');
                },

                clearMessage() {
                    this.message = '';
                    this.selectedType = null;
                    this.lastResponse = null;
                    this.manualMode = false;
                },

                async testConnection() {
                    this.testingConnection = true;
                    this.connectionStatus = null;
                    
                    try {
                        const response = await fetch('{{ route("adt.test-connection") }}');
                        const data = await response.json();
                        
                        this.connectionStatus = data.success ? 'online' : 'offline';
                        this.connectionMessage = data.message;
                    } catch (error) {
                        this.connectionStatus = 'offline';
                        this.connectionMessage = 'Failed to check connection: ' + error.message;
                    } finally {
                        this.testingConnection = false;
                    }
                },

                async sendMessage() {
                    if (!this.message.trim()) {
                        alert('Please enter an HL7 message to send');
                        return;
                    }

                    this.sending = true;
                    this.lastResponse = null;

                    try {
                        const response = await fetch('{{ route("adt.test.send") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                host: this.host,
                                port: parseInt(this.port),
                                message: this.message,
                            }),
                        });

                        const data = await response.json();
                        this.lastResponse = data;
                        
                    } catch (error) {
                        this.lastResponse = {
                            success: false,
                            message: 'Request failed',
                            error: error.message,
                        };
                    } finally {
                        this.sending = false;
                    }
                }
            };
        }
    </script>
</x-app-layout>



