{{-- Blood units registered or run during the stay. --}}
@php
    use App\Models\BloodTransfusion;

    $transfusionStatus = [
        BloodTransfusion::STATUS_PENDING => ['Registered, not started', 'text-gray-600'],
        BloodTransfusion::STATUS_IN_PROGRESS => ['Running', 'text-blue-700'],
        BloodTransfusion::STATUS_COMPLETED => ['Completed', 'text-green-700'],
        BloodTransfusion::STATUS_STOPPED => ['Stopped', 'text-red-700'],
    ];
@endphp

<section data-section="transfusions" class="mb-5 rounded-lg border border-gray-300 bg-white">
    <h3 class="{{ $sectionHeading }}">
        <span>Blood transfusion</span>
        <span class="{{ $sectionCount }}">
            {{ $transfusions->count() }} {{ Str::plural('unit', $transfusions->count()) }} during this admission
        </span>
    </h3>

    @if ($transfusions->isEmpty())
        <p class="px-4 py-3 text-sm text-gray-500">No blood product was given during this admission.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="{{ $tableHead }}">
                        <th class="px-4 py-1.5 font-semibold">Product</th>
                        <th class="px-2 py-1.5 font-semibold">Groups</th>
                        <th class="px-2 py-1.5 font-semibold">Bedside checks</th>
                        <th class="px-2 py-1.5 font-semibold">Started</th>
                        <th class="px-2 py-1.5 font-semibold">Finished</th>
                        <th class="px-4 py-1.5 font-semibold">Outcome</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($transfusions as $unit)
                        @php
                            $checks = collect($unit->checklist());
                            $checksDone = $checks->filter(fn($check) => $check['done'] ?? $check['checked'] ?? false)->count();
                            [$statusLabel, $statusTone] = $transfusionStatus[$unit->status] ?? [ucfirst((string) $unit->status), 'text-gray-600'];
                        @endphp
                        <tr class="break-inside-avoid align-top">
                            <td class="px-4 py-1.5">
                                <span class="font-medium text-gray-900">{{ $unit->product_type }}</span>
                                <span class="block text-[11px] text-gray-500">
                                    Unit {{ $unit->unit_number ?? '—' }}{{ $unit->volume_ml ? ' · ' . $unit->volume_ml . ' mL' : '' }}
                                </span>
                            </td>
                            <td class="px-2 py-1.5 text-gray-700">{{ $unit->groupSummary() }}</td>
                            <td class="px-2 py-1.5 {{ $checks->isNotEmpty() && $checksDone === $checks->count() ? 'text-green-700' : 'text-amber-700' }}">
                                {{ $checksDone }} of {{ $checks->count() }} confirmed
                                @if ($unit->checkedBy)
                                    <span class="block text-[11px] text-gray-500">{{ $unit->checkedBy->name }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-2 py-1.5 text-gray-600">{{ $unit->started_at?->format('d M, H:i') ?? '—' }}</td>
                            <td class="whitespace-nowrap px-2 py-1.5 text-gray-600">{{ $unit->completed_at?->format('d M, H:i') ?? '—' }}</td>
                            <td class="px-4 py-1.5">
                                <span class="font-medium {{ $statusTone }}">{{ $statusLabel }}</span>
                                @if ($unit->status === BloodTransfusion::STATUS_STOPPED && $unit->stop_reason)
                                    <span class="block text-[11px] text-red-700">{{ $unit->stop_reason }}</span>
                                @endif
                                @if ($unit->completed_at && $unit->started_at)
                                    <span class="block text-[11px] text-gray-500">
                                        Ran {{ \App\Support\AdmissionTimeline::duration((int) $unit->elapsedMinutes()) }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
