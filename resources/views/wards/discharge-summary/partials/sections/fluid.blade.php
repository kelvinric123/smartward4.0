{{-- Intake / output over the stay, by the ward's chart day (the I/O chart's
     own 24 h, starting with the ward's first shift). Struck-out entries are
     left out of every figure, as on the chart. --}}
@php
    use App\Models\FluidBalanceEntry;

    $fb = $fluidBalance;
    $fbTotals = $fb['totals'];
    $balanceTone = fn(int $ml) => $ml > 0 ? 'text-blue-700' : ($ml < 0 ? 'text-amber-700' : 'text-gray-700');
    $hasFluidRecords = $fb['entries']->isNotEmpty() || $fb['plans']->isNotEmpty() || $fb['assessments']->isNotEmpty();
@endphp

<section data-section="fluid" class="mb-5 rounded-lg border border-gray-300 bg-white">
    <h3 class="{{ $sectionHeading }}">
        <span>Intake / output</span>
        <span class="{{ $sectionCount }}">
            {{ $fb['entries']->count() }} charted {{ Str::plural('entry', $fb['entries']->count()) }}
            @if ($fb['days'])
                over {{ count($fb['days']) }} chart {{ Str::plural('day', count($fb['days'])) }}
            @endif
        </span>
    </h3>

    @if (!$hasFluidRecords)
        <p class="px-4 py-3 text-sm text-gray-500">No intake or output was charted during this admission.</p>
    @else
        @if ($fb['entries']->isNotEmpty())
            <div class="grid grid-cols-1 gap-3 border-b border-gray-200 px-4 py-3 sm:grid-cols-3 print:grid-cols-3">
                <div class="break-inside-avoid">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Total intake</p>
                    <p class="text-lg font-bold text-gray-900">{{ FluidBalanceEntry::formatMl($fbTotals['intake']) }}</p>
                    <p class="text-[11px] text-gray-500">
                        @forelse ($fbTotals['by_type']['intake'] as $type => $ml)
                            {{ FluidBalanceEntry::INTAKE_TYPES[$type] ?? ucfirst($type) }} {{ FluidBalanceEntry::formatMl($ml) }}@if (!$loop->last) &middot; @endif
                        @empty
                            None charted
                        @endforelse
                    </p>
                </div>
                <div class="break-inside-avoid">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Total output</p>
                    <p class="text-lg font-bold text-gray-900">{{ FluidBalanceEntry::formatMl($fbTotals['output']) }}</p>
                    <p class="text-[11px] text-gray-500">
                        @forelse ($fbTotals['by_type']['output'] as $type => $ml)
                            {{ FluidBalanceEntry::OUTPUT_TYPES[$type] ?? ucfirst($type) }} {{ FluidBalanceEntry::formatMl($ml) }}@if (!$loop->last) &middot; @endif
                        @empty
                            None charted
                        @endforelse
                    </p>
                </div>
                <div class="break-inside-avoid">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Net balance for the stay</p>
                    <p class="text-lg font-bold {{ $balanceTone($fbTotals['balance']) }}">
                        {{ FluidBalanceEntry::formatBalance($fbTotals['balance']) }}
                    </p>
                    <p class="text-[11px] text-gray-500">Intake minus output</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="{{ $tableHead }}">
                            <th class="px-4 py-1.5 font-semibold">Chart day</th>
                            <th class="px-2 py-1.5 text-right font-semibold">Entries</th>
                            <th class="px-2 py-1.5 text-right font-semibold">Intake</th>
                            <th class="px-2 py-1.5 text-right font-semibold">Output</th>
                            <th class="px-2 py-1.5 text-right font-semibold">Urine</th>
                            <th class="px-2 py-1.5 text-right font-semibold">Balance</th>
                            <th class="px-2 py-1.5 text-right font-semibold">Running</th>
                            <th class="px-4 py-1.5 text-right font-semibold">Intake limit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($fb['days'] as $row)
                            <tr class="break-inside-avoid {{ $row['count'] === 0 ? 'text-gray-400' : '' }}">
                                <td class="whitespace-nowrap px-4 py-1.5 text-gray-900">
                                    {{ $row['start']->format('D d M, H:i') }}
                                    <span class="text-gray-400">&rarr; {{ $row['end']->format('H:i') }}</span>
                                </td>
                                <td class="px-2 py-1.5 text-right">{{ $row['count'] ?: '—' }}</td>
                                <td class="whitespace-nowrap px-2 py-1.5 text-right {{ $row['over'] ? 'font-semibold text-red-700' : '' }}">
                                    {{ $row['count'] ? FluidBalanceEntry::formatMl($row['intake']) : '—' }}
                                </td>
                                <td class="whitespace-nowrap px-2 py-1.5 text-right">
                                    {{ $row['count'] ? FluidBalanceEntry::formatMl($row['output']) : '—' }}
                                </td>
                                <td class="whitespace-nowrap px-2 py-1.5 text-right">
                                    {{ $row['count'] ? FluidBalanceEntry::formatMl($row['urine']) : '—' }}
                                </td>
                                <td class="whitespace-nowrap px-2 py-1.5 text-right font-semibold {{ $row['count'] ? $balanceTone($row['balance']) : '' }}">
                                    {{ $row['count'] ? FluidBalanceEntry::formatBalance($row['balance']) : '—' }}
                                </td>
                                <td class="whitespace-nowrap px-2 py-1.5 text-right {{ $balanceTone($row['cumulative']) }}">
                                    {{ FluidBalanceEntry::formatBalance($row['cumulative']) }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-1.5 text-right text-gray-600">
                                    @if ($row['limit'])
                                        {{ FluidBalanceEntry::formatMl($row['limit']) }}
                                        @if ($row['over'])
                                            <span class="ml-1 rounded bg-red-100 px-1 text-[10px] font-bold uppercase text-red-700">Over</span>
                                        @endif
                                    @else
                                        &mdash;
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($fb['voidedCount'])
                <p class="border-t border-gray-200 px-4 py-1.5 text-[11px] text-gray-500">
                    {{ $fb['voidedCount'] }} struck-out {{ Str::plural('entry', $fb['voidedCount']) }} not counted.
                </p>
            @endif
        @endif

        @if ($fb['plans']->isNotEmpty())
            <div class="border-t border-gray-200 px-4 py-3">
                <p class="mb-1.5 text-[11px] font-bold uppercase tracking-wider text-gray-600">Fluid plan</p>
                <ul class="space-y-1 text-sm">
                    @foreach ($fb['plans'] as $plan)
                        <li class="break-inside-avoid">
                            <span class="whitespace-nowrap text-gray-500">{{ $plan->created_at->format('d M Y, H:i') }}</span>
                            &middot; <span class="text-gray-900">{{ $plan->summary() }}</span>
                            @if ($plan->setBy)
                                <span class="text-gray-500">&middot; {{ $plan->setBy->name }}</span>
                            @endif
                            @if ($plan->notes)
                                <span class="block text-xs text-gray-500">{{ $plan->notes }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($fb['assessments']->isNotEmpty())
            <div class="border-t border-gray-200">
                <p class="px-4 pb-1 pt-3 text-[11px] font-bold uppercase tracking-wider text-gray-600">Fluid overload assessments</p>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="{{ $tableHead }}">
                            <th class="px-4 py-1.5 font-semibold">Assessed</th>
                            <th class="px-2 py-1.5 font-semibold">Edema</th>
                            <th class="px-2 py-1.5 font-semibold">Signs</th>
                            <th class="px-2 py-1.5 text-right font-semibold">Weight</th>
                            <th class="px-4 py-1.5 font-semibold">By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($fb['assessments'] as $assessment)
                            <tr class="break-inside-avoid">
                                <td class="whitespace-nowrap px-4 py-1.5 text-gray-900">{{ $assessment->assessed_at?->format('d M Y, H:i') }}</td>
                                <td class="px-2 py-1.5">{{ $assessment->edemaSummary() }}</td>
                                <td class="px-2 py-1.5 {{ $assessment->hasUrgentSign() ? 'font-semibold text-red-700' : 'text-gray-700' }}">
                                    {{ implode(', ', $assessment->signLabels()) ?: 'None' }}
                                </td>
                                <td class="whitespace-nowrap px-2 py-1.5 text-right">
                                    {{ $assessment->weight_kg !== null ? number_format((float) $assessment->weight_kg, 1) . ' kg' : '—' }}
                                </td>
                                <td class="px-4 py-1.5 text-gray-600">{{ $assessment->recordedBy?->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</section>
