@php
    $hospital = $admission->ward?->hospital ?? \App\Models\Hospital::where('is_active', true)->first();

    // Only the hospital's own logo goes on the printed sheet. The app-wide
    // fallback is the white navbar mark, which is invisible on white paper -
    // better to print the hospital name alone than an empty box.
    $logoUrl = $hospital?->logo_path ? \Illuminate\Support\Facades\Storage::url($hospital->logo_path) : null;
    $patientName = $patient?->name ?? $admission->patient_name;

    // How much each section would add to the printout, so a ward deciding what
    // to print can see which ones are the long ones.
    $sectionSizes = [
        'care-team' => $careTeam['careProviders']->count(),
        'nursing' => $nursingRoster['nurses']->count(),
        'vitals' => $vitalSigns->count(),
        'ecg' => $ecgFiles->count(),
        'infusions' => $infusions->count(),
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Discharge Summary - {{ $patientName }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        @media print {
            @page {
                size: A4 portrait;
                margin: 12mm 10mm;
            }

            body {
                background: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .no-print {
                display: none !important;
            }

            /* Keep a section's heading with at least some of its rows. */
            section,
            tr {
                break-inside: avoid;
            }

            thead {
                display: table-header-group;
            }
        }

        /* Stamped across every printed page of a summary whose admission is
           still open, so a provisional copy can never be mistaken for the
           final record once it leaves the printer. */
        .provisional-watermark {
            position: fixed;
            inset: 0;
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
        }

        .provisional-watermark span {
            transform: rotate(-35deg);
            font-size: 68px;
            font-weight: 800;
            letter-spacing: 0.15em;
            color: rgba(217, 119, 6, 0.13);
            white-space: nowrap;
            text-transform: uppercase;
        }
    </style>
    @include('components.autofill-guard')
</head>

<body class="bg-gray-100 text-gray-900">
    @unless ($episode->isDischarged())
        <div class="provisional-watermark" aria-hidden="true">
            <span>Provisional</span>
        </div>
    @endunless

    {{-- Print controls. The page below is the preview: ticking a section off
         removes it here and from the printout, so what is on screen is what
         comes out of the printer. --}}
    <div class="no-print sticky top-0 z-[60] border-b border-gray-200 bg-white shadow-sm">
        <div class="mx-auto max-w-5xl px-6 py-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="text-sm text-gray-600">
                    <span class="font-semibold text-gray-800">Discharge summary</span>
                    &middot; {{ $patientName }}
                    @unless ($episode->isDischarged())
                        <span class="ml-2 rounded bg-amber-100 px-2 py-0.5 text-xs font-bold uppercase text-amber-800">
                            Provisional
                        </span>
                    @endunless
                </div>
                <div class="flex gap-2">
                    <button onclick="printSummary()"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                        Print / Save PDF
                    </button>
                    <button onclick="window.close()"
                        class="rounded-lg bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-300">
                        Close
                    </button>
                </div>
            </div>

            <div class="mt-3 border-t border-gray-100 pt-3">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Sections to print</span>

                    @foreach ($sections as $key => $label)
                        <label
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1 text-sm text-gray-700 hover:bg-gray-100">
                            <input type="checkbox" data-print-section="{{ $key }}" checked
                                class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            {{ $label }}
                            @isset($sectionSizes[$key])
                                <span class="text-xs text-gray-400">({{ $sectionSizes[$key] }})</span>
                            @endisset
                        </label>
                    @endforeach

                    <span class="ml-auto flex gap-2">
                        <button type="button" onclick="setAllSections(true)"
                            class="text-xs font-semibold text-blue-600 hover:text-blue-800">Select all</button>
                        <span class="text-xs text-gray-300">|</span>
                        <button type="button" onclick="setAllSections(false)"
                            class="text-xs font-semibold text-gray-500 hover:text-gray-700">Clear all</button>
                    </span>
                </div>

                <p data-no-sections hidden class="mt-2 text-xs font-semibold text-red-600">
                    Every section is switched off &mdash; the printout would be the header and footer only.
                </p>
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-5xl bg-white p-8 print:max-w-none print:p-0">
        {{-- Document header --}}
        <header class="mb-6 flex items-start justify-between gap-6 border-b-2 border-gray-800 pb-4">
            <div class="flex items-center gap-4">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="" class="h-14 w-auto object-contain">
                @endif
                <div>
                    <p class="text-lg font-bold text-gray-900">{{ $hospital?->name ?? 'Hospital' }}</p>
                    <p class="text-sm text-gray-600">{{ $admission->ward?->ward_name ?? 'Ward' }}</p>
                </div>
            </div>
            <div class="text-right">
                <h1 class="text-xl font-bold uppercase tracking-wide text-gray-900">Discharge Summary</h1>
                <p class="mt-1 text-xs text-gray-600">Ref {{ $episode->reference() }}</p>
                <p class="text-xs text-gray-600">
                    Printed {{ now()->format('d M Y, H:i') }}
                    @auth
                        by {{ auth()->user()->name }}
                    @endauth
                </p>
            </div>
        </header>

        @include('wards.discharge-summary.partials.summary')

        {{-- Document footer --}}
        <footer class="mt-6 border-t border-gray-300 pt-3 text-[11px] text-gray-500">
            <div class="flex items-start justify-between gap-6">
                <p>
                    Generated by PHKL Smart Ward from records held at the time of printing.
                    <span data-partial-note hidden class="font-semibold text-gray-700">
                        Partial copy: not every section of this summary was printed.
                    </span>
                    @unless ($episode->isDischarged())
                        <span class="font-semibold text-amber-700">
                            This is a provisional copy: the patient had not been discharged when it was printed.
                        </span>
                    @endunless
                </p>
                <p class="whitespace-nowrap">{{ $patientName }} &middot; {{ $episode->reference() }}</p>
            </div>
        </footer>
    </div>

    @include('wards.discharge-summary.partials.section-toggle')

    <script>
        const admissionIsOpen = @json(!$episode->isDischarged());
        const sectionChoiceKey = 'discharge-summary.print-sections';
        const checkboxes = Array.from(document.querySelectorAll('[data-print-section]'));

        function applySectionChoice() {
            const chosen = checkboxes.filter(function (box) { return box.checked; })
                .map(function (box) { return box.dataset.printSection; });

            window.showSummarySections(chosen);

            // A printout missing sections says so, so nobody reads a partial
            // copy as the whole record.
            document.querySelector('[data-partial-note]').hidden = chosen.length === checkboxes.length;
            document.querySelector('[data-no-sections]').hidden = chosen.length > 0;

            try {
                localStorage.setItem(sectionChoiceKey, JSON.stringify(chosen));
            } catch (e) {
                // Private browsing or blocked storage: the choice just will not
                // be remembered for next time.
            }
        }

        function setAllSections(checked) {
            checkboxes.forEach(function (box) { box.checked = checked; });
            applySectionChoice();
        }

        function restoreSectionChoice() {
            try {
                const stored = JSON.parse(localStorage.getItem(sectionChoiceKey) || 'null');
                if (Array.isArray(stored) && stored.length) {
                    checkboxes.forEach(function (box) {
                        box.checked = stored.includes(box.dataset.printSection);
                    });
                }
            } catch (e) {
                // Fall through to every section checked.
            }
        }

        function printSummary() {
            if (!checkboxes.some(function (box) { return box.checked; })) {
                window.alert('Choose at least one section to print.');
                return;
            }

            if (admissionIsOpen && !window.confirm(
                'This patient has NOT been discharged yet.\n\n' +
                'The discharge summary may not be correct or complete: anything recorded ' +
                'between now and the actual discharge will be missing.\n\n' +
                'Print it anyway as a provisional copy?')) {
                return;
            }

            window.print();
        }

        checkboxes.forEach(function (box) {
            box.addEventListener('change', applySectionChoice);
        });

        restoreSectionChoice();
        applySectionChoice();
    </script>
</body>

</html>
