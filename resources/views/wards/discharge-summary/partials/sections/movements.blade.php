{{-- Where the patient was during the stay: the bed history (admission,
     transfers, discharge) and trips out of the ward for procedures. --}}
@php
    $bedHistory = $admissionEvents
        ->filter(fn($log) => in_array($log->action, ['admit', 'check-in', 'transfer', 'discharge'], true))
        ->values();
    $movementStatus = [
        'scheduled' => ['Booked', 'text-gray-600'],
        'sent' => ['Out of the ward', 'text-amber-700'],
        'returned' => ['Returned', 'text-green-700'],
        'cancelled' => ['Cancelled', 'text-gray-400'],
    ];
@endphp

<section data-section="movements" class="mb-5 {{ $sectionBox }}">
    <h3 class="{{ $sectionHeading }}">
        <span>Transfers &amp; movements</span>
        <span class="{{ $sectionCount }}">
            {{ $highlights['transfers'] }} {{ Str::plural('transfer', $highlights['transfers']) }},
            {{ $movements->count() }} {{ Str::plural('trip', $movements->count()) }} out of the ward
        </span>
    </h3>

    <div class="px-4 py-3">
        <p class="mb-1.5 text-[11px] font-bold uppercase tracking-wider text-gray-600">Bed history</p>
        <ol class="space-y-1 text-sm">
            @foreach ($bedHistory as $log)
                @php
                    $isOpening = $log === $admission || ($log->exists && $log->id === $admission->id);
                    $when = $isOpening ? $episode->admittedAt : ($log->action === 'discharge' ? ($log->discharged_at ?? $log->created_at) : $log->created_at);
                    $what = match (true) {
                        $isOpening => $log->action === 'check-in' ? 'Checked in to' : 'Admitted to',
                        $log->action === 'transfer' => 'Transferred to',
                        $log->action === 'discharge' => 'Discharged from',
                        default => \App\Models\AdmissionLog::actionLabel($log->action),
                    };
                @endphp
                <li class="break-inside-avoid">
                    <span class="inline-block w-32 whitespace-nowrap text-gray-500">{{ $when?->format('d M Y, H:i') }}</span>
                    <span class="font-medium text-gray-900">{{ $what }}</span>
                    {{ $log->ward?->ward_name ?? 'ward' }}, bed {{ $log->bed_number ?? '—' }}
                    @if ($log->action === 'transfer' && $log->notes)
                        <span class="block pl-32 text-xs text-gray-500">{{ $log->notes }}</span>
                    @endif
                </li>
            @endforeach
            @if ($episode->isDischarged() && !$bedHistory->contains('action', 'discharge'))
                <li>
                    <span class="inline-block w-32 whitespace-nowrap text-gray-500">{{ $episode->dischargedAt->format('d M Y, H:i') }}</span>
                    <span class="font-medium text-gray-900">Discharged</span>
                </li>
            @endif
        </ol>
    </div>

    @if ($movements->isNotEmpty())
        <div class="border-t border-gray-200">
            <p class="px-4 pb-1 pt-3 text-[11px] font-bold uppercase tracking-wider text-gray-600">Out of the ward</p>
            <table class="w-full text-sm">
                <thead>
                    <tr class="{{ $tableHead }}">
                        <th class="px-4 py-1.5 font-semibold">Destination</th>
                        <th class="px-2 py-1.5 font-semibold">Booked for</th>
                        <th class="px-2 py-1.5 font-semibold">Left</th>
                        <th class="px-2 py-1.5 font-semibold">Back</th>
                        <th class="px-4 py-1.5 font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($movements as $movement)
                        @php [$statusLabel, $statusTone] = $movementStatus[$movement->status] ?? [ucfirst((string) $movement->status), 'text-gray-600']; @endphp
                        <tr class="break-inside-avoid align-top">
                            <td class="px-4 py-1.5">
                                <span class="font-medium text-gray-900">{{ $movement->location }}</span>
                                @if ($movement->location_type)
                                    <span class="text-[11px] text-gray-500">({{ $movement->location_type }})</span>
                                @endif
                                @if ($movement->notes)
                                    <span class="block text-[11px] text-gray-500">{{ $movement->notes }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-2 py-1.5 text-gray-600">{{ $movement->scheduled_at?->format('d M, H:i') ?? '—' }}</td>
                            <td class="whitespace-nowrap px-2 py-1.5 text-gray-600">{{ $movement->sent_at?->format('d M, H:i') ?? '—' }}</td>
                            <td class="whitespace-nowrap px-2 py-1.5 text-gray-600">{{ $movement->returned_at?->format('d M, H:i') ?? '—' }}</td>
                            <td class="px-4 py-1.5 font-medium {{ $statusTone }}">{{ $statusLabel }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
