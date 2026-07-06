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
                    <div x-show="activeTab === 'seed-patients'" x-transition
                        x-data="{ dietMode: 'random', allergyMode: 'random', hgtMode: 'random' }">
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

                            {{-- ---------- Clinical indicators (shown on the Ward Dashboard bed cards) ---------- --}}
                            <div class="mt-8 border-t border-gray-200 dark:border-gray-700 pt-6">
                                <div class="flex items-center gap-2 mb-1">
                                    <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                    </svg>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Clinical Indicators</h4>
                                </div>
                                <p class="text-xs text-gray-500 mb-4">These appear on the Ward Dashboard bed cards (nursing level, fall risk, isolation, diet, allergies, HGT). "Random mix" gives each patient a realistic weighted value.</p>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    {{-- Nursing Level --}}
                                    <div>
                                        <label for="nursing_level" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nursing Level of Care</label>
                                        <select id="nursing_level" name="nursing_level" class="mt-1 block w-full pl-3 pr-10 py-2 border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                            <option value="random" selected>Random mix</option>
                                            <option value="none">None</option>
                                            <option value="level_1">Level 1</option>
                                            <option value="level_2">Level 2</option>
                                            <option value="level_3">Level 3</option>
                                            <option value="level_4">Level 4</option>
                                        </select>
                                    </div>

                                    {{-- Fall Risk --}}
                                    <div>
                                        <label for="fall_risk" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fall Risk</label>
                                        <select id="fall_risk" name="fall_risk" class="mt-1 block w-full pl-3 pr-10 py-2 border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                            <option value="random" selected>Random mix</option>
                                            <option value="none">None</option>
                                            <option value="low">Low</option>
                                            <option value="moderate">Moderate</option>
                                            <option value="high">High</option>
                                            <option value="alert_active">FR Alert Active</option>
                                        </select>
                                    </div>

                                    {{-- Isolation --}}
                                    <div>
                                        <label for="isolation_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Isolation Precautions</label>
                                        <select id="isolation_type" name="isolation_type" class="mt-1 block w-full pl-3 pr-10 py-2 border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                            <option value="random" selected>Random mix</option>
                                            <option value="none">None</option>
                                            <option value="contact">Contact</option>
                                            <option value="droplet">Droplet</option>
                                            <option value="airborne">Airborne</option>
                                            <option value="protective">Protective</option>
                                            <option value="mrsa">MRSA</option>
                                            <option value="vre">VRE</option>
                                            <option value="cdiff">C.Diff</option>
                                            <option value="covid">COVID-19</option>
                                            <option value="tb">TB</option>
                                        </select>
                                    </div>

                                    {{-- Diet Orders --}}
                                    <div class="md:col-span-3">
                                        <label for="diet_mode" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Diet Orders</label>
                                        <select id="diet_mode" name="diet_mode" x-model="dietMode" class="mt-1 block w-full md:w-1/3 pl-3 pr-10 py-2 border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                            <option value="random" selected>Random mix (~60% get 1–2 diet orders)</option>
                                            <option value="none">No diet orders (regular diet)</option>
                                            <option value="custom">Choose specific diets…</option>
                                        </select>

                                        <div x-show="dietMode === 'custom'" x-transition class="mt-3 border border-gray-200 dark:border-gray-600 rounded-md p-3 max-h-52 overflow-y-auto">
                                            <p class="text-xs text-gray-500 mb-2">Every seeded patient will get all selected diet orders. NBM = Nil By Mouth (highlighted on dashboard).</p>
                                            <div class="grid grid-cols-2 md:grid-cols-4 gap-x-4 gap-y-1.5">
                                                @foreach($dietTypes as $diet)
                                                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                                        <input type="checkbox" name="diet_codes[]" value="{{ $diet->code }}"
                                                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                        <span class="truncate" title="{{ $diet->name }} ({{ $diet->code }})">{{ $diet->name }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Allergies --}}
                                    <div class="md:col-span-3 lg:col-span-1">
                                        <label for="allergy_mode" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Allergies</label>
                                        <select id="allergy_mode" name="allergy_mode" x-model="allergyMode" class="mt-1 block w-full pl-3 pr-10 py-2 border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                            <option value="random" selected>Random mix (~35% get 1–2 allergies)</option>
                                            <option value="none">None</option>
                                            <option value="custom">Custom list…</option>
                                        </select>
                                        <input x-show="allergyMode === 'custom'" x-transition type="text" name="custom_allergies"
                                            placeholder="e.g. Penicillin, Seafood"
                                            class="mt-2 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                        <p x-show="allergyMode === 'custom'" class="mt-1 text-xs text-gray-500">Comma-separated. Applied to every seeded patient.</p>
                                    </div>

                                    {{-- HGT / Glucose monitoring --}}
                                    <div class="md:col-span-3 lg:col-span-2">
                                        <label for="hgt_mode" class="block text-sm font-medium text-gray-700 dark:text-gray-300">HGT (Glucose) Monitoring</label>
                                        <div class="mt-1 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <select id="hgt_mode" name="hgt_mode" x-model="hgtMode" class="block w-full pl-3 pr-10 py-2 border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                                <option value="random" selected>Random (~30% monitored)</option>
                                                <option value="none">Disabled</option>
                                                <option value="enabled">Enabled for all</option>
                                            </select>
                                            <select name="hgt_frequency" x-show="hgtMode !== 'none'" x-transition class="block w-full pl-3 pr-10 py-2 border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                                <option value="random" selected>Random frequency</option>
                                                <option value="bd">BD (Twice Daily)</option>
                                                <option value="tds">TDS (Three Times Daily)</option>
                                                <option value="qid">QID (Four Times Daily)</option>
                                                <option value="pid">PRN (As Needed)</option>
                                            </select>
                                        </div>
                                        <label x-show="hgtMode !== 'none'" x-transition class="mt-2 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                            <input type="hidden" name="seed_sugar_reading" value="0">
                                            <input type="checkbox" name="seed_sugar_reading" value="1" checked
                                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                            Seed an initial glucose reading (shows as "Last HGT" on the dashboard)
                                        </label>
                                    </div>
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
