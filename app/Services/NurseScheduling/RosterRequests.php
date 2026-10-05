<?php

namespace App\Services\NurseScheduling;

use App\Models\Nurse;
use App\Models\NurseLeave;
use App\Models\NurseRosterEntry;
use App\Models\NurseRosterRequest;
use App\Models\Ward;
use App\Models\WardScheduleAssignment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Leave and shift-swap requests from the nurse app, and the nurse manager's
 * decision on the AI Nurse Schedule.
 *
 * Leave goes straight to the manager; approving it books the leave the way the
 * Leave tab does (LeaveBooking). A swap first goes to the colleague asked: once
 * they accept, it goes to the manager, and approving it swaps the two nurses'
 * shifts that day, in the roster (as entries set by hand) and in the beds each
 * held. A swap with no shift in return gives the shift away and leaves the
 * requester off that day.
 *
 * Every refusal is a RuntimeException whose message is meant for the person.
 */
final class RosterRequests
{
    public static function requestLeave(Nurse $nurse, string $type, Carbon $start, Carbon $end, ?string $note): NurseRosterRequest
    {
        $wardId = self::homeWard($nurse);
        if ($end->lt($start)) {
            throw new RuntimeException('The last day of leave cannot be before the first.');
        }
        if ($start->lt(now()->startOfDay())) {
            throw new RuntimeException('Leave cannot start in the past. Ask the nurse manager to record it.');
        }
        if ($start->diffInDays($end) + 1 > LeaveBooking::MAX_DAYS) {
            throw new RuntimeException('Leave can be at most ' . LeaveBooking::MAX_DAYS . ' days at a time.');
        }
        if (NurseLeave::where('nurse_id', $nurse->id)->overlapping($start, $end)->exists()) {
            throw new RuntimeException('You already have leave on some of those days.');
        }
        $overlapping = NurseRosterRequest::where('nurse_id', $nurse->id)->open()
            ->where('type', NurseRosterRequest::TYPE_LEAVE)
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->exists();
        if ($overlapping) {
            throw new RuntimeException('You already asked for leave on some of those days.');
        }

        return NurseRosterRequest::create([
            'ward_id' => $wardId,
            'nurse_id' => $nurse->id,
            'type' => NurseRosterRequest::TYPE_LEAVE,
            'leave_type' => $type,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'note' => $note,
            'status' => NurseRosterRequest::STATUS_PENDING,
        ]);
    }

    /**
     * Ask a colleague to take a shift: in exchange for their shift that day
     * ($colleagueShift), or as a give-away when they are not working (null).
     */
    public static function requestSwap(Nurse $nurse, string $date, Nurse $colleague, ?string $colleagueShift, ?string $note): NurseRosterRequest
    {
        $mine = NurseRoster::shiftOn($nurse, $date);
        if (!$mine) {
            throw new RuntimeException('You are not rostered to work on ' . Carbon::parse($date)->format('D j M') . '.');
        }
        if (!$mine['day']['swappable']) {
            throw new RuntimeException($mine['day']['leave'] ? 'You are on leave that day.' : 'That shift has already started.');
        }
        if (NurseRosterRequest::where('nurse_id', $nurse->id)->open()->where('type', NurseRosterRequest::TYPE_SWAP)->whereDate('shift_date', $date)->exists()) {
            throw new RuntimeException('You already asked to swap that shift.');
        }

        self::checkColleague($colleague, $nurse, $mine, $date, $colleagueShift);

        return NurseRosterRequest::create([
            'ward_id' => $mine['ward_id'] ?? self::homeWard($nurse),
            'nurse_id' => $nurse->id,
            'type' => NurseRosterRequest::TYPE_SWAP,
            'shift_date' => $date,
            'shift' => $mine['shift'],
            'colleague_id' => $colleague->id,
            'colleague_shift' => $colleagueShift,
            'note' => $note,
            'status' => NurseRosterRequest::STATUS_AWAITING_COLLEAGUE,
        ]);
    }

    /** The colleague's answer to a swap: on to the nurse manager, or declined. */
    public static function respond(NurseRosterRequest $request, Nurse $colleague, bool $accept, ?string $note): void
    {
        if ($request->status !== NurseRosterRequest::STATUS_AWAITING_COLLEAGUE || (int) $request->colleague_id !== $colleague->id) {
            throw new RuntimeException('That request is no longer waiting for your answer.');
        }

        $request->update($accept ? [
            'status' => NurseRosterRequest::STATUS_PENDING,
            'colleague_responded_at' => now(),
        ] : [
            'status' => NurseRosterRequest::STATUS_DECLINED,
            'colleague_responded_at' => now(),
            'decided_at' => now(),
            'decided_by_name' => $colleague->name,
            'decision_note' => $note ?: 'Declined by ' . $colleague->name . '.',
        ]);
    }

    public static function cancel(NurseRosterRequest $request, Nurse $nurse): void
    {
        if ((int) $request->nurse_id !== $nurse->id || !$request->isOpen()) {
            throw new RuntimeException('That request can no longer be cancelled.');
        }

        $request->update(['status' => NurseRosterRequest::STATUS_CANCELLED, 'decided_at' => now(), 'decided_by_name' => $nurse->name]);
    }

    /** The nurse manager approves: the leave is booked, or the shifts are swapped. */
    public static function approve(NurseRosterRequest $request, ?int $userId, ?string $userName, ?string $note = null): string
    {
        if ($request->status !== NurseRosterRequest::STATUS_PENDING) {
            throw new RuntimeException($request->status === NurseRosterRequest::STATUS_AWAITING_COLLEAGUE
                ? 'The colleague has not accepted this swap yet.'
                : 'That request has already been ' . strtolower($request->statusLabel()) . '.');
        }

        $message = DB::transaction(function () use ($request, $userId) {
            if (!$request->isSwap()) {
                ['leave' => $leave, 'removed' => $removed] = LeaveBooking::book(
                    $request->nurse_id,
                    $request->leave_type,
                    $request->start_date,
                    $request->end_date,
                    trim('Requested in the nurse app. ' . ($request->note ?? '')),
                    $userId
                );

                return LeaveBooking::message($leave, $removed, 'approved');
            }

            return self::applySwap($request);
        });

        $request->update([
            'status' => NurseRosterRequest::STATUS_APPROVED,
            'decided_by' => $userId,
            'decided_by_name' => $userName,
            'decided_at' => now(),
            'decision_note' => $note,
        ]);

        return $message;
    }

    public static function decline(NurseRosterRequest $request, ?int $userId, ?string $userName, ?string $note = null): void
    {
        if (!$request->isOpen()) {
            throw new RuntimeException('That request has already been ' . strtolower($request->statusLabel()) . '.');
        }

        $request->update([
            'status' => NurseRosterRequest::STATUS_DECLINED,
            'decided_by' => $userId,
            'decided_by_name' => $userName,
            'decided_at' => now(),
            'decision_note' => $note,
        ]);
    }

    // ------------------------------------------------------------- swaps

    /**
     * Swap the shifts as the roster stands now, which may have moved on since the
     * request: both nurses must still work (or be free) as the request says.
     */
    private static function applySwap(NurseRosterRequest $request): string
    {
        $date = $request->shift_date->toDateString();
        $nurse = $request->nurse;
        $colleague = $request->colleague;
        if (!$nurse || !$colleague) {
            throw new RuntimeException('One of the nurses is no longer on the system.');
        }

        $mine = NurseRoster::shiftOn($nurse, $date);
        if (!$mine || $mine['shift'] !== $request->shift) {
            throw new RuntimeException($nurse->name . ' is no longer rostered ' . $request->shift . ' that day. Decline the request.');
        }
        if (!$mine['day']['swappable']) {
            throw new RuntimeException('The shift has already started, or ' . $nurse->name . ' is now on leave.');
        }
        self::checkColleague($colleague, $nurse, $mine, $date, $request->colleague_shift, 'now ');

        $wardId = (int) ($mine['ward_id'] ?? $request->ward_id);

        // The roster: set by hand, so the generator plans around them
        NurseRosterEntry::updateOrCreate(
            ['nurse_id' => $nurse->id, 'roster_date' => $date],
            ['ward_id' => $wardId, 'shift' => $request->colleague_shift ?? NurseRosterEntry::OFF, 'source' => NurseRosterEntry::SOURCE_MANUAL]
        );
        NurseRosterEntry::updateOrCreate(
            ['nurse_id' => $colleague->id, 'roster_date' => $date],
            ['ward_id' => $wardId, 'shift' => $request->shift, 'source' => NurseRosterEntry::SOURCE_MANUAL]
        );

        // The beds go with the shift. Row by row, so the bedside-screen observer sees each change.
        $mineBeds = WardScheduleAssignment::where('nurse_id', $nurse->id)->whereDate('scheduled_date', $date)->where('shift', $request->shift)->get();
        $theirBeds = $request->colleague_shift
            ? WardScheduleAssignment::where('nurse_id', $colleague->id)->whereDate('scheduled_date', $date)->where('shift', $request->colleague_shift)->get()
            : collect();
        $mineBeds->each(fn (WardScheduleAssignment $row) => $row->update(['nurse_id' => $colleague->id]));
        $theirBeds->each(fn (WardScheduleAssignment $row) => $row->update(['nurse_id' => $nurse->id]));

        $day = Carbon::parse($date)->format('D j M');
        $beds = $mineBeds->count() + $theirBeds->count();

        return $request->colleague_shift
            ? "Swapped on {$day}: {$colleague->name} works {$request->shift}, {$nurse->name} works {$request->colleague_shift}."
                . ($beds ? " {$beds} bed assignments moved with the shifts." : '')
            : "{$colleague->name} takes {$nurse->name}'s {$request->shift} shift on {$day}; {$nurse->name} is off that day."
                . ($beds ? " {$beds} bed assignments moved with the shift." : '');
    }

    /** The colleague is on the same ward team, not on leave, and working (or free) as asked. */
    private static function checkColleague(Nurse $colleague, Nurse $nurse, array $mine, string $date, ?string $colleagueShift, string $when = ''): void
    {
        if ($colleague->id === $nurse->id) {
            throw new RuntimeException('Choose a colleague to swap with.');
        }
        $ward = Ward::find($mine['ward_id'] ?? $nurse->ward_id);
        $team = $ward ? WardTeam::for($ward)['nurses']->pluck('id') : collect();
        if (!$colleague->is_active || !$team->contains($colleague->id)) {
            throw new RuntimeException($colleague->name . ' is not on the roster for ' . ($ward?->ward_name ?? 'this ward') . '.');
        }

        $theirs = NurseRoster::days($colleague, Carbon::parse($date), 1)[0];
        if ($theirs['leave']) {
            throw new RuntimeException($colleague->name . ' is ' . $when . 'on ' . strtolower($theirs['leave']['label']) . ' that day.');
        }

        $theirShift = in_array($theirs['shift'], WardShifts::CODES, true) ? $theirs['shift'] : null;
        if ($colleagueShift === null && $theirShift !== null) {
            throw new RuntimeException($colleague->name . ' is ' . $when . 'working ' . $theirShift . ' that day: ask to swap shifts instead.');
        }
        if ($colleagueShift !== null) {
            if ($colleagueShift === $mine['shift']) {
                throw new RuntimeException('Swap for a different shift, or give the shift away.');
            }
            if ($theirShift !== $colleagueShift) {
                throw new RuntimeException($colleague->name . ' is ' . $when . 'not working ' . $colleagueShift . ' that day.');
            }
        }
    }

    private static function homeWard(Nurse $nurse): int
    {
        if ($nurse->ward_id) {
            return (int) $nurse->ward_id;
        }

        // No home ward: the ward of the nurse's next shift
        $next = NurseRosterEntry::where('nurse_id', $nurse->id)
            ->whereDate('roster_date', '>=', now()->toDateString())
            ->orderBy('roster_date')
            ->value('ward_id')
            ?? WardScheduleAssignment::where('nurse_id', $nurse->id)
                ->whereDate('scheduled_date', '>=', now()->toDateString())
                ->orderBy('scheduled_date')
                ->value('ward_id');

        if (!$next) {
            throw new RuntimeException('You have no home ward yet. Ask the nurse manager to set it on your nurse record.');
        }

        return (int) $next;
    }
}
