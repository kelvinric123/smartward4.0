<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-bold text-2xl text-gray-800 leading-tight">
                    {{ __('EKad Integration') }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">SEEKINK E-Ink device management - push patient info to E-Ink
                    displays</p>
            </div>
            <div class="flex items-center space-x-2">
                <span x-data="{ tokenValid: {{ ($config->exists && $config->isTokenValid()) ? 'true' : 'false' }} }"
                    :class="tokenValid ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'"
                    class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium">
                    <span class="w-2 h-2 rounded-full mr-2"
                        :class="tokenValid ? 'bg-green-500' : 'bg-yellow-500'"></span>
                    <span x-text="tokenValid ? 'Token Active' : 'Not Authenticated'"></span>
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-8" x-data="ekadManager()">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Configuration Settings -->
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-teal-100">
                <div class="p-6 border-b border-teal-100 bg-gradient-to-r from-teal-50 to-cyan-50">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="p-3 bg-teal-600 rounded-xl mr-4">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800">Configuration & Login</h3>
                                <p class="text-sm text-gray-500">SEEKINK API credentials and settings</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span x-show="token" class="inline-flex items-center text-green-600 text-sm">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Authenticated
                            </span>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">API Base URL</label>
                            <input type="text" x-model="baseUrl"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 font-mono text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                            <input type="text" x-model="username"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                            <input type="password" x-model="password"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Template ID</label>
                            <input type="text" x-model="templateId"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 font-mono text-sm">
                        </div>
                    </div>

                    <!-- Privacy & Auto-push Settings -->
                    <div class="flex flex-wrap items-center gap-6 mt-4 p-4 bg-gray-50 rounded-xl">
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="checkbox" x-model="autoPushEnabled"
                                class="form-checkbox h-5 w-5 text-teal-600 rounded">
                            <span class="ml-2 text-sm text-gray-700">Auto-push on admission</span>
                        </label>
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="checkbox" x-model="maskPatientName"
                                class="form-checkbox h-5 w-5 text-teal-600 rounded">
                            <span class="ml-2 text-sm text-gray-700">Mask patient name</span>
                        </label>
                        <div x-show="maskPatientName" x-transition class="flex items-center">
                            <label class="text-sm text-gray-700 mr-2">Style:</label>
                            <select x-model="maskStyle"
                                class="rounded-lg border-gray-300 text-sm focus:border-teal-500 focus:ring-teal-500">
                                <option value="partial">Partial (A*** G******)</option>
                                <option value="full">Full (**********)</option>
                                <option value="initials">Initials (A.G.)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-4 flex items-center justify-between">
                        <button @click="saveConfiguration()" :disabled="savingConfig"
                            class="inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 disabled:bg-gray-400 text-white font-medium rounded-lg transition-colors">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                            </svg>
                            <span x-text="savingConfig ? 'Saving...' : 'Save Config'"></span>
                        </button>
                        <button @click="testLogin()" :disabled="loggingIn"
                            class="inline-flex items-center px-4 py-2 bg-teal-600 hover:bg-teal-700 disabled:bg-teal-400 text-white font-medium rounded-lg transition-colors">
                            <svg x-show="!loggingIn" class="w-4 h-4 mr-2" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                            </svg>
                            <svg x-show="loggingIn" class="w-4 h-4 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <span x-text="loggingIn ? 'Connecting...' : 'Login & Get Token'"></span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Bed Mapping -->
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-indigo-100">
                <div class="p-6 border-b border-indigo-100 bg-gradient-to-r from-indigo-50 to-violet-50">
                    <div class="flex items-center">
                        <div class="p-3 bg-indigo-600 rounded-xl mr-4">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800">Bed Mapping</h3>
                            <p class="text-sm text-gray-500">Map beds to E-Ink device MAC addresses</p>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    <!-- Add New Mapping -->
                    <div class="mb-6 p-4 bg-gray-50 rounded-xl border border-gray-200">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">Add New Mapping</h4>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Ward</label>
                                <select x-model="selectedWardId" @change="selectedBedId = ''"
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Select Ward</option>
                                    @foreach($wards as $ward)
                                        <option value="{{ $ward->id }}">{{ $ward->ward_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Bed</label>
                                <select x-model="selectedBedId"
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Select Bed</option>
                                    <template x-for="bed in getBedsForWard(selectedWardId)" :key="bed.id">
                                        <option :value="bed.id" x-text="bed.bed_display_name || bed.bed_number">
                                        </option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">MAC Address</label>
                                <input type="text" x-model="newMacAddress"
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono text-sm"
                                    placeholder="D43D393CC02C">
                            </div>
                            <div class="flex items-end">
                                <button @click="addBedMapping()"
                                    :disabled="!selectedBedId || !newMacAddress || addingMapping"
                                    class="w-full inline-flex items-center justify-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:bg-gray-400 text-white font-medium rounded-lg transition-colors">
                                    <svg x-show="!addingMapping" class="w-4 h-4 mr-2" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 4v16m8-8H4" />
                                    </svg>
                                    <span x-text="addingMapping ? 'Adding...' : 'Add Mapping'"></span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Existing Mappings Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ward
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bed</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">MAC
                                        Address</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <template x-for="mapping in bedMappings" :key="mapping.id">
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-gray-900"
                                            x-text="mapping.bed?.ward?.ward_name || '-'"></td>
                                        <td class="px-4 py-3 text-sm text-gray-900"
                                            x-text="mapping.bed?.bed_display_name || mapping.bed?.bed_number || '-'">
                                        </td>
                                        <td class="px-4 py-3 text-sm font-mono text-gray-900"
                                            x-text="mapping.mac_address"></td>
                                        <td class="px-4 py-3">
                                            <span
                                                :class="mapping.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'"
                                                class="inline-flex px-2 py-1 text-xs font-medium rounded-full"
                                                x-text="mapping.is_active ? 'Active' : 'Inactive'"></span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <button @click="deleteBedMapping(mapping.id)"
                                                class="text-red-600 hover:text-red-800 text-sm font-medium">Delete</button>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="bedMappings.length === 0">
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">No bed mappings
                                        configured yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Push Patient Info -->
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-green-100">
                <div class="p-6 border-b border-green-100 bg-gradient-to-r from-green-50 to-emerald-50">
                    <div class="flex items-center">
                        <div class="p-3 bg-green-600 rounded-xl mr-4">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800">Manual Push Patient Info</h3>
                            <p class="text-sm text-gray-500">Send patient information to E-Ink display via template</p>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    <!-- Device Settings -->
                    <div class="mb-6 p-4 bg-gray-50 rounded-xl border border-gray-200">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">Device MAC Address</h4>
                        <div class="flex gap-2">
                            <input type="text" x-model="newMac"
                                class="flex-1 rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 font-mono text-sm"
                                placeholder="D43D393CC02C (without colons)" @keyup.enter="addMac()">
                            <button @click="addMac()"
                                class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors">
                                Add
                            </button>
                        </div>
                        <div x-show="macList.length > 0" class="mt-3">
                            <label class="block text-xs font-medium text-gray-500 mb-2">Target Devices:</label>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="(mac, index) in macList" :key="index">
                                    <span
                                        class="inline-flex items-center px-3 py-1 bg-green-100 text-green-800 text-sm font-mono rounded-full">
                                        <span x-text="mac"></span>
                                        <button @click="removeMac(index)"
                                            class="ml-2 text-green-600 hover:text-green-800">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </span>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Patient Information (order matches E-Ink API: bed no, MRN, patient_name, diet_type, doctor, nurse, anaesthetist) -->
                    <div class="mb-6">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">Patient Information</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Bed No</label>
                                <input type="text" x-model="bedNo"
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                                    placeholder="600">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">MRN</label>
                                <input type="text" x-model="mrn"
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 font-mono"
                                    placeholder="2400123456">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Patient Name *</label>
                                <input type="text" x-model="patientName"
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                                    placeholder="SIM HUI XIN">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Diet Type</label>
                                <input type="text" x-model="dietType"
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                                    placeholder="Regular Diet">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Doctor</label>
                                <input type="text" x-model="doctor"
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                                    placeholder="Dr. John Doe">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nurse</label>
                                <input type="text" x-model="nurse"
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                                    placeholder="Nurse Jane">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Anaesthetist</label>
                                <input type="text" x-model="anaesthetist"
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                                    placeholder="-">
                            </div>
                        </div>
                    </div>

                    <!-- Masking Preview -->
                    <div x-show="maskPatientName && patientName" x-transition
                        class="mb-6 p-4 bg-yellow-50 rounded-xl border border-yellow-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-yellow-800">Privacy Masking Preview:</p>
                                <p class="text-lg font-bold text-yellow-900 mt-1" x-text="getMaskedName(patientName)">
                                </p>
                            </div>
                            <span
                                class="inline-flex items-center px-2 py-1 bg-yellow-100 text-yellow-800 text-xs font-medium rounded">
                                <span x-text="maskStyle"></span> mode
                            </span>
                        </div>
                    </div>

                    <!-- Button Requirements Status -->
                    <div class="mb-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
                        <p class="text-xs font-medium text-gray-600 mb-2">Push Requirements:</p>
                        <div class="flex flex-wrap gap-3 text-xs">
                            <span :class="token ? 'text-green-600' : 'text-red-500'" class="inline-flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path x-show="token" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7" />
                                    <path x-show="!token" stroke-linecap="round" stroke-linejoin="round"
                                        stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                <span x-text="token ? 'Logged In' : 'Need Login'"></span>
                            </span>
                            <span :class="macList.length > 0 ? 'text-green-600' : 'text-red-500'"
                                class="inline-flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path x-show="macList.length > 0" stroke-linecap="round" stroke-linejoin="round"
                                        stroke-width="2" d="M5 13l4 4L19 7" />
                                    <path x-show="macList.length === 0" stroke-linecap="round" stroke-linejoin="round"
                                        stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                <span
                                    x-text="macList.length > 0 ? 'MAC Added (' + macList.length + ')' : 'Need MAC Address'"></span>
                            </span>
                            <span :class="patientName ? 'text-green-600' : 'text-red-500'"
                                class="inline-flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path x-show="patientName" stroke-linecap="round" stroke-linejoin="round"
                                        stroke-width="2" d="M5 13l4 4L19 7" />
                                    <path x-show="!patientName" stroke-linecap="round" stroke-linejoin="round"
                                        stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                <span x-text="patientName ? 'Patient Name Set' : 'Need Patient Name'"></span>
                            </span>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button @click="pushPatientInfo()"
                            :disabled="!token || macList.length === 0 || !patientName || pushingInfo"
                            class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600 disabled:from-gray-400 disabled:to-gray-500 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transition-all">
                            <svg x-show="!pushingInfo" class="w-5 h-5 mr-2" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                            <svg x-show="pushingInfo" class="w-5 h-5 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <span x-text="pushingInfo ? 'Pushing...' : 'Push Patient Info'"></span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Response Log -->
            <div x-show="responseLog.length > 0" x-transition
                class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-gray-200">
                <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-slate-50">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="p-3 bg-gray-600 rounded-xl mr-4">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800">Response Log</h3>
                                <p class="text-sm text-gray-500">API responses and debug information</p>
                            </div>
                        </div>
                        <button @click="responseLog = []" class="text-sm text-gray-500 hover:text-gray-700">Clear
                            Log</button>
                    </div>
                </div>
                <div class="p-6 max-h-96 overflow-y-auto">
                    <div class="space-y-3">
                        <template x-for="(log, index) in responseLog" :key="index">
                            <div :class="log.success ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'"
                                class="border rounded-lg p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-medium text-gray-500" x-text="log.time"></span>
                                    <span :class="log.success ? 'text-green-600' : 'text-red-600'"
                                        class="text-xs font-bold" x-text="log.action"></span>
                                </div>
                                <p :class="log.success ? 'text-green-700' : 'text-red-700'" class="text-sm font-medium"
                                    x-text="log.message"></p>
                                <pre x-show="log.data"
                                    class="mt-2 text-xs text-gray-600 bg-white/50 rounded p-2 overflow-x-auto"
                                    x-text="JSON.stringify(log.data, null, 2)"></pre>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Activity Logs (Server-side EKad logs) -->
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-purple-100">
                <div class="p-6 border-b border-purple-100 bg-gradient-to-r from-purple-50 to-fuchsia-50">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="p-3 bg-purple-600 rounded-xl mr-4">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800">Activity Logs</h3>
                                <p class="text-sm text-gray-500">Auto-push activity from PatientObserver (patient
                                    admissions/updates)</p>
                            </div>
                        </div>
                        <button @click="loadActivityLogs()" :disabled="loadingLogs"
                            class="inline-flex items-center px-3 py-1.5 bg-purple-600 hover:bg-purple-700 disabled:bg-purple-400 text-white text-sm font-medium rounded-lg transition-colors">
                            <svg x-show="!loadingLogs" class="w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <svg x-show="loadingLogs" class="w-4 h-4 mr-1 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <span x-text="loadingLogs ? 'Loading...' : 'Refresh Logs'"></span>
                        </button>
                    </div>
                </div>
                <div class="p-6 max-h-96 overflow-y-auto">
                    <div x-show="activityLogs.length === 0" class="text-center text-gray-500 py-8">
                        <p>No activity logs yet. Click "Refresh Logs" or admit a patient with a mapped bed.</p>
                        <p class="text-xs mt-2">Make sure: 1) Login done 2) Auto-push enabled 3) Bed mapped to MAC 4)
                            Patient admitted</p>
                    </div>
                    <div class="space-y-2">
                        <template x-for="(log, index) in activityLogs" :key="index">
                            <div :class="{
                                'bg-blue-50 border-blue-200': log.level === 'DEBUG',
                                'bg-green-50 border-green-200': log.level === 'INFO',
                                'bg-yellow-50 border-yellow-200': log.level === 'WARNING',
                                'bg-red-50 border-red-200': log.level === 'ERROR'
                            }" class="border rounded-lg p-3">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-xs font-medium text-gray-500" x-text="log.time"></span>
                                    <span :class="{
                                        'text-blue-600': log.level === 'DEBUG',
                                        'text-green-600': log.level === 'INFO',
                                        'text-yellow-600': log.level === 'WARNING',
                                        'text-red-600': log.level === 'ERROR'
                                    }" class="text-xs font-bold" x-text="log.level"></span>
                                </div>
                                <p class="text-sm text-gray-700" x-text="log.message"></p>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- API Response Logs (Database-stored) -->
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-blue-100">
                <div class="p-6 border-b border-blue-100 bg-gradient-to-r from-blue-50 to-sky-50">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="p-3 bg-blue-600 rounded-xl mr-4">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800">API Response Logs</h3>
                                <p class="text-sm text-gray-500">Database-stored API requests and responses (all
                                    sources)</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <select x-model="apiLogsFilter" @change="loadApiResponseLogs()"
                                class="text-sm rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                                <option value="">All</option>
                                <option value="success">Success Only</option>
                                <option value="failed">Failed Only</option>
                            </select>
                            <button @click="loadApiResponseLogs()" :disabled="loadingApiLogs"
                                class="inline-flex items-center px-3 py-1.5 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg transition-colors">
                                <svg x-show="!loadingApiLogs" class="w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                <svg x-show="loadingApiLogs" class="w-4 h-4 mr-1 animate-spin" fill="none"
                                    viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <span x-text="loadingApiLogs ? 'Loading...' : 'Refresh'"></span>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    <div x-show="apiResponseLogs.length === 0 && !loadingApiLogs"
                        class="text-center text-gray-500 py-8">
                        <p>No API response logs yet.</p>
                        <p class="text-xs mt-2">Logs are automatically created when patient/bed data changes trigger
                            EKAD API calls.</p>
                    </div>
                    <div x-show="loadingApiLogs && apiResponseLogs.length === 0" class="text-center text-gray-500 py-8">
                        <svg class="w-8 h-8 mx-auto animate-spin text-blue-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                            </circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <p class="mt-2">Loading logs...</p>
                    </div>
                    <div x-show="apiResponseLogs.length > 0" class="space-y-3 max-h-96 overflow-y-auto">
                        <template x-for="(log, index) in apiResponseLogs" :key="log.id">
                            <div :class="log.success ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'"
                                class="border rounded-lg p-4">
                                <div class="flex items-start justify-between mb-2">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-2 mb-1">
                                            <span
                                                :class="log.success ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path x-show="log.success" stroke-linecap="round"
                                                        stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                    <path x-show="!log.success" stroke-linecap="round"
                                                        stroke-linejoin="round" stroke-width="2"
                                                        d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                                <span x-text="log.success ? 'SUCCESS' : 'FAILED'"></span>
                                            </span>
                                            <span class="text-xs text-gray-500"
                                                x-text="new Date(log.created_at).toLocaleString()"></span>
                                            <span
                                                class="inline-flex items-center px-2 py-0.5 bg-gray-100 text-gray-700 rounded text-xs">
                                                <span x-text="log.triggered_by"></span>
                                            </span>
                                        </div>
                                        <div class="text-sm text-gray-700 space-y-1">
                                            <div class="flex items-center space-x-2">
                                                <span class="font-medium">MAC:</span>
                                                <span class="font-mono text-xs" x-text="log.mac_address || '-'"></span>
                                            </div>
                                            <div x-show="log.bed" class="flex items-center space-x-2">
                                                <span class="font-medium">Bed:</span>
                                                <span class="text-xs" x-text="log.bed?.bed_number || '-'"></span>
                                            </div>
                                            <div x-show="log.patient" class="flex items-center space-x-2">
                                                <span class="font-medium">Patient:</span>
                                                <span class="text-xs" x-text="log.patient?.name || '-'"></span>
                                            </div>
                                            <div x-show="log.response_code" class="flex items-center space-x-2">
                                                <span class="font-medium">HTTP Code:</span>
                                                <span class="text-xs font-mono" x-text="log.response_code"></span>
                                            </div>
                                            <div x-show="log.error_message" class="flex items-start space-x-2">
                                                <span class="font-medium text-red-600">Error:</span>
                                                <span class="text-xs text-red-700" x-text="log.error_message"></span>
                                            </div>
                                        </div>
                                    </div>
                                    <button @click="log.expanded = !log.expanded"
                                        class="ml-4 text-blue-600 hover:text-blue-800 text-xs font-medium">
                                        <span x-text="log.expanded ? 'Hide Details' : 'View Details'"></span>
                                    </button>
                                </div>
                                <div x-show="log.expanded" x-transition class="mt-3 space-y-2">
                                    <div x-show="log.request_payload">
                                        <p class="text-xs font-semibold text-gray-700 mb-1">Request Payload:</p>
                                        <pre class="text-xs text-gray-600 bg-white/70 rounded p-2 overflow-x-auto"
                                            x-text="JSON.stringify(log.request_payload, null, 2)"></pre>
                                    </div>
                                    <div x-show="log.response_payload">
                                        <p class="text-xs font-semibold text-gray-700 mb-1">Response Payload:</p>
                                        <pre class="text-xs text-gray-600 bg-white/70 rounded p-2 overflow-x-auto"
                                            x-text="JSON.stringify(log.response_payload, null, 2)"></pre>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                    <!-- Pagination -->
                    <div x-show="apiLogsPagination.last_page > 1"
                        class="mt-4 flex items-center justify-between border-t border-gray-200 pt-4">
                        <div class="text-sm text-gray-500">
                            Showing <span x-text="apiLogsPagination.from || 0"></span> to <span
                                x-text="apiLogsPagination.to || 0"></span> of <span
                                x-text="apiLogsPagination.total || 0"></span> logs
                        </div>
                        <div class="flex space-x-2">
                            <button @click="loadApiResponseLogs(apiLogsPagination.current_page - 1)"
                                :disabled="apiLogsPagination.current_page <= 1"
                                class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                Previous
                            </button>
                            <span class="px-3 py-1 text-sm text-gray-700">
                                Page <span x-text="apiLogsPagination.current_page"></span> of <span
                                    x-text="apiLogsPagination.last_page"></span>
                            </span>
                            <button @click="loadApiResponseLogs(apiLogsPagination.current_page + 1)"
                                :disabled="apiLogsPagination.current_page >= apiLogsPagination.last_page"
                                class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                Next
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function ekadManager() {
            return {
                // Configuration
                baseUrl: @json($config->base_url ?? 'http://iot.seekink.com/cloud/prod-api'),
                username: @json($config->username ?? ''),
                password: @json($config->password ?? ''),
                templateId: @json($config->template_id ?? ''),
                token: @json($config->bearer_token ?? ''),
                autoPushEnabled: @json($config->auto_push_enabled ?? false),
                maskPatientName: @json($config->mask_patient_name ?? false),
                maskStyle: @json($config->mask_style ?? 'partial'),
                loggingIn: false,
                savingConfig: false,

                // Bed Mapping
                wards: @json($wards),
                bedMappings: @json($bedMappings),
                selectedWardId: '',
                selectedBedId: '',
                newMacAddress: '',
                addingMapping: false,

                // Push Patient Info
                macList: [],
                newMac: '',
                mrn: '',
                patientName: '',
                bedNo: '',
                dietType: 'Regular Diet',
                doctor: '',
                nurse: '',
                anaesthetist: '-',
                pushingInfo: false,

                // Response Log
                responseLog: [],

                // Activity Logs (server-side)
                activityLogs: [],
                loadingLogs: false,

                // API Response Logs (database-stored)
                apiResponseLogs: [],
                loadingApiLogs: false,
                apiLogsFilter: '',
                apiLogsPagination: {
                    current_page: 1,
                    last_page: 1,
                    from: 0,
                    to: 0,
                    total: 0
                },

                init() {
                    // Load API response logs on page load
                    this.loadApiResponseLogs();
                },

                addLog(action, success, message, data = null) {
                    const now = new Date();
                    this.responseLog.unshift({
                        time: now.toLocaleTimeString(),
                        action: action,
                        success: success,
                        message: message,
                        data: data
                    });
                    if (this.responseLog.length > 20) {
                        this.responseLog.pop();
                    }
                },

                getBedsForWard(wardId) {
                    if (!wardId) return [];
                    const ward = this.wards.find(w => w.id == wardId);
                    return ward ? ward.beds : [];
                },

                getMaskedName(name) {
                    if (!name || !this.maskPatientName) return name;
                    const parts = name.split(' ');
                    switch (this.maskStyle) {
                        case 'partial':
                            return parts.map(p => p.length > 1 ? p[0] + '*'.repeat(p.length - 1) : p).join(' ');
                        case 'full':
                            return '*'.repeat(name.length);
                        case 'initials':
                            return parts.map(p => p[0].toUpperCase() + '.').join('');
                        default:
                            return name;
                    }
                },

                addMac() {
                    if (this.newMac && !this.macList.includes(this.newMac)) {
                        const cleanMac = this.newMac.replace(/:/g, '').toUpperCase();
                        this.macList.push(cleanMac);
                        this.newMac = '';
                    }
                },

                removeMac(index) {
                    this.macList.splice(index, 1);
                },

                async saveConfiguration() {
                    this.savingConfig = true;
                    try {
                        const response = await fetch('{{ route("ekad.configuration.save") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                base_url: this.baseUrl,
                                username: this.username,
                                password: this.password,
                                template_id: this.templateId,
                                auto_push_enabled: this.autoPushEnabled,
                                mask_patient_name: this.maskPatientName,
                                mask_style: this.maskStyle
                            })
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.addLog('SAVE CONFIG', true, data.message);
                        } else {
                            this.addLog('SAVE CONFIG', false, data.message, data);
                        }
                    } catch (error) {
                        this.addLog('SAVE CONFIG', false, 'Request failed: ' + error.message);
                    }
                    this.savingConfig = false;
                },

                async testLogin() {
                    this.loggingIn = true;
                    try {
                        const response = await fetch('{{ route("ekad.login") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                username: this.username,
                                password: this.password,
                                base_url: this.baseUrl
                            })
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.token = data.token;
                            this.addLog('LOGIN', true, data.message);
                        } else {
                            this.token = '';
                            this.addLog('LOGIN', false, data.message, data);
                        }
                    } catch (error) {
                        this.addLog('LOGIN', false, 'Request failed: ' + error.message);
                    }
                    this.loggingIn = false;
                },

                async addBedMapping() {
                    this.addingMapping = true;
                    try {
                        const response = await fetch('{{ route("ekad.bed-mappings.store") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                bed_id: this.selectedBedId,
                                mac_address: this.newMacAddress
                            })
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.bedMappings.unshift(data.mapping);
                            this.selectedWardId = '';
                            this.selectedBedId = '';
                            this.newMacAddress = '';
                            this.addLog('ADD MAPPING', true, data.message);
                        } else {
                            this.addLog('ADD MAPPING', false, data.message, data);
                        }
                    } catch (error) {
                        this.addLog('ADD MAPPING', false, 'Request failed: ' + error.message);
                    }
                    this.addingMapping = false;
                },

                async deleteBedMapping(id) {
                    if (!confirm('Are you sure you want to delete this mapping?')) return;
                    try {
                        const response = await fetch(`/ekad/bed-mappings/${id}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            }
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.bedMappings = this.bedMappings.filter(m => m.id !== id);
                            this.addLog('DELETE MAPPING', true, data.message);
                        } else {
                            this.addLog('DELETE MAPPING', false, data.message, data);
                        }
                    } catch (error) {
                        this.addLog('DELETE MAPPING', false, 'Request failed: ' + error.message);
                    }
                },

                async pushPatientInfo() {
                    this.pushingInfo = true;
                    try {
                        const response = await fetch('{{ route("ekad.push") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                token: this.token,
                                base_url: this.baseUrl,
                                template_id: this.templateId,
                                mac_list: this.macList,
                                mrn: this.mrn,
                                patient_name: this.patientName,
                                bed_no: this.bedNo,
                                diet_type: this.dietType,
                                doctor: this.doctor,
                                nurse: this.nurse,
                                anaesthetist: this.anaesthetist,
                                apply_masking: this.maskPatientName
                            })
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.addLog('PUSH INFO', true, data.message, data.payload);
                        } else {
                            this.addLog('PUSH INFO', false, data.message, data);
                        }
                    } catch (error) {
                        this.addLog('PUSH INFO', false, 'Request failed: ' + error.message);
                    }
                    this.pushingInfo = false;
                },

                async loadActivityLogs() {
                    this.loadingLogs = true;
                    try {
                        const response = await fetch('{{ route("ekad.activity-logs") }}', {
                            method: 'GET',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            }
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.activityLogs = data.logs;
                            this.addLog('ACTIVITY LOGS', true, `Loaded ${data.logs.length} log entries`);
                        } else {
                            this.addLog('ACTIVITY LOGS', false, 'Failed to load logs');
                        }
                    } catch (error) {
                        this.addLog('ACTIVITY LOGS', false, 'Request failed: ' + error.message);
                    }
                    this.loadingLogs = false;
                },

                async loadApiResponseLogs(page = 1) {
                    this.loadingApiLogs = true;
                    try {
                        let url = '{{ route("ekad.response-logs") }}?page=' + page + '&per_page=20';
                        
                        // Add filter if selected
                        if (this.apiLogsFilter === 'success') {
                            url += '&success=1';
                        } else if (this.apiLogsFilter === 'failed') {
                            url += '&success=0';
                        }

                        const response = await fetch(url, {
                            method: 'GET',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            }
                        });
                        const data = await response.json();
                        if (data.success) {
                            // Add expanded property to each log for toggling details
                            this.apiResponseLogs = data.logs.data.map(log => ({
                                ...log,
                                expanded: false
                            }));
                            
                            // Update pagination info
                            this.apiLogsPagination = {
                                current_page: data.logs.current_page,
                                last_page: data.logs.last_page,
                                from: data.logs.from,
                                to: data.logs.to,
                                total: data.logs.total
                            };
                        } else {
                            this.addLog('API LOGS', false, 'Failed to load API response logs');
                        }
                    } catch (error) {
                        this.addLog('API LOGS', false, 'Request failed: ' + error.message);
                    }
                    this.loadingApiLogs = false;
                }
            }
        }
    </script>
</x-app-layout>