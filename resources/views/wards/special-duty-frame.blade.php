<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Special Duty Assignment</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            background: linear-gradient(135deg, #fef3c7 0%, #fed7aa 100%);
            min-height: 100vh;
        }
        .duty-cell {
            min-width: 140px;
        }
        .shift-badge {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;
        }
        .shift-am { background: #dbeafe; color: #1e40af; }
        .shift-pm { background: #fef3c7; color: #92400e; }
        .shift-on { background: #ede9fe; color: #5b21b6; }
        
        /* Searchable Select Styles */
        .searchable-select-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            max-height: 200px;
            overflow-y: auto;
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            z-index: 50;
        }
        .searchable-select-option {
            padding: 0.5rem;
            cursor: pointer;
            font-size: 0.75rem;
        }
        .searchable-select-option:hover, .searchable-select-option.focused {
            background-color: #f3f4f6;
        }
        .searchable-select-option.selected {
            background-color: #fef3c7;
            color: #92400e;
        }
    </style>
    @include('components.autofill-guard')
</head>
<body class="antialiased" x-data="specialDutyApp()">
    <div class="p-6">
        @if(session('success'))
            <div class="mb-4 p-4 rounded-lg bg-green-100 border border-green-300 text-green-800 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-4 p-4 rounded-lg bg-red-100 border border-red-300 text-red-800 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                {{ session('error') }}
            </div>
        @endif

        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-bold text-amber-800">Special Duty Assignment</h2>
                @if($ward)
                    <p class="text-sm text-amber-600 mt-1">{{ $ward->ward_name }} ({{ $ward->ward_code }}) · Starting {{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }}</p>
                @endif
            </div>
            <button type="submit" form="specialDutyForm"
                class="inline-flex items-center px-6 py-2.5 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white rounded-lg font-semibold shadow-lg transition-all">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Save All Duties
            </button>
        </div>

        @if(!$ward)
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-6 text-amber-800">
                <p class="font-semibold">No ward selected</p>
                <p class="text-sm mt-1">Please select a ward from the schedule page first.</p>
            </div>
        @else
            <form id="specialDutyForm" method="POST" action="{{ route('ward.schedule.special-duty.save') }}">
                @csrf
                <input type="hidden" name="ward_id" value="{{ $wardId }}">
                
                <div class="bg-white rounded-xl shadow-lg overflow-visible border border-amber-200 pb-24">
                    <div class="overflow-x-visible">
                        <table class="min-w-full divide-y divide-amber-200">
                            <thead class="bg-gradient-to-r from-amber-100 to-orange-100">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-amber-800 uppercase tracking-wider sticky left-0 bg-gradient-to-r from-amber-100 to-orange-100 z-10 min-w-[160px]">
                                        Duty Type
                                    </th>
                                    @foreach($dateRange as $date)
                                        @php
                                            $isToday = $date->isSameDay(\Carbon\Carbon::now());
                                        @endphp
                                        <th class="px-2 py-3 text-center text-xs font-bold uppercase tracking-wider {{ $isToday ? 'bg-yellow-200 text-yellow-800' : 'text-amber-800' }}">
                                            <div class="flex flex-col items-center">
                                                <span class="text-sm font-semibold">{{ $date->format('D') }}</span>
                                                <span class="text-[10px] {{ $isToday ? 'font-bold' : 'font-normal' }}">
                                                    @if($isToday) Today, @endif
                                                    {{ $date->format('d/m') }}
                                                </span>
                                            </div>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-amber-100 font-sans">
                                @foreach($dutyTypes as $dutyIndex => $duty)
                                    @php
                                        $isDaily = count($duty['shifts']) > 1; // True for Team Leader, etc.
                                    @endphp
                                    <tr class="{{ $dutyIndex % 2 === 0 ? 'bg-white' : 'bg-amber-50/30' }}">
                                        <td class="px-4 py-3 text-sm font-semibold text-gray-800 sticky left-0 {{ $dutyIndex % 2 === 0 ? 'bg-white' : 'bg-amber-50/30' }} z-10 border-r border-amber-100">
                                            <div class="flex items-center">
                                                <span class="w-2 h-2 rounded-full mr-2 {{ !$isDaily ? 'bg-purple-500' : 'bg-amber-500' }}"></span>
                                                {{ $duty['label'] }}
                                            </div>
                                            @if(!$isDaily)
                                                <span class="text-[10px] text-purple-600 font-medium">(Night Only)</span>
                                            @elseif($isDaily)
                                                 <span class="text-[10px] text-amber-600 font-medium">(Daily)</span>
                                            @endif
                                        </td>
                                        @foreach($dateRange as $date)
                                            @php
                                                $isToday = $date->isSameDay(\Carbon\Carbon::now());
                                            @endphp
                                            <td class="px-2 py-2 duty-cell {{ $isToday ? 'bg-yellow-50' : '' }} align-top">
                                                @if($isDaily)
                                                    {{-- Daily Assignment (One selector for all shifts) --}}
                                                    @php
                                                        // Use the first shift (AM) to determine initial value
                                                        $key = $duty['key'] . '|' . $date->format('Y-m-d') . '|' . $duty['shifts'][0];
                                                        $initialValue = $existingDuties[$key] ?? '';
                                                    @endphp
                                                    <div x-data="{ selectedNurse: '{{ $initialValue }}' }" class="w-full">
                                                        <!-- Searchable Select Component -->
                                                        <div x-data="searchableSelect({
                                                                options: nurses,
                                                                value: '{{ $initialValue }}',
                                                                placeholder: 'Select Nurse',
                                                                onSelect: (val) => selectedNurse = val
                                                            })" class="relative">
                                                            
                                                            <div class="relative">
                                                                <input type="text"
                                                                    x-model="search"
                                                                    @focus="open = true"
                                                                    @click.away="open = false"
                                                                    @keydown.escape="open = false"
                                                                    @keydown.down.prevent="focusNext()"
                                                                    @keydown.up.prevent="focusPrev()"
                                                                    @keydown.enter.prevent="selectFocused()"
                                                                    autocomplete="off"
                                                                    class="w-full text-xs border-gray-200 rounded-md focus:border-amber-400 focus:ring-amber-400 py-1.5 pl-2 pr-6"
                                                                    :placeholder="selectedName || placeholder">
                                                                
                                                                <div class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                                                    <svg class="h-3 w-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                                    </svg>
                                                                </div>
                                                            </div>

                                                            <div x-show="open" 
                                                                 class="searchable-select-dropdown" 
                                                                 style="display: none;">
                                                                <template x-for="(option, index) in filteredOptions" :key="option.id">
                                                                    <div class="searchable-select-option"
                                                                         :class="{ 'selected': option.id == value, 'focused': index === focusedIndex }"
                                                                         @click="selectOption(option)"
                                                                         @mouseenter="focusedIndex = index">
                                                                        <span x-text="option.name"></span>
                                                                    </div>
                                                                </template>
                                                                <div x-show="filteredOptions.length === 0" class="p-2 text-xs text-gray-500">
                                                                    No nurse found
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Hidden Inputs for Syncing to All Shifts -->
                                                        @foreach($duty['shifts'] as $shift)
                                                            @php
                                                                $inputName = 'duties[' . $duty['key'] . '_' . $date->format('Y-m-d') . '_' . $shift . ']';
                                                            @endphp
                                                            <input type="hidden" name="{{ $inputName }}[nurse_id]" :value="selectedNurse">
                                                            <input type="hidden" name="{{ $inputName }}[duty_type]" value="{{ $duty['key'] }}">
                                                            <input type="hidden" name="{{ $inputName }}[date]" value="{{ $date->format('Y-m-d') }}">
                                                            <input type="hidden" name="{{ $inputName }}[shift]" value="{{ $shift }}">
                                                        @endforeach
                                                    </div>
                                                @else
                                                    {{-- Shift-Specific Assignment (Night Only) --}}
                                                    <div class="space-y-2">
                                                        @foreach($duty['shifts'] as $shift)
                                                            @php
                                                                $key = $duty['key'] . '|' . $date->format('Y-m-d') . '|' . $shift;
                                                                $initialValue = $existingDuties[$key] ?? '';
                                                                $inputName = 'duties[' . $duty['key'] . '_' . $date->format('Y-m-d') . '_' . $shift . ']';
                                                            @endphp
                                                            <div x-data="{ selectedNurse: '{{ $initialValue }}' }" class="flex items-center gap-1 w-full">
                                                                <span class="shift-badge shift-{{ strtolower($shift) }}">{{ $shift }}</span>
                                                                
                                                                <div x-data="searchableSelect({
                                                                        options: nurses,
                                                                        value: '{{ $initialValue }}',
                                                                        placeholder: 'Select...',
                                                                        onSelect: (val) => selectedNurse = val
                                                                    })" class="relative flex-1 min-w-[100px]">
                                                                    
                                                                    <div class="relative">
                                                                        <input type="text"
                                                                            x-model="search"
                                                                            @focus="open = true"
                                                                            @click.away="open = false"
                                                                            @keydown.escape="open = false"
                                                                            @keydown.down.prevent="focusNext()"
                                                                            @keydown.up.prevent="focusPrev()"
                                                                            @keydown.enter.prevent="selectFocused()"
                                                                            autocomplete="off"
                                                                            class="w-full text-xs border-gray-200 rounded-md focus:border-purple-400 focus:ring-purple-400 py-1 pl-2 pr-6"
                                                                            :placeholder="selectedName || placeholder">
                                                                        <div class="absolute inset-y-0 right-0 flex items-center pr-1 pointer-events-none">
                                                                            <svg class="h-3 w-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                                            </svg>
                                                                        </div>
                                                                    </div>
                                                                    
                                                                    <div x-show="open" 
                                                                         class="searchable-select-dropdown" 
                                                                         style="display: none;">
                                                                        <template x-for="(option, index) in filteredOptions" :key="option.id">
                                                                            <div class="searchable-select-option"
                                                                                 :class="{ 'selected': option.id == value, 'focused': index === focusedIndex }"
                                                                                 @click="selectOption(option)"
                                                                                 @mouseenter="focusedIndex = index">
                                                                                <span x-text="option.name"></span>
                                                                            </div>
                                                                        </template>
                                                                        <div x-show="filteredOptions.length === 0" class="p-2 text-xs text-gray-500">
                                                                            No nurse found
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <input type="hidden" name="{{ $inputName }}[nurse_id]" :value="selectedNurse">
                                                                <input type="hidden" name="{{ $inputName }}[duty_type]" value="{{ $duty['key'] }}">
                                                                <input type="hidden" name="{{ $inputName }}[date]" value="{{ $date->format('Y-m-d') }}">
                                                                <input type="hidden" name="{{ $inputName }}[shift]" value="{{ $shift }}">
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </form>

            <div class="mt-6 bg-white/70 rounded-xl p-4 border border-amber-200">
                <h4 class="text-sm font-semibold text-amber-800 mb-2">Legend</h4>
                <div class="flex flex-wrap gap-4 text-xs">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                        <span class="text-gray-600">Daily Duty (Covers all 3 shifts)</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                        <span class="text-gray-600">Night Only Duties</span>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
             Alpine.data('specialDutyApp', () => ({
                // Prepend a "None" option for clearing assignments
                nurses: [{ id: '', name: '— None (Unassigned) —' }, ...@json($nurses)],
            }));

            Alpine.data('searchableSelect', (config) => ({
                options: config.options || [],
                value: config.value || '',
                placeholder: config.placeholder || 'Select',
                search: '',
                open: false,
                focusedIndex: -1,
                
                init() {
                    // Pre-fill search with current selected name if value exists
                    if (this.value) {
                        const selected = this.options.find(o => o.id == this.value);
                        if (selected) {
                            this.search = selected.name;
                        }
                    }
                    
                    this.$watch('value', (val) => {
                         const selected = this.options.find(o => o.id == val);
                         if (selected) {
                             this.search = selected.name;
                         } else {
                             this.search = '';
                         }
                         if (config.onSelect) {
                             config.onSelect(val);
                         }
                    });
                },

                get filteredOptions() {
                    if (this.search === '') {
                        return this.options;
                    }
                    // If the search exactly matches the selected item, show all options (allows changing selection easily)
                    const selected = this.options.find(o => o.id == this.value);
                    if (selected && selected.name === this.search) {
                         return this.options;
                    }
                    
                    return this.options.filter(option => 
                        option.name.toLowerCase().includes(this.search.toLowerCase())
                    );
                },
                
                get selectedName() {
                    const option = this.options.find(o => o.id == this.value);
                    return option ? option.name : '';
                },

                selectOption(option) {
                    this.value = option.id;
                    this.search = option.name;
                    this.open = false;
                    this.focusedIndex = -1;
                },

                focusNext() {
                    if (!this.open) { this.open = true; return; }
                    this.focusedIndex = Math.min(this.focusedIndex + 1, this.filteredOptions.length - 1);
                    this.scrollToFocused();
                },

                focusPrev() {
                    if (!this.open) { this.open = true; return; }
                    this.focusedIndex = Math.max(this.focusedIndex - 1, 0);
                    this.scrollToFocused();
                },

                selectFocused() {
                    if (this.focusedIndex >= 0 && this.focusedIndex < this.filteredOptions.length) {
                        this.selectOption(this.filteredOptions[this.focusedIndex]);
                    } else if (this.filteredOptions.length === 1) {
                         this.selectOption(this.filteredOptions[0]);
                    }
                },
                
                scrollToFocused() {
                     // logic to scroll to the focused element if needed
                }
            }));
        });
    </script>
</body>
</html>
