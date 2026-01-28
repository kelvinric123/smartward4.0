<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-bold text-2xl text-gray-800 leading-tight">
                    {{ __('Ward Schedule') }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">Visualise beds by shift for the selected ward</p>
            </div>
            <div class="flex items-center space-x-3">
                <span class="px-3 py-1 rounded-full text-sm font-semibold bg-blue-100 text-blue-800">
                    Columns: Dates (base {{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }})
                </span>
                <span class="px-3 py-1 rounded-full text-sm font-semibold bg-cyan-100 text-cyan-800">
                    Rows: Bed → Shift (AM / PM / ON)
                </span>

                @if(!($isLockedToNurse ?? false))
                    @if(isset($individualMode) && $individualMode)
                        <a href="{{ route('ward.schedule') }}"
                            class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-xl font-semibold shadow-sm transition-all text-sm">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            View All Staff
                        </a>
                    @else
                        <a href="{{ route('ward.schedule.individual') }}"
                            class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-xl font-semibold shadow-sm transition-all text-sm">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            View Individual
                        </a>
                    @endif

                    <button type="button" onclick="openDownloadModal()"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-xl font-semibold shadow-sm transition-all text-sm">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Template
                    </button>

                    <button type="button" onclick="openUploadModal()"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-xl font-semibold shadow-sm transition-all text-sm">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        Upload
                    </button>

                    <button type="button" onclick="openShiftSettings()"
                        class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-purple-500 to-indigo-600 hover:from-purple-600 hover:to-indigo-700 text-white rounded-xl font-semibold shadow-sm transition-all text-sm">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Shift Setting
                    </button>

                    <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-print-modal'))"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-xl font-semibold shadow-sm transition-all text-sm">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        Print
                    </button>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-lg rounded-2xl border border-blue-100" x-data="{
                    selected: [],
                    assignModal: false,
                    sectionAssignModal: false,
                    selectedNurse: '{{ $nurses->first()->id ?? '' }}',
                    assignError: '',
                    assignLoading: false,
                    hasNurses: {{ $nurses->count() ? 'true' : 'false' }},
                    patientModal: { open: false, patientId: null },
                    printModal: false,
                    printStartDate: '{{ $selectedDate }}',
                    printEndDate: '{{ \Carbon\Carbon::parse($selectedDate)->addDays(6)->toDateString() }}',
                    
                    // Section Assign Data
                    beds: @js($beds),
                    existingAssignments: @js($assignments),
                    nurses: @js($nurses),
                    sectionAssignSection: '',
                    sectionAssignNurse: '',
                    sectionAssignShifts: [], // Default none selected
                    sectionAssignExcludedBeds: [],

                    submitFilters() {
                        if (this.$refs.filterForm) {
                            this.$refs.filterForm.submit();
                        }
                    },
                    toggle(cell) {
                        const idx = this.selected.findIndex(c => c.key === cell.key);
                        if (idx === -1) {
                            this.selected.push(cell);
                        } else {
                            this.selected.splice(idx, 1);
                        }
                    },
                    isSelected(key) {
                        return this.selected.some(c => c.key === key);
                    },
                    clearSelection() {
                        this.selected = [];
                    },
                    openPatientDetails(id) {
                        this.patientModal.open = true;
                        this.patientModal.patientId = id;
                    },
                    closePatientDetails() {
                        this.patientModal.open = false;
                        this.patientModal.patientId = null;
                    },
                    assignNurses() {
                        this.assignError = '';

                        if (!this.selected.length) {
                            this.assignError = 'Select at least one shift.';
                            return;
                        }

                        if (!this.selectedNurse) {
                            this.assignError = 'Choose a nurse to assign.';
                            return;
                        }

                        const assignments = this.selected.map(cell => ({
                            bed_id: cell.bed_id,
                            date: cell.date,
                            shift: cell.shift,
                        }));

                        this.$refs.assignmentsField.value = JSON.stringify(assignments);
                        this.$refs.nurseField.value = this.selectedNurse;
                        this.assignLoading = true;
                        this.$refs.assignForm.submit();
                    },
                    
                    // Section Assign Methods
                    getSectionBeds() {
                        if (!this.sectionAssignSection) return [];
                        return this.beds.filter(b => String(b.section) === String(this.sectionAssignSection));
                    },
                    isExcluded(bedId) {
                        return this.sectionAssignExcludedBeds.includes(bedId);
                    },
                    toggleExclusion(bedId) {
                        const index = this.sectionAssignExcludedBeds.indexOf(bedId);
                        if (index === -1) {
                            this.sectionAssignExcludedBeds.push(bedId);
                        } else {
                            this.sectionAssignExcludedBeds.splice(index, 1);
                        }
                    },
                    toggleAllExclusion(shouldInclude) {
                         const sectionBeds = this.getSectionBeds();
                         if (shouldInclude) {
                             // Remove all section beds from exclusion list
                             this.sectionAssignExcludedBeds = this.sectionAssignExcludedBeds.filter(id => !sectionBeds.some(b => b.id === id));
                         } else {
                             // Add all section beds to exclusion list (if not already there)
                             sectionBeds.forEach(bed => {
                                 if (!this.sectionAssignExcludedBeds.includes(bed.id)) {
                                     this.sectionAssignExcludedBeds.push(bed.id);
                                 }
                             });
                         }
                    },
                    areAllIncluded() {
                        const sectionBeds = this.getSectionBeds();
                        if (!sectionBeds.length) return false;
                        return sectionBeds.every(b => !this.sectionAssignExcludedBeds.includes(b.id));
                    },
                    getNurseName(bedId, shift) {
                        const key = bedId + '|{{ $selectedDate }}|' + shift;
                        const assignment = this.existingAssignments[key];
                        return assignment ? assignment.nurse_name : '';
                    },
                    getNurseLabel(nurseId) {
                        const nurse = this.nurses.find(n => n.id == nurseId);
                        return nurse ? nurse.name : 'Unknown';
                    },
                    getTaggingNurseName(nurseId) {
                        if (!nurseId) return '-';
                        const nurse = this.nurses.find(n => n.id == nurseId);
                        if (!nurse || !nurse.is_tagging || !nurse.tagging_nurses || !nurse.tagging_nurses.length) return '-';
                        return nurse.tagging_nurses.map(tn => tn.name).join(', ');
                    },
                    submitSectionAssign() {
                        this.assignError = '';
                        // get section beds that are NOT excluded
                        const beds = this.getSectionBeds().filter(b => !this.isExcluded(b.id));
                        
                        if (!beds.length) {
                             if (this.sectionAssignExcludedBeds.length > 0) {
                                 this.assignError = 'All beds in this section are excluded. Uncheck some to assign.';
                             } else {
                                this.assignError = 'No beds in this section.';
                             }
                            return;
                        }
                        
                        if (!this.sectionAssignShifts.length) {
                            this.assignError = 'Select at least one shift.';
                            return;
                        }

                        // Generate assignments
                        const assignments = [];
                        beds.forEach(bed => {
                            this.sectionAssignShifts.forEach(shift => {
                                assignments.push({
                                    bed_id: bed.id,
                                    date: '{{ $selectedDate }}',
                                    shift: shift
                                });
                            });
                        });

                        this.$refs.assignmentsField.value = JSON.stringify(assignments);
                        this.$refs.nurseField.value = this.sectionAssignNurse;
                        this.assignLoading = true;
                        this.$refs.assignForm.submit();
                    },
                    openPrintModal() {
                        this.printModal = true;
                    },
                    submitPrint() {
                        const url = '{{ route('ward.schedule.print') }}' + 
                            '?ward_id=' + '{{ $selectedWardId }}' + 
                            '&start_date=' + this.printStartDate + 
                            '&end_date=' + this.printEndDate;
                        window.open(url, '_blank');
                        this.printModal = false;
                    }
                 }" x-on:open-print-modal.window="openPrintModal()"
                x-effect="document.body.style.overflow = (assignModal || sectionAssignModal || printModal) ? 'hidden' : ''">
                <div class="p-6 space-y-6">
                    @if (session('success'))
                        <div
                            class="flex items-start gap-3 p-4 rounded-xl bg-green-50 border border-green-200 text-green-800">
                            <svg class="w-5 h-5 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <div>
                                <p class="font-semibold">{{ session('success') }}</p>
                            </div>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="flex items-start gap-3 p-4 rounded-xl bg-red-50 border border-red-200 text-red-800">
                            <svg class="w-5 h-5 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            <div>
                                <p class="font-semibold">{{ session('error') }}</p>
                            </div>
                        </div>
                    @endif

                    <form x-ref="assignForm" method="POST" action="{{ route('ward.schedule.assign') }}" class="hidden">
                        @csrf
                        <input type="hidden" name="ward_id" value="{{ $selectedWardId }}">
                        <input type="hidden" name="date" value="{{ $selectedDate }}">
                        <input type="hidden" name="nurse_id" x-ref="nurseField">
                        <input type="hidden" name="assignments" x-ref="assignmentsField">
                    </form>
                    <form x-ref="filterForm" method="GET"
                        action="{{ isset($individualMode) && $individualMode ? route('ward.schedule.individual') : route('ward.schedule') }}"
                        class="grid gap-4 md:gap-6 md:grid-cols-3 items-end">
                        <div>
                            <label for="ward_id" class="block text-sm font-semibold text-gray-700 mb-2">Ward</label>
                            <select id="ward_id" name="ward_id"
                                class="w-full rounded-xl border-gray-200 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                @change="submitFilters()">
                                @forelse($wards as $ward)
                                    <option value="{{ $ward->id }}" {{ $selectedWardId == $ward->id ? 'selected' : '' }}>
                                        {{ $ward->ward_name }} ({{ $ward->ward_code }})
                                    </option>
                                @empty
                                    <option disabled>No active wards found</option>
                                @endforelse
                            </select>
                        </div>
                        <div>
                            <label for="date" class="block text-sm font-semibold text-gray-700 mb-2">Base date</label>
                            <input type="date" id="date" name="date" value="{{ $selectedDate }}"
                                class="w-full rounded-xl border-gray-200 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                @change="submitFilters()">
                        </div>
                        <div>
                            @if(isset($individualMode) && $individualMode)
                                <label for="nurse_id" class="block text-sm font-semibold text-gray-700 mb-2">Select
                                    Nurse</label>
                                @if($isLockedToNurse ?? false)
                                    <div
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-gray-700 shadow-sm">
                                        {{ $nurses->firstWhere('id', $selectedNurseId)->name ?? 'Unknown' }}
                                    </div>
                                    <input type="hidden" name="nurse_id" value="{{ $selectedNurseId }}">
                                @else
                                    <select id="nurse_id" name="nurse_id"
                                        class="w-full rounded-xl border-gray-200 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                        @change="submitFilters()">
                                        <option value="">-- All Assignments --</option>
                                        @forelse($nurses as $nurse)
                                            <option value="{{ $nurse->id }}" {{ (isset($selectedNurseId) && $selectedNurseId == $nurse->id) ? 'selected' : '' }}>
                                                {{ $nurse->name }}
                                            </option>
                                        @empty
                                            <option disabled>No nurses found</option>
                                        @endforelse
                                    </select>
                                @endif
                            @endif
                        </div>
                    </form>

                    <div
                        class="flex items-center justify-between gap-3 sticky top-0 z-30 bg-white/95 backdrop-blur px-4 py-3 -mx-4 border border-blue-100 rounded-xl">
                        @if(!($isLockedToNurse ?? false))
                            <div class="flex items-center gap-2">
                                <span
                                    class="px-3 py-1 rounded-full bg-blue-50 text-blue-700 text-xs font-semibold border border-blue-100">
                                    Selected shifts: <span x-text="selected.length"></span>
                                </span>
                                <button type="button" class="text-xs text-gray-500 hover:text-gray-700 underline"
                                    @click="clearSelection()" x-show="selected.length">Clear</button>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" @click="sectionAssignModal = true"
                                    class="inline-flex items-center px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-semibold shadow-sm transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                    </svg>
                                    Section Assign
                                </button>
                                <button type="button" @click="assignModal = true" :disabled="!selected.length"
                                    class="inline-flex items-center px-4 py-2 rounded-lg font-semibold shadow-sm transition-all"
                                    :class="selected.length ? 'bg-green-600 hover:bg-green-700 text-white' : 'bg-gray-200 text-gray-500 cursor-not-allowed'">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    Assign Nurses
                                </button>
                            </div>
                        @else
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold text-gray-700">Schedule View</span>
                            </div>
                        @endif
                    </div>

                    <div class="mt-4">
                        @if(!$selectedWard)
                            <div
                                class="flex items-center p-6 bg-gradient-to-r from-amber-50 to-yellow-50 border border-amber-200 rounded-xl text-amber-800">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 9v2m0 4h.01M5.455 19h13.09c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.723 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <div>
                                    <p class="font-semibold">No active wards available</p>
                                    <p class="text-sm text-amber-700 mt-1">Add a ward first to view schedules.</p>
                                </div>
                            </div>
                        @else
                            <div class="flex flex-wrap items-center gap-3 justify-between">
                                <div>
                                    <p class="text-sm text-gray-500">Selected ward</p>
                                    <p class="text-lg font-semibold text-gray-800">{{ $selectedWard->ward_name }}
                                        ({{ $selectedWard->ward_code }})</p>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="px-3 py-1 rounded-full bg-blue-50 text-blue-700 text-sm font-semibold border border-blue-100">
                                        Base date: {{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }}
                                    </span>
                                    <span
                                        class="px-3 py-1 rounded-full bg-gray-50 text-gray-700 text-sm font-semibold border border-gray-200">
                                        {{ $beds->count() }} beds
                                    </span>
                                </div>
                            </div>

                            <div class="overflow-x-auto mt-6">
                                @php
                                    $statusColors = [
                                        'available' => 'bg-green-100 text-green-800 border-green-200',
                                        'occupied' => 'bg-red-100 text-red-800 border-red-200',
                                        'reserved' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                                        'maintenance' => 'bg-gray-100 text-gray-800 border-gray-200',
                                    ];
                                @endphp
                                <table class="min-w-full divide-y divide-blue-100">
                                    <thead>
                                        <tr class="bg-gradient-to-r from-blue-50 to-cyan-50">
                                            <th
                                                class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider w-32">
                                                Bed</th>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider w-32">
                                                Shift</th>
                                            @foreach($dateRange as $date)
                                                @php
                                                    $isToday = $date->isSameDay(\Carbon\Carbon::now());
                                                @endphp
                                                <th
                                                    class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider {{ $isToday ? 'bg-yellow-50 text-amber-800 border-b-2 border-yellow-300' : 'text-gray-700' }}">
                                                    <div class="flex flex-col">
                                                        <span
                                                            class="text-sm font-semibold {{ $isToday ? 'text-amber-900' : 'text-gray-800' }}">{{ $date->format('l') }}</span>
                                                        <span
                                                            class="text-xs {{ $isToday ? 'text-amber-700' : 'text-gray-500' }}">
                                                            @if($isToday)
                                                                Today,
                                                            @endif
                                                            {{ $date->toDateString() }}
                                                        </span>
                                                    </div>
                                                </th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-blue-50">
                                        @forelse($beds as $bed)
                                            @foreach($shifts as $shiftIndex => $shift)
                                                <tr class="hover:bg-blue-50 transition-colors">
                                                    @if($loop->first)
                                                        <td class="px-6 py-4 align-top" rowspan="{{ count($shifts) }}">
                                                            <div class="space-y-1">
                                                                <p class="font-semibold text-gray-800">
                                                                    {{ $bed->bed_display_name ?? 'Bed ' . $bed->bed_number }}
                                                                </p>
                                                                <p class="text-xs text-gray-500">Ward bed #{{ $bed->bed_number }}</p>
                                                                <span
                                                                    class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full border {{ $statusColors[$bed->status] ?? 'bg-gray-100 text-gray-800 border-gray-200' }}">
                                                                    {{ ucfirst($bed->status) }}
                                                                </span>
                                                                @if(strtolower($bed->status) === 'occupied' && $bed->patient_id)
                                                                    <div class="pt-2">
                                                                        <button type="button"
                                                                            class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm transition-colors"
                                                                            @click="openPatientDetails({{ $bed->patient_id }})">
                                                                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor"
                                                                                viewBox="0 0 24 24">
                                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                                    stroke-width="2" d="M5 7h14M5 12h14M5 17h14" />
                                                                            </svg>
                                                                            Patient Detail
                                                                        </button>
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        </td>
                                                    @endif
                                                    <td class="px-6 py-4 w-32">
                                                        <span
                                                            class="px-3 py-1 inline-flex text-sm font-semibold rounded-full bg-blue-100 text-blue-800 border border-blue-200">
                                                            {{ $shift }}
                                                        </span>
                                                    </td>
                                                    @foreach($dateRange as $date)
                                                        @php
                                                            $isSelectedDate = $date->isSameDay(\Carbon\Carbon::parse($selectedDate));
                                                            $isToday = $date->isSameDay(\Carbon\Carbon::now());
                                                            $cellKey = $bed->id . '|' . $date->toDateString() . '|' . $shift;
                                                        @endphp
                                                        <td class="px-6 py-4 {{ $isToday ? 'bg-yellow-50/30' : '' }}">
                                                            <button type="button" @if(!($isLockedToNurse ?? false))
                                                                @click="toggle({ key: '{{ $cellKey }}', bed_id: {{ $bed->id }}, bed: @js($bed->bed_display_name ?? 'Bed ' . $bed->bed_number), shift: '{{ $shift }}', date: '{{ $date->toDateString() }}' })"
                                                            @else disabled @endif
                                                                :class="isSelected('{{ $cellKey }}') ? 'ring-2 ring-offset-2 ring-green-400' : ''"
                                                                class="w-full text-left {{ ($isLockedToNurse ?? false) ? 'cursor-default' : '' }}">
                                                                <div
                                                                    class="rounded-lg border {{ $isSelectedDate ? 'border-green-200 bg-green-50' : ($isToday ? 'border-yellow-200 bg-yellow-50' : 'border-gray-100 bg-white') }} p-3 hover:border-blue-200 hover:bg-blue-50 transition-colors">
                                                                    <div class="flex items-center justify-between">
                                                                        <p
                                                                            class="text-xs {{ $isToday ? 'text-amber-700 font-medium' : 'text-gray-500' }}">
                                                                            {{ $date->toDateString() }}
                                                                        </p>
                                                                        <span x-show="isSelected('{{ $cellKey }}')"
                                                                            class="text-xs font-semibold text-green-700 bg-green-100 border border-green-200 rounded-full px-2 py-0.5">Selected</span>
                                                                    </div>
                                                                    <p class="text-sm font-semibold text-gray-800 mt-1">
                                                                        {{ ucfirst($bed->status) }}
                                                                    </p>
                                                                    <p class="text-xs text-gray-400">Shift {{ $shift }}</p>
                                                                    @php $assignment = $assignments[$cellKey] ?? null; @endphp
                                                                    @if($assignment)
                                                                        <p class="text-xs text-green-700 font-semibold mt-2">
                                                                            Nurse: {{ $assignment['nurse_name'] ?? 'Unassigned' }}
                                                                        </p>
                                                                    @endif
                                                                </div>
                                                            </button>
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        @empty
                                            <tr>
                                                <td colspan="{{ 2 + $dateRange->count() }}" class="px-6 py-12 text-center">
                                                    <div class="flex flex-col items-center">
                                                        <svg class="w-14 h-14 text-gray-300 mb-3" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2" d="M3 7h18M3 12h18M3 17h18" />
                                                        </svg>
                                                        @if(isset($individualMode) && $individualMode && isset($selectedNurseId))
                                                            <p class="text-gray-500 font-medium">No assignments found</p>
                                                            <p class="text-gray-400 text-sm mt-1">This nurse has no scheduled shifts
                                                                in this ward for the selected dates.</p>
                                                        @else
                                                            <p class="text-gray-500 font-medium">No beds found for this ward</p>
                                                            <p class="text-gray-400 text-sm mt-1">Beds created for the ward will
                                                                appear here.</p>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    <!-- Assign Nurses Modal -->
                    <div x-show="assignModal" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto">
                        <div class="fixed inset-0 bg-black/40" @click="assignModal = false"></div>
                        <div class="flex min-h-full items-center justify-center p-4">
                            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl p-6 space-y-4 max-h-[90vh] overflow-y-auto"
                                @click.stop>
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg font-semibold text-gray-800">Assign Nurses</h3>
                                    <button type="button" class="text-gray-400 hover:text-gray-600"
                                        @click="assignModal = false">&times;</button>
                                </div>
                                <p class="text-sm text-gray-600">Selected shifts (<span
                                        x-text="selected.length"></span>):</p>
                                <div class="max-h-64 overflow-y-auto space-y-2">
                                    <template x-if="!selected.length">
                                        <p class="text-sm text-gray-500">No shifts selected yet.</p>
                                    </template>
                                    <template x-for="cell in selected" :key="cell.key">
                                        <div
                                            class="flex items-center justify-between border border-gray-100 rounded-lg px-3 py-2">
                                            <div>
                                                <p class="text-sm font-semibold text-gray-800" x-text="cell.bed"></p>
                                                <p class="text-xs text-gray-500">Shift: <span
                                                        x-text="cell.shift"></span> · Date: <span
                                                        x-text="cell.date"></span></p>
                                            </div>
                                            <button type="button" class="text-xs text-red-600 hover:text-red-700"
                                                @click="toggle(cell)">Remove</button>
                                        </div>
                                    </template>
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-sm font-semibold text-gray-700">Select nurse</label>
                                    <select x-model="selectedNurse"
                                        class="w-full rounded-lg border-gray-200 shadow-sm focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100 disabled:text-gray-500"
                                        :disabled="!hasNurses">
                                        <option value="">Choose a nurse...</option>
                                        @forelse($nurses as $nurse)
                                            <option value="{{ $nurse->id }}">
                                                {{ $nurse->name }}{{ $nurse->registration_number ? ' · ' . $nurse->registration_number : '' }}
                                            </option>
                                        @empty
                                            <option disabled>No active nurses available</option>
                                        @endforelse
                                    </select>
                                    @if(!$nurses->count())
                                        <p class="text-sm text-amber-600">Add active nurses first before assigning.</p>
                                    @else
                                        <p class="text-xs text-gray-500">The selected nurse will be applied to all chosen
                                            shifts.</p>
                                    @endif
                                    <p x-show="assignError" class="text-sm text-red-600" x-text="assignError"></p>
                                </div>
                                <div class="flex items-center justify-end gap-3">
                                    <button type="button"
                                        class="px-4 py-2 rounded-lg text-gray-600 hover:text-gray-800 hover:bg-gray-100"
                                        @click="assignModal = false">Cancel</button>
                                    <button type="button"
                                        class="px-4 py-2 rounded-lg bg-green-600 hover:bg-green-700 text-white font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
                                        :disabled="!selected.length || !hasNurses || assignLoading"
                                        @click="assignNurses()">
                                        <span x-show="assignLoading">Assigning...</span>
                                        <span x-show="!assignLoading">Confirm Assign</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section Assign Modal -->
                    <div x-show="sectionAssignModal" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto">
                        <div class="fixed inset-0 bg-black/40" @click="sectionAssignModal = false"></div>
                        <div class="flex min-h-full items-center justify-center p-4">
                            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl p-6 space-y-4 max-h-[90vh] overflow-y-auto"
                                @click.stop>
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg font-semibold text-gray-800">Section Assign</h3>
                                    <button type="button" class="text-gray-400 hover:text-gray-600"
                                        @click="sectionAssignModal = false">&times;</button>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <!-- Controls -->
                                    <div class="space-y-4">
                                        <!-- Section Select -->
                                        <div>
                                            <label class="block text-sm font-semibold text-gray-700">Select
                                                Section</label>
                                            <select x-model="sectionAssignSection"
                                                class="w-full rounded-lg border-gray-200 shadow-sm focus:ring-blue-500 focus:border-blue-500 mt-1">
                                                <option value="">Select Section...</option>
                                                <option value="1">Section 1</option>
                                                <option value="2">Section 2</option>
                                                <option value="3">Section 3</option>
                                                <option value="4">Section 4</option>
                                            </select>
                                        </div>

                                        <!-- Nurse Select -->
                                        <div>
                                            <label class="block text-sm font-semibold text-gray-700">Select
                                                Nurse</label>
                                            <select x-model="sectionAssignNurse"
                                                class="w-full rounded-lg border-gray-200 shadow-sm focus:ring-blue-500 focus:border-blue-500 mt-1 disabled:bg-gray-100 disabled:text-gray-500"
                                                :disabled="!hasNurses">
                                                <option value="">Choose a nurse...</option>
                                                @forelse($nurses as $nurse)
                                                    <option value="{{ $nurse->id }}">
                                                        {{ $nurse->name }}{{ $nurse->registration_number ? ' · ' . $nurse->registration_number : '' }}
                                                    </option>
                                                @empty
                                                    <option disabled>No active nurses available</option>
                                                @endforelse
                                            </select>
                                            <!-- Tagging Nurse Display -->
                                            <div class="mt-2 p-2 bg-gray-50 border border-gray-200 rounded-lg">
                                                <p class="text-xs text-gray-600 font-medium flex items-center">
                                                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1">
                                                        </path>
                                                    </svg>
                                                    Tagging Nurse:
                                                </p>
                                                <p class="text-sm font-semibold text-gray-800 mt-0.5"
                                                    x-text="getTaggingNurseName(sectionAssignNurse)"></p>
                                            </div>
                                        </div>

                                        <!-- Shifts Select -->
                                        <div>
                                            <label class="block text-sm font-semibold text-gray-700 mb-2">Select
                                                Shifts</label>
                                            <div class="flex flex-col gap-2">
                                                <label class="inline-flex items-center">
                                                    <input type="checkbox" value="AM" x-model="sectionAssignShifts"
                                                        class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                                    <span class="ml-2 text-gray-700">AM Shift</span>
                                                </label>
                                                <label class="inline-flex items-center">
                                                    <input type="checkbox" value="PM" x-model="sectionAssignShifts"
                                                        class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                                    <span class="ml-2 text-gray-700">PM Shift</span>
                                                </label>
                                                <label class="inline-flex items-center">
                                                    <input type="checkbox" value="ON" x-model="sectionAssignShifts"
                                                        class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                                    <span class="ml-2 text-gray-700">ON Shift</span>
                                                </label>
                                            </div>
                                        </div>

                                        <p x-show="assignError" class="text-sm text-red-600" x-text="assignError"></p>

                                        <div class="pt-4">
                                            <button type="button"
                                                class="w-full px-4 py-2 rounded-lg bg-purple-600 hover:bg-purple-700 text-white font-semibold disabled:opacity-50 disabled:cursor-not-allowed shadow-md transition-colors"
                                                :disabled="!sectionAssignSection || !sectionAssignNurse || !sectionAssignShifts.length || assignLoading"
                                                @click="submitSectionAssign()">
                                                <span x-show="assignLoading">Assigning...</span>
                                                <span x-show="!assignLoading">Assign to Section</span>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Preview Table -->
                                    <div class="md:col-span-2 bg-gray-50 rounded-xl p-4 border border-gray-200">
                                        <h4 class="font-semibold text-gray-700 mb-2 flex items-center justify-between">
                                            <span>
                                                Preview: Beds in Section <span
                                                    x-text="sectionAssignSection || '...'"></span>
                                                <span class="text-xs font-normal text-gray-500 ml-2"
                                                    x-show="sectionAssignSection">(Uncheck to exclude)</span>
                                            </span>
                                            <span class="text-xs text-gray-500">Date: {{ $selectedDate }}</span>
                                        </h4>

                                        <div class="overflow-y-auto max-h-[400px] border rounded-lg bg-white shadow-sm">
                                            <table class="min-w-full divide-y divide-gray-200">
                                                <thead class="bg-gray-50 sticky top-0 z-10">
                                                    <tr>
                                                        <th class="px-3 py-2 text-center w-10">
                                                            <input type="checkbox"
                                                                @change="toggleAllExclusion($event.target.checked)"
                                                                :checked="areAllIncluded()"
                                                                class="rounded border-gray-300 text-purple-600 shadow-sm focus:border-purple-300 focus:ring focus:ring-purple-200 focus:ring-opacity-50">
                                                        </th>
                                                        <th
                                                            class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                            Bed</th>
                                                        <th
                                                            class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                            AM</th>
                                                        <th
                                                            class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                            PM</th>
                                                        <th
                                                            class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                            ON</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white divide-y divide-gray-200">
                                                    <template x-for="bed in getSectionBeds()" :key="bed.id">
                                                        <tr
                                                            :class="isExcluded(bed.id) ? 'bg-gray-50 opacity-60' : (bed.id % 2 === 0 ? 'bg-white' : 'bg-gray-50')">
                                                            <td class="px-3 py-2 text-center border-r">
                                                                <input type="checkbox" :checked="!isExcluded(bed.id)"
                                                                    @change="toggleExclusion(bed.id)"
                                                                    class="rounded border-gray-300 text-purple-600 shadow-sm focus:border-purple-300 focus:ring focus:ring-purple-200 focus:ring-opacity-50">
                                                            </td>
                                                            <td class="px-3 py-2 text-sm font-medium text-gray-900 border-r"
                                                                x-text="bed.bed_display_name"></td>
                                                            <template x-for="shift in ['AM', 'PM', 'ON']">
                                                                <td class="px-3 py-2 text-xs border-r relative"
                                                                    :class="(!isExcluded(bed.id) && sectionAssignShifts.includes(shift)) ? 'bg-purple-50' : ''">
                                                                    <!-- Current Assignment -->
                                                                    <div x-text="getNurseName(bed.id, shift) || '-'"
                                                                        class="truncate max-w-[80px]"
                                                                        :class="getNurseName(bed.id, shift) ? 'text-gray-900' : 'text-gray-400'">
                                                                    </div>

                                                                    <!-- New Assignment Indicator -->
                                                                    <div x-show="!isExcluded(bed.id) && sectionAssignShifts.includes(shift) && sectionAssignNurse"
                                                                        class="text-[10px] text-purple-600 font-bold mt-1 flex items-center">
                                                                        <svg class="w-3 h-3 mr-0.5" fill="none"
                                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round"
                                                                                stroke-linejoin="round" stroke-width="2"
                                                                                d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                                                        </svg>
                                                                        <span
                                                                            x-text="getNurseLabel(sectionAssignNurse)"></span>
                                                                    </div>
                                                                </td>
                                                            </template>
                                                        </tr>
                                                    </template>
                                                    <tr x-show="sectionAssignSection && !getSectionBeds().length">
                                                        <td colspan="5"
                                                            class="px-3 py-4 text-center text-sm text-gray-500">
                                                            No beds found in this section
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Patient Details Modal -->
                    <div x-show="patientModal.open" style="display:none;" class="fixed inset-0 z-50 overflow-y-auto">
                        <div class="fixed inset-0 bg-black/40" @click="closePatientDetails()"></div>
                        <div class="flex min-h-full items-center justify-center p-4">
                            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-5xl p-4" @click.stop>
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="text-lg font-semibold text-gray-800">Patient Details</h3>
                                    <button type="button" class="text-gray-400 hover:text-gray-600"
                                        @click="closePatientDetails()">&times;</button>
                                </div>
                                <template x-if="patientModal.patientId">
                                    <iframe
                                        :src="'{{ route('ward.schedule.patient-details') }}?patient_id=' + patientModal.patientId"
                                        class="w-full h-[600px] border-0 rounded-lg" title="Patient Details">
                                    </iframe>
                                </template>
                                <template x-if="!patientModal.patientId">
                                    <p class="text-sm text-gray-500">No patient selected.</p>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Print Modal -->
                    <div x-show="printModal" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto">
                        <div class="fixed inset-0 bg-black/40" @click="printModal = false"></div>
                        <div class="flex min-h-full items-center justify-center p-4">
                            <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4"
                                @click.stop>
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg font-semibold text-gray-800">Print Schedule</h3>
                                    <button type="button" class="text-gray-400 hover:text-gray-600"
                                        @click="printModal = false">&times;</button>
                                </div>
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-1">Start Date</label>
                                        <input type="date" x-model="printStartDate"
                                            class="w-full rounded-lg border-gray-200 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-1">End Date</label>
                                        <input type="date" x-model="printEndDate"
                                            class="w-full rounded-lg border-gray-200 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <p class="text-xs text-gray-500">
                                        Note: A landscape layout works best for date ranges up to 7-10 days.
                                    </p>
                                </div>
                                <div class="flex justify-end gap-3 pt-2">
                                    <button type="button"
                                        class="px-4 py-2 rounded-lg text-gray-600 hover:text-gray-800 hover:bg-gray-100"
                                        @click="printModal = false">Cancel</button>
                                    <button type="button"
                                        class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-semibold flex items-center"
                                        @click="submitPrint()">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                        </svg>
                                        Print View
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Shift Settings Modal -->
    <div id="shiftSettingsModal" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="fixed inset-0 bg-black/40" onclick="closeShiftSettings()"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden">
                <div
                    class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-purple-50 to-indigo-50">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Shift Settings
                    </h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600 transition-colors"
                        onclick="closeShiftSettings()">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <iframe id="shiftSettingsIframe" src="" class="w-full h-[600px] border-0" title="Shift Settings">
                </iframe>
            </div>
        </div>
    </div>

    <!-- Download Template Modal -->
    <div id="downloadModal" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="fixed inset-0 bg-black/40" onclick="closeDownloadModal()"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Download Roster Template</h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600"
                        onclick="closeDownloadModal()">&times;</button>
                </div>
                <form action="{{ route('ward.schedule.download-template') }}" method="GET">
                    <input type="hidden" name="ward_id" value="{{ $selectedWardId }}">
                    <div class="space-y-4">
                        <div>
                            <label for="download_date" class="block text-sm font-medium text-gray-700">Select
                                Month</label>
                            <input type="date" name="date" id="download_date" value="{{ $selectedDate }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            <p class="mt-1 text-xs text-gray-500">The template will be generated for the month of the
                                selected date.</p>
                        </div>
                        <div class="flex justify-end gap-3">
                            <button type="button"
                                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200"
                                onclick="closeDownloadModal()">Cancel</button>
                            <button type="submit"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Download
                                XML</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Upload Roster Modal -->
    <div id="uploadModal" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="fixed inset-0 bg-black/40" onclick="closeUploadModal()"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Upload Roster</h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600"
                        onclick="closeUploadModal()">&times;</button>
                </div>
                <form action="{{ route('ward.schedule.upload') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="ward_id" value="{{ $selectedWardId }}">
                    <div class="space-y-4">
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-sm text-yellow-800">
                            <strong>Note:</strong> Please ensure you are uploading the XML file generated from the
                            "Download Template" feature.
                        </div>
                        <div>
                            <label for="file" class="block text-sm font-medium text-gray-700">Select XML File</label>
                            <input type="file" name="file" id="file" accept=".xml" class="mt-1 block w-full text-sm text-gray-500
                                file:mr-4 file:py-2 file:px-4
                                file:rounded-full file:border-0
                                file:text-sm file:font-semibold
                                file:bg-blue-50 file:text-blue-700
                                hover:file:bg-blue-100
                            ">
                        </div>
                        <div class="flex justify-end gap-3">
                            <button type="button"
                                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200"
                                onclick="closeUploadModal()">Cancel</button>
                            <button type="submit"
                                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">Upload</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openShiftSettings() {
            const modal = document.getElementById('shiftSettingsModal');
            const iframe = document.getElementById('shiftSettingsIframe');
            const wardId = document.getElementById('ward_id')?.value || '{{ $selectedWardId }}';
            iframe.src = '{{ route("ward.shift-settings") }}?ward_id=' + wardId;
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeShiftSettings() {
            const modal = document.getElementById('shiftSettingsModal');
            const iframe = document.getElementById('shiftSettingsIframe');
            modal.style.display = 'none';
            iframe.src = '';
            document.body.style.overflow = '';
        }

        // Close modal on escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeShiftSettings();
            }
        });

        function openDownloadModal() {
            document.getElementById('downloadModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeDownloadModal() {
            document.getElementById('downloadModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        function openUploadModal() {
            document.getElementById('uploadModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeUploadModal() {
            document.getElementById('uploadModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        // Close all modals on escape
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeDownloadModal();
                closeUploadModal();
            }
        });
    </script>
</x-app-layout>