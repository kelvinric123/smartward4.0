@php
    /**
     * The body of a discharge summary, shared by the on-screen view and the
     * printable page so the two can never drift apart.
     *
     * Every block carries data-section="<key>" from
     * DischargeSummaryService::SECTIONS. The view's tabs and the print page's
     * section chooser both work by toggling those keys, so neither page has to
     * know what a section contains - only that it exists.
     */
    $ward = $admission->ward;
    $dischargeLog = $episode->dischargeLog;
    $consultant = $careTeam['consultant'];
    $anaesthetist = $careTeam['anaesthetist'];
    $rosterNurses = $nursingRoster['nurses'];
    $shiftLabels = $nursingRoster['shiftLabels'];

    // ADT stores the address as the parsed PID-11 components; a hand-entered
    // one can still be a plain string. Only scalars are joined, so a nested
    // component never turns into "Array".
    $address = is_array($patient?->address)
        ? array_filter($patient->address, fn($part) => is_scalar($part) && trim((string) $part) !== '')
        : null;
    $addressLine = $address ? implode(', ', $address) : (is_string($patient?->address) ? $patient->address : null);

    $sectionBox = 'break-inside-avoid rounded-lg border border-gray-300 bg-white';
    $sectionHeading =
        'flex items-center justify-between gap-4 border-b border-gray-300 bg-gray-100 px-4 py-2 text-xs font-bold uppercase tracking-wider text-gray-700';
    $sectionCount = 'font-semibold normal-case tracking-normal text-gray-500';
    $tableHead = 'border-b border-gray-200 bg-gray-50 text-left text-[10px] uppercase tracking-wide text-gray-500';
@endphp

{{-- Provisional warning: a summary can be read before the patient leaves, but
     the reader has to know the stay is still open and the record incomplete.
     It sits outside the section system so no tab and no print option can take
     it off the page. --}}
@unless ($episode->isDischarged())
    <div class="mb-6 rounded-lg border-2 border-amber-500 bg-amber-50 px-4 py-3 print:bg-amber-50">
        <div class="flex items-start gap-3">
            <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-600" fill="none" stroke="currentColor"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div>
                <p class="text-sm font-bold uppercase tracking-wide text-amber-900">
                    Provisional &mdash; patient not yet discharged
                </p>
                <p class="mt-1 text-sm text-amber-800">
                    This summary covers an admission that is still open, so it may not be correct or complete.
                    Readings, ECGs and infusions recorded between now and the actual discharge will not appear here.
                    Print it only as an interim record.
                </p>
            </div>
        </div>
    </div>
@endunless

{{-- Patient --}}
<section data-section="patient" class="mb-5 {{ $sectionBox }}">
    <h3 class="{{ $sectionHeading }}">Patient</h3>
    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 px-4 py-3 sm:grid-cols-4">
        <x-summary-field label="Name" :value="$patient?->name ?? $admission->patient_name" />
        <x-summary-field label="MRN" :value="$patient?->mrn ?? $admission->mrn" />
        <x-summary-field label="RN" :value="$patient?->rn" />
        <x-summary-field label="IC / Passport" :value="$patient?->ic_passport" />
        <x-summary-field label="Gender" :value="$patient?->gender ?? $admission->gender" />
        <x-summary-field label="Age at admission" :value="$admission->age ?? $patient?->age" />
        <x-summary-field label="Date of birth" :value="$patient?->date_of_birth?->format('d M Y')" />
        <x-summary-field label="Visit number" :value="$patient?->visit_number" />
        <x-summary-field label="Race" :value="$patient?->race" />
        <x-summary-field label="Religion" :value="$patient?->religion" />
        <x-summary-field label="Phone" :value="$patient?->phone" />
        <x-summary-field label="Patient class" :value="$patient?->patient_class" />
        <div class="col-span-2 sm:col-span-4">
            <x-summary-field label="Address" :value="$addressLine" />
        </div>
    </dl>
</section>

{{-- Admission and discharge sit side by side, but each is its own section so
     either can be switched off alone. The wrapper collapses when both are. --}}
<div data-section-group class="mb-5 grid grid-cols-1 gap-5 lg:grid-cols-2 print:grid-cols-2">
    <section data-section="admission" class="{{ $sectionBox }}">
        <h3 class="{{ $sectionHeading }}">Admission</h3>
        <dl class="grid grid-cols-2 gap-x-6 gap-y-3 px-4 py-3">
            <x-summary-field label="Admitted on" :value="$episode->admittedAt->format('d M Y, H:i')" />
            <x-summary-field label="Ward" :value="$ward?->ward_name" />
            <x-summary-field label="Bed" :value="$admission->bed_number" />
            <x-summary-field label="Type"
                :value="$admission->action === 'check-in' ? 'Check-in (pre-booked)' : 'Direct admission'" />
            <x-summary-field label="Source" :value="$admission->source === 'adt' ? 'HIS (ADT)' : 'Ward (manual)'" />
            <x-summary-field label="Admitted by"
                :value="$careTeam['admittedBy']?->name ?? ($admission->source === 'adt' ? 'HIS interface' : null)" />
            <x-summary-field label="Booked on" :value="$admission->booked_at?->format('d M Y, H:i')" />
            <x-summary-field label="Nursing level" :value="$patient?->nursing_level" />
            <div class="col-span-2">
                <x-summary-field label="Admission notes" :value="$admission->notes" />
            </div>
        </dl>
    </section>

    <section data-section="discharge" class="{{ $sectionBox }}">
        <h3 class="{{ $sectionHeading }}">Discharge</h3>
        <dl class="grid grid-cols-2 gap-x-6 gap-y-3 px-4 py-3">
            @if ($episode->isDischarged())
                <x-summary-field label="Discharged on" :value="$episode->dischargedAt->format('d M Y, H:i')" />
                <x-summary-field label="Length of stay" :value="$episode->lengthOfStay()" />
                <x-summary-field label="Discharged from bed"
                    :value="$dischargeLog?->bed_number ?? $admission->bed_number" />
                <x-summary-field label="Source"
                    :value="($dischargeLog?->source ?? 'manual') === 'adt' ? 'HIS (ADT)' : 'Ward (manual)'" />
                <x-summary-field label="Discharged by"
                    :value="$careTeam['dischargedBy']?->name ??
                        (($dischargeLog?->source ?? null) === 'adt' ? 'HIS interface' : null)" />
                <x-summary-field label="Recorded" :value="$dischargeLog?->created_at?->format('d M Y, H:i')" />
                <div class="col-span-2">
                    <x-summary-field label="Reason / discharge notes" :value="$dischargeLog?->notes" />
                </div>
            @else
                <div class="col-span-2">
                    <x-summary-field label="Status">
                        <span class="font-semibold text-amber-700">Not discharged &mdash; admission still open</span>
                    </x-summary-field>
                </div>
                <x-summary-field label="Stay so far" :value="$episode->lengthOfStay()" />
                <x-summary-field label="Current status"
                    :value="$patient?->status ? ucfirst(str_replace('_', ' ', $patient->status)) : null" />
                <x-summary-field label="Expected discharge"
                    :value="$patient?->expected_discharge_at?->format('d M Y, H:i')" />
                <x-summary-field label="Pending discharge since"
                    :value="$patient?->pending_discharge_at?->format('d M Y, H:i')" />
            @endif
        </dl>
    </section>
</div>

{{-- Doctors --}}
<section data-section="care-team" class="mb-5 {{ $sectionBox }}">
    <h3 class="{{ $sectionHeading }}">Doctor &amp; care team</h3>

    <div
        class="grid grid-cols-1 divide-y divide-gray-200 lg:grid-cols-2 lg:divide-x lg:divide-y-0 print:grid-cols-2 print:divide-x print:divide-y-0">
        <div class="px-4 py-3">
            <p class="mb-2 text-[11px] font-bold uppercase tracking-wider text-blue-700">Consultant / doctor</p>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-3">
                <x-summary-field label="Name" :value="$consultant?->name" />
                <x-summary-field label="Specialty" :value="$consultant?->specialty?->name" />
                <x-summary-field label="Registration no." :value="$consultant?->registration_number" />
                <x-summary-field label="Personnel code" :value="$consultant?->personnel_code" />
                <x-summary-field label="Phone" :value="$consultant?->phone" />
                <x-summary-field label="Email" :value="$consultant?->email" />
                @if ($careTeam['consultantAtAdmission'] && $careTeam['consultantAtAdmission'] !== $consultant?->name)
                    <div class="col-span-2">
                        <x-summary-field label="Consultant recorded at admission"
                            :value="$careTeam['consultantAtAdmission']" />
                    </div>
                @endif
            </dl>
        </div>

        <div class="px-4 py-3">
            <p class="mb-2 text-[11px] font-bold uppercase tracking-wider text-purple-700">Anaesthetist</p>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-3">
                <x-summary-field label="Name" :value="$anaesthetist?->name" />
                <x-summary-field label="Registration no." :value="$anaesthetist?->registration_number" />
                <x-summary-field label="Personnel code" :value="$anaesthetist?->personnel_code" />
                <x-summary-field label="Qualifications" :value="$anaesthetist?->qualifications" />
                <x-summary-field label="Phone" :value="$anaesthetist?->phone" />
                <x-summary-field label="Email" :value="$anaesthetist?->email" />
                @foreach ($careTeam['anaesthetistProviders'] as $provider)
                    @if ($provider->anaesthetist_id !== $anaesthetist?->id)
                        <div class="col-span-2">
                            <x-summary-field :label="'Also recorded (' . ucfirst($provider->role) . ')'"
                                :value="$provider->anaesthetist?->name ?? $provider->doctor_name" />
                        </div>
                    @endif
                @endforeach
            </dl>
        </div>
    </div>

    @if ($careTeam['careProviders']->isNotEmpty())
        <div class="border-t border-gray-200 px-4 py-3">
            <p class="mb-2 text-[11px] font-bold uppercase tracking-wider text-gray-600">
                Care providers on record
            </p>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-[10px] uppercase tracking-wide text-gray-500">
                        <th class="py-1 pr-3 font-semibold">Role</th>
                        <th class="py-1 pr-3 font-semibold">Name</th>
                        <th class="py-1 pr-3 font-semibold">Code</th>
                        <th class="py-1 pr-3 font-semibold">Specialty</th>
                        <th class="py-1 pr-3 font-semibold">Assigned</th>
                        <th class="py-1 font-semibold">Source</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($careTeam['careProviders'] as $provider)
                        <tr>
                            <td class="py-1.5 pr-3 capitalize">{{ $provider->role }}</td>
                            <td class="py-1.5 pr-3 font-medium text-gray-900">
                                {{ $provider->doctor_name ?? $provider->consultant?->name ?? $provider->anaesthetist?->name ?? '—' }}
                            </td>
                            <td class="py-1.5 pr-3 text-gray-600">{{ $provider->doctor_code ?? '—' }}</td>
                            <td class="py-1.5 pr-3 text-gray-600">
                                {{ $provider->consultant?->specialty?->name ?? '—' }}</td>
                            <td class="py-1.5 pr-3 text-gray-600">
                                {{ $provider->assigned_at?->format('d M Y, H:i') ?? '—' }}</td>
                            <td class="py-1.5 text-gray-600">{{ strtoupper($provider->source ?? '—') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

{{-- Nursing: the ward roster, not the single nurse_id on the patient record.
     Over a stay of any length the roster is the team that actually gave the
     care; the patient record only ever names the current nurse. --}}
<section data-section="nursing" class="mb-5 {{ $sectionBox }}">
    <h3 class="{{ $sectionHeading }}">
        <span>Nursing team (ward roster)</span>
        <span class="{{ $sectionCount }}">
            {{ $rosterNurses->count() }} {{ Str::plural('nurse', $rosterNurses->count()) }},
            {{ $nursingRoster['assignmentCount'] }} {{ Str::plural('shift', $nursingRoster['assignmentCount']) }}
        </span>
    </h3>

    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 border-b border-gray-200 px-4 py-3 sm:grid-cols-4">
        <x-summary-field label="Bed(s) rostered"
            :value="$nursingRoster['beds']->map(fn($bed) => $bed->bed_display_name ?: $bed->bed_number)->implode(', ') ?: null" />
        <x-summary-field label="Ward" :value="$ward?->ward_name" />
        <x-summary-field label="Nurse on patient record" :value="$careTeam['nurse']?->name" />
        <x-summary-field label="Nurse at admission" :value="$careTeam['nurseAtAdmission']" />
    </dl>

    @if ($rosterNurses->isEmpty())
        <p class="px-4 py-3 text-sm text-gray-500">
            No roster assignments cover this bed for the admission period.
        </p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="{{ $tableHead }}">
                        <th class="px-4 py-1.5 font-semibold">Nurse</th>
                        <th class="px-2 py-1.5 font-semibold">Designation</th>
                        @foreach ($shiftLabels as $shiftLabel)
                            <th class="px-2 py-1.5 text-center font-semibold">{{ $shiftLabel }}</th>
                        @endforeach
                        <th class="px-2 py-1.5 text-center font-semibold">Shifts</th>
                        <th class="px-2 py-1.5 font-semibold">Bed</th>
                        <th class="px-4 py-1.5 font-semibold">Rostered</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($rosterNurses as $entry)
                        <tr class="break-inside-avoid">
                            <td class="px-4 py-1.5 font-medium text-gray-900">
                                {{ $entry['nurse']->name }}
                                @if ($entry['nurse']->taggingNurses?->isNotEmpty())
                                    <span class="block text-[11px] font-normal text-purple-700">
                                        Tagging: {{ $entry['nurse']->taggingNurses->pluck('name')->implode(', ') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-2 py-1.5 text-gray-600">{{ $entry['nurse']->designation ?? '—' }}</td>
                            @foreach ($shiftLabels as $shiftCode => $shiftLabel)
                                <td
                                    class="px-2 py-1.5 text-center {{ $entry['shifts'][$shiftCode] ? 'text-gray-900' : 'text-gray-300' }}">
                                    {{ $entry['shifts'][$shiftCode] ?: '—' }}
                                </td>
                            @endforeach
                            <td class="px-2 py-1.5 text-center font-semibold text-gray-900">{{ $entry['total'] }}</td>
                            <td class="px-2 py-1.5 text-gray-600">{{ $entry['beds']->implode(', ') ?: '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-1.5 text-gray-600">
                                {{ $entry['firstDate']->format('d M Y') }}
                                @unless ($entry['firstDate']->isSameDay($entry['lastDate']))
                                    &ndash; {{ $entry['lastDate']->format('d M Y') }}
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

{{-- Vital signs --}}
<section data-section="vitals" class="mb-5 rounded-lg border border-gray-300 bg-white">
    <h3 class="{{ $sectionHeading }}">
        <span>Vital signs</span>
        <span class="{{ $sectionCount }}">
            {{ $vitalSigns->count() }} {{ Str::plural('reading', $vitalSigns->count()) }} during this admission
        </span>
    </h3>

    @if ($vitalSigns->isEmpty())
        <p class="px-4 py-3 text-sm text-gray-500">No vital signs were recorded during this admission.</p>
    @else
        @if (!empty($vitalRanges))
            <div
                class="grid grid-cols-2 gap-3 border-b border-gray-200 px-4 py-3 sm:grid-cols-3 lg:grid-cols-6 print:grid-cols-6">
                @foreach ($vitalRanges as $range)
                    <div class="break-inside-avoid rounded border border-gray-200 bg-gray-50 px-2 py-1.5">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">
                            {{ $range['label'] }}
                        </p>
                        <p class="text-sm font-bold text-gray-900">
                            {{ number_format($range['min'], $range['decimals']) }}&ndash;{{ number_format($range['max'], $range['decimals']) }}
                        </p>
                        <p class="text-[10px] text-gray-500">
                            avg {{ number_format($range['avg'], $range['decimals']) }} {!! $range['unit'] !!}
                        </p>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="{{ $tableHead }}">
                        <th class="px-4 py-1.5 font-semibold">Recorded</th>
                        <th class="px-2 py-1.5 font-semibold">BP</th>
                        <th class="px-2 py-1.5 font-semibold">Pulse</th>
                        <th class="px-2 py-1.5 font-semibold">Temp</th>
                        <th class="px-2 py-1.5 font-semibold">SpO2</th>
                        <th class="px-2 py-1.5 font-semibold">RR</th>
                        <th class="px-2 py-1.5 font-semibold">Type</th>
                        <th class="px-4 py-1.5 font-semibold">By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($vitalSigns as $vital)
                        <tr class="break-inside-avoid">
                            <td class="whitespace-nowrap px-4 py-1.5 text-gray-900">
                                {{ $vital->recorded_at?->format('d M Y, H:i') ?? '—' }}</td>
                            <td class="px-2 py-1.5">{{ $vital->blood_pressure ?? '—' }}</td>
                            <td class="px-2 py-1.5">{{ $vital->pulse_rate_display ?? '—' }}</td>
                            <td class="px-2 py-1.5">
                                {{ $vital->temperature !== null ? number_format((float) $vital->temperature, 1) : '—' }}
                            </td>
                            <td class="px-2 py-1.5">{{ $vital->spo2_display ?? '—' }}</td>
                            <td class="px-2 py-1.5">{{ $vital->respiratory_rate ?? '—' }}</td>
                            <td class="px-2 py-1.5 capitalize text-gray-600">{{ $vital->reading_type ?? '—' }}</td>
                            <td class="px-4 py-1.5 text-gray-600">
                                {{ $vital->operator?->name ?? $vital->recordedBy?->name ?? ($vital->gateway_id ? 'Gateway ' . $vital->gateway_id : '—') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

{{-- ECG: how many were taken, and when. The traces themselves stay in the ECG
     store as their own reports - this summary counts them, it does not
     reproduce them. --}}
<section data-section="ecg" class="mb-5 {{ $sectionBox }}">
    <h3 class="{{ $sectionHeading }}">ECG recordings</h3>

    @if ($ecgFiles->isEmpty())
        <p class="px-4 py-3 text-sm text-gray-500">No ECG was recorded during this admission.</p>
    @else
        <div class="flex flex-wrap items-baseline gap-x-8 gap-y-2 px-4 py-3">
            <p class="text-gray-900">
                <span class="text-2xl font-bold">{{ $ecgFiles->count() }}</span>
                {{-- Str::plural would shout "ECGS" back at an all-caps word --}}
                <span class="ml-1 text-sm font-semibold">ECG{{ $ecgFiles->count() === 1 ? '' : 's' }} taken</span>
                <span class="text-sm text-gray-500">during this admission</span>
            </p>
            <p class="text-sm text-gray-600">
                <span class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Recorded</span>
                {{ $ecgFiles->map(fn($ecg) => \Carbon\Carbon::parse($ecg['recorded_at'])->format('d M Y, H:i'))->implode(' · ') }}
            </p>
        </div>
    @endif
</section>

{{-- Infusions --}}
<section data-section="infusions" class="mb-5 rounded-lg border border-gray-300 bg-white">
    <h3 class="{{ $sectionHeading }}">
        <span>Infusions</span>
        <span class="{{ $sectionCount }}">
            {{ $infusions->count() }} {{ Str::plural('infusion', $infusions->count()) }} during this admission
        </span>
    </h3>

    @if ($infusions->isEmpty())
        <p class="px-4 py-3 text-sm text-gray-500">No infusion was recorded during this admission.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="{{ $tableHead }}">
                        <th class="px-4 py-1.5 font-semibold">Medication</th>
                        <th class="px-2 py-1.5 font-semibold">Pump</th>
                        <th class="px-2 py-1.5 font-semibold">Started</th>
                        <th class="px-2 py-1.5 font-semibold">Ended / last update</th>
                        <th class="px-2 py-1.5 font-semibold">Status</th>
                        <th class="px-2 py-1.5 font-semibold">Volume</th>
                        <th class="px-2 py-1.5 font-semibold">Rate</th>
                        <th class="px-4 py-1.5 font-semibold">Dose</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($infusions as $infusion)
                        <tr class="break-inside-avoid">
                            <td class="px-4 py-1.5 font-medium text-gray-900">
                                {{ $infusion->medication_name ?? '—' }}
                                @if ($infusion->formatted_concentration)
                                    <span class="block text-[11px] font-normal text-gray-500">
                                        {{ $infusion->formatted_concentration }}
                                    </span>
                                @endif
                                @if ($infusion->alarm_message)
                                    <span class="block text-[11px] font-normal text-red-600">
                                        Alarm: {{ $infusion->alarm_message }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-2 py-1.5 text-gray-600">
                                {{ $infusion->infusionPump?->device_name ?? $infusion->infusionPump?->serial_no ?? '—' }}
                            </td>
                            <td class="whitespace-nowrap px-2 py-1.5 text-gray-600">
                                {{ ($infusion->started_at ?? $infusion->last_updated_at)?->format('d M Y, H:i') ?? '—' }}
                            </td>
                            <td class="whitespace-nowrap px-2 py-1.5 text-gray-600">
                                {{ ($infusion->completed_at ?? $infusion->last_updated_at)?->format('d M Y, H:i') ?? '—' }}
                            </td>
                            <td class="px-2 py-1.5">
                                <span class="font-medium capitalize">{{ $infusion->status }}</span>
                                @unless ($infusion->exists)
                                    <span class="block text-[10px] uppercase tracking-wide text-amber-700">live</span>
                                @endunless
                            </td>
                            <td class="whitespace-nowrap px-2 py-1.5 text-gray-600">
                                @if ($infusion->infused_volume !== null)
                                    {{ number_format((float) $infusion->infused_volume, 1) }}
                                    @if ($infusion->total_volume)
                                        / {{ number_format((float) $infusion->total_volume, 1) }}
                                    @endif
                                    mL
                                @else
                                    —
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-2 py-1.5 text-gray-600">
                                {{ $infusion->flow_rate !== null ? number_format((float) $infusion->flow_rate, 1) . ' mL/h' : '—' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-1.5 text-gray-600">
                                {{ $infusion->formatted_dose_rate ?? '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($infusions->contains(fn($infusion) => !$infusion->exists))
            <p class="border-t border-gray-200 px-4 py-2 text-[11px] text-gray-500">
                Rows marked <span class="font-semibold uppercase text-amber-700">live</span> are the pump's current
                state read from the infusion engine, not a completed record.
            </p>
        @endif
    @endif
</section>
