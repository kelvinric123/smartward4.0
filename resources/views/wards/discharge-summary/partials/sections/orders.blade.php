{{-- What the doctors asked for during the stay: consultant orders (with how
     each was closed), referrals, and notes written from the doctor app. --}}
@php
    use App\Models\ConsultantOrder;

    $orderStatus = [
        ConsultantOrder::STATUS_OPEN => ['Open', 'text-indigo-700'],
        ConsultantOrder::STATUS_DONE => ['Done', 'text-green-700'],
        ConsultantOrder::STATUS_CANCELLED => ['Cancelled', 'text-gray-500'],
    ];
    $urgencyBadge = [
        'stat' => 'bg-red-600 text-white',
        'urgent' => 'bg-amber-100 text-amber-800',
        'routine' => 'bg-gray-100 text-gray-700',
    ];
    $orderRecordCount = $consultantOrders->count() + $referrals->count() + $consultantNotes->count();
@endphp

<section data-section="orders" class="mb-5 rounded-lg border border-gray-300 bg-white">
    <h3 class="{{ $sectionHeading }}">
        <span>Consultant orders &amp; notes</span>
        <span class="{{ $sectionCount }}">
            {{ $consultantOrders->count() }} {{ Str::plural('order', $consultantOrders->count()) }},
            {{ $consultantNotes->count() }} {{ Str::plural('note', $consultantNotes->count()) }}
        </span>
    </h3>

    @if ($orderRecordCount === 0)
        <p class="px-4 py-3 text-sm text-gray-500">No consultant orders, referrals or notes were recorded during this admission.</p>
    @else
        @if ($consultantOrders->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="{{ $tableHead }}">
                            <th class="px-4 py-1.5 font-semibold">Ordered</th>
                            <th class="px-2 py-1.5 font-semibold">Order</th>
                            <th class="px-2 py-1.5 font-semibold">Consultant</th>
                            <th class="px-2 py-1.5 font-semibold">Nurse</th>
                            <th class="px-4 py-1.5 font-semibold">Outcome</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($consultantOrders as $order)
                            @php [$statusLabel, $statusTone] = $orderStatus[$order->status] ?? [ucfirst((string) $order->status), 'text-gray-600']; @endphp
                            <tr class="break-inside-avoid align-top">
                                <td class="whitespace-nowrap px-4 py-1.5 text-gray-900">
                                    {{ $order->ordered_at?->format('d M, H:i') }}
                                    <span class="mt-0.5 block">
                                        <span class="rounded px-1.5 py-px text-[10px] font-bold uppercase {{ $urgencyBadge[$order->urgency] ?? $urgencyBadge['routine'] }}">
                                            {{ $order->urgencyLabel() }}
                                        </span>
                                    </span>
                                </td>
                                <td class="px-2 py-1.5 text-gray-900">
                                    <span class="whitespace-pre-line">{{ $order->instruction }}</span>
                                    @if ($order->handovers->isNotEmpty())
                                        <span class="block text-[11px] text-gray-500">
                                            Handed over {{ $order->handovers->count() }} {{ Str::plural('time', $order->handovers->count()) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-2 py-1.5 text-gray-600">{{ $order->consultant_name ?? $order->consultant?->name ?? '—' }}</td>
                                <td class="px-2 py-1.5 text-gray-600">{{ $order->assignedNurse?->name ?? '—' }}</td>
                                <td class="px-4 py-1.5">
                                    <span class="font-medium {{ $statusTone }}">{{ $statusLabel }}</span>
                                    @if ($order->closed_at)
                                        <span class="block whitespace-nowrap text-[11px] text-gray-500">
                                            {{ $order->closed_at->format('d M, H:i') }}{{ $order->closedBy ? ' · ' . $order->closedBy->name : '' }}
                                        </span>
                                    @endif
                                    @if ($order->outcome_note)
                                        <span class="block text-[11px] text-gray-600">{{ $order->outcome_note }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($referrals->isNotEmpty())
            <div class="border-t border-gray-200 px-4 py-3">
                <p class="mb-1.5 text-[11px] font-bold uppercase tracking-wider text-gray-600">Referrals</p>
                <ul class="space-y-1.5 text-sm">
                    @foreach ($referrals as $referral)
                        <li class="break-inside-avoid">
                            <span class="whitespace-nowrap text-gray-500">{{ $referral->created_at->format('d M Y, H:i') }}</span>
                            &middot;
                            <span class="font-medium text-gray-900">
                                {{ $referral->referral_type === 'anaesthetist' ? ($referral->anaesthetist?->name ?? 'Anaesthetist') : ($referral->consultant?->name ?? 'Consultant') }}
                            </span>
                            <span class="text-gray-500">({{ $referral->referral_type === 'anaesthetist' ? 'anaesthetist' : 'consultant' }})</span>
                            @if ($referral->creator)
                                <span class="text-gray-500">&middot; referred by {{ $referral->creator->name }}</span>
                            @endif
                            @if ($referral->reason || $referral->notes)
                                <span class="block text-xs text-gray-600">{{ collect([$referral->reason, $referral->notes])->filter()->implode(' — ') }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($consultantNotes->isNotEmpty())
            <div class="border-t border-gray-200 px-4 py-3">
                <p class="mb-1.5 text-[11px] font-bold uppercase tracking-wider text-gray-600">Consultant notes</p>
                <ul class="space-y-2 text-sm">
                    @foreach ($consultantNotes as $note)
                        <li class="break-inside-avoid border-l-2 border-indigo-200 pl-3">
                            <p class="text-xs text-gray-500">
                                {{ $note->created_at->format('d M Y, H:i') }} &middot;
                                <span class="font-semibold text-gray-700">{{ $note->consultant?->name ?? 'Consultant' }}</span>
                            </p>
                            <p class="whitespace-pre-line text-gray-900">{{ $note->note }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif
</section>
