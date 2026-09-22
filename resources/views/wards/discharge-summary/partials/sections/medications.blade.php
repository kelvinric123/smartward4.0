{{-- Medication orders of the stay (medication monitoring). "Active" means
     still running when the stay ended - or now, for a stay that has not. --}}
@php
    use App\Models\PatientMedication;

    $activeMeds = $medications->where('state', PatientMedication::STATUS_ACTIVE)->values();
    $closedMeds = $medications->where('state', '!=', PatientMedication::STATUS_ACTIVE)->values();
    $medGroups = [
        ['title' => $episode->isDischarged() ? 'Active at discharge' : 'Active now', 'rows' => $activeMeds, 'tone' => 'text-green-700'],
        ['title' => 'Stopped or completed during the stay', 'rows' => $closedMeds, 'tone' => 'text-gray-600'],
    ];
@endphp

<section data-section="medications" class="mb-5 rounded-lg border border-gray-300 bg-white">
    <h3 class="{{ $sectionHeading }}">
        <span>Medications</span>
        <span class="{{ $sectionCount }}">
            {{ $medications->count() }} {{ Str::plural('order', $medications->count()) }},
            {{ $medications->sum('given') }} {{ Str::plural('dose', $medications->sum('given')) }} given
        </span>
    </h3>

    @if ($medications->isEmpty())
        <p class="px-4 py-3 text-sm text-gray-500">No medication was charted during this admission.</p>
    @else
        @foreach ($medGroups as $group)
            @continue($group['rows']->isEmpty())
            <p class="border-b border-gray-200 px-4 pb-1 pt-3 text-[11px] font-bold uppercase tracking-wider {{ $group['tone'] }}">
                {{ $group['title'] }} ({{ $group['rows']->count() }})
            </p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="{{ $tableHead }}">
                            <th class="px-4 py-1.5 font-semibold">Medication</th>
                            <th class="px-2 py-1.5 font-semibold">Dose &amp; frequency</th>
                            <th class="px-2 py-1.5 font-semibold">Charted</th>
                            <th class="px-2 py-1.5 text-center font-semibold">Given</th>
                            <th class="px-2 py-1.5 text-center font-semibold">Held / refused</th>
                            <th class="px-2 py-1.5 font-semibold">Last given</th>
                            <th class="px-4 py-1.5 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($group['rows'] as $row)
                            @php $order = $row['order']; @endphp
                            <tr class="break-inside-avoid align-top">
                                <td class="px-4 py-1.5">
                                    <span class="font-medium text-gray-900">{{ $order->medication_name }}</span>
                                    @if ($order->is_high_alert)
                                        <span class="ml-1 rounded bg-red-100 px-1 text-[10px] font-bold uppercase text-red-700">High alert</span>
                                    @endif
                                    @if ($order->instructions)
                                        <span class="block text-[11px] text-gray-500">{{ $order->instructions }}</span>
                                    @endif
                                </td>
                                <td class="px-2 py-1.5 text-gray-700">
                                    {{ $order->summary() }}
                                    <span class="block text-[11px] text-gray-500">{{ $order->routeLabel() }}</span>
                                </td>
                                <td class="whitespace-nowrap px-2 py-1.5 text-gray-600">
                                    {{ $order->created_at?->format('d M, H:i') }}
                                    @if ($order->createdBy)
                                        <span class="block text-[11px] text-gray-500">{{ $order->createdBy->name }}</span>
                                    @endif
                                </td>
                                <td class="px-2 py-1.5 text-center font-semibold text-gray-900">{{ $row['given'] ?: '—' }}</td>
                                <td class="px-2 py-1.5 text-center {{ $row['held'] + $row['refused'] ? 'font-semibold text-amber-700' : 'text-gray-400' }}">
                                    @if ($row['held'] + $row['refused'])
                                        {{ $row['held'] }} / {{ $row['refused'] }}
                                    @else
                                        &mdash;
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-2 py-1.5 text-gray-600">{{ $row['lastGiven']?->format('d M, H:i') ?? '—' }}</td>
                                <td class="px-4 py-1.5">
                                    @if ($row['state'] === PatientMedication::STATUS_STOPPED)
                                        <span class="font-medium text-gray-700">Stopped</span>
                                        <span class="block whitespace-nowrap text-[11px] text-gray-500">
                                            {{ $order->stopped_at?->format('d M, H:i') }}{{ $order->stoppedBy ? ' · ' . $order->stoppedBy->name : '' }}
                                        </span>
                                        @if ($order->stop_reason)
                                            <span class="block text-[11px] text-gray-500">{{ $order->stop_reason }}</span>
                                        @endif
                                    @elseif ($row['state'] === PatientMedication::STATUS_COMPLETED)
                                        <span class="font-medium text-gray-700">Completed</span>
                                    @else
                                        <span class="font-medium text-green-700">Active</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
        <p class="border-t border-gray-200 px-4 py-1.5 text-[11px] text-gray-500">
            Each dose given, held or refused is listed with its time in the clinical timeline.
        </p>
    @endif
</section>
