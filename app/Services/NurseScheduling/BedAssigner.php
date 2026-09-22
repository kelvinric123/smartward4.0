<?php

namespace App\Services\NurseScheduling;

use App\Models\Bed;
use App\Models\Ward;
use App\Models\WardScheduleAssignment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Shares a ward's beds out between the nurses on a shift.
 *
 * Beds are handed out heaviest first, each to the nurse whose load would stay
 * lowest, so the workload evens out (the classic longest-job-first method).
 * Two small nudges break near-ties: a nurse keeps a bed they had on the same
 * shift the day before, for continuity, and a nurse's beds stay next to each
 * other. Each nudge is the imbalance, in workload points, the ward's weights
 * accept for it, so neither outweighs a real difference in load.
 *
 * The result is written to ward_schedule_assignments, which the ward
 * dashboard, the Ward Schedule page and the bedside screens all read.
 */
final class BedAssigner
{
    /**
     * @param  Collection  $beds  WorkloadCalculator::forWard() rows, keyed by bed id in bed order
     * @param  array<int, int>  $nurseIds
     * @param  array<int, int>  $previous  bed id => nurse id on the same shift the day before
     * @return array<int, int>  bed id => nurse id, in bed order
     */
    public static function propose(Collection $beds, array $nurseIds, array $previous = [], ?WorkloadWeights $weights = null): array
    {
        $weights ??= WorkloadWeights::defaults();
        $continuity = $weights->get('continuity');
        $neighbour = $weights->get('neighbour');
        $nurseIds = array_values(array_unique(array_map('intval', $nurseIds)));

        if (empty($nurseIds) || $beds->isEmpty()) {
            return [];
        }

        $position = $beds->keys()->values()->flip()->all();
        $load = array_fill_keys($nurseIds, 0.0);
        $held = array_fill_keys($nurseIds, []);
        $rank = array_flip($nurseIds);

        $heaviestFirst = $beds->sortBy(fn (array $row, int $bedId) => [-$row['score'], $position[$bedId]]);

        $mapping = [];
        foreach ($heaviestFirst as $bedId => $row) {
            $best = null;
            $bestCost = INF;

            foreach ($nurseIds as $nurseId) {
                $cost = $load[$nurseId] + $row['score'];

                if ((int) ($previous[$bedId] ?? 0) === $nurseId) {
                    $cost -= $continuity;
                }
                if (in_array($position[$bedId] - 1, $held[$nurseId], true) || in_array($position[$bedId] + 1, $held[$nurseId], true)) {
                    $cost -= $neighbour;
                }
                $cost += $rank[$nurseId] / 1000;

                if ($cost < $bestCost) {
                    $bestCost = $cost;
                    $best = $nurseId;
                }
            }

            $mapping[$bedId] = $best;
            $load[$best] += $row['score'];
            $held[$best][] = $position[$bedId];
        }

        uksort($mapping, fn (int $a, int $b) => $position[$a] <=> $position[$b]);

        return $mapping;
    }

    /** Who had each bed on the same shift the day before: bed id => nurse id. */
    public static function previous(Ward $ward, string $date, string $shift): array
    {
        return self::current($ward, Carbon::parse($date)->subDay()->toDateString(), $shift);
    }

    /** The assignments saved now for a shift: bed id => nurse id. */
    public static function current(Ward $ward, string $date, string $shift): array
    {
        $bedIds = Bed::where('ward_id', $ward->id)->pluck('id');

        return WardScheduleAssignment::whereIn('bed_id', $bedIds)
            ->where('scheduled_date', $date)
            ->where('shift', $shift)
            ->pluck('nurse_id', 'bed_id')
            ->map(fn ($nurseId) => (int) $nurseId)
            ->all();
    }

    /**
     * Save a shift's assignments. Each row goes through Eloquent, so the
     * bedside-screen observer sees every change. A bed left without a nurse
     * loses its assignment. Beds under maintenance are left alone.
     *
     * @param  array<int, int|null>  $mapping  bed id => nurse id
     * @return array{saved: int, removed: int}
     */
    public static function apply(Ward $ward, string $date, string $shift, array $mapping): array
    {
        $bedIds = Bed::where('ward_id', $ward->id)
            ->where('is_active', true)
            ->where('status', '<>', Bed::STATUS_MAINTENANCE)
            ->pluck('id');
        $existing = WardScheduleAssignment::whereIn('bed_id', $bedIds)
            ->where('scheduled_date', $date)
            ->where('shift', $shift)
            ->get()
            ->keyBy('bed_id');

        $saved = 0;
        $removed = 0;

        foreach ($bedIds as $bedId) {
            $nurseId = (int) ($mapping[$bedId] ?? 0) ?: null;
            $current = $existing->get($bedId);

            if ($nurseId) {
                if (!$current) {
                    WardScheduleAssignment::create([
                        'ward_id' => $ward->id,
                        'bed_id' => $bedId,
                        'nurse_id' => $nurseId,
                        'scheduled_date' => $date,
                        'shift' => $shift,
                    ]);
                    $saved++;
                } elseif ((int) $current->nurse_id !== $nurseId) {
                    $current->update(['nurse_id' => $nurseId, 'ward_id' => $ward->id]);
                    $saved++;
                }
            } elseif ($current) {
                $current->delete();
                $removed++;
            }
        }

        return ['saved' => $saved, 'removed' => $removed];
    }
}
