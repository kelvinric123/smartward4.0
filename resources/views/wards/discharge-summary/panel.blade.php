{{--
    Patient Details -> Discharge Summary tab body (an HTML fragment, fetched
    by wards.partials.discharge-summary-tab). Buttons call that component's
    load() / jump() / printSummary(); no <script> here would run once it is
    inserted into the page.
--}}
@if (!$episode)
    <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-6 py-10 text-center">
        <svg class="mx-auto h-10 w-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <p class="mt-2 text-sm font-semibold text-gray-700">No admission to summarise yet</p>
        <p class="mt-1 text-sm text-gray-500">
            The discharge summary starts when {{ $patient->name }} is admitted or checked in.
            This patient is currently <span class="font-medium">{{ strtolower($patient->statusLabel()) }}</span>.
        </p>
    </div>
@else
    @php
        $hospital = $admission->ward?->hospital ?? \App\Models\Hospital::where('is_active', true)->first();
        $logoUrl = $hospital?->logo_path ? \Illuminate\Support\Facades\Storage::url($hospital->logo_path) : null;
        $isFinal = $episode->isDischarged();

        // The contents bar: every section, with how much is in it where that helps.
        $contents = [
            'patient' => 'Overview',
            'highlights' => 'At a glance',
            'alerts' => 'Alerts',
            'care-team' => 'Doctors',
            'nursing' => 'Nurses',
            'timeline' => 'Timeline',
            'vitals' => 'Vitals',
            'fluid' => 'I/O',
            'medications' => 'Medications',
            'infusions' => 'Infusions',
            'transfusions' => 'Transfusion',
            'orders' => 'Orders & notes',
            'assessments' => 'Assessments',
            'ecg' => 'ECG',
            'movements' => 'Movements',
            'signoff' => 'Sign-off',
        ];
        $contentCounts = [
            'nursing' => $nursingRoster['nurses']->count(),
            'timeline' => $timeline['total'],
            'vitals' => $vitalSigns->count(),
            'fluid' => $fluidBalance['entries']->count(),
            'medications' => $medications->count(),
            'infusions' => $infusions->count(),
            'transfusions' => $transfusions->count(),
            'orders' => $consultantOrders->count() + $consultantNotes->count(),
            'assessments' => $assessmentScores->count() + $glucoseReadings->count(),
            'ecg' => $ecgFiles->count(),
            'movements' => $movements->count(),
        ];
    @endphp

    <div class="space-y-3" data-discharge-summary-panel>
        {{-- Toolbar --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <span
                    class="{{ $isFinal ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }} rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide">
                    {{ $isFinal ? 'Final' : 'Provisional' }}
                </span>

                @if ($admissions->count() > 1)
                    <label for="discharge-summary-admission" class="text-xs font-semibold text-gray-500">Admission</label>
                    <select id="discharge-summary-admission" @change="load($event.target.value)"
                        class="rounded-md border-gray-300 py-1 pl-2 pr-8 text-sm focus:border-blue-500 focus:ring-blue-500">
                        @foreach ($admissions as $option)
                            <option value="{{ $option->id }}" @selected($admission->exists && $option->id === $admission->id)>
                                {{ $option->episode->admittedAt->format('d M Y') }}
                                &ndash;
                                {{ $option->episode->isDischarged() ? $option->episode->dischargedAt->format('d M Y') : 'still admitted' }}
                                @if ($option->ward)
                                    &middot; {{ $option->ward->ward_name }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                @else
                    <span class="text-sm text-gray-600">
                        Admission of {{ $episode->admittedAt->format('d M Y, H:i') }}
                    </span>
                @endif

                <span class="text-xs text-gray-400">Built at {{ $generatedAt->format('H:i') }}</span>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" @click="load()"
                    class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Refresh
                </button>
                <button type="button" @click="printSummary(@js($printUrl), @js(!$isFinal))"
                    class="inline-flex items-center gap-1.5 rounded-md bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-blue-700">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Print / Save PDF
                </button>
            </div>
        </div>

        {{-- Contents: jump to a section; stays in view while scrolling --}}
        <nav data-summary-contents aria-label="Summary sections"
            class="sticky top-0 z-20 flex flex-wrap gap-1 rounded-lg border border-gray-200 bg-white/95 px-2 py-1.5 shadow-sm backdrop-blur">
            @foreach ($contents as $key => $label)
                <button type="button" @click="jump('{{ $key }}')"
                    class="inline-flex items-center gap-1 rounded px-2 py-0.5 text-xs font-medium text-gray-600 hover:bg-blue-50 hover:text-blue-700">
                    {{ $label }}
                    @if (!empty($contentCounts[$key]))
                        <span class="rounded-full bg-gray-100 px-1.5 text-[10px] font-semibold text-gray-500">{{ $contentCounts[$key] }}</span>
                    @endif
                </button>
            @endforeach
        </nav>

        {{-- The document --}}
        <article class="rounded-lg border border-gray-300 bg-white shadow-sm">
            <header class="flex flex-wrap items-start justify-between gap-4 border-b-2 border-gray-800 px-6 pb-4 pt-5">
                <div class="flex items-center gap-4">
                    @if ($logoUrl)
                        <img src="{{ $logoUrl }}" alt="" class="h-12 w-auto object-contain">
                    @endif
                    <div>
                        <p class="text-base font-bold text-gray-900">{{ $hospital?->name ?? 'Hospital' }}</p>
                        <p class="text-sm text-gray-600">{{ $admission->ward?->ward_name ?? $patient?->ward?->ward_name ?? 'Ward' }}</p>
                    </div>
                </div>
                <div class="text-right">
                    <h2 class="text-lg font-bold uppercase tracking-wide text-gray-900">Discharge Summary</h2>
                    <p class="mt-0.5 text-xs text-gray-600">Ref {{ $episode->reference() }}</p>
                    <p class="text-xs text-gray-600">
                        {{ $patient?->name ?? $admission->patient_name }} &middot; MRN {{ $patient?->mrn ?? $admission->mrn ?? '—' }}
                    </p>
                </div>
            </header>

            <div class="px-6 pb-2 pt-5">
                @include('wards.discharge-summary.partials.summary')
            </div>

            <footer class="border-t border-gray-200 px-6 py-3 text-[11px] text-gray-500">
                Compiled by SmartWard from the records held at {{ $generatedAt->format('d M Y, H:i') }}.
                @unless ($isFinal)
                    <span class="font-semibold text-amber-700">
                        Provisional: the patient has not been discharged, so the record is still open.
                    </span>
                @endunless
            </footer>
        </article>
    </div>
@endif
