{{--
    Add or edit one clinical privilege on the Credentialing and Privileging
    tab. Expects $nurse, $privilege (null to add), $formId (tells a failed
    save's old input which form it belongs to) and $credentialing.
--}}
@php
    $isNew = $privilege === null;
    $useOld = old('_form') === $formId;
    $value = fn (string $key, $default = null) => $useOld ? old("privilege.{$key}", $default) : $default;
    $fieldError = fn (string $key) => $useOld ? $errors->first("privilege.{$key}") : null;
    $id = fn (string $key) => "{$formId}-{$key}";

    $today = $credentialing['today']->format('Y-m-d');
    // One from the built-in list keeps the list's name and level
    $listed = $privilege ? \App\Support\NursePrivilegeCatalogue::item($privilege->catalogueCode()) : null;
    $status = $value('status', $privilege?->status ?? 'granted');
    $category = $listed['level'] ?? $value('category', $privilege?->category ?? 'advanced');
    $categories = \App\Models\NursePrivilege::CATEGORIES;
    $reasonRequired = \App\Models\NursePrivilege::REASON_REQUIRED;
@endphp

<form method="POST"
    action="{{ $isNew ? route('nurses.privileges.store', $nurse) : route('nurses.privileges.update', [$nurse, $privilege]) }}"
    class="rounded-xl border border-blue-100 bg-blue-50/40 p-5 space-y-4"
    x-data="{
        status: @js($status),
        category: @js($category),
        start: { status: @js($status), category: @js($category) },
        catalogue: @js(\App\Models\NursePrivilege::catalogueIndex()),
        hints: @js(array_map(fn ($entry) => $entry['hint'], $categories)),
        {{-- A privilege picked from the list brings its category with it --}}
        pickCategory(name) {
            const known = this.catalogue[name.trim().toLowerCase()];
            if (known) this.category = known;
        },
        {{-- Review date a number of years after the date granted (or today) --}}
        reviewIn(years) {
            const granted = this.$refs.granted_on.value;
            const date = granted ? new Date(granted + 'T00:00:00') : new Date();
            date.setFullYear(date.getFullYear() + years);
            const pad = (n) => String(n).padStart(2, '0');
            this.$refs.review_on.value = date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
        },
        needsReason() {
            return @js($reasonRequired).includes(this.status);
        },
        cancel(form) {
            form.reset();
            this.status = this.start.status;
            this.category = this.start.category;
        },
    }"
    @submit="discardChanges() || $event.preventDefault()">
    @csrf
    @unless($isNew)
        @method('PUT')
    @endunless
    <input type="hidden" name="active_tab" value="credentialing">
    <input type="hidden" name="_form" value="{{ $formId }}">

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
            <label for="{{ $id('name') }}" class="{{ $label }}">Privilege <span class="text-red-500">*</span></label>
            @if($listed)
                <input type="text" name="privilege[name]" id="{{ $id('name') }}" value="{{ $listed['name'] }}" readonly
                    class="{{ $field }} bg-gray-50 text-gray-600">
                <p class="{{ $hint }}">Named by the built-in list.</p>
            @else
                <input type="text" name="privilege[name]" id="{{ $id('name') }}" list="privilege-names"
                    value="{{ $value('name', $privilege?->name) }}" @input="pickCategory($event.target.value)" required
                    maxlength="150" autocomplete="off" placeholder="e.g. Plaster of Paris application"
                    class="{{ $field }}">
                <p class="{{ $hint }}">Type your own, or pick from the list.</p>
            @endif
            @if($msg = $fieldError('name'))
                <p class="{{ $error }}">{{ $msg }}</p>
            @endif
        </div>

        <div>
            <label for="{{ $id('category') }}" class="{{ $label }}">Level <span class="text-red-500">*</span></label>
            @if($listed)
                <input type="hidden" name="privilege[category]" value="{{ $listed['level'] }}">
                <input type="text" id="{{ $id('category') }}" value="{{ $categories[$listed['level']]['label'] }}" readonly
                    class="{{ $field }} bg-gray-50 text-gray-600">
                <p class="{{ $hint }}">{{ $categories[$listed['level']]['hint'] }}, as set by the built-in list.</p>
            @else
                <select name="privilege[category]" id="{{ $id('category') }}" x-model="category" required
                    class="{{ $field }}">
                    @foreach($categories as $key => $meta)
                        <option value="{{ $key }}" @selected($category === $key)>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
                <p class="{{ $hint }}" x-text="hints[category]">{{ $categories[$category]['hint'] ?? '' }}</p>
            @endif
            @if($msg = $fieldError('category'))
                <p class="{{ $error }}">{{ $msg }}</p>
            @endif
        </div>

        <div>
            <label for="{{ $id('status') }}" class="{{ $label }}">Status <span class="text-red-500">*</span></label>
            <select name="privilege[status]" id="{{ $id('status') }}" x-model="status" required class="{{ $field }}">
                @foreach(\App\Models\NursePrivilege::STATUSES as $key => $meta)
                    <option value="{{ $key }}" @selected($status === $key)>{{ $meta['label'] }}: {{ lcfirst($meta['hint']) }}</option>
                @endforeach
            </select>
            @if($msg = $fieldError('status'))
                <p class="{{ $error }}">{{ $msg }}</p>
            @endif
        </div>

        <div>
            <label for="{{ $id('granted_on') }}" class="{{ $label }}">Date granted</label>
            <input type="date" name="privilege[granted_on]" id="{{ $id('granted_on') }}" x-ref="granted_on"
                value="{{ $value('granted_on', $privilege?->granted_on?->format('Y-m-d') ?? ($isNew ? $today : null)) }}"
                max="{{ $today }}" class="{{ $field }}">
            @if($msg = $fieldError('granted_on'))
                <p class="{{ $error }}">{{ $msg }}</p>
            @endif
        </div>

        <div>
            <label for="{{ $id('review_on') }}" class="{{ $label }}">Review due</label>
            <input type="date" name="privilege[review_on]" id="{{ $id('review_on') }}" x-ref="review_on"
                value="{{ $value('review_on', $privilege?->review_on?->format('Y-m-d')) }}" class="{{ $field }}">
            <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500">
                <span>Set to</span>
                @foreach([1, 2, 3] as $years)
                    <button type="button" @click="reviewIn({{ $years }})"
                        class="rounded-md border border-blue-200 bg-white px-2 py-0.5 font-medium text-blue-700 hover:bg-blue-50">
                        {{ $years }} {{ Str::plural('year', $years) }}
                    </button>
                @endforeach
                <span>after the date granted</span>
            </p>
            @if($msg = $fieldError('review_on'))
                <p class="{{ $error }}">{{ $msg }}</p>
            @endif
        </div>

        <div class="md:col-span-2">
            <label for="{{ $id('approved_by') }}" class="{{ $label }}">Approved by</label>
            <input type="text" name="privilege[approved_by]" id="{{ $id('approved_by') }}" list="privilege-approvers"
                value="{{ $value('approved_by', $privilege?->approved_by) }}" maxlength="150" autocomplete="off"
                placeholder="e.g. {{ \App\Models\NursePrivilege::DEFAULT_APPROVER }}" class="{{ $field }}">
            @if($msg = $fieldError('approved_by'))
                <p class="{{ $error }}">{{ $msg }}</p>
            @endif
        </div>

        <div class="md:col-span-2">
            <label for="{{ $id('notes') }}" class="{{ $label }}">
                <span x-text="needsReason() ? 'Reason' : (status === 'supervised' ? 'Conditions of supervision' : 'Notes')">Notes</span>
                <span class="text-red-500" x-show="needsReason()" x-cloak>*</span>
            </label>
            <textarea name="privilege[notes]" id="{{ $id('notes') }}" rows="2" maxlength="1000" :required="needsReason()"
                :placeholder="needsReason() ? 'Why the privilege is being stopped' : (status === 'supervised' ? 'e.g. who supervises, and what must be done before it is granted in full' : '')"
                class="{{ $field }}">{{ $value('notes', $privilege?->notes) }}</textarea>
            @if($msg = $fieldError('notes'))
                <p class="{{ $error }}">{{ $msg }}</p>
            @endif
        </div>
    </div>

    <div class="flex justify-end gap-2">
        <button type="button" @click="cancel($el.form); open = null"
            class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg font-semibold text-sm text-gray-700 hover:bg-gray-50 shadow-sm transition">
            Cancel
        </button>
        <button type="submit"
            class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 rounded-lg font-semibold text-sm text-white shadow-md transition-all">
            {{ $isNew ? 'Add privilege' : 'Save changes' }}
        </button>
    </div>
</form>
