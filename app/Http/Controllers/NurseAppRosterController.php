<?php

namespace App\Http\Controllers;

use App\Models\Nurse;
use App\Models\NurseLeave;
use App\Models\NurseRosterAcknowledgement;
use App\Models\NurseRosterRequest;
use App\Services\NurseApp\NurseAppActor;
use App\Services\NurseApp\NurseAppSchedule;
use App\Services\NurseScheduling\NurseRoster;
use App\Services\NurseScheduling\RosterRequests;
use App\Services\NurseScheduling\WardShifts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Nurse app: the nurse's own roster from the AI Nurse Schedule (shifts, leave,
 * public holidays, special duties), acknowledging it, asking for leave or a
 * shift swap, answering a colleague's swap, and the ward team on shift now.
 *
 * Every roster action answers { success, message, roster } with the refreshed
 * roster, so the app redraws from the server's view, as the patient chart does.
 */
class NurseAppRosterController extends Controller
{
    /** Weeks shown at once: this week and the next by default. */
    private const DEFAULT_WEEKS = 2;
    private const MAX_WEEKS = 6;

    /** How long a decided request stays listed. */
    private const HISTORY_DAYS = 14;

    /**
     * GET /api/nurse/roster?week=Y-m-d&weeks=2
     */
    public function show(Request $request): JsonResponse
    {
        $nurse = NurseAppActor::nurse($request);
        if (!$nurse) {
            return $this->unauthenticated();
        }

        return response()->json([
            'success' => true,
            'roster' => $this->roster($nurse, $request->input('week'), (int) $request->input('weeks', self::DEFAULT_WEEKS)),
        ]);
    }

    /**
     * POST /api/nurse/roster/acknowledge { week } - "I have seen my roster for this week".
     */
    public function acknowledge(Request $request): JsonResponse
    {
        $nurse = NurseAppActor::nurse($request);
        if (!$nurse) {
            return $this->unauthenticated();
        }

        $validated = $request->validate(['week' => 'required|date']);
        $week = NurseRoster::week($nurse, Carbon::parse($validated['week']));

        NurseRosterAcknowledgement::updateOrCreate(
            ['nurse_id' => $nurse->id, 'week_start' => $week['start']],
            ['fingerprint' => $week['fingerprint'], 'acknowledged_at' => now()]
        );

        $this->log('Roster acknowledged', $nurse, ['week' => $week['start']]);

        return $this->done($nurse, $request, 'Roster for ' . $week['label'] . ' acknowledged.');
    }

    /**
     * GET /api/nurse/roster/colleagues?date=Y-m-d - who could take a shift that day:
     * the ward team, each with their own shift (or off) and any leave.
     */
    public function colleagues(Request $request): JsonResponse
    {
        $nurse = NurseAppActor::nurse($request);
        if (!$nurse) {
            return $this->unauthenticated();
        }

        $validated = $request->validate(['date' => 'required|date']);
        $mine = NurseRoster::shiftOn($nurse, Carbon::parse($validated['date'])->toDateString());
        if (!$mine) {
            return $this->refuse('You are not rostered to work that day.');
        }

        return response()->json([
            'success' => true,
            'date' => $mine['day']['date'],
            'shift' => $mine['shift'],
            'colleagues' => NurseRoster::colleaguesOn($nurse, (int) $mine['ward_id'], $mine['day']['date'])->all(),
        ]);
    }

    /**
     * POST /api/nurse/requests
     *   { type: 'leave', leave_type, start_date, end_date, note? }
     *   { type: 'swap', shift_date, colleague_id, colleague_shift?, note? }
     */
    public function storeRequest(Request $request): JsonResponse
    {
        $nurse = NurseAppActor::nurse($request);
        if (!$nurse) {
            return $this->unauthenticated();
        }

        $validated = $request->validate([
            'type' => ['required', Rule::in([NurseRosterRequest::TYPE_LEAVE, NurseRosterRequest::TYPE_SWAP])],
            'leave_type' => ['required_if:type,leave', 'nullable', Rule::in(array_keys(NurseLeave::TYPES))],
            'start_date' => 'required_if:type,leave|nullable|date',
            'end_date' => 'required_if:type,leave|nullable|date',
            'shift_date' => 'required_if:type,swap|nullable|date',
            'colleague_id' => 'required_if:type,swap|nullable|integer|exists:nurses,id',
            'colleague_shift' => ['nullable', Rule::in(WardShifts::CODES)],
            'note' => 'nullable|string|max:255',
        ], [
            'colleague_id.required_if' => 'Choose the colleague to swap with.',
            'shift_date.required_if' => 'Choose the shift to swap.',
            'start_date.required_if' => 'Choose the first day of leave.',
            'end_date.required_if' => 'Choose the last day of leave.',
        ]);
        $note = filled($validated['note'] ?? null) ? trim($validated['note']) : null;

        try {
            if ($validated['type'] === NurseRosterRequest::TYPE_LEAVE) {
                $created = RosterRequests::requestLeave(
                    $nurse,
                    $validated['leave_type'],
                    Carbon::parse($validated['start_date'])->startOfDay(),
                    Carbon::parse($validated['end_date'])->startOfDay(),
                    $note
                );
                $message = 'Leave request sent to the nurse manager.';
            } else {
                $colleague = Nurse::findOrFail($validated['colleague_id']);
                $created = RosterRequests::requestSwap(
                    $nurse,
                    Carbon::parse($validated['shift_date'])->toDateString(),
                    $colleague,
                    $validated['colleague_shift'] ?? null,
                    $note
                );
                $message = 'Swap request sent to ' . $colleague->name . '. Once they accept, it goes to the nurse manager.';
            }
        } catch (RuntimeException $e) {
            return $this->refuse($e->getMessage());
        }

        $this->log('Roster request made', $nurse, ['nurse_roster_request_id' => $created->id, 'type' => $created->type]);

        return $this->done($nurse, $request, $message);
    }

    /**
     * POST /api/nurse/requests/{rosterRequest}/cancel - the requester withdraws it.
     */
    public function cancelRequest(Request $request, NurseRosterRequest $rosterRequest): JsonResponse
    {
        $nurse = NurseAppActor::nurse($request);
        if (!$nurse) {
            return $this->unauthenticated();
        }

        try {
            RosterRequests::cancel($rosterRequest, $nurse);
        } catch (RuntimeException $e) {
            return $this->refuse($e->getMessage(), 409);
        }

        $this->log('Roster request cancelled', $nurse, ['nurse_roster_request_id' => $rosterRequest->id]);

        return $this->done($nurse, $request, 'Request cancelled.');
    }

    /**
     * POST /api/nurse/requests/{rosterRequest}/respond { accept: bool, note? } - the
     * colleague asked to take a shift accepts (on to the nurse manager) or declines.
     */
    public function respondRequest(Request $request, NurseRosterRequest $rosterRequest): JsonResponse
    {
        $nurse = NurseAppActor::nurse($request);
        if (!$nurse) {
            return $this->unauthenticated();
        }

        $validated = $request->validate(['accept' => 'required|boolean', 'note' => 'nullable|string|max:255']);
        $accept = (bool) $validated['accept'];

        try {
            RosterRequests::respond($rosterRequest, $nurse, $accept, filled($validated['note'] ?? null) ? trim($validated['note']) : null);
        } catch (RuntimeException $e) {
            return $this->refuse($e->getMessage(), 409);
        }

        $this->log('Swap request ' . ($accept ? 'accepted' : 'declined'), $nurse, ['nurse_roster_request_id' => $rosterRequest->id]);

        return $this->done($nurse, $request, $accept
            ? 'Accepted. The swap now goes to the nurse manager for approval.'
            : 'Swap declined. ' . ($rosterRequest->nurse?->name ?? 'Your colleague') . ' will see your answer.');
    }

    /**
     * GET /api/nurse/team - who is on with the nurse this shift and next, with their
     * beds, workload and special duties (team leader first).
     */
    public function team(Request $request): JsonResponse
    {
        $nurse = NurseAppActor::nurse($request);
        if (!$nurse) {
            return $this->unauthenticated();
        }

        return response()->json(['success' => true, 'team' => NurseAppSchedule::team($nurse)]);
    }

    // -------------------------------------------------------------- helpers

    /** The roster screen: the weeks, the nurse's requests and the swaps asked of them. */
    private function roster(Nurse $nurse, ?string $week, int $weeks): array
    {
        try {
            $start = $week ? Carbon::parse($week) : now();
        } catch (\Throwable) {
            $start = now();
        }
        $start = $start->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $weeks = max(1, min(self::MAX_WEEKS, $weeks ?: self::DEFAULT_WEEKS));

        $weekRows = collect(range(0, $weeks - 1))
            ->map(fn (int $i) => collect(NurseRoster::week($nurse, $start->copy()->addWeeks($i)))->except('fingerprint')->all())
            ->all();

        $mine = NurseRosterRequest::with(['colleague:id,name', 'nurse:id,name'])
            ->where('nurse_id', $nurse->id)
            ->where(fn ($query) => $query->open()->orWhere('updated_at', '>=', now()->subDays(self::HISTORY_DAYS)))
            ->latest('id')
            ->get();
        $incoming = NurseRosterRequest::with(['colleague:id,name', 'nurse:id,name'])
            ->where('colleague_id', $nurse->id)
            ->where('status', NurseRosterRequest::STATUS_AWAITING_COLLEAGUE)
            ->latest('id')
            ->get();

        return [
            'nurse' => ['id' => $nurse->id, 'name' => $nurse->name, 'ward' => $nurse->ward?->ward_name],
            'start' => $start->toDateString(),
            'previous_week' => $start->copy()->subWeek()->toDateString(),
            'next_week' => $start->copy()->addWeek()->toDateString(),
            'weeks' => $weekRows,
            'requests' => [
                'mine' => $mine->map(fn (NurseRosterRequest $r) => $this->requestRow($r, $nurse))->values()->all(),
                'incoming' => $incoming->map(fn (NurseRosterRequest $r) => $this->requestRow($r, $nurse))->values()->all(),
            ],
            'options' => [
                'leave_types' => collect(NurseLeave::TYPES)
                    ->map(fn (array $type, string $key) => ['key' => $key, 'label' => $type['label'], 'code' => $type['code']])
                    ->values()
                    ->all(),
                'shifts' => collect(WardShifts::forWard($nurse->ward_id))
                    ->map(fn (array $shift) => ['code' => $shift['code'], 'name' => $shift['name'], 'time' => $shift['time']])
                    ->values()
                    ->all(),
            ],
        ];
    }

    private function requestRow(NurseRosterRequest $r, Nurse $nurse): array
    {
        $incoming = (int) $r->colleague_id === $nurse->id && (int) $r->nurse_id !== $nurse->id;

        // A swap asked of me reads from my side: what I would work, and what they take
        $day = $r->shift_date?->format('D j M');
        $summary = $incoming
            ? ($r->colleague_shift
                ? "You work {$r->shift} on {$day}; " . ($r->nurse?->name ?? 'they') . " works your {$r->colleague_shift}"
                : "Take " . ($r->nurse?->name ?? 'their') . "'s {$r->shift} shift on {$day} (you are off that day)")
            : $r->summary();

        return [
            'id' => $r->id,
            'type' => $r->type,
            'summary' => $summary,
            'from' => $incoming ? $r->nurse?->name : null,
            'status' => $r->status,
            'status_label' => $r->statusLabel(),
            'note' => $r->note,
            'created_label' => $r->created_at?->format('d M H:i'),
            'decided_label' => $r->decided_at?->format('d M H:i'),
            'decided_by' => $r->decided_by_name,
            'decision_note' => $r->decision_note,
            'can_cancel' => !$incoming && $r->isOpen(),
            'can_respond' => $incoming && $r->status === NurseRosterRequest::STATUS_AWAITING_COLLEAGUE,
        ];
    }

    private function done(Nurse $nurse, Request $request, string $message): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'roster' => $this->roster($nurse->fresh(), $request->input('week_shown'), (int) $request->input('weeks', self::DEFAULT_WEEKS)),
        ]);
    }

    private function refuse(string $message, int $status = 422): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }

    private function unauthenticated(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Unauthenticated. Please log in again.'], 401);
    }

    private function log(string $what, Nurse $nurse, array $context = []): void
    {
        Log::info('Nurse app: ' . $what, $context + ['nurse_id' => $nurse->id]);
    }
}
