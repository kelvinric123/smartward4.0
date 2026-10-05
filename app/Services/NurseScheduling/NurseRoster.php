<?php

namespace App\Services\NurseScheduling;

use App\Models\Nurse;
use App\Models\NurseLeave;
use App\Models\NurseRosterAcknowledgement;
use App\Models\NurseRosterEntry;
use App\Models\PublicHoliday;
use App\Models\WardScheduleAssignment;
use App\Models\WardSpecialDuty;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One nurse's own roster, day by day, as the nurse app shows it: the shift
 * (from the AI Nurse Schedule, or the beds assigned on the Ward Schedule when
 * there is no roster entry, as RosterBoard reads it), the ward, the beds, any
 * leave, public holiday and special duty (Team Leader, ...).
 *
 * ON on a date is the night that starts that evening.
 */
final class NurseRoster
{
    /** Special duties (Ward Schedule > Special Duty) by key. */
    public const DUTY_LABELS = [
        'team_leader' => 'Team Leader',
        'dda_mc_book' => 'DDA + MC book',
        'medication_fridge' => 'Medication fridge',
        'e_trolley' => 'E-trolley',
        'qc_checking' => 'QC checking',
    ];

    /**
     * @return array<int, array> one row per day, from $from for $count days
     */
    public static function days(Nurse $nurse, CarbonInterface $from, int $count, ?CarbonInterface $now = null): array
    {
        $now = $now ? Carbon::instance($now) : now();
        $start = Carbon::instance($from)->copy()->startOfDay();
        $end = $start->copy()->addDays($count - 1);
        [$fromDate, $toDate] = [$start->toDateString(), $end->toDateString()];

        $entries = NurseRosterEntry::where('nurse_id', $nurse->id)
            ->whereBetween('roster_date', [$fromDate, $toDate])
            ->with('ward:id,ward_name')
            ->get()
            ->keyBy(fn (NurseRosterEntry $entry) => $entry->roster_date->toDateString());
        $beds = WardScheduleAssignment::where('nurse_id', $nurse->id)
            ->whereBetween('scheduled_date', [$fromDate, $toDate])
            ->with(['bed:id,bed_number,bed_display_name', 'ward:id,ward_name'])
            ->get()
            ->groupBy(fn (WardScheduleAssignment $row) => $row->scheduled_date->toDateString());
        $leaves = NurseLeave::where('nurse_id', $nurse->id)->overlapping($start, $end)->get();
        $holidays = PublicHoliday::whereBetween('holiday_date', [$fromDate, $toDate])->get()
            ->mapWithKeys(fn (PublicHoliday $holiday) => [$holiday->holiday_date->toDateString() => $holiday->name]);
        $duties = WardSpecialDuty::where('nurse_id', $nurse->id)
            ->whereBetween('date', [$fromDate, $toDate])
            ->get()
            ->groupBy(fn (WardSpecialDuty $duty) => $duty->date->toDateString());

        $shiftTimes = [];
        $timesFor = function (?int $wardId) use (&$shiftTimes) {
            return $shiftTimes[$wardId ?? 0] ??= WardShifts::forWard($wardId);
        };

        $days = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $day = $date->toDateString();
            $entry = $entries->get($day);
            $dayBeds = $beds->get($day) ?? collect();
            $leave = $leaves->first(fn (NurseLeave $leave) => $leave->covers($date));

            $shift = $entry?->shift
                ?? collect(WardShifts::CODES)->first(fn (string $code) => $dayBeds->contains('shift', $code));
            $working = in_array($shift, WardShifts::CODES, true);
            $wardId = $entry?->ward_id ?? $dayBeds->first()?->ward_id ?? $nurse->ward_id;
            $wardName = $entry?->ward?->ward_name ?? $dayBeds->first()?->ward?->ward_name;
            $times = $working ? ($timesFor($wardId)[$shift] ?? null) : null;
            $shiftBeds = $working ? $dayBeds->where('shift', $shift) : collect();
            $started = $times ? Carbon::parse($day . ' ' . $times['start'])->lessThanOrEqualTo($now) : false;

            $days[] = [
                'date' => $day,
                'label' => $date->format('D j M'),
                'weekday' => $date->format('D'),
                'day' => (int) $date->format('j'),
                'month' => $date->format('M'),
                'is_today' => $date->isSameDay($now),
                'is_past' => $date->lt($now->copy()->startOfDay()),
                'is_weekend' => $date->isWeekend(),
                'shift' => $shift,
                'shift_name' => $times['name'] ?? ($shift === NurseRosterEntry::OFF ? 'Day off' : null),
                'time' => $times['time'] ?? null,
                'hours' => $times['hours'] ?? 0,
                'ward_id' => $working ? $wardId : null,
                'ward' => $working ? $wardName : null,
                'other_ward' => $working && $nurse->ward_id && $wardId && (int) $wardId !== (int) $nurse->ward_id,
                'beds' => $shiftBeds->map(fn (WardScheduleAssignment $row) => $row->bed?->bed_display_name ?: $row->bed?->bed_number)
                    ->filter()->values()->all(),
                'leave' => $leave ? ['code' => $leave->code(), 'label' => $leave->label()] : null,
                'holiday' => $holidays->get($day),
                'duties' => $working
                    ? ($duties->get($day) ?? collect())->where('shift', $shift)
                        ->map(fn (WardSpecialDuty $duty) => self::DUTY_LABELS[$duty->duty_type] ?? ucfirst(str_replace('_', ' ', $duty->duty_type)))
                        ->values()->all()
                    : [],
                'source' => $entry?->source ?? ($dayBeds->isNotEmpty() ? 'schedule' : null),
                // A shift not yet started can be offered to a colleague
                'swappable' => $working && !$leave && !$started,
            ];
        }

        return $days;
    }

    /**
     * One week, Monday to Sunday: its days, totals, and whether the nurse has seen it
     * as it stands (seen), saw an earlier version (changed), or has not looked (new).
     */
    public static function week(Nurse $nurse, CarbonInterface $weekStart, ?CarbonInterface $now = null): array
    {
        $start = Carbon::instance($weekStart)->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $days = self::days($nurse, $start, 7, $now);
        $working = collect($days)->filter(fn (array $day) => in_array($day['shift'], WardShifts::CODES, true));
        $fingerprint = self::fingerprint($days);

        $ack = NurseRosterAcknowledgement::where('nurse_id', $nurse->id)
            ->whereDate('week_start', $start->toDateString())
            ->first();

        return [
            'start' => $start->toDateString(),
            'label' => $start->format('j M') . ' – ' . $start->copy()->addDays(6)->format('j M'),
            'days' => $days,
            'totals' => [
                'shifts' => $working->count(),
                'hours' => round($working->sum('hours'), 1),
                'nights' => $working->where('shift', 'ON')->count(),
                'leave_days' => collect($days)->whereNotNull('leave')->count(),
            ],
            'fingerprint' => $fingerprint,
            'acknowledgement' => [
                'state' => $ack ? ($ack->fingerprint === $fingerprint ? 'seen' : 'changed') : 'new',
                'at_label' => $ack?->acknowledged_at?->format('d M H:i'),
            ],
        ];
    }

    /**
     * The roster as the nurse sees it, for acknowledgements: each day's shift, ward
     * and leave. Beds are left out, since they are reassigned every shift.
     */
    public static function fingerprint(array $days): string
    {
        return hash('sha256', json_encode(array_map(fn (array $day) => [
            $day['date'],
            $day['shift'],
            $day['ward_id'],
            $day['leave']['code'] ?? null,
        ], $days)));
    }

    /** What a nurse works on a date: the shift and ward, from the roster or the beds assigned. */
    public static function shiftOn(Nurse $nurse, string $date): ?array
    {
        $day = self::days($nurse, Carbon::parse($date), 1)[0];

        return in_array($day['shift'], WardShifts::CODES, true)
            ? ['shift' => $day['shift'], 'ward_id' => $day['ward_id'], 'day' => $day]
            : null;
    }

    /** The nurses on a ward's roster who are working, or free, on a date: for the swap form. */
    public static function colleaguesOn(Nurse $nurse, int $wardId, string $date): Collection
    {
        $ward = \App\Models\Ward::find($wardId);
        if (!$ward) {
            return collect();
        }

        return WardTeam::for($ward)['nurses']
            ->reject(fn (Nurse $other) => $other->id === $nurse->id)
            ->map(function (Nurse $other) use ($date) {
                $day = self::days($other, Carbon::parse($date), 1)[0];

                return [
                    'id' => $other->id,
                    'name' => $other->name,
                    'designation' => $other->designation,
                    'shift' => $day['shift'],
                    'on_leave' => $day['leave']['label'] ?? null,
                ];
            })
            ->values();
    }
}
