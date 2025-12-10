<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shift Settings</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="p-6" x-data="{
        shifts: @js($shifts->map(fn($s) => [
            'id' => $s->id,
            'shift_code' => $s->shift_code,
            'shift_name' => $s->shift_name,
            'start_time' => substr($s->start_time, 0, 5),
            'end_time' => substr($s->end_time, 0, 5),
            'is_active' => $s->is_active,
        ])->toArray()),
        wardId: {{ $ward->id ?? 'null' }},
        saving: false,
        formatTime(time) {
            if (!time) return '';
            const [hours, minutes] = time.split(':');
            const h = parseInt(hours);
            const ampm = h >= 12 ? 'PM' : 'AM';
            const displayHour = h % 12 || 12;
            return displayHour + ':' + minutes + ' ' + ampm;
        }
    }">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold text-gray-800">Shift Settings</h1>
                    <p class="text-sm text-gray-500 mt-1">Configure shift times for ward scheduling</p>
                </div>
                @if($ward)
                <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-semibold">
                    {{ $ward->ward_name }} ({{ $ward->ward_code }})
                </span>
                @endif
            </div>
        </div>

        @if (session('success'))
            <div class="mb-4 flex items-start gap-3 p-4 rounded-xl bg-green-50 border border-green-200 text-green-800">
                <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <div>
                    <p class="font-semibold">{{ session('success') }}</p>
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-4 flex items-start gap-3 p-4 rounded-xl bg-red-50 border border-red-200 text-red-800">
                <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                <div>
                    <p class="font-semibold">{{ session('error') }}</p>
                </div>
            </div>
        @endif

        @if($ward)
        <!-- Shift Cards -->
        <div class="space-y-4">
            <template x-for="(shift, index) in shifts" :key="index">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 hover:shadow-md transition-shadow">
                    <div class="flex items-start justify-between gap-4">
                        <!-- Shift Badge -->
                        <div class="flex items-center gap-3">
                            <div class="w-14 h-14 rounded-xl flex items-center justify-center text-white font-bold text-lg"
                                 :class="{
                                    'bg-gradient-to-br from-amber-400 to-orange-500': shift.shift_code === 'AM',
                                    'bg-gradient-to-br from-blue-400 to-cyan-500': shift.shift_code === 'PM',
                                    'bg-gradient-to-br from-indigo-500 to-purple-600': shift.shift_code === 'ON'
                                 }">
                                <span x-text="shift.shift_code"></span>
                            </div>
                            <div>
                                <input type="text" 
                                       x-model="shift.shift_name"
                                       class="font-semibold text-gray-800 text-lg border-0 border-b-2 border-transparent focus:border-blue-500 focus:ring-0 p-0 bg-transparent"
                                       placeholder="Shift Name">
                                <div class="text-sm text-gray-500 mt-1">
                                    <span x-text="formatTime(shift.start_time)"></span> - <span x-text="formatTime(shift.end_time)"></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Active Toggle -->
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-500">Active</span>
                            <button type="button"
                                    @click="shift.is_active = !shift.is_active"
                                    :class="shift.is_active ? 'bg-green-500' : 'bg-gray-300'"
                                    class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                                <span :class="shift.is_active ? 'translate-x-5' : 'translate-x-0'"
                                      class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Time Settings -->
                    <div class="mt-4 grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">Start Time</label>
                            <input type="time" 
                                   x-model="shift.start_time"
                                   class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1">End Time</label>
                            <input type="time" 
                                   x-model="shift.end_time"
                                   class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>

                    <!-- Shift Code (hidden but editable if needed) -->
                    <div class="mt-3">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Shift Code</label>
                        <input type="text" 
                               x-model="shift.shift_code"
                               class="w-24 rounded-lg border-gray-200 text-sm shadow-sm focus:ring-blue-500 focus:border-blue-500 bg-gray-50"
                               maxlength="10">
                    </div>
                </div>
            </template>
        </div>

        <!-- Info Box -->
        <div class="mt-6 bg-blue-50 border border-blue-100 rounded-xl p-4">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-blue-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div class="text-sm text-blue-800">
                    <p class="font-semibold mb-1">Shift Time Configuration</p>
                    <ul class="list-disc list-inside space-y-1 text-blue-700">
                        <li><strong>AM (Morning):</strong> Default 7:00 AM - 2:00 PM</li>
                        <li><strong>PM (Afternoon):</strong> Default 2:00 PM - 11:00 PM</li>
                        <li><strong>ON (Night/Overnight):</strong> Default 11:00 PM - 7:00 AM</li>
                    </ul>
                    <p class="mt-2 text-blue-600">The nurse assigned to a bed during the current shift will be displayed on the Ward Dashboard.</p>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="mt-6 flex items-center justify-between">
            <form action="{{ route('ward.shift-settings.reset') }}" method="POST" class="inline"
                  onsubmit="return confirm('Are you sure you want to reset all shifts to defaults?')">
                @csrf
                <input type="hidden" name="ward_id" value="{{ $ward->id }}">
                <button type="submit" 
                        class="px-4 py-2 text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-lg transition-colors text-sm font-medium">
                    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Reset to Defaults
                </button>
            </form>

            <form action="{{ route('ward.shift-settings.update') }}" method="POST" x-ref="saveForm">
                @csrf
                <input type="hidden" name="ward_id" value="{{ $ward->id }}">
                <template x-for="(shift, index) in shifts" :key="index">
                    <div>
                        <input type="hidden" :name="'shifts[' + index + '][id]'" :value="shift.id">
                        <input type="hidden" :name="'shifts[' + index + '][shift_code]'" :value="shift.shift_code">
                        <input type="hidden" :name="'shifts[' + index + '][shift_name]'" :value="shift.shift_name">
                        <input type="hidden" :name="'shifts[' + index + '][start_time]'" :value="shift.start_time">
                        <input type="hidden" :name="'shifts[' + index + '][end_time]'" :value="shift.end_time">
                        <input type="hidden" :name="'shifts[' + index + '][is_active]'" :value="shift.is_active ? '1' : '0'">
                    </div>
                </template>
                <button type="submit" 
                        :disabled="saving"
                        @click="saving = true"
                        class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold shadow-sm transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg x-show="saving" class="w-4 h-4 inline-block mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span x-text="saving ? 'Saving...' : 'Save Settings'"></span>
                </button>
            </form>
        </div>
        @else
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-6 text-center">
            <svg class="w-12 h-12 text-amber-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.455 19h13.09c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.723 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <p class="text-amber-800 font-semibold">No ward selected</p>
            <p class="text-amber-700 text-sm mt-1">Please select a ward to configure shift settings.</p>
        </div>
        @endif
    </div>
</body>
</html>

