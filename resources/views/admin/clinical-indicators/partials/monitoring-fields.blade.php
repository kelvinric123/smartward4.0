{{--
    Monitoring interval fields for the clinical indicator forms. Expects
    $clinicalIndicator, null when adding one.
--}}

@php
    $monitoringEnabled = (bool) old('monitoring_enabled', $clinicalIndicator?->monitoring_enabled ?? false);
    $monitoringLevels = [
        'suggested' => [
            'label' => 'Suggested interval',
            'current' => \App\Support\ClinicalIndicatorMonitoring::splitInterval($clinicalIndicator?->monitoring_suggested_minutes),
            'dot' => 'bg-amber-400',
            'hint' => 'Shown as due, in amber, once this long has passed.',
        ],
        'warning' => [
            'label' => 'Warning level',
            'current' => \App\Support\ClinicalIndicatorMonitoring::splitInterval($clinicalIndicator?->monitoring_warning_minutes),
            'dot' => 'bg-red-500',
            'hint' => 'Shown as overdue, in red, once this long has passed.',
        ],
    ];
@endphp

<div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-4" x-data="{ enabled: @js($monitoringEnabled) }">
    <div class="flex items-start justify-between gap-4">
        <div>
            <span class="block text-sm font-medium text-gray-700">Monitoring interval</span>
            <p class="mt-1 text-xs text-gray-500">Flags patients on the ward dashboard when this scale has not been
                scored for a while, counted from their last score, or from admission before the first.</p>
        </div>
        <label class="relative inline-flex shrink-0 cursor-pointer items-center">
            <input type="hidden" name="monitoring_enabled" value="0">
            <input type="checkbox" name="monitoring_enabled" value="1" x-model="enabled" class="peer sr-only"
                @checked($monitoringEnabled)>
            <div
                class="w-11 h-6 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600">
            </div>
            <span class="ms-2 w-6 text-sm font-medium text-gray-700" x-text="enabled ? 'On' : 'Off'">{{ $monitoringEnabled ? 'On' : 'Off' }}</span>
        </label>
    </div>

    <div x-show="enabled" x-cloak class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
        @foreach ($monitoringLevels as $level => $field)
            <div>
                <label for="monitoring_{{ $level }}_value" class="flex items-center gap-2 text-sm font-medium text-gray-700">
                    <span class="h-2 w-2 rounded-full {{ $field['dot'] }}"></span>
                    {{ $field['label'] }}
                </label>
                <div class="mt-1 flex gap-2">
                    <input type="number" name="monitoring_{{ $level }}_value" id="monitoring_{{ $level }}_value"
                        min="1" value="{{ old('monitoring_' . $level . '_value', $field['current']['value']) }}"
                        :required="enabled"
                        class="block w-28 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <select name="monitoring_{{ $level }}_unit" aria-label="{{ $field['label'] }} unit"
                        class="block rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @foreach (array_keys(\App\Support\ClinicalIndicatorMonitoring::UNITS) as $unit)
                            <option value="{{ $unit }}"
                                @selected(old('monitoring_' . $level . '_unit', $field['current']['unit']) === $unit)>
                                {{ $unit }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <p class="mt-1 text-xs text-gray-500">{{ $field['hint'] }}</p>
                @error('monitoring_' . $level . '_value')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                @error('monitoring_' . $level . '_unit')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        @endforeach
    </div>
</div>
