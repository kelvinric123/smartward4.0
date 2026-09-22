{{--
    Dose history for one medication order, latest first.
    Expects $order (administrations eager loaded, latest first) and $canUndo:
    when true the latest record can be removed with the delete passphrase.
--}}
@php
    $pm = \App\Models\PatientMedication::class;
@endphp
<div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
    <table class="min-w-full divide-y divide-gray-200 text-xs">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-3 py-2 text-left font-semibold text-gray-600">Time</th>
                <th class="px-3 py-2 text-left font-semibold text-gray-600">Outcome</th>
                <th class="px-3 py-2 text-left font-semibold text-gray-600">Dose</th>
                <th class="px-3 py-2 text-left font-semibold text-gray-600">Timing</th>
                <th class="px-3 py-2 text-left font-semibold text-gray-600">By</th>
                <th class="px-3 py-2 text-left font-semibold text-gray-600">Note</th>
                <th class="px-3 py-2"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach ($order->administrations as $dose)
                <tr>
                    <td class="px-3 py-2 whitespace-nowrap text-gray-800">{{ $dose->administered_at?->format('d M H:i') ?? '-' }}</td>
                    <td class="px-3 py-2">
                        <span class="inline-flex px-1.5 py-0.5 rounded font-semibold {{ $dose->statusBadgeClass() }}">
                            {{ $dose->statusLabel() }}
                        </span>
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap text-gray-600">
                        {{ $dose->dose_amount !== null ? $pm::formatAmount($dose->dose_amount) . ' ' . $dose->dose_unit : '-' }}
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap">
                        @if ($dose->minutesLate())
                            <span class="font-semibold text-red-600">{{ $pm::formatDuration($dose->minutesLate()) }} after due</span>
                        @elseif ($dose->due_at)
                            <span class="text-emerald-700">On time</span>
                        @else
                            <span class="text-gray-400">-</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-gray-600">{{ $dose->recordedBy->name ?? '-' }}</td>
                    <td class="px-3 py-2 text-gray-600">{{ $dose->notes ?: '-' }}</td>
                    <td class="px-3 py-2 text-right whitespace-nowrap">
                        @if ($canUndo && $loop->first)
                            {{-- The passphrase field only exists once Undo is clicked, so browsers never treat the page as a login form --}}
                            <div x-data="{ undoing: false }">
                                <button type="button" x-show="!undoing" @click="undoing = true"
                                    class="text-xs font-semibold text-red-600 hover:underline"
                                    title="Remove this record if it was entered by mistake">
                                    Undo
                                </button>
                                <template x-if="undoing">
                                    <form method="POST" action="{{ route('ward.medications.undo', $dose) }}"
                                        class="inline-flex items-center gap-1">
                                        @csrf
                                        <input type="hidden" name="active_tab" value="medications">
                                        <input type="password" name="delete_passphrase" required autocomplete="new-password"
                                            data-lpignore="true" data-1p-ignore data-form-type="other" placeholder="Passphrase"
                                            class="w-28 rounded border-gray-300 text-xs py-1 focus:border-red-500 focus:ring-red-500">
                                        <button type="submit"
                                            class="px-2 py-1 rounded bg-red-600 text-white text-xs font-semibold hover:bg-red-700">
                                            Remove
                                        </button>
                                        <button type="button" @click="undoing = false"
                                            class="px-2 py-1 text-xs text-gray-600 hover:underline">
                                            Cancel
                                        </button>
                                    </form>
                                </template>
                            </div>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
