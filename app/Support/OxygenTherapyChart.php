<?php

namespace App\Support;

use App\Models\OxygenTherapyChange;
use App\Models\Patient;
use App\Models\VitalSign;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A patient's oxygen this admission, for the Oxygen Therapy tab of Patient Details.
 *
 * Oxygen is recorded in two places: changes made on the Oxygen Therapy tab, and the
 * oxygen status recorded with a vital signs reading. Both are read together, oldest
 * first, and the latest is what the patient is on now. A reading that records the
 * oxygen already in place is not a change, so only readings that differ are listed.
 * The SpO2 target is set on the tab and stays in force through the readings that
 * follow it; SpO2 itself comes from the vital signs.
 */
final class OxygenTherapyChart
{
    /** The key of the Oxygen Therapy tab in the per-user Patient Details tab settings. */
    public const SETTINGS_TAB = 'oxygen';

    /** The chart opens on the whole admission unless that is longer than this. */
    private const DEFAULT_RANGE_HOURS = 72;

    /**
     * Everything the Oxygen Therapy tab shows: the oxygen now, each setting in force this
     * admission (oldest first), the history (newest first, struck-out changes included),
     * SpO2 against the target, and the chart.
     */
    public static function forPatient(Patient $patient, ?CarbonInterface $now = null): array
    {
        $now = $now ? Carbon::instance($now) : now();
        $since = $patient->admitted_at;

        $changes = OxygenTherapyChange::where('patient_id', $patient->id)
            ->when($since, fn ($query) => $query->where('started_at', '>=', $since))
            ->with(['recordedBy:id,name', 'voidedBy:id,name'])
            ->orderBy('started_at')
            ->orderBy('id')
            ->get();

        $readings = VitalSign::where('patient_id', $patient->id)
            ->when($since, fn ($query) => $query->where('recorded_at', '>=', $since))
            ->where(fn ($query) => $query->whereNotNull('oxygen_delivery')->orWhereNotNull('spo2'))
            ->with('recordedBy:id,name')
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get();

        $timeline = self::timeline($changes->reject->isVoided(), $readings->whereNotNull('oxygen_delivery'), $now);
        $current = $timeline ? $timeline[array_key_last($timeline)] : null;

        $history = collect($timeline)
            ->concat($changes->filter->isVoided()->map(fn ($change) => self::entry($change, $change->target())))
            ->sortByDesc('sort')
            ->values()
            ->all();

        $spo2 = self::spo2Points($readings->whereNotNull('spo2')->values(), $timeline);
        $latestSpo2 = $spo2 ? $spo2[array_key_last($spo2)] : null;
        if ($latestSpo2) {
            // Taken before the latest change, so it says little about the oxygen now
            $latestSpo2['stale'] = $current !== null && ($latestSpo2['setting']['key'] ?? null) !== $current['key'];
        }

        // The start of the unbroken spell on supplemental oxygen that runs to now
        $onOxygenSince = null;
        for ($i = count($timeline) - 1; $i >= 0 && $timeline[$i]['on_oxygen']; $i--) {
            $onOxygenSince = $timeline[$i]['at'];
        }

        return [
            'current' => $current,
            'timeline' => $timeline,
            'history' => $history,
            'latest_spo2' => $latestSpo2,
            'on_oxygen_since' => $onOxygenSince,
            'chart' => self::chart($timeline, $spo2, $now),
        ];
    }

    /**
     * The oxygen the patient is on now, or null when none is recorded this admission.
     */
    public static function current(Patient $patient): ?array
    {
        return self::forPatient($patient)['current'];
    }

    /**
     * A length of time on a setting: "2 d 3 h", "3 h 20 min", "45 min".
     */
    public static function durationLabel(int $minutes): string
    {
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $mins = $minutes % 60;

        if ($days) {
            return $days . ' d' . ($hours ? ' ' . $hours . ' h' : '');
        }
        if ($hours) {
            return $hours . ' h' . ($mins ? ' ' . $mins . ' min' : '');
        }

        return $mins . ' min';
    }

    /**
     * Each oxygen setting in force this admission, oldest first. One lasts until the next
     * starts; the last one is current.
     */
    private static function timeline(Collection $changes, Collection $readings, Carbon $now): array
    {
        $records = $changes->map(fn ($change) => self::entry($change, $change->target()))
            ->concat($readings->map(fn ($vital) => self::entry($vital, null)))
            ->sortBy('sort')
            ->values();

        $entries = [];
        $target = null;
        foreach ($records as $entry) {
            if ($entry['source'] === 'therapy') {
                $target = $entry['target'];
            } else {
                // A reading of the oxygen already in place is not a change
                $previous = $entries ? $entries[array_key_last($entries)]['record'] : null;
                if ($previous && $previous->hasSameOxygenAs($entry['record'])) {
                    continue;
                }
                $entry['target'] = $target;
            }

            $entries[] = $entry;
        }

        foreach ($entries as $i => $entry) {
            $until = $entries[$i + 1]['at'] ?? null;
            $entries[$i]['until'] = $until;
            $entries[$i]['minutes'] = (int) round(abs($entry['at']->diffInMinutes($until ?? $now)));
        }

        return $entries;
    }

    /**
     * One oxygen setting, from a change made on the tab or a vital signs reading.
     */
    private static function entry(OxygenTherapyChange|VitalSign $record, ?array $target): array
    {
        $isChange = $record instanceof OxygenTherapyChange;
        $at = $isChange ? $record->started_at : $record->recorded_at;

        return [
            'key' => ($isChange ? 'change-' : 'reading-') . $record->id,
            'source' => $isChange ? 'therapy' : 'vitals',
            'id' => $record->id,
            'record' => $record,
            'at' => $at,
            // A reading timed the same moment as a change is taken as just before it
            'sort' => sprintf('%011d-%d-%010d', $at->getTimestamp(), $isChange ? 1 : 0, $record->id),
            'until' => null,
            'minutes' => null,
            'delivery' => $record->oxygen_delivery,
            'label' => $record->oxygenDeliveryLabel(),
            'abbr' => VitalSign::OXYGEN_DELIVERY_SHORT[$record->oxygen_delivery] ?? strtoupper(substr($record->oxygen_delivery, 0, 4)),
            'short' => $record->oxygenShortLabel(),
            'settings' => $record->oxygenSettingsLabel(),
            'flow' => $record->oxygen_flow_rate !== null ? (float) $record->oxygen_flow_rate : null,
            'fio2' => $record->fio2_percent,
            'on_oxygen' => $record->isOnOxygen(),
            'target' => $target,
            'notes' => $isChange ? $record->notes : null,
            'by' => $record->recordedBy?->name,
            'spo2' => $isChange ? null : $record->spo2,
            'voided' => $isChange && $record->isVoided(),
        ];
    }

    /**
     * SpO2 from the vital signs, oldest first, each with the oxygen setting in force when it
     * was taken and how it read against the target then: 'below', 'above' (only while on
     * supplemental oxygen, when it is room to wean) or null.
     */
    private static function spo2Points(Collection $readings, array $timeline): array
    {
        $points = [];
        $i = -1;
        foreach ($readings as $vital) {
            $sort = sprintf('%011d-0-%010d', $vital->recorded_at->getTimestamp(), $vital->id);
            while (isset($timeline[$i + 1]) && $timeline[$i + 1]['sort'] <= $sort) {
                $i++;
            }

            $setting = $timeline[$i] ?? null;
            $target = $setting['target'] ?? null;
            $state = null;
            if ($target && $vital->spo2 < $target[0]) {
                $state = 'below';
            } elseif ($target && $vital->spo2 > $target[1] && $setting['on_oxygen']) {
                $state = 'above';
            }

            $points[] = [
                'at' => $vital->recorded_at,
                'spo2' => $vital->spo2,
                'setting' => $setting,
                'state' => $state,
                'by' => $vital->recordedBy?->name,
            ];
        }

        return $points;
    }

    /**
     * What the progression chart draws. Times are the hospital's wall clock in "UTC"
     * milliseconds, so every browser shows ward time.
     */
    private static function chart(array $timeline, array $spo2, Carbon $now): array
    {
        $toLocalMs = fn (CarbonInterface $time) => ($time->getTimestamp() + $time->getOffset()) * 1000;

        $steps = array_map(fn ($entry) => [
            't' => $toLocalMs($entry['at']),
            'device' => $entry['delivery'],
            'abbr' => $entry['abbr'],
            'label' => $entry['label'],
            'settings' => $entry['settings'],
            // Room air adds no flow and is 21% oxygen; a device recorded without one leaves a gap
            'flow' => $entry['on_oxygen'] ? $entry['flow'] : 0,
            'fio2' => $entry['on_oxygen'] ? $entry['fio2'] : 21,
            'target' => $entry['target'],
        ], $timeline);

        $points = array_map(fn ($point) => [
            't' => $toLocalMs($point['at']),
            'y' => $point['spo2'],
            'on' => $point['setting']['short'] ?? null,
            'state' => $point['state'],
        ], $spo2);

        $times = array_merge(array_column($steps, 't'), array_column($points, 't'));
        $nowMs = $toLocalMs($now);
        $spanHours = $times ? ($nowMs - min($times)) / 3600000 : 0;

        return [
            'now' => $nowMs,
            'steps' => $steps,
            'spo2' => $points,
            'range' => $spanHours > self::DEFAULT_RANGE_HOURS ? self::DEFAULT_RANGE_HOURS . 'h' : 'all',
        ];
    }
}
