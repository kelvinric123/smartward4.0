{{--
    Add or edit one credential on the Credentialing and Privileging tab.
    Expects $nurse, $credential (null to add), $formId (tells a failed save's
    old input which form it belongs to) and $credentialing.
--}}
@php
    $isNew = $credential === null;
    $useOld = old('_form') === $formId;
    $value = fn (string $key, $default = null) => $useOld ? old("credential.{$key}", $default) : $default;
    $fieldError = fn (string $key) => $useOld ? $errors->first("credential.{$key}") : null;
    $id = fn (string $key) => "{$formId}-{$key}";

    $type = $value('type', $credential?->type ?? 'apc');
    $verified = $useOld ? (bool) old('credential.verified') : (bool) $credential?->isVerified();
    $expiring = \App\Models\NurseCredential::EXPIRING_TYPES;
@endphp

<form method="POST"
    action="{{ $isNew ? route('nurses.credentials.store', $nurse) : route('nurses.credentials.update', [$nurse, $credential]) }}"
    class="rounded-xl border border-blue-100 bg-blue-50/40 p-5 space-y-4"
    x-data="{
        type: @js($type),
        startType: @js($type),
        presets: @js($credentialing['presets']),
        filled: {},
        {{-- Fill in what a type always has (the APC's title, board and
             31 Dec expiry), taking back earlier fills nobody has changed --}}
        prefill() {
            for (const [field, value] of Object.entries(this.filled)) {
                if (this.$refs[field].value === value) this.$refs[field].value = '';
            }
            this.filled = {};
            for (const [field, value] of Object.entries(this.presets[this.type] || {})) {
                if (!this.$refs[field].value) {
                    this.$refs[field].value = value;
                    this.filled[field] = value;
                }
            }
        },
        cancel(form) {
            form.reset();
            this.type = this.startType;
            this.filled = {};
            @if($isNew && !$useOld) this.prefill(); @endif
        },
    }"
    @if($isNew && !$useOld) x-init="prefill()" @endif
    @submit="discardChanges() || $event.preventDefault()">
    @csrf
    @unless($isNew)
        @method('PUT')
    @endunless
    <input type="hidden" name="active_tab" value="credentialing">
    <input type="hidden" name="_form" value="{{ $formId }}">

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label for="{{ $id('type') }}" class="{{ $label }}">Type <span class="text-red-500">*</span></label>
            <select name="credential[type]" id="{{ $id('type') }}" x-model="type" @change="prefill()" required
                class="{{ $field }}">
                @foreach(\App\Models\NurseCredential::TYPES as $key => $meta)
                    <option value="{{ $key }}" @selected($type === $key)>{{ $meta['label'] }}</option>
                @endforeach
            </select>
            @if($msg = $fieldError('type'))
                <p class="{{ $error }}">{{ $msg }}</p>
            @endif
        </div>

        <div>
            <label for="{{ $id('title') }}" class="{{ $label }}">Title <span class="text-red-500">*</span></label>
            <input type="text" name="credential[title]" id="{{ $id('title') }}" x-ref="title"
                :list="'credential-titles-' + type" value="{{ $value('title', $credential?->title) }}" required
                maxlength="150" autocomplete="off" placeholder="Pick from the list or type your own"
                class="{{ $field }}">
            @if($msg = $fieldError('title'))
                <p class="{{ $error }}">{{ $msg }}</p>
            @endif
        </div>

        <div>
            <label for="{{ $id('reference_number') }}" class="{{ $label }}">Certificate / reference no.</label>
            <input type="text" name="credential[reference_number]" id="{{ $id('reference_number') }}"
                value="{{ $value('reference_number', $credential?->reference_number) }}" maxlength="100"
                autocomplete="off" class="{{ $field }}">
            @if($msg = $fieldError('reference_number'))
                <p class="{{ $error }}">{{ $msg }}</p>
            @endif
        </div>

        <div>
            <label for="{{ $id('issuing_body') }}" class="{{ $label }}">Issued by</label>
            <input type="text" name="credential[issuing_body]" id="{{ $id('issuing_body') }}" x-ref="issuing_body"
                list="credential-issuers" value="{{ $value('issuing_body', $credential?->issuing_body) }}"
                maxlength="150" autocomplete="off" class="{{ $field }}">
            @if($msg = $fieldError('issuing_body'))
                <p class="{{ $error }}">{{ $msg }}</p>
            @endif
        </div>

        <div>
            <label for="{{ $id('issued_on') }}" class="{{ $label }}">Issue date</label>
            <input type="date" name="credential[issued_on]" id="{{ $id('issued_on') }}"
                value="{{ $value('issued_on', $credential?->issued_on?->format('Y-m-d')) }}"
                max="{{ $credentialing['today']->format('Y-m-d') }}" class="{{ $field }}">
            @if($msg = $fieldError('issued_on'))
                <p class="{{ $error }}">{{ $msg }}</p>
            @endif
        </div>

        <div>
            <label for="{{ $id('expires_on') }}" class="{{ $label }}">
                Expiry date <span class="text-red-500" x-show="@js($expiring).includes(type)">*</span>
            </label>
            <input type="date" name="credential[expires_on]" id="{{ $id('expires_on') }}" x-ref="expires_on"
                value="{{ $value('expires_on', $credential?->expires_on?->format('Y-m-d')) }}"
                :required="@js($expiring).includes(type)" class="{{ $field }}">
            <p class="{{ $hint }}" x-show="type === 'apc'">An APC runs for the calendar year it is issued for.</p>
            <p class="{{ $hint }}" x-show="type === 'life_support'" x-cloak>Most life support certificates are valid
                for 2 years.</p>
            <p class="{{ $hint }}" x-show="!@js($expiring).includes(type)" x-cloak>Leave blank if it never expires.</p>
            @if($msg = $fieldError('expires_on'))
                <p class="{{ $error }}">{{ $msg }}</p>
            @endif
        </div>
    </div>

    <label class="flex items-start gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3 cursor-pointer">
        <input type="checkbox" name="credential[verified]" value="1" @checked($verified)
            class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
        <span class="text-sm text-gray-700">
            <span class="font-medium">I have sighted the original document</span>
            <span class="block text-xs text-gray-500">
                @if($credential?->isVerified())
                    Verified by {{ $credential->verifiedBy->name ?? 'a former user' }} on {{ $credential->verified_at->format('j M Y') }}. Untick to clear it.
                @else
                    Records you as the verifier, with today's date.
                @endif
            </span>
        </span>
    </label>

    <div>
        <label for="{{ $id('notes') }}" class="{{ $label }}">Notes</label>
        <textarea name="credential[notes]" id="{{ $id('notes') }}" rows="2" maxlength="1000"
            class="{{ $field }}">{{ $value('notes', $credential?->notes) }}</textarea>
        @if($msg = $fieldError('notes'))
            <p class="{{ $error }}">{{ $msg }}</p>
        @endif
    </div>

    <div class="flex justify-end gap-2">
        <button type="button" @click="cancel($el.form); open = null"
            class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg font-semibold text-sm text-gray-700 hover:bg-gray-50 shadow-sm transition">
            Cancel
        </button>
        <button type="submit"
            class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 rounded-lg font-semibold text-sm text-white shadow-md transition-all">
            {{ $isNew ? 'Add credential' : 'Save changes' }}
        </button>
    </div>
</form>
