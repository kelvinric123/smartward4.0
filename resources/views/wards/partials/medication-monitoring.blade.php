{{--
    Patient Details > Medications: medication orders monitored by interval.
    Expects $patient, $medicationOrders (PatientMedication::forPatient) and
    $medicationFormulary (active Medication rows).
--}}
@php
    $pm = \App\Models\PatientMedication::class;
    $ma = \App\Models\MedicationAdministration::class;
    $now = now();

    $stateOf = fn ($order) => $order->dueState($now);
    $sortKey = fn ($order) => [$pm::urgencyRank($stateOf($order)), $order->next_due_at?->getTimestamp() ?? PHP_INT_MAX];

    // Most urgent first: overdue, due soon, later, then PRN
    $activeOrders = $medicationOrders
        ->filter(fn ($order) => $order->isActive())
        ->sort(fn ($a, $b) => $sortKey($a) <=> $sortKey($b))
        ->values();
    $finishedOrders = $medicationOrders->reject(fn ($order) => $order->isActive())->values();

    // What the header badges and the overdue banner count, kept live in the browser
    $scheduledOrders = $activeOrders->reject(fn ($order) => $order->isPrn())->map(fn ($order) => [
        'id' => $order->id,
        'name' => $order->medication_name,
        'summary' => $order->summary(),
        'due' => $order->next_due_at?->getTimestampMs(),
        'dueAt' => $order->next_due_at?->format('d M H:i'),
        'stat' => $order->isStat(),
    ])->values();

    // A form that failed validation opens again with what was entered
    $failedForm = old('active_tab') === 'medications' ? old('_form') : null;
    $addFailed = $failedForm === 'add';

    $formularyData = $medicationFormulary->mapWithKeys(fn ($medication) => [
        (string) $medication->id => [
            'dose' => $medication->default_dose !== null ? $pm::formatAmount($medication->default_dose) : '',
            'unit' => $medication->dose_unit,
            'route' => $medication->default_route,
            'frequency' => $medication->default_frequency,
            'interval' => $medication->default_interval_hours !== null ? $pm::formatAmount($medication->default_interval_hours) : '',
            'caution' => $medication->caution,
            'highAlert' => $medication->is_high_alert,
        ],
    ]);

    $outcomeClasses = [
        $ma::STATUS_GIVEN => 'bg-emerald-600 border-emerald-600 text-white',
        $ma::STATUS_HELD => 'bg-amber-500 border-amber-500 text-white',
        $ma::STATUS_REFUSED => 'bg-red-600 border-red-600 text-white',
    ];

    $fieldClass = 'block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500';
    $labelClass = 'block text-xs font-semibold text-gray-700 mb-1';
@endphp

<div x-data="{
        now: Date.now(),
        adding: @js($addFailed || $medicationOrders->isEmpty()),
        orders: @js($scheduledOrders),
        init() { setInterval(() => this.now = Date.now(), 30000); },
        duration(minutes) {
            minutes = Math.max(0, minutes);
            if (minutes < 60) return minutes + 'm';
            if (minutes < 1440) return Math.floor(minutes / 60) + 'h ' + String(minutes % 60).padStart(2, '0') + 'm';
            return Math.floor(minutes / 1440) + 'd ' + Math.floor((minutes % 1440) / 60) + 'h';
        },
        stateOf(due) {
            if (due === null) return 'scheduled';
            if (this.now > due) return 'overdue';
            return due - this.now <= {{ $pm::DUE_SOON_MINUTES }} * 60000 ? 'due_soon' : 'scheduled';
        },
        dueText(due, stat) {
            if (due === null) return 'Scheduled';
            if (stat && this.stateOf(due) !== 'scheduled') return 'STAT - give now';
            if (this.now > due) return 'Overdue ' + this.duration(Math.floor((this.now - due) / 60000));
            return 'Due in ' + this.duration(Math.floor((due - this.now) / 60000));
        },
        localNow() {
            const d = new Date(), pad = n => String(n).padStart(2, '0');
            return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
        },
        get overdue() { return this.orders.filter(o => this.stateOf(o.due) === 'overdue'); },
        get dueSoon() { return this.orders.filter(o => this.stateOf(o.due) === 'due_soon'); },
    }">
    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <div class="flex flex-wrap items-center gap-2">
            <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                <svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.5 20.5l10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7z" />
                    <path d="M8.5 8.5l7 7" />
                </svg>
                Medication Monitoring
            </h3>
            <span x-show="overdue.length" x-cloak
                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-red-600 text-white"
                x-text="overdue.length + ' overdue'"></span>
            <span x-show="dueSoon.length" x-cloak
                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800"
                x-text="dueSoon.length + ' due soon'"></span>
            <span class="text-xs text-gray-500">{{ $activeOrders->count() }} active</span>
        </div>

        <button type="button" @click="adding = !adding"
            class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-md shadow-sm transition-colors"
            :class="adding ? 'bg-gray-200 text-gray-700 hover:bg-gray-300' : 'bg-blue-600 text-white hover:bg-blue-700'">
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    :d="adding ? 'M6 18L18 6M6 6l12 12' : 'M12 4v16m8-8H4'" />
            </svg>
            <span x-text="adding ? 'Cancel' : 'Add medication'">Add medication</span>
        </button>
    </div>

    {{-- Why the last form was not saved --}}
    @if ($failedForm && $errors->any())
        <div class="mb-3 rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-xs text-red-800">
            <div class="font-semibold">Not saved</div>
            <ul class="mt-0.5 list-disc ml-4">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Add medication --}}
    <div x-show="adding" x-cloak class="mb-4">
        <div x-data="{
                formulary: @js($formularyData),
                hasFormulary: @js($medicationFormulary->isNotEmpty()),
                medicationId: @js($addFailed ? (string) old('medication_id', '') : ''),
                custom: @js(($addFailed && !old('medication_id') && filled(old('medication_name'))) || $medicationFormulary->isEmpty()),
                dose: @js($addFailed ? (string) old('dose_amount', '') : ''),
                unit: @js($addFailed ? (string) old('dose_unit', 'mg') : 'mg'),
                route: @js($addFailed ? (string) old('route', 'PO') : 'PO'),
                frequency: @js($addFailed ? (string) old('frequency', 'od') : 'od'),
                interval: @js($addFailed ? (string) old('interval_hours', '') : ''),
                firstDose: @js($addFailed ? (string) old('first_dose', 'due_now') : 'due_now'),
                startAt: @js($addFailed ? (string) old('start_at', '') : ''),
                get selected() { return this.custom ? null : (this.formulary[this.medicationId] || null); },
                get needsInterval() { return this.frequency === 'custom' || this.frequency === 'prn'; },
                pick() {
                    if (this.medicationId === 'other') {
                        this.custom = true;
                        this.medicationId = '';
                        this.$nextTick(() => this.$refs.customName.focus());
                        return;
                    }
                    const m = this.selected;
                    if (!m) return;
                    this.dose = m.dose;
                    if (m.unit) this.unit = m.unit;
                    if (m.route) this.route = m.route;
                    if (m.frequency) this.frequency = m.frequency;
                    this.interval = m.interval || '';
                    this.syncFirstDose();
                },
                useList() { this.custom = false; this.medicationId = ''; },
                // PRN has nothing due, so a set due time does not apply
                syncFirstDose() { if (this.frequency === 'prn' && this.firstDose === 'due_at') this.firstDose = 'due_now'; },
                chooseDueAt() { if (!this.startAt) this.startAt = this.localNow(); },
            }" class="rounded-xl border border-blue-200 bg-blue-50/40 p-4">
            <form method="POST" action="{{ route('ward.medications.store') }}">
                @csrf
                <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                <input type="hidden" name="active_tab" value="medications">
                <input type="hidden" name="_form" value="add">

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    {{-- Medication: from the list, or typed in --}}
                    <div class="sm:col-span-2">
                        <label class="{{ $labelClass }}">Medication <span class="text-red-500">*</span></label>
                        <div x-show="!custom">
                            <select name="medication_id" x-model="medicationId" @change="pick()"
                                :disabled="custom" :required="!custom" class="{{ $fieldClass }}">
                                <option value="">Choose from the list...</option>
                                @foreach ($medicationFormulary->groupBy(fn ($medication) => $medication->category ?: 'Other') as $category => $items)
                                    <optgroup label="{{ $category }}">
                                        @foreach ($items as $item)
                                            <option value="{{ $item->id }}">
                                                {{ $item->name }}{{ $item->is_high_alert ? ' (high-alert)' : '' }} - {{ $item->defaultsLabel() }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                                <option value="other">Other - type the name</option>
                            </select>
                        </div>
                        <div x-show="custom" x-cloak class="flex items-center gap-2">
                            <input type="text" name="medication_name" x-ref="customName" maxlength="120"
                                :disabled="!custom" :required="custom" value="{{ $addFailed ? old('medication_name') : '' }}"
                                placeholder="Medication name" class="{{ $fieldClass }}">
                            <button type="button" x-show="hasFormulary" @click="useList()"
                                class="shrink-0 text-xs font-semibold text-blue-700 hover:underline">
                                Use the list
                            </button>
                        </div>
                        <template x-if="selected && (selected.caution || selected.highAlert)">
                            <p class="mt-1 text-[11px]" :class="selected.highAlert ? 'text-red-700' : 'text-blue-700'">
                                <span x-show="selected.highAlert" class="font-bold">High-alert medication. </span>
                                <span x-text="selected.caution"></span>
                            </p>
                        </template>
                    </div>

                    <div>
                        <label class="{{ $labelClass }}">Dose <span class="text-red-500">*</span></label>
                        <div class="flex items-stretch gap-2">
                            <input type="number" name="dose_amount" x-model="dose" step="any" min="0" required
                                placeholder="e.g. 500"
                                class="block w-full min-w-0 rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                            <select name="dose_unit" x-model="unit" required
                                class="w-24 shrink-0 rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                @foreach ($pm::DOSE_UNITS as $unit)
                                    <option value="{{ $unit }}">{{ $unit }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="{{ $labelClass }}">Route <span class="text-red-500">*</span></label>
                        <select name="route" x-model="route" required class="{{ $fieldClass }}">
                            @foreach ($pm::ROUTES as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="{{ $labelClass }}">Frequency <span class="text-red-500">*</span></label>
                        <select name="frequency" x-model="frequency" @change="syncFirstDose()" required class="{{ $fieldClass }}">
                            @foreach ($pm::FREQUENCIES as $code => $frequency)
                                <option value="{{ $code }}">{{ $frequency['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div x-show="needsInterval" x-cloak>
                        <label class="{{ $labelClass }}">
                            <span x-text="frequency === 'prn' ? 'Minimum gap (hours)' : 'Every (hours)'"></span>
                            <span x-show="frequency === 'custom'" class="text-red-500">*</span>
                        </label>
                        <input type="number" name="interval_hours" x-model="interval" step="0.5"
                            min="{{ $pm::MIN_INTERVAL_HOURS }}" max="{{ $pm::MAX_INTERVAL_HOURS }}"
                            :disabled="!needsInterval" :required="frequency === 'custom'" class="{{ $fieldClass }}">
                        <p class="mt-1 text-[11px] text-gray-500"
                            x-text="frequency === 'prn' ? 'Optional. Warns when a dose is recorded sooner.' : 'Time between doses.'"></p>
                    </div>

                    {{-- First dose --}}
                    <div class="sm:col-span-2 lg:col-span-4">
                        <label class="{{ $labelClass }}">First dose</label>
                        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-gray-700">
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="first_dose" value="due_now" x-model="firstDose"
                                    class="text-blue-600 focus:ring-blue-500">
                                <span x-text="frequency === 'prn' ? 'Not given yet' : 'Due now'">Due now</span>
                            </label>
                            <label class="inline-flex items-center gap-2 cursor-pointer" x-show="frequency !== 'prn'">
                                <input type="radio" name="first_dose" value="due_at" x-model="firstDose" @change="chooseDueAt()"
                                    class="text-blue-600 focus:ring-blue-500">
                                <span>Due at</span>
                            </label>
                            <input type="datetime-local" name="start_at" x-model="startAt"
                                x-show="firstDose === 'due_at' && frequency !== 'prn'" x-cloak
                                :disabled="firstDose !== 'due_at' || frequency === 'prn'"
                                :required="firstDose === 'due_at' && frequency !== 'prn'"
                                class="rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="first_dose" value="given_now" x-model="firstDose"
                                    class="text-blue-600 focus:ring-blue-500">
                                <span>Already given now <span class="text-gray-500">(records the first dose)</span></span>
                            </label>
                        </div>
                    </div>

                    <div class="sm:col-span-2 lg:col-span-4">
                        <label class="{{ $labelClass }}">Instructions</label>
                        <input type="text" name="instructions" maxlength="255"
                            value="{{ $addFailed ? old('instructions') : '' }}"
                            placeholder="e.g. give after food, check HGT before each dose" class="{{ $fieldClass }}">
                    </div>
                </div>

                <div class="mt-3 flex justify-end gap-2">
                    <button type="button" @click="adding = false"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-md hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-blue-700">
                        Add medication
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Overdue doses, worst first --}}
    <template x-if="overdue.length">
        <div class="mb-4 flex items-start gap-2 rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-red-800">
            <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div class="text-xs">
                <span class="font-semibold" x-text="overdue.length === 1 ? '1 dose is overdue' : overdue.length + ' doses are overdue'"></span>
                <ul class="mt-0.5 space-y-0.5">
                    <template x-for="order in overdue" :key="order.id">
                        <li>
                            <span class="font-semibold" x-text="order.name"></span>
                            <span x-text="order.summary"></span>
                            <span class="opacity-60">&middot;</span>
                            <span x-text="dueText(order.due, order.stat)"></span>
                            <span class="opacity-70" x-text="'(due ' + order.dueAt + ')'"></span>
                        </li>
                    </template>
                </ul>
            </div>
        </div>
    </template>

    @if ($activeOrders->isEmpty())
        <div x-show="!adding" class="p-6 text-center text-gray-500 border border-dashed border-gray-300 rounded-lg">
            <p class="text-sm">No active medications.</p>
            <p class="text-xs text-gray-400 mt-1">Use Add medication to start monitoring doses for this patient.</p>
        </div>
    @endif

    {{-- Active orders --}}
    @foreach ($activeOrders as $order)
        @php
            $lastDose = $order->administrations->first();
            $allowedAt = $order->nextAllowedAt();
            $recordFailed = $failedForm === 'record-' . $order->id;
            $stopFailed = $failedForm === 'stop-' . $order->id;
            $intervalText = $order->interval_minutes ? $pm::formatInterval($order->interval_minutes) : null;
            $earlyMessage = $order->isPrn()
                ? 'The last dose was given at ' . ($order->last_given_at?->format('H:i') ?? '-') . '. This order asks for at least '
                    . $intervalText . ' between doses, so the next is not before ' . ($allowedAt?->format('H:i') ?? '-') . '.'
                : 'Not due until ' . ($order->next_due_at?->format('d M H:i') ?? '-') . '.'
                    . ($intervalText ? ' Recording it now restarts the ' . $intervalText . ' interval from now.' : '');
        @endphp
        <div class="mb-3 rounded-xl border p-4 transition-colors"
            x-data="{
                due: @js($order->next_due_at?->getTimestampMs()),
                allowed: @js($allowedAt?->getTimestampMs()),
                allowedAt: @js($allowedAt?->format('H:i')),
                stat: @js($order->isStat()),
                prn: @js($order->isPrn()),
                recording: @js($recordFailed),
                stopping: @js($stopFailed),
                history: false,
                outcome: @js($recordFailed ? (string) old('status', $ma::STATUS_GIVEN) : $ma::STATUS_GIVEN),
                earlier: @js($recordFailed && filled(old('administered_at'))),
                givenAt: @js($recordFailed ? (string) old('administered_at', '') : ''),
                get state() { return this.prn ? 'prn' : this.stateOf(this.due); },
                get label() {
                    if (!this.prn) return this.dueText(this.due, this.stat);
                    return this.allowed !== null && this.now < this.allowed ? 'PRN - not before ' + this.allowedAt : 'PRN - when required';
                },
                get early() {
                    if (this.prn) return this.allowed !== null && this.now < this.allowed;
                    return this.due !== null && this.due - this.now > {{ $pm::DUE_SOON_MINUTES }} * 60000;
                },
                openRecord() { this.recording = true; this.stopping = false; },
                useEarlier() { this.earlier = true; if (!this.givenAt) this.givenAt = this.localNow(); },
            }"
            :class="{
                'border-red-300 bg-red-50/60': state === 'overdue',
                'border-amber-300 bg-amber-50/60': state === 'due_soon',
                'border-gray-200 bg-white': state === 'scheduled' || state === 'prn',
            }">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold"
                            :class="{
                                'bg-red-600 text-white': state === 'overdue',
                                'bg-amber-400 text-gray-900': state === 'due_soon',
                                'bg-blue-100 text-blue-800': state === 'scheduled',
                                'bg-purple-100 text-purple-800': state === 'prn',
                            }"
                            x-text="label">{{ $order->dueLabel($now) }}</span>
                        <span class="font-semibold text-gray-900">{{ $order->medication_name }}</span>
                        @if ($order->is_high_alert)
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-700 border border-red-200">
                                HIGH-ALERT
                            </span>
                        @endif
                    </div>
                    <div class="mt-1 text-sm text-gray-700">
                        <span class="font-semibold">{{ $order->doseLabel() }}</span>
                        <span class="text-gray-400 mx-1">&middot;</span>{{ $order->routeLabel() }}
                        <span class="text-gray-400 mx-1">&middot;</span>{{ $order->frequencyLabel() }}
                    </div>
                    @if ($order->instructions)
                        <div class="mt-1 text-xs text-gray-600">{{ $order->instructions }}</div>
                    @endif
                </div>

                <div class="text-right shrink-0">
                    @if ($order->next_due_at)
                        <div class="text-[11px] text-gray-500">{{ $order->isStat() ? 'Give' : 'Next dose' }}</div>
                        <div class="text-sm font-semibold" :class="state === 'overdue' ? 'text-red-700' : 'text-gray-800'">
                            {{ $order->next_due_at->format('d M, H:i') }}
                        </div>
                    @elseif ($order->isPrn())
                        <div class="text-[11px] text-gray-500">When required</div>
                        @if ($order->last_given_at)
                            <div class="text-sm font-semibold text-gray-800">last {{ $order->last_given_at->format('d M, H:i') }}</div>
                        @endif
                    @endif
                </div>
            </div>

            <div class="mt-2 text-xs text-gray-600">
                @if ($lastDose)
                    Last recorded:
                    <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold {{ $lastDose->statusBadgeClass() }}">
                        {{ $lastDose->statusLabel() }}
                    </span>
                    {{ $lastDose->administered_at?->format('d M H:i') }}
                    @if ($lastDose->recordedBy)
                        by {{ $lastDose->recordedBy->name }}
                    @endif
                    @if ($lastDose->minutesLate())
                        <span class="text-red-600">({{ $pm::formatDuration($lastDose->minutesLate()) }} after due)</span>
                    @endif
                    @if ($lastDose->notes)
                        <span class="text-gray-400">&middot;</span> <span class="italic">{{ $lastDose->notes }}</span>
                    @endif
                @else
                    No doses recorded yet
                    <span class="text-gray-400">&middot;</span>
                    added {{ $order->created_at->format('d M H:i') }}{{ $order->createdBy ? ' by ' . $order->createdBy->name : '' }}
                @endif
            </div>

            {{-- Actions --}}
            <div class="mt-3 flex flex-wrap items-center gap-2" x-show="!recording && !stopping">
                <button type="button" @click="openRecord()"
                    class="inline-flex items-center px-3 py-1.5 bg-emerald-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-emerald-700">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Record dose
                </button>
                @if ($order->administrations->isNotEmpty())
                    <button type="button" @click="history = !history"
                        class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-md hover:bg-gray-50">
                        <span x-text="history ? 'Hide history' : @js('Dose history (' . $order->administrations->count() . ')')">
                            Dose history ({{ $order->administrations->count() }})
                        </span>
                    </button>
                @endif
                <button type="button" @click="stopping = true; recording = false"
                    class="ml-auto inline-flex items-center px-3 py-1.5 bg-white border border-red-200 text-red-700 text-sm font-semibold rounded-md hover:bg-red-50">
                    Stop
                </button>
            </div>

            {{-- Record a dose --}}
            <form x-show="recording" x-cloak method="POST" action="{{ route('ward.medications.administer', $order) }}"
                class="mt-3 rounded-lg border border-emerald-200 bg-white p-3">
                @csrf
                <input type="hidden" name="active_tab" value="medications">
                <input type="hidden" name="_form" value="record-{{ $order->id }}">

                <div class="flex flex-wrap gap-2">
                    @foreach ($ma::STATUSES as $value => $statusLabel)
                        <label class="cursor-pointer">
                            <input type="radio" name="status" value="{{ $value }}" x-model="outcome" class="sr-only">
                            <span class="inline-flex items-center px-3 py-1.5 rounded-md border text-sm font-semibold transition-colors"
                                :class="outcome === '{{ $value }}' ? '{{ $outcomeClasses[$value] }}' : 'bg-white border-gray-300 text-gray-600 hover:bg-gray-50'">
                                {{ $statusLabel }}
                            </span>
                        </label>
                    @endforeach
                </div>

                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="{{ $labelClass }}">Time</label>
                        <div x-show="!earlier" class="flex items-center gap-3 text-sm py-2">
                            <span class="font-semibold text-gray-800">Now</span>
                            <button type="button" @click="useEarlier()" class="text-xs font-semibold text-blue-700 hover:underline">
                                Record an earlier time
                            </button>
                        </div>
                        <div x-show="earlier" x-cloak class="flex items-center gap-2">
                            <input type="datetime-local" name="administered_at" x-model="givenAt"
                                :disabled="!earlier" :required="earlier" class="{{ $fieldClass }}">
                            <button type="button" @click="earlier = false; givenAt = ''"
                                class="shrink-0 text-xs font-semibold text-blue-700 hover:underline">
                                Use now
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">
                            <span x-text="outcome === '{{ $ma::STATUS_GIVEN }}' ? 'Note (optional)' : 'Reason (required)'">Note (optional)</span>
                        </label>
                        <input type="text" name="notes" maxlength="255" value="{{ $recordFailed ? old('notes') : '' }}"
                            :required="outcome !== '{{ $ma::STATUS_GIVEN }}'"
                            :placeholder="outcome === '{{ $ma::STATUS_HELD }}' ? 'e.g. NBM for OT, BP too low' : (outcome === '{{ $ma::STATUS_REFUSED }}' ? 'e.g. patient declined' : '')"
                            class="{{ $fieldClass }}">
                    </div>
                </div>

                <div x-show="early" x-cloak
                    class="mt-3 flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span>{{ $earlyMessage }}</span>
                </div>

                <div class="mt-3 flex justify-end gap-2">
                    <button type="button" @click="recording = false"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-md hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-emerald-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-emerald-700">
                        Save
                    </button>
                </div>
            </form>

            {{-- Stop --}}
            <form x-show="stopping" x-cloak method="POST" action="{{ route('ward.medications.stop', $order) }}"
                class="mt-3 rounded-lg border border-red-200 bg-red-50/50 p-3 flex flex-wrap items-end gap-2">
                @csrf
                <input type="hidden" name="active_tab" value="medications">
                <input type="hidden" name="_form" value="stop-{{ $order->id }}">
                <div class="flex-1 min-w-[12rem]">
                    <label class="{{ $labelClass }}">Reason for stopping <span class="text-red-500">*</span></label>
                    <input type="text" name="stop_reason" list="medication-stop-reasons" required maxlength="255"
                        value="{{ $stopFailed ? old('stop_reason') : '' }}" placeholder="e.g. course completed"
                        class="{{ $fieldClass }}">
                </div>
                <button type="button" @click="stopping = false"
                    class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-md hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit"
                    class="inline-flex items-center px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-red-700">
                    Stop medication
                </button>
            </form>

            @if ($order->administrations->isNotEmpty())
                <div x-show="history" x-cloak class="mt-3">
                    @include('wards.partials.medication-dose-history', ['order' => $order, 'canUndo' => true])
                </div>
            @endif
        </div>
    @endforeach

    {{-- Stopped and completed orders --}}
    @if ($finishedOrders->isNotEmpty())
        <div class="mt-4" x-data="{ open: false }">
            <button type="button" @click="open = !open"
                class="flex items-center text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                <svg class="w-3.5 h-3.5 mr-1 transition-transform" :class="open ? 'rotate-90' : ''" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
                Stopped and completed ({{ $finishedOrders->count() }})
            </button>
            <div x-show="open" x-cloak class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Medication</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Order</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Started</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Ended</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Doses given</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Outcome</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        @foreach ($finishedOrders as $order)
                            @php
                                $endedAt = $order->stopped_at ?? $order->administrations->first()?->administered_at;
                            @endphp
                            <tr>
                                <td class="px-3 py-2 font-medium text-gray-800">{{ $order->medication_name }}</td>
                                <td class="px-3 py-2 text-gray-600 whitespace-nowrap">{{ $order->summary() }}</td>
                                <td class="px-3 py-2 text-gray-600 whitespace-nowrap">{{ $order->start_at?->format('d M H:i') ?? '-' }}</td>
                                <td class="px-3 py-2 text-gray-600 whitespace-nowrap">{{ $endedAt?->format('d M H:i') ?? '-' }}</td>
                                <td class="px-3 py-2 text-gray-600">
                                    {{ $order->administrations->where('status', $ma::STATUS_GIVEN)->count() }}
                                </td>
                                <td class="px-3 py-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                        {{ $order->status === $pm::STATUS_COMPLETED ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }}">
                                        {{ ucfirst($order->status) }}
                                    </span>
                                    @if ($order->stop_reason)
                                        <span class="block text-[11px] text-gray-500 mt-0.5">
                                            {{ $order->stop_reason }}{{ $order->stoppedBy ? ' - ' . $order->stoppedBy->name : '' }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <datalist id="medication-stop-reasons">
        <option value="Course completed"></option>
        <option value="Changed by doctor"></option>
        <option value="Switched to oral"></option>
        <option value="Adverse reaction"></option>
        <option value="Patient discharged"></option>
        <option value="Entered in error"></option>
    </datalist>

    <p class="mt-4 text-[11px] text-gray-400">
        Doses are tracked by interval: the next dose falls due one interval after the last dose recorded (given, held or
        refused), and turns overdue once that time passes. This supports the medication chart; it does not replace it.
    </p>
</div>
