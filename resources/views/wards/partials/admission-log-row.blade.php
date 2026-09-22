{{--
    One Admission Logs row, shared by the page and the printout.
    Expects $log, $columns (column keys in order), and optionally $cellClass
    and $clampNotes (the page clamps long notes to two lines; print shows all).
--}}
<tr class="{{ $rowClass ?? '' }}">
    @foreach ($columns as $column)
        <td data-col="{{ $column }}" class="{{ $cellClass ?? 'px-3 py-2' }} align-top">
            @switch($column)
                @case('time')
                    <div class="whitespace-nowrap text-gray-900">{{ $log->created_at?->format('d M Y') }}</div>
                    <div class="text-xs text-gray-500">{{ $log->created_at?->format('H:i') }}</div>
                    @break
                @case('action')
                    {{-- Long labels may wrap onto two lines rather than widen the table --}}
                    <span class="inline-block px-2 py-0.5 rounded-lg text-xs font-semibold leading-snug {{ $log::actionBadgeClass($log->action) }}">
                        {{ $log::actionLabel($log->action) }}
                    </span>
                    @break
                @case('patient')
                    <span class="font-medium text-gray-900 break-words">{{ $log->patient_name ?: '-' }}</span>
                    @break
                @case('mrn')
                    <span class="whitespace-nowrap text-gray-700">{{ $log->mrn ?: '-' }}</span>
                    @break
                @case('ward')
                    {{ $log->ward?->ward_name ?? '-' }}
                    @break
                @case('bed')
                    <span class="font-semibold whitespace-nowrap text-gray-900">{{ $log->bed_number ?: '-' }}</span>
                    @break
                @case('consultant')
                    <span class="break-words">{{ $log->consultant_name ?: '-' }}</span>
                    @break
                @case('nurse')
                    <span class="break-words">{{ $log->nurse_name ?: '-' }}</span>
                    @break
                @case('gender')
                    {{ $log->gender ? ucfirst($log->gender) : '-' }}
                    @break
                @case('age')
                    {{ $log->age ?? '-' }}
                    @break
                @case('admitted_at')
                    <span class="whitespace-nowrap">{{ $log->admitted_at?->format('d M Y H:i') ?? '-' }}</span>
                    @break
                @case('booked_at')
                    <span class="whitespace-nowrap">{{ $log->booked_at?->format('d M Y H:i') ?? '-' }}</span>
                    @break
                @case('source')
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold whitespace-nowrap {{ $log->source === 'adt' ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-700' }}">
                        {{ $log->sourceLabel() }}
                    </span>
                    @break
                @case('notes')
                    @if ($log->notes)
                        <div class="break-words {{ ($clampNotes ?? true) ? 'line-clamp-2' : '' }}" title="{{ $log->notes }}">{{ $log->notes }}</div>
                    @else
                        <span class="text-gray-400">-</span>
                    @endif
                    @break
                @case('user')
                    <span class="break-words">{{ $log->recordedByLabel() }}</span>
                    @break
            @endswitch
        </td>
    @endforeach
</tr>
