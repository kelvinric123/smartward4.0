<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Demo Integration') }}
        </h2>
    </x-slot>

    <div class="py-12" x-data="{ activeTab: 'seed-patients' }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="border-b border-gray-200 dark:border-gray-700">
                    <nav class="-mb-px flex" aria-label="Tabs">
                        <button @click="activeTab = 'seed-patients'"
                                :class="{ 'border-indigo-500 text-indigo-600 dark:text-indigo-400': activeTab === 'seed-patients', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'seed-patients' }"
                                class="w-1/3 py-4 px-1 text-center border-b-2 font-medium text-sm">
                            1) Create Demo Patients
                        </button>
                        <button @click="activeTab = 'seed-vitals'"
                                :class="{ 'border-indigo-500 text-indigo-600 dark:text-indigo-400': activeTab === 'seed-vitals', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'seed-vitals' }"
                                class="w-1/3 py-4 px-1 text-center border-b-2 font-medium text-sm">
                            2) Create Demo Vital Signs
                        </button>
                        <button @click="activeTab = 'seed-infusions'"
                                :class="{ 'border-indigo-500 text-indigo-600 dark:text-indigo-400': activeTab === 'seed-infusions', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'seed-infusions' }"
                                class="w-1/3 py-4 px-1 text-center border-b-2 font-medium text-sm">
                            3) Create Demo Infusion
                        </button>
                    </nav>
                </div>

                <div class="p-6">
                    <!-- Tab 1: Seed Patients -->
                    <div x-show="activeTab === 'seed-patients'" x-transition>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Admit Demo Patients to a Ward</h3>
                        <form action="{{ route('integration.demo.seed-patients') }}" method="POST">
                            @csrf
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="ward_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Select Ward</label>
                                    <select id="ward_id" name="ward_id" required class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                        <option value="">-- Choose a Ward --</option>
                                        @foreach($wards as $ward)
                                            <option value="{{ $ward->id }}">{{ $ward->ward_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="number_of_beds" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Number of Beds to Admit</label>
                                    <input type="number" id="number_of_beds" name="number_of_beds" min="1" max="50" value="1" required class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                    <p class="mt-2 text-sm text-gray-500">Will find unoccupied beds and seed patients. Max 50.</p>
                                </div>
                            </div>
                            
                            <div class="mt-6">
                                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    Seed Patients
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Tab 2: Seed Vital Signs -->
                    <div x-show="activeTab === 'seed-vitals'" x-cloak>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Generate Demo Vital Signs for Admitted Patient</h3>
                        <form action="{{ route('integration.demo.seed-vital-signs') }}" method="POST">
                            @csrf
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="col-span-1 md:col-span-3">
                                    <label for="patient_id_vitals" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Select Admitted Patient</label>
                                    <select id="patient_id_vitals" name="patient_id" required class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                        <option value="">-- Choose a Patient --</option>
                                        @foreach($admittedPatients as $patient)
                                            <option value="{{ $patient->id }}">
                                                {{ $patient->name }} 
                                                (Ward: {{ $patient->ward->ward_name ?? 'N/A' }}, Bed: {{ $patient->bed->bed_number ?? $patient->bed_number ?? 'N/A' }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="readings_per_day" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Readings per day</label>
                                    <input type="number" id="readings_per_day" name="readings_per_day" min="1" max="24" value="4" required class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                </div>

                                <div>
                                    <label for="past_days" class="block text-sm font-medium text-gray-700 dark:text-gray-300">For the past how many days</label>
                                    <input type="number" id="past_days" name="past_days" min="0" max="30" value="3" required class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                    <p class="mt-2 text-xs text-gray-500">0 means today only. Max 30 days.</p>
                                </div>
                            </div>
                            
                            <div class="mt-6">
                                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    Seed Vital Signs
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Tab 3: Seed Infusion -->
                    <div x-show="activeTab === 'seed-infusions'" x-cloak>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Generate Demo Infusion for Admitted Patient</h3>
                        <form action="{{ route('integration.demo.seed-infusion') }}" method="POST">
                            @csrf
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="col-span-1 md:col-span-2">
                                    <label for="patient_id_infusion" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Select Admitted Patient</label>
                                    <select id="patient_id_infusion" name="patient_id" required class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                        <option value="">-- Choose a Patient --</option>
                                        @foreach($admittedPatients as $patient)
                                            <option value="{{ $patient->id }}">
                                                {{ $patient->name }} 
                                                (Ward: {{ $patient->ward->ward_name ?? 'N/A' }}, Bed: {{ $patient->bed->bed_number ?? $patient->bed_number ?? 'N/A' }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mt-6">
                                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    Seed Infusion
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
