{{-- Stay at a glance: the handful of numbers a reader wants before the detail.
     Every tile has a section of its own further down. --}}
@php
    $h = $highlights;
    $fluidTotals = $h['fluid'];
    $tile = 'break-inside-avoid rounded-lg border border-gray-200 bg-gray-50 px-3 py-2';
    $tileLabel = 'text-[10px] font-semibold uppercase tracking-wide text-gray-500';
    $tileValue = 'mt-0.5 text-lg font-bold leading-tight text-gray-900';
    $tileSub = 'mt-0.5 text-[11px] leading-snug text-gray-500';
    $ewsTone = fn($score) => $score === null ? '' : ($score >= 5 ? 'text-red-700' : ($score >= 3 ? 'text-amber-700' : ''));
@endphp

<section data-section="highlights" class="mb-5 {{ $sectionBox }}">
    <h3 class="{{ $sectionHeading }}">
        <span>Stay at a glance</span>
        <span class="{{ $sectionCount }}">
            {{ number_format($h['events']) }} {{ Str::plural('event', $h['events']) }} recorded
        </span>
    </h3>

    <div class="grid grid-cols-2 gap-2 p-3 sm:grid-cols-3 lg:grid-cols-5 print:grid-cols-5">
        <div class="{{ $tile }}">
            <p class="{{ $tileLabel }}">{{ $episode->isDischarged() ? 'Length of stay' : 'Stay so far' }}</p>
            <p class="{{ $tileValue }}">{{ $h['lengthOfStay'] }}</p>
            <p class="{{ $tileSub }}">
                @if ($h['transfers'])
                    {{ $h['transfers'] }} {{ Str::plural('transfer', $h['transfers']) }}
                @else
                    No transfers
                @endif
            </p>
        </div>

        <div class="{{ $tile }}">
            <p class="{{ $tileLabel }}">Vital signs</p>
            <p class="{{ $tileValue }}">{{ $h['vitals'] }} {{ Str::plural('set', $h['vitals']) }}</p>
            <p class="{{ $tileSub }}">
                @if ($h['latestEws'] !== null)
                    Latest {{ $ews['label'] }} <span class="font-semibold {{ $ewsTone($h['latestEws']) }}">{{ $h['latestEws'] }}</span>
                    &middot; highest <span class="font-semibold {{ $ewsTone($h['highestEws']) }}">{{ $h['highestEws'] }}</span>
                @elseif ($h['vitals'])
                    Last {{ $h['latestVitalAt']?->format('d M, H:i') }}
                @else
                    None recorded
                @endif
            </p>
        </div>

        <div class="{{ $tile }}">
            <p class="{{ $tileLabel }}">Fluid balance</p>
            @if ($fluidTotals)
                <p class="{{ $tileValue }}">{{ \App\Models\FluidBalanceEntry::formatBalance($fluidTotals['balance']) }}</p>
                <p class="{{ $tileSub }}">
                    In {{ \App\Models\FluidBalanceEntry::formatMl($fluidTotals['intake']) }}
                    &middot; out {{ \App\Models\FluidBalanceEntry::formatMl($fluidTotals['output']) }}
                </p>
            @else
                <p class="{{ $tileValue }} text-gray-400">&mdash;</p>
                <p class="{{ $tileSub }}">Not charted</p>
            @endif
        </div>

        <div class="{{ $tile }}">
            <p class="{{ $tileLabel }}">Medications</p>
            <p class="{{ $tileValue }}">{{ $h['dosesGiven'] }} {{ Str::plural('dose', $h['dosesGiven']) }} given</p>
            <p class="{{ $tileSub }}">
                {{ $h['medicationOrders'] }} {{ Str::plural('order', $h['medicationOrders']) }}
                &middot; {{ $h['activeMedications'] }} active
                @if ($h['dosesNotGiven'])
                    &middot; <span class="font-semibold text-amber-700">{{ $h['dosesNotGiven'] }} held / refused</span>
                @endif
            </p>
        </div>

        <div class="{{ $tile }}">
            <p class="{{ $tileLabel }}">Consultant orders</p>
            <p class="{{ $tileValue }}">{{ $h['orders'] }}</p>
            <p class="{{ $tileSub }}">
                @if ($h['openOrders'])
                    <span class="font-semibold text-indigo-700">{{ $h['openOrders'] }} still open</span>
                @else
                    None open
                @endif
            </p>
        </div>

        <div class="{{ $tile }}">
            <p class="{{ $tileLabel }}">Infusions</p>
            <p class="{{ $tileValue }}">{{ $h['infusions'] }}</p>
            <p class="{{ $tileSub }}">
                {{ $h['transfusions'] }} blood {{ Str::plural('unit', $h['transfusions']) }}
            </p>
        </div>

        <div class="{{ $tile }}">
            <p class="{{ $tileLabel }}">Assessments</p>
            <p class="{{ $tileValue }}">{{ $h['assessments'] }}</p>
            <p class="{{ $tileSub }}">{{ $h['glucose'] }} HGT {{ Str::plural('reading', $h['glucose']) }}</p>
        </div>

        <div class="{{ $tile }}">
            <p class="{{ $tileLabel }}">ECG</p>
            <p class="{{ $tileValue }}">{{ $h['ecg'] }}</p>
            <p class="{{ $tileSub }}">{{ Str::plural('recording', $h['ecg']) }}</p>
        </div>

        <div class="{{ $tile }}">
            <p class="{{ $tileLabel }}">Calls &amp; alerts</p>
            <p class="{{ $tileValue }}">{{ $h['alerts'] }}</p>
            <p class="{{ $tileSub }}">{{ $h['patientCalls'] }} patient {{ Str::plural('call', $h['patientCalls']) }}</p>
        </div>

        <div class="{{ $tile }}">
            <p class="{{ $tileLabel }}">Nursing team</p>
            <p class="{{ $tileValue }}">{{ $nursingRoster['nurses']->count() }} {{ Str::plural('nurse', $nursingRoster['nurses']->count()) }}</p>
            <p class="{{ $tileSub }}">
                {{ $nursingRoster['assignmentCount'] }} rostered {{ Str::plural('shift', $nursingRoster['assignmentCount']) }}
            </p>
        </div>
    </div>
</section>
