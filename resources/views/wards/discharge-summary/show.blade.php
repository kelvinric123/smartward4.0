@php
    /**
     * Tabs over the summary's sections. Each tab names the section keys it
     * shows; the sections themselves live in partials/summary and are toggled
     * by partials/section-toggle.
     */
    $tabs = [
        'overview' => ['label' => 'Overview', 'keys' => ['patient', 'admission', 'discharge', 'highlights', 'alerts'], 'count' => null],
        'timeline' => ['label' => 'Timeline', 'keys' => ['timeline'], 'count' => $timeline['total'] ?: null],
        'care-team' => ['label' => 'Doctor & care team', 'keys' => ['care-team'], 'count' => $careTeam['careProviders']->count() ?: null],
        'nursing' => ['label' => 'Nursing team', 'keys' => ['nursing'], 'count' => $nursingRoster['nurses']->count() ?: null],
        'vitals' => ['label' => 'Vital signs', 'keys' => ['vitals'], 'count' => $vitalSigns->count() ?: null],
        'fluid' => ['label' => 'I/O', 'keys' => ['fluid'], 'count' => $fluidBalance['entries']->count() ?: null],
        'medications' => ['label' => 'Medications', 'keys' => ['medications'], 'count' => $medications->count() ?: null],
        'infusions' => ['label' => 'Infusions', 'keys' => ['infusions', 'transfusions'], 'count' => ($infusions->count() + $transfusions->count()) ?: null],
        'orders' => ['label' => 'Orders & notes', 'keys' => ['orders'], 'count' => ($consultantOrders->count() + $consultantNotes->count()) ?: null],
        'assessments' => ['label' => 'Assessments', 'keys' => ['assessments', 'ecg'], 'count' => ($assessmentScores->count() + $glucoseReadings->count() + $ecgFiles->count()) ?: null],
        'movements' => ['label' => 'Movements', 'keys' => ['movements'], 'count' => $movements->count() ?: null],
        'all' => ['label' => 'All sections', 'keys' => array_keys($sections), 'count' => null],
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold leading-tight text-gray-800">
                    {{ __('Discharge Summary') }}
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $patient?->name ?? $admission->patient_name }}
                    &middot; MRN {{ $patient?->mrn ?? $admission->mrn ?? '—' }}
                    &middot; {{ $episode->reference() }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('discharge-summaries.index') }}"
                    class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition-colors hover:bg-gray-50">
                    <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Back to list
                </a>

                {{-- Printing a summary for a patient who is still in the ward
                     puts an incomplete record on paper, so the warning comes
                     before the print window opens, not after. --}}
                <button type="button" onclick="openPrintView({{ $episode->isDischarged() ? 'false' : 'true' }})"
                    class="inline-flex items-center rounded-lg border border-transparent bg-gradient-to-r from-brand-600 to-accent-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg transition-all duration-200 hover:from-brand-700 hover:to-accent-700 hover:shadow-xl">
                    <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Print / Save PDF
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-2xl border border-blue-100 bg-white/90 shadow-lg backdrop-blur-sm">
                {{-- Section tabs --}}
                <nav class="flex flex-wrap gap-1 border-b border-blue-100 bg-gradient-to-r from-brand-50 to-accent-50 px-4 pt-3 sm:px-6"
                    aria-label="Summary sections">
                    @foreach ($tabs as $key => $tab)
                        <button type="button" data-summary-tab="{{ $key }}"
                            data-summary-keys="{{ implode(',', $tab['keys']) }}"
                            class="summary-tab -mb-px inline-flex items-center gap-2 rounded-t-lg border border-transparent px-4 py-2.5 text-sm font-semibold text-gray-600 transition-colors hover:bg-white/70 hover:text-blue-700">
                            {{ $tab['label'] }}
                            @if ($tab['count'])
                                <span
                                    class="summary-tab-badge rounded-full bg-white px-2 py-0.5 text-xs font-bold text-gray-500">
                                    {{ $tab['count'] }}
                                </span>
                            @endif
                        </button>
                    @endforeach
                </nav>

                <div class="p-6 sm:p-8">
                    @include('wards.discharge-summary.partials.summary')
                </div>
            </div>
        </div>
    </div>

    @include('wards.discharge-summary.partials.section-toggle')

    <script>
        (function () {
            const tabs = Array.from(document.querySelectorAll('[data-summary-tab]'));
            const activeClasses = ['border-blue-200', 'border-b-white', 'bg-white', 'text-blue-700', 'shadow-sm'];

            function selectTab(button) {
                tabs.forEach(function (other) {
                    const isActive = other === button;
                    other.classList.toggle('border-transparent', !isActive);
                    activeClasses.forEach(function (cls) { other.classList.toggle(cls, isActive); });
                    other.setAttribute('aria-current', isActive ? 'true' : 'false');

                    const badge = other.querySelector('.summary-tab-badge');
                    if (badge) {
                        badge.classList.toggle('text-blue-700', isActive);
                        badge.classList.toggle('text-gray-500', !isActive);
                    }
                });

                window.showSummarySections(button.dataset.summaryKeys.split(','));
            }

            tabs.forEach(function (button) {
                button.addEventListener('click', function () { selectTab(button); });
            });

            if (tabs.length) {
                selectTab(tabs[0]);
            }
        })();

        function openPrintView(isProvisional) {
            if (isProvisional && !window.confirm(
                'This patient has NOT been discharged yet.\n\n' +
                'The discharge summary may not be correct or complete: anything recorded ' +
                'between now and the actual discharge will be missing.\n\n' +
                'Print it anyway as a provisional copy?')) {
                return;
            }

            window.open('{{ route('discharge-summaries.print', $admission) }}', '_blank');
        }
    </script>
</x-app-layout>
