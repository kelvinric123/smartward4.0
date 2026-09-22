{{--
    The dates, approver and notes of one privilege record, under its name.
    Expects $row (a privilege row from App\Support\NurseCredentialing).
--}}
@php
    $record = $row['privilege'];
    $recordHeld = $record->isActive();
    $recordMeta = array_filter([
        $record->granted_on ? 'Granted ' . $record->granted_on->format('j M Y') : null,
        $row['review'] ? 'Review by ' . $record->review_on->format('j M Y') : null,
        !$recordHeld && $record->status_changed_at
            ? $record->statusLabel() . ' ' . $record->status_changed_at->format('j M Y')
            : null,
        $record->approved_by ? 'Approved by ' . $record->approved_by : null,
    ]);
    $notesLabel = match (true) {
        in_array($record->status, \App\Models\NursePrivilege::REASON_REQUIRED, true) => 'Reason',
        $record->status === 'supervised' => 'Conditions',
        default => 'Notes',
    };
@endphp
@if($recordMeta)
    <p class="mt-1 text-xs text-gray-500">{{ implode(' · ', $recordMeta) }}</p>
@endif
@if($record->notes)
    <p class="mt-1 text-xs text-gray-600 whitespace-pre-line"><span class="font-medium">{{ $notesLabel }}:</span> {{ $record->notes }}</p>
@endif
