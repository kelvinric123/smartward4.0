<?php

namespace App\Services\NurseApp;

use App\Models\Bed;
use App\Models\Nurse;
use App\Models\NurseRosterEntry;
use App\Models\Ward;
use App\Models\WardScheduleAssignment;
use App\Models\WardSpecialDuty;
use App\Services\NurseScheduling\BedAssigner;
use App\Services\NurseScheduling\NurseRoster;
use App\Services\NurseScheduling\WardShifts;
use App\Services\NurseScheduling\WorkloadCalculator;
use App\Services\NurseScheduling\WorkloadWeights;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The AI Nurse Schedule as the nurse app shows it: who is on with the nurse this
 * shift and next (their beds, workload and special duties), and how heavy each
 * of the nurse's beds is, scored as the AI planner scores them.
 */
final class NurseAppSchedule
{
    /**
     * The shift on now and the one after it, for a ward: date + shift code.
     *
     * "Now" is today's entry for the shift the clock is in, the rule the ward
     * dashboard and the nurse app's bed list use (see the night-shift note in
     * NurseRosterEntry: after midnight that reads the night starting tonight).
     *
     * @return array{current: ?array, next: array}
     */
    public static function slots(?int $wardId, ?CarbonInterface $now = null): array
    {
        $now = $now ? Carbon::instance($now) : now();
        $shifts = WardShifts::forWard($wardId);
        $code = WardShifts::currentCode($shifts, $now);
        $today = $now->toDateString();

        $order = WardShifts::CODES;
        if ($code === null) {
            // Between shifts: next is whichever starts soonest
            $next = collect($shifts)
                ->map(fn (array $shift) => Carbon::parse($today . ' ' . $shift['start']))
                ->map(fn (Carbon $start) => $start->lessThanOrEqualTo($now) ? $start->addDay() : $start)
                ->sort()
                ->keys()
                ->first();
            $nextDate = Carbon::parse($today . ' ' . $shifts[$next]['start'])->lessThanOrEqualTo($now) ? $now->copy()->addDay()->toDateString() : $today;
        } else {
            $next = $order[(array_search($code, $order, true) + 1) % count($order)];
            // After the night: this morning's AM when past midnight, tomorrow's before it
            $nextDate = $code === 'ON' && $now->format('H:i') >= $shifts['ON']['start'] ? $now->copy()->addDay()->toDateString() : $today;
        }

        $slot = fn (string $c, string $date) => [
            'date' => $date,
            'code' => $c,
            'name' => $shifts[$c]['name'],
            'time' => $shifts[$c]['time'],
            'label' => $c . ($date === $today ? '' : ($date === $now->copy()->addDay()->toDateString() ? ' tomorrow' : ' ' . Carbon::parse($date)->format('j M'))),
        ];

        return [
            'current' => $code ? $slot($code, $today) : null,
            'next' => $slot($next, $nextDate),
        ];
    }

    /**
     * The nurse's ward team this shift and next: each nurse with their beds, patients,
     * workload against the shift average, and special duties; the team leader first.
     */
    public static function team(Nurse $nurse): array
    {
        $ward = self::wardNow($nurse);
        if (!$ward) {
            return ['ward' => null, 'current' => null, 'next' => null];
        }

        $slots = self::slots($ward->id);
        $weights = WorkloadWeights::forWard($ward);
        $beds = WorkloadCalculator::forWard($ward, $weights);

        return [
            'ward' => ['id' => $ward->id, 'name' => $ward->ward_name],
            'current' => $slots['current'] ? self::slotTeam($ward, $slots['current'], $beds, $weights, $nurse) : null,
            'next' => self::slotTeam($ward, $slots['next'], $beds, $weights, $nurse),
        ];
    }

    /**
     * How heavy each bed is on the given wards, and each nurse's share of the shift on
     * now there. Keyed by bed id; each nurse's load under 'loads' by nurse id.
     *
     * @return array{beds: array<int, array>, loads: array<int, array>}
     */
    public static function workload(Collection $wardIds): array
    {
        $beds = [];
        $loads = [];

        foreach (Ward::whereIn('id', $wardIds->filter()->unique())->get() as $ward) {
            $weights = WorkloadWeights::forWard($ward);
            $rows = WorkloadCalculator::forWard($ward, $weights);
            $slot = self::slots($ward->id)['current'];
            $mapping = $slot ? BedAssigner::current($ward, $slot['date'], $slot['code']) : [];

            foreach ($rows as $bedId => $row) {
                $beds[$bedId] = [
                    'score' => $row['score'],
                    'factors' => array_map(fn (array $factor) => ['label' => $factor['label'], 'points' => $factor['points']], $row['factors']),
                ];
            }
            foreach (WorkloadCalculator::loads($rows, $mapping, [], $weights->band()) as $nurseId => $load) {
                $loads[$nurseId] = $load + [
                    'ward_id' => $ward->id,
                    'team_average' => self::average($rows, $mapping),
                    'team_size' => count(array_unique(array_values($mapping))),
                ];
            }
        }

        return ['beds' => $beds, 'loads' => $loads];
    }

    // ----------------------------------------------------------------- team

    private static function slotTeam(Ward $ward, array $slot, Collection $beds, WorkloadWeights $weights, Nurse $me): array
    {
        $mapping = BedAssigner::current($ward, $slot['date'], $slot['code']);
        $rostered = NurseRosterEntry::where('ward_id', $ward->id)
            ->whereDate('roster_date', $slot['date'])
            ->where('shift', $slot['code'])
            ->pluck('nurse_id');
        $nurseIds = collect(array_values($mapping))->merge($rostered)->map(fn ($id) => (int) $id)->unique()->values();
        $loads = WorkloadCalculator::loads($beds, $mapping, $nurseIds, $weights->band());
        $duties = WardSpecialDuty::where('ward_id', $ward->id)
            ->whereDate('date', $slot['date'])
            ->where('shift', $slot['code'])
            ->get()
            ->groupBy('nurse_id');
        $nurses = Nurse::whereIn('id', $nurseIds)->get()->keyBy('id');
        $bedLabel = function (int $bedId) use ($beds) {
            $bed = $beds->get($bedId)['bed'] ?? Bed::find($bedId);

            return $bed ? ($bed->bed_display_name ?: $bed->bed_number) : null;
        };

        $team = $nurseIds->map(function (int $nurseId) use ($nurses, $mapping, $loads, $duties, $bedLabel, $me) {
            $nurse = $nurses->get($nurseId);
            $mine = collect($mapping)->filter(fn (int $id) => $id === $nurseId)->keys();
            $dutyLabels = ($duties->get($nurseId) ?? collect())
                ->map(fn (WardSpecialDuty $duty) => NurseRoster::DUTY_LABELS[$duty->duty_type] ?? ucfirst(str_replace('_', ' ', $duty->duty_type)))
                ->values()
                ->all();

            return [
                'id' => $nurseId,
                'name' => $nurse?->name ?? 'Nurse #' . $nurseId,
                'designation' => $nurse?->designation,
                'is_me' => $nurseId === $me->id,
                'is_team_leader' => in_array('Team Leader', $dutyLabels, true),
                'duties' => $dutyLabels,
                'beds' => $mine->map($bedLabel)->filter()->values()->all(),
                'patients' => $loads[$nurseId]['patients'] ?? 0,
                'score' => $loads[$nurseId]['score'] ?? 0,
                'level' => $loads[$nurseId]['level'] ?? 'even',
            ];
        })
            // Team leader first, then me, then by name
            ->sortBy(fn (array $row) => [$row['is_team_leader'] ? 0 : 1, $row['is_me'] ? 0 : 1, $row['name']])
            ->values();

        $unassigned = $beds->filter(fn (array $row) => $row['patient'] && !isset($mapping[$row['bed']->id]))
            ->map(fn (array $row) => $row['bed']->bed_display_name ?: $row['bed']->bed_number)
            ->values();

        return $slot + [
            'nurses' => $team->all(),
            'average' => $team->isNotEmpty() ? round($team->avg('score'), 1) : 0,
            'unassigned_beds' => $unassigned->all(),
        ];
    }

    /** The ward the nurse is working on now (from the beds or roster), else their home ward. */
    private static function wardNow(Nurse $nurse): ?Ward
    {
        $home = $nurse->ward_id ? Ward::find($nurse->ward_id) : null;
        $slot = self::slots($home?->id)['current'];

        $wardId = $slot
            ? (WardScheduleAssignment::where('nurse_id', $nurse->id)->whereDate('scheduled_date', $slot['date'])->where('shift', $slot['code'])->value('ward_id')
                ?? NurseRosterEntry::where('nurse_id', $nurse->id)->whereDate('roster_date', $slot['date'])->value('ward_id'))
            : null;

        return $wardId ? Ward::find($wardId) : $home;
    }

    private static function average(Collection $rows, array $mapping): float
    {
        $nurses = array_unique(array_values($mapping));
        if (!$nurses) {
            return 0.0;
        }
        $total = 0.0;
        foreach (array_keys($mapping) as $bedId) {
            $total += $rows->get($bedId)['score'] ?? 0;
        }

        return round($total / count($nurses), 1);
    }
}
