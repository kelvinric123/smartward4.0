{{-- Add / correct / remove a reading. Shown in the patient details vitals tab (?edit=1);
     the ward dashboard modal loads the same page read-only. --}}
@php
    $fieldClass = 'w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-rose-500 focus:ring-rose-500';
    $labelClass = 'block text-xs font-semibold text-gray-600 mb-1';
@endphp

{{-- Record / edit --}}
<div x-show="editorMode === 'add' || editorMode === 'edit'" x-cloak
    class="mb-4 rounded-lg border border-rose-200 bg-rose-50/60 p-4">
    <form method="POST"
        :action="editorMode === 'edit' ? '{{ url('/vital-signs') }}/' + editorForm.id : '{{ route('vital-signs.store') }}'">
        @csrf
        <template x-if="editorMode === 'edit'">
            <input type="hidden" name="_method" value="PUT">
        </template>
        <input type="hidden" name="patient_id" value="{{ $patient->id }}">

        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-bold text-gray-800"
                x-text="editorMode === 'edit' ? 'Edit Reading' : 'Record New Reading'"></h3>
            <span class="text-xs text-gray-500">{{ $patient->name }} &middot; MRN {{ $patient->mrn }}</span>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="col-span-2 md:col-span-1">
                <label class="{{ $labelClass }}">Date / Time</label>
                <input type="datetime-local" name="recorded_at" x-model="editorForm.recorded_at" class="{{ $fieldClass }}">
            </div>
            <div>
                <label class="{{ $labelClass }}">Temperature (°C)</label>
                <input type="number" step="0.1" min="30" max="45" name="temperature" x-model="editorForm.temperature"
                    placeholder="36.8" class="{{ $fieldClass }}">
            </div>
            <div>
                <label class="{{ $labelClass }}">Systolic BP</label>
                <input type="number" min="40" max="300" name="systolic_bp" x-model="editorForm.systolic_bp"
                    placeholder="120" class="{{ $fieldClass }}">
            </div>
            <div>
                <label class="{{ $labelClass }}">Diastolic BP</label>
                <input type="number" min="20" max="200" name="diastolic_bp" x-model="editorForm.diastolic_bp"
                    placeholder="80" class="{{ $fieldClass }}">
            </div>
            <div>
                <label class="{{ $labelClass }}">Pulse (bpm)</label>
                <input type="number" min="20" max="250" name="pulse_rate" x-model="editorForm.pulse_rate"
                    placeholder="78" class="{{ $fieldClass }}">
            </div>
            <div>
                <label class="{{ $labelClass }}">Resp. Rate (/min)</label>
                <input type="number" min="5" max="60" name="respiratory_rate" x-model="editorForm.respiratory_rate"
                    placeholder="16" class="{{ $fieldClass }}">
            </div>
            <div>
                <label class="{{ $labelClass }}">SpO₂ (%)</label>
                <input type="number" min="50" max="100" name="spo2" x-model="editorForm.spo2"
                    placeholder="98" class="{{ $fieldClass }}">
            </div>
        </div>

        {{-- Oxygen delivery --}}
        <div class="mt-4 pt-3 border-t border-rose-200">
            <p class="text-xs font-bold text-gray-700 mb-2">Oxygen Status</p>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="col-span-2">
                    <label class="{{ $labelClass }}">Delivery</label>
                    <select name="oxygen_delivery" x-model="editorForm.oxygen_delivery" class="{{ $fieldClass }}">
                        <option value="">Not recorded</option>
                        @foreach(\App\Models\VitalSign::OXYGEN_DELIVERY_OPTIONS as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Flow (L/min)</label>
                    <input type="number" step="0.5" min="0" max="100" name="oxygen_flow_rate"
                        x-model="editorForm.oxygen_flow_rate" :disabled="!onOxygen()"
                        :class="onOxygen() ? '' : 'bg-gray-100 cursor-not-allowed'"
                        placeholder="2" class="{{ $fieldClass }}">
                </div>
                <div>
                    <label class="{{ $labelClass }}">FiO₂ (%)</label>
                    <input type="number" min="21" max="100" name="fio2_percent"
                        x-model="editorForm.fio2_percent" :disabled="!onOxygen()"
                        :class="onOxygen() ? '' : 'bg-gray-100 cursor-not-allowed'"
                        placeholder="40" class="{{ $fieldClass }}">
                </div>
            </div>
            <p class="mt-1 text-[11px] text-gray-500" x-show="!onOxygen()">
                Flow rate and FiO₂ apply once a delivery device other than room air is selected.
            </p>
            @if (!empty($oxygenPrefill))
                <p class="mt-1 text-[11px] text-sky-700" x-show="editorMode === 'add'" x-cloak>
                    Filled in from the oxygen the patient is on now ({{ $currentOxygen['short'] }}).
                    If it has changed, record the new oxygen here and Oxygen Therapy will show it.
                </p>
            @endif
        </div>

        <div class="mt-3">
            <label class="{{ $labelClass }}">Notes</label>
            <textarea name="notes" rows="2" x-model="editorForm.notes" maxlength="500"
                placeholder="Optional" class="{{ $fieldClass }}"></textarea>
        </div>

        {{-- Editing an existing reading needs the same passphrase as deleting one --}}
        <div class="mt-3" x-show="editorMode === 'edit'" x-cloak>
            <label class="{{ $labelClass }}">Passphrase <span class="text-red-500">*</span></label>
            <input type="password" name="delete_passphrase" x-model="passphrase" autocomplete="new-password"
                :required="editorMode === 'edit'" placeholder="Required to change a recorded reading"
                class="{{ $fieldClass }} md:w-1/2">
        </div>

        <div class="mt-4 flex items-center justify-end gap-2">
            <button type="button" @click="closeEditor()"
                class="px-3 py-1.5 rounded-md bg-white border border-gray-300 text-gray-700 text-sm font-semibold hover:bg-gray-50">
                Cancel
            </button>
            <button type="submit"
                class="px-4 py-1.5 rounded-md bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold shadow-sm"
                x-text="editorMode === 'edit' ? 'Save Changes' : 'Save Reading'"></button>
        </div>
    </form>
</div>

{{-- Remove a reading --}}
<div x-show="editorMode === 'delete'" x-cloak class="mb-4 rounded-lg border border-red-300 bg-red-50 p-4">
    <form method="POST" :action="'{{ url('/vital-signs') }}/' + deleteId">
        @csrf
        @method('DELETE')
        <h3 class="text-sm font-bold text-red-800 mb-1">Remove this reading?</h3>
        <p class="text-xs text-red-700 mb-3">The reading is kept in the audit trail but no longer shown on the chart.</p>
        <div class="flex flex-wrap items-end gap-2">
            <div class="flex-1 min-w-[12rem]">
                <label class="{{ $labelClass }}">Passphrase <span class="text-red-500">*</span></label>
                <input type="password" name="delete_passphrase" x-model="passphrase" required
                    autocomplete="new-password" placeholder="Required to remove a reading" class="{{ $fieldClass }}">
            </div>
            <button type="button" @click="closeEditor()"
                class="px-3 py-1.5 rounded-md bg-white border border-gray-300 text-gray-700 text-sm font-semibold hover:bg-gray-50">
                Cancel
            </button>
            <button type="submit"
                class="px-4 py-1.5 rounded-md bg-red-600 hover:bg-red-700 text-white text-sm font-semibold shadow-sm">
                Remove Reading
            </button>
        </div>
    </form>
</div>
