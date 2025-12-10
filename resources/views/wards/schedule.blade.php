<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-bold text-2xl text-gray-800 leading-tight">
                    {{ __('Ward Schedule') }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">Visualise beds by shift for the selected ward</p>
            </div>
                <div class="flex items-center space-x-2">
                    <span class="px-3 py-1 rounded-full text-sm font-semibold bg-blue-100 text-blue-800">
                        Columns: Dates (base {{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }})
                    </span>
                    <span class="px-3 py-1 rounded-full text-sm font-semibold bg-cyan-100 text-cyan-800">
                        Rows: Bed → Shift (AM / PM / ON)
                    </span>
                </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white/90 backdrop-blur-sm shadow-lg rounded-2xl border border-blue-100" 
                 x-data="{
                    selected: [],
                    assignModal: false,
                    patientModal: { open: false, patientId: null },
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
                    }
                 }">
                <div class="p-6 space-y-6">
                    <form method="GET" action="{{ route('ward.schedule') }}" class="grid gap-4 md:gap-6 md:grid-cols-3 items-end">
                        <div>
                            <label for="ward_id" class="block text-sm font-semibold text-gray-700 mb-2">Ward</label>
                            <select id="ward_id" name="ward_id" class="w-full rounded-xl border-gray-200 shadow-sm focus:ring-blue-500 focus:border-blue-500">
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
                            <input type="date" id="date" name="date" value="{{ $selectedDate }}" class="w-full rounded-xl border-gray-200 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="flex md:justify-end">
                            <button type="submit" class="inline-flex items-center px-5 py-3 bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 text-white font-semibold rounded-xl shadow-md hover:shadow-lg transition-all duration-200">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V5a3 3 0 013-3h2a3 3 0 013 3v2m4 0H4a2 2 0 00-2 2v9a3 3 0 003 3h14a3 3 0 003-3v-9a2 2 0 00-2-2z"/>
                                </svg>
                                Apply
                            </button>
                        </div>
                    </form>

                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <span class="px-3 py-1 rounded-full bg-blue-50 text-blue-700 text-xs font-semibold border border-blue-100">
                                Selected shifts: <span x-text="selected.length"></span>
                            </span>
                            <button type="button" class="text-xs text-gray-500 hover:text-gray-700 underline" @click="clearSelection()" x-show="selected.length">Clear</button>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button"
                                    @click="assignModal = true"
                                    :disabled="!selected.length"
                                    class="inline-flex items-center px-4 py-2 rounded-lg font-semibold shadow-sm transition-all"
                                    :class="selected.length ? 'bg-green-600 hover:bg-green-700 text-white' : 'bg-gray-200 text-gray-500 cursor-not-allowed'">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                Assign Nurses
                            </button>
                        </div>
                    </div>

                    <div class="mt-4">
                        @if(!$selectedWard)
                            <div class="flex items-center p-6 bg-gradient-to-r from-amber-50 to-yellow-50 border border-amber-200 rounded-xl text-amber-800">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.455 19h13.09c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.723 16c-.77 1.333.192 3 1.732 3z"/>
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
                                    <p class="text-lg font-semibold text-gray-800">{{ $selectedWard->ward_name }} ({{ $selectedWard->ward_code }})</p>
                                </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="px-3 py-1 rounded-full bg-blue-50 text-blue-700 text-sm font-semibold border border-blue-100">
                                    Base date: {{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }}
                                </span>
                                <span class="px-3 py-1 rounded-full bg-gray-50 text-gray-700 text-sm font-semibold border border-gray-200">
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
                                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider w-32">Bed</th>
                                            <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider w-32">Shift</th>
                                            @foreach($dateRange as $date)
                                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                                    <div class="flex flex-col">
                                                        <span class="text-sm font-semibold text-gray-800">{{ $date->format('l') }}</span>
                                                        <span class="text-xs text-gray-500">{{ $date->toDateString() }}</span>
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
                                                                <p class="font-semibold text-gray-800">{{ $bed->bed_display_name ?? 'Bed '.$bed->bed_number }}</p>
                                                                <p class="text-xs text-gray-500">Ward bed #{{ $bed->bed_number }}</p>
                                                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full border {{ $statusColors[$bed->status] ?? 'bg-gray-100 text-gray-800 border-gray-200' }}">
                                                                    {{ ucfirst($bed->status) }}
                                                                </span>
                                                                        @if(strtolower($bed->status) === 'occupied' && $bed->patient_id)
                                                                            <div class="pt-2">
                                                                                <button type="button"
                                                                                    class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm transition-colors"
                                                                                    @click="openPatientDetails({{ $bed->patient_id }})">
                                                                                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 7h14M5 12h14M5 17h14"/>
                                                                                    </svg>
                                                                                    Patient Detail
                                                                                </button>
                                                                            </div>
                                                                        @endif
                                                            </div>
                                                        </td>
                                                    @endif
                                                    <td class="px-6 py-4 w-32">
                                                        <span class="px-3 py-1 inline-flex text-sm font-semibold rounded-full bg-blue-100 text-blue-800 border border-blue-200">
                                                            {{ $shift }}
                                                        </span>
                                                    </td>
                                                    @foreach($dateRange as $date)
                                                        @php
                                                            $isSelectedDate = $date->isSameDay(\Carbon\Carbon::parse($selectedDate));
                                                            $cellKey = $bed->id . '|' . $date->toDateString() . '|' . $shift;
                                                        @endphp
                                                        <td class="px-6 py-4">
                                                            <button type="button"
                                                                @click="toggle({ key: '{{ $cellKey }}', bed: @js($bed->bed_display_name ?? 'Bed '.$bed->bed_number), shift: '{{ $shift }}', date: '{{ $date->toDateString() }}' })"
                                                                :class="isSelected('{{ $cellKey }}') ? 'ring-2 ring-offset-2 ring-green-400' : ''"
                                                                class="w-full text-left">
                                                                <div class="rounded-lg border {{ $isSelectedDate ? 'border-green-200 bg-green-50' : 'border-gray-100 bg-white' }} p-3 hover:border-blue-200 hover:bg-blue-50 transition-colors">
                                                                    <div class="flex items-center justify-between">
                                                                        <p class="text-xs text-gray-500">{{ $date->toDateString() }}</p>
                                                                        <span x-show="isSelected('{{ $cellKey }}')" class="text-xs font-semibold text-green-700 bg-green-100 border border-green-200 rounded-full px-2 py-0.5">Selected</span>
                                                                    </div>
                                                                    <p class="text-sm font-semibold text-gray-800 mt-1">{{ ucfirst($bed->status) }}</p>
                                                                    <p class="text-xs text-gray-400">Shift {{ $shift }}</p>
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
                                                        <svg class="w-14 h-14 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18"/>
                                                        </svg>
                                                        <p class="text-gray-500 font-medium">No beds found for this ward</p>
                                                        <p class="text-gray-400 text-sm mt-1">Beds created for the ward will appear here.</p>
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
                    <div x-show="assignModal" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center">
                        <div class="absolute inset-0 bg-black/40" @click="assignModal = false"></div>
                        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl p-6 space-y-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-semibold text-gray-800">Assign Nurses</h3>
                                <button type="button" class="text-gray-400 hover:text-gray-600" @click="assignModal = false">&times;</button>
                            </div>
                            <p class="text-sm text-gray-600">Selected shifts (<span x-text="selected.length"></span>):</p>
                            <div class="max-h-64 overflow-y-auto space-y-2">
                                <template x-if="!selected.length">
                                    <p class="text-sm text-gray-500">No shifts selected yet.</p>
                                </template>
                                <template x-for="cell in selected" :key="cell.key">
                                    <div class="flex items-center justify-between border border-gray-100 rounded-lg px-3 py-2">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-800" x-text="cell.bed"></p>
                                            <p class="text-xs text-gray-500">Shift: <span x-text="cell.shift"></span> · Date: <span x-text="cell.date"></span></p>
                                        </div>
                                        <button type="button" class="text-xs text-red-600 hover:text-red-700" @click="toggle(cell)">Remove</button>
                                    </div>
                                </template>
                            </div>
                            <div class="flex items-center justify-end gap-3">
                                <button type="button" class="px-4 py-2 rounded-lg text-gray-600 hover:text-gray-800 hover:bg-gray-100" @click="assignModal = false">Cancel</button>
                                <button type="button" class="px-4 py-2 rounded-lg bg-green-600 hover:bg-green-700 text-white font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
                                        :disabled="!selected.length"
                                        @click="alert('Hook up nurse assignment action here with ' + selected.length + ' shift(s).')">
                                    Confirm Assign
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Patient Details Modal -->
                    <div x-show="patientModal.open" style="display:none;" class="fixed inset-0 z-50 flex items-center justify-center">
                        <div class="absolute inset-0 bg-black/40" @click="closePatientDetails()"></div>
                        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-5xl p-4"
                             @click.stop>
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-lg font-semibold text-gray-800">Patient Details</h3>
                                <button type="button" class="text-gray-400 hover:text-gray-600" @click="closePatientDetails()">&times;</button>
                            </div>
                            <template x-if="patientModal.patientId">
                                <iframe :src="'{{ route('ward.schedule.patient-details') }}?patient_id=' + patientModal.patientId"
                                        class="w-full h-[600px] border-0 rounded-lg"
                                        title="Patient Details">
                                </iframe>
                            </template>
                            <template x-if="!patientModal.patientId">
                                <p class="text-sm text-gray-500">No patient selected.</p>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

