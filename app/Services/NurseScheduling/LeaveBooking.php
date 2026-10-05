<?php

namespace App\Services\NurseScheduling;

use App\Models\NurseLeave;
use App\Models\NurseRosterEntry;
use Carbon\CarbonInterface;

/**
 * Book a nurse's leave: the leave itself, and the AI-planned shifts on those
 * days removed, since the roster never books a nurse on a leave day. Shifts set
 * by hand are kept, so the clash shows on the roster for the manager to settle.
 */
final class LeaveBooking
{
    public const MAX_DAYS = 366;

    /**
     * @return array{leave: NurseLeave, removed: int}
     */
    public static function book(int $nurseId, string $type, CarbonInterface $start, CarbonInterface $end, ?string $note, ?int $createdBy): array
    {
        $leave = NurseLeave::create([
            'nurse_id' => $nurseId,
            'type' => $type,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'note' => $note,
            'created_by' => $createdBy,
        ]);

        $removed = NurseRosterEntry::where('nurse_id', $nurseId)
            ->where('source', NurseRosterEntry::SOURCE_AUTO)
            ->whereBetween('roster_date', [$start->toDateString(), $end->toDateString()])
            ->delete();

        return ['leave' => $leave, 'removed' => $removed];
    }

    /** "Annual leave added for Aisyah, 12 Oct to 14 Oct." plus what it removed from the roster. */
    public static function message(NurseLeave $leave, int $removed, string $verb = 'added'): string
    {
        $message = $leave->label() . ' ' . $verb . ' for ' . $leave->nurse->name . ', ' . $leave->start_date->format('j M')
            . ($leave->start_date->equalTo($leave->end_date) ? '' : ' to ' . $leave->end_date->format('j M')) . '.';

        if ($removed) {
            $message .= ' ' . $removed . ' AI-planned shifts on those days were removed; generate the roster again to fill the gaps.';
        }

        return $message;
    }
}
