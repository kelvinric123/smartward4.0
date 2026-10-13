<?php

namespace App\Services\NurseScheduling;

use App\Models\AdmissionLog;
use App\Models\Nurse;
use App\Models\PatientAcuityRating;
use App\Models\Ward;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Nursing productivity for one calendar month, for the monthly productivity
 * printout: the patients each day, the hours of care they needed, and the
 * registered nurse hours the roster put on the ward to give it, per day, for
 * the month and per nurse. This is the "improving" phase of workforce
 * management, monitoring and evaluating staffing (ANA, 2017, pp. 4, 8),
 * with benchmarks such as hours of nursing care per patient day, the staffing
 * mix and extra shifts (ANA, 2017, pp. 24-25).
 *
 * The figures are SmartWard's own and rest on what it records, which the
 * printout states:
 *   - hours are ROSTERED hours: SmartWard has no time-and-attendance clock,
 *     so overtime shows only as shifts above a nurse's target;
 *   - the census is the midnight census, worked back from the inpatients now
 *     through the admission log as the Command Center does, with moves
 *     between wards too; days after today are projected at today's count and
 *     are left out of the month's figures, which run to today;
 *   - the care hours a patient needs come from the nurses' acuity ratings that
 *     day for the patients rated, and from today's mix of care levels for
 *     the rest.
 *
 * Plain arrays out, no models: the printout reads only what is here.
 */
final class ProductivityReport
{
    public static function build(Ward $ward, CarbonInterface $month): array
    {
        $from = Carbon::instance($month)->copy()->startOfMonth()->startOfDay();
        $to = $from->copy()->endOfMonth()->startOfDay();
        $today = now()->startOfDay();
        $rules = RosterRules::forWard($ward);
        $settings = AcuitySettings::forWard($ward);

        // The roster a week at a time, as the roster grid builds it; only the month's days are used
        $boards = [];
        $boardOf = [];
        for ($monday = $from->copy()->startOfWeek(Carbon::MONDAY); $monday->lte($to); $monday->addWeek()) {
            $board = RosterBoard::build($ward, $monday->copy(), $rules);
            foreach ($board['dates'] as $date) {
                $boardOf[$date->toDateString()] = count($boards);
            }
            $boards[] = $board;
        }
        $shifts = $boards[0]['shifts'];
        $holidays = collect($boards)->flatMap(fn (array $board) => $board['holidays'])
            ->filter(fn (string $name, string $day) => $day >= $from->toDateString() && $day <= $to->toDateString())
            ->sortKeys()
            ->all();

        $census = self::census($ward, $from, $to, $today);
        $rated = self::ratedHours($ward, $from, $to, $settings);

        // For patients not rated that day, today's mix of care levels; with no patients now, Level 1's hours
        $current = AcuityStaffing::forShifts(PatientAcuity::forWard($ward), $shifts, $settings);
        $estimate = $current['patients'] ? (float) $current['hours_per_patient'] : $settings->hours(1);

        $days = [];
        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            $day = $date->toDateString();
            $board = $boards[$boardOf[$day]];
            $count = $census[$day];
            $ratings = $rated[$day] ?? null;
            if ($ratings && $ratings['patients'] >= $count['census']) {
                // Every patient rated (or more, with the day's comings and goings): their average
                $source = 'rated';
                $required = $count['census'] * $ratings['average'];
            } elseif ($ratings) {
                // Ratings are saved only for the patients whose level was set: the rest at today's mix
                $source = 'partly';
                $required = $ratings['hours'] + ($count['census'] - $ratings['patients']) * $estimate;
            } else {
                $source = 'estimated';
                $required = $count['census'] * $estimate;
            }
            $hoursPerPatient = $count['census'] ? $required / $count['census'] : ($ratings['average'] ?? $estimate);
            $required = round($required, 1);

            $perShift = [];
            $rnHours = 0.0;
            $allHours = 0.0;
            foreach (WardShifts::CODES as $code) {
                $onShift = RosterBoard::onShift($board, $day, $code);
                $registered = $onShift->filter(fn (Nurse $nurse) => AcuityStaffing::isRegisteredNurse($nurse))->count();
                $length = (float) $shifts[$code]['hours'];
                $min = (int) ($board['staffing'][$day][$code]['min'] ?? 0);
                $recommended = self::recommended($required * $settings->share($code) / 100, $length, $count['census'] > 0);

                $rnHours += $registered * $length;
                $allHours += $onShift->count() * $length;
                $perShift[$code] = [
                    'count' => $onShift->count(),
                    'registered' => $registered,
                    'min' => $min,
                    'recommended' => $recommended,
                    'below_min' => $onShift->count() < $min,
                    'short' => $registered < $recommended,
                ];
            }

            $days[] = [
                'date' => $date->copy(),
                'day' => $day,
                'weekend' => $date->isWeekend(),
                'holiday' => $holidays[$day] ?? null,
                'is_today' => $day === $today->toDateString(),
                'census' => $count['census'],
                'admissions' => $count['admissions'],
                'discharges' => $count['discharges'],
                'transfers_in' => $count['transfers_in'],
                'transfers_out' => $count['transfers_out'],
                'projected' => $count['projected'],
                'hours_per_patient' => round($hoursPerPatient, 1),
                'source' => $source,
                'rated_patients' => $ratings['patients'] ?? 0,
                'required_hours' => $required,
                'rostered' => $allHours > 0,
                'rn_hours' => round($rnHours, 1),
                'all_hours' => round($allHours, 1),
                'actual_hppd' => $count['census'] ? round($rnHours / $count['census'], 1) : null,
                'productivity' => $rnHours > 0 ? (int) round($required / $rnHours * 100) : null,
                'shifts_below_min' => count(array_filter($perShift, fn (array $shift) => $shift['below_min'])),
                'shifts_short' => count(array_filter($perShift, fn (array $shift) => $shift['short'])),
                'shifts' => $perShift,
            ];
        }

        // A nurse's month is weighed up to today, or to the last day rostered when the roster runs further:
        // set against the whole month, a roster built to the 18th leaves everyone "under"
        $lastRostered = collect($days)->where('rostered', true)->max('day');
        $through = max(min($today->toDateString(), $to->toDateString()), $lastRostered ?? '');
        $targetDays = collect($days)->filter(fn (array $day) => $day['day'] <= $through)->count();

        return [
            'month' => $from->copy(),
            'from' => $from,
            'to' => $to,
            'days_in_month' => $from->daysInMonth,
            'today' => $today->toDateString(),
            'shifts' => $shifts,
            'rules' => [
                'min_staff' => $rules->minStaff,
                'target_shifts_per_week' => $rules->targetShiftsPerWeek,
                'target_shifts' => self::targetShifts($rules, $targetDays),
                'target_days' => $targetDays,
                'target_through' => $targetDays ? Carbon::parse($through) : null,
            ],
            'settings' => [
                'hours' => collect(array_keys(PatientAcuity::LEVELS))->mapWithKeys(fn (int $level) => [$level => $settings->hours($level)])->all(),
                'shares' => collect(WardShifts::CODES)->mapWithKeys(fn (string $code) => [$code => $settings->share($code)])->all(),
            ],
            'estimate' => [
                'hours_per_patient' => round($estimate, 1),
                'patients' => $current['patients'],
            ],
            'holidays' => collect($holidays)->map(fn (string $name, string $day) => ['date' => Carbon::parse($day), 'name' => $name])->values()->all(),
            'days' => $days,
            'totals' => self::totals($days),
            'nurses' => self::nurses($boards, $from, $through, $targetDays, $holidays, $shifts, $rules),
        ];
    }

    /**
     * The census each day of the month, worked back from the inpatients now
     * as CommandCenterSummary::flow() does: each midnight had the next day's
     * count, less that day's admissions and transfers in, plus its discharges
     * and transfers out. Today is the count now; later days are projected at
     * it. Never below 0.
     *
     * @return array<string, array{census: int, admissions: int, discharges: int, transfers_in: int, transfers_out: int, projected: bool}>
     */
    private static function census(Ward $ward, Carbon $from, Carbon $to, Carbon $today): array
    {
        $inpatients = WorkloadCalculator::wardBeds($ward)['patients']->count();
        $until = ($to->gt($today) ? $to : $today)->copy()->endOfDay();
        $perDay = fn (string $action, string $column) => AdmissionLog::where('action', $action)
            ->where('ward_id', $ward->id)
            ->whereBetween($column, [$from, $until])
            ->toBase()
            ->pluck($column)
            ->countBy(fn ($at) => date('Y-m-d', strtotime((string) $at)))
            ->all();
        $admissions = $perDay('admit', 'admitted_at');
        $discharges = $perDay('discharge', 'discharged_at');
        [$transfersIn, $transfersOut] = self::transfers($ward, $from, $until);

        $counts = [];
        $running = $inpatients;
        for ($date = $today->copy(); $date->gte($from); $date->subDay()) {
            if ($date->lt($today)) {
                $next = $date->copy()->addDay()->toDateString();
                $running = $running - ($admissions[$next] ?? 0) - ($transfersIn[$next] ?? 0)
                    + ($discharges[$next] ?? 0) + ($transfersOut[$next] ?? 0);
            }
            if ($date->lte($to)) {
                $counts[$date->toDateString()] = $running;
            }
        }

        $days = [];
        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            $day = $date->toDateString();
            $projected = $date->gt($today);
            $days[$day] = [
                'census' => max(0, $projected ? $inpatients : $counts[$day]),
                'admissions' => $admissions[$day] ?? 0,
                'discharges' => $discharges[$day] ?? 0,
                'transfers_in' => $transfersIn[$day] ?? 0,
                'transfers_out' => $transfersOut[$day] ?? 0,
                'projected' => $projected,
            ];
        }

        return $days;
    }

    /**
     * Moves between wards per day, [in, out]. A move is logged only as a
     * 'transfer' on the ward the patient went to (when it was written), with
     * nothing on the ward they left, so the ward left is that of the patient's
     * log entry before it: an admission or an earlier move. A move between
     * beds on one ward is neither; after a discharge there is no ward left.
     * The Command Center needs none of this: over all wards a move cancels out.
     *
     * @return array{0: array<string, int>, 1: array<string, int>}
     */
    private static function transfers(Ward $ward, Carbon $from, Carbon $until): array
    {
        // Only a patient who has been on this ward can have moved out of it
        $ours = AdmissionLog::where('ward_id', $ward->id)->whereIn('action', ['admit', 'transfer'])->select('patient_id');
        $moves = AdmissionLog::where('action', 'transfer')
            ->whereNotNull('patient_id')
            ->whereBetween('created_at', [$from, $until])
            ->where(fn ($query) => $query->where('ward_id', $ward->id)->orWhereIn('patient_id', $ours))
            ->toBase()
            ->get(['id', 'patient_id', 'ward_id', 'created_at']);
        if ($moves->isEmpty()) {
            return [[], []];
        }

        // Each of those patients' stay entries, in the order they were written
        $history = AdmissionLog::whereIn('patient_id', $moves->pluck('patient_id')->unique())
            ->whereIn('action', ['admit', 'transfer', 'discharge'])
            ->where('id', '<', $moves->max('id'))
            ->orderBy('id')
            ->toBase()
            ->get(['id', 'patient_id', 'ward_id', 'action'])
            ->groupBy('patient_id');

        $in = [];
        $out = [];
        foreach ($moves as $move) {
            $before = $history->get($move->patient_id, collect())->last(fn (object $log) => $log->id < $move->id);
            $left = $before && $before->action !== 'discharge' ? (int) $before->ward_id : null;
            $went = (int) $move->ward_id;
            if ($left === $went) {
                continue;
            }
            $day = date('Y-m-d', strtotime((string) $move->created_at));
            if ($went === $ward->id) {
                $in[$day] = ($in[$day] ?? 0) + 1;
            } elseif ($left === $ward->id) {
                $out[$day] = ($out[$day] ?? 0) + 1;
            }
        }

        return [$in, $out];
    }

    /**
     * The nurses' ratings each day: how many patients were rated, the hours
     * of care their last rating that day calls for, and the average per
     * patient rated. The rest of that day's patients are not in here.
     *
     * @return array<string, array{hours: float, average: float, patients: int}>
     */
    private static function ratedHours(Ward $ward, Carbon $from, Carbon $to, AcuitySettings $settings): array
    {
        return PatientAcuityRating::where('ward_id', $ward->id)
            ->whereBetween('rated_at', [$from, $to->copy()->endOfDay()])
            ->orderBy('rated_at')
            ->orderBy('id')
            ->toBase()
            ->get(['patient_id', 'level', 'rated_at'])
            ->groupBy(fn (object $rating) => date('Y-m-d', strtotime((string) $rating->rated_at)))
            ->map(function (Collection $ratings) use ($settings) {
                // Sorted oldest first, so keying by patient keeps each one's last rating
                $latest = $ratings->keyBy('patient_id');
                $hours = (float) $latest->sum(fn (object $rating) => $settings->hours((int) $rating->level));

                return [
                    'hours' => $hours,
                    'average' => $hours / $latest->count(),
                    'patients' => $latest->count(),
                ];
            })
            ->all();
    }

    /**
     * The registered nurses a shift's care hours call for, rounded as
     * AcuityStaffing::forShifts() rounds them: a sliver of a nurse is not
     * rounded up into a whole one, and any patient needs at least one nurse.
     */
    private static function recommended(float $careHours, float $length, bool $patients): int
    {
        $exact = $length > 0 ? $careHours / $length : 0.0;

        return $patients ? max(1, (int) ceil(round($exact, 2) - AcuityStaffing::ROUNDING_SLACK)) : 0;
    }

    /** A nurse's shifts over so many days before leave: the weekly target spread over them. */
    private static function targetShifts(RosterRules $rules, int $days): float
    {
        return round($rules->targetShiftsPerWeek * $days / 7, 1);
    }

    /**
     * The month's figures, from the days up to today. Days after today carry
     * today's census and often no roster yet, so they are added up apart
     * ("projected") and never into the month's figures or shortfalls.
     */
    private static function totals(array $days): array
    {
        $all = collect($days);
        $days = $all->where('projected', false);
        $projected = $all->where('projected', true);
        $patientDays = $days->sum('census');
        $required = round($days->sum('required_hours'), 1);
        $rnHours = round($days->sum('rn_hours'), 1);
        $allHours = round($days->sum('all_hours'), 1);

        return [
            'days' => $days->count(),
            'patient_days' => $patientDays,
            'admissions' => $days->sum('admissions'),
            'discharges' => $days->sum('discharges'),
            'transfers_in' => $days->sum('transfers_in'),
            'transfers_out' => $days->sum('transfers_out'),
            'average_census' => $days->isEmpty() ? 0.0 : round($patientDays / $days->count(), 1),
            'required_hours' => $required,
            'rn_hours' => $rnHours,
            'all_hours' => $allHours,
            'rn_share' => $allHours > 0 ? (int) round($rnHours / $allHours * 100) : null,
            'actual_hppd' => $patientDays ? round($rnHours / $patientDays, 1) : null,
            'target_hppd' => $patientDays ? round($required / $patientDays, 1) : null,
            'productivity' => $rnHours > 0 ? (int) round($required / $rnHours * 100) : null,
            'shifts' => $days->count() * count(WardShifts::CODES),
            'shifts_below_min' => $days->sum('shifts_below_min'),
            'shifts_short' => $days->sum('shifts_short'),
            'days_rated' => $days->where('source', 'rated')->count(),
            'days_partly_rated' => $days->where('source', 'partly')->count(),
            'days_estimated' => $days->where('source', 'estimated')->count(),
            'days_projected' => $projected->count(),
            'holidays' => $all->whereNotNull('holiday')->count(),
            'projected' => [
                'days' => $projected->count(),
                'days_rostered' => $projected->where('rostered', true)->count(),
                'patient_days' => $projected->sum('census'),
                'required_hours' => round($projected->sum('required_hours'), 1),
                'rn_hours' => round($projected->sum('rn_hours'), 1),
                'all_hours' => round($projected->sum('all_hours'), 1),
            ],
        ];
    }

    /**
     * Each nurse on the month's roster: shifts by code, hours, nights,
     * weekend and holiday shifts and leave days from the 1st to $through
     * (the $days the target covers), against the target: the weekly target
     * spread over those days, less each leave day's share of it (leave is
     * booked by the calendar day, weekends included, as the target is spread).
     * Shifts above it are extra shifts, the nearest SmartWard has to overtime.
     * Like the roster's totals, a shift on another ward counts.
     */
    private static function nurses(array $boards, Carbon $from, string $through, int $days, array $holidays, array $shifts, RosterRules $rules): array
    {
        $target = self::targetShifts($rules, $days);
        $team = collect($boards)->flatMap(fn (array $board) => $board['team'])->unique('id');

        return $team->map(function (Nurse $nurse) use ($boards, $from, $through, $days, $holidays, $shifts, $target, $rules) {
            $row = ['AM' => 0, 'PM' => 0, 'ON' => 0, 'shifts' => 0, 'hours' => 0.0, 'weekend' => 0, 'holiday' => 0, 'other_ward' => 0, 'leave' => 0];

            foreach ($boards as $board) {
                foreach ($board['cells'][$nurse->id] ?? [] as $day => $cell) {
                    if ($day < $from->toDateString() || $day > $through) {
                        continue;
                    }
                    if (in_array($cell['shift'], WardShifts::CODES, true)) {
                        $row[$cell['shift']]++;
                        $row['shifts']++;
                        $row['hours'] += $shifts[$cell['shift']]['hours'];
                        $row['weekend'] += Carbon::parse($day)->isWeekend() ? 1 : 0;
                        $row['holiday'] += isset($holidays[$day]) ? 1 : 0;
                        $row['other_ward'] += $cell['other_ward'] ? 1 : 0;
                    }
                    if ($cell['leave']) {
                        $row['leave']++;
                    }
                }
            }

            $expected = self::targetShifts($rules, max(0, $days - $row['leave']));

            return [
                'id' => $nurse->id,
                'name' => $nurse->name,
                'designation' => $nurse->designation,
                'registered' => AcuityStaffing::isRegisteredNurse($nurse),
                'hours' => round($row['hours'], 1),
                'target' => $target,
                'expected' => $expected,
                'variance' => round($row['shifts'] - $expected, 1),
            ] + $row;
        })
            ->sortBy(fn (array $row) => mb_strtolower($row['name']))
            ->values()
            ->all();
    }
}
