<?php

namespace App\Support;

use App\Models\ClinicalIndicator;
use App\Models\ClinicalIndicatorScore;
use App\Models\Patient;
use App\Models\Ward;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * When each monitored scale is next due for a patient.
 *
 * A clinical indicator with monitoring switched on carries two levels, both
 * counted from the patient's last score for that scale, or from admission
 * before the first. Past the suggested interval the scale is due (amber),
 * past the warning level it is overdue (red). The ward dashboard flags both.
 */
final class ClinicalIndicatorMonitoring
{
    public const STATE_OK = 'ok';
    public const STATE_DUE = 'due';
    public const STATE_OVERDUE = 'overdue';

    /** The units a level is entered in, with their length in minutes. */
    public const UNITS = ['minutes' => 1, 'hours' => 60, 'days' => 1440];

    /** The longest level accepted: 30 days, in minutes. */
    public const MAX_MINUTES = 43200;

    private const RANK = [self::STATE_OVERDUE => 0, self::STATE_DUE => 1, self::STATE_OK => 2];

    /**
     * The monitored scales of each patient, keyed by patient id: the active
     * ones bound to their ward's ward type with monitoring switched on.
     * Patients with none are left out.
     *
     * @param  iterable<Patient>  $patients
     * @return array<int, array{monitored: int, due: int, overdue: int, items: array<int, array>}>
     */
    public static function forPatients(iterable $patients, ?CarbonInterface $now = null): array
    {
        $patients = collect($patients)->filter(fn (Patient $patient) => $patient->ward_id);

        if ($patients->isEmpty()) {
            return [];
        }

        $now ??= now();

        // Looked up per ward rather than per patient: a dashboard is one ward.
        $monitoredByWard = Ward::with('wardType.clinicalIndicators')
            ->whereIn('id', $patients->pluck('ward_id')->unique())
            ->get()
            ->mapWithKeys(fn (Ward $ward) => [$ward->id => self::monitoredIndicators($ward)]);

        $indicatorIds = $monitoredByWard->collapse()->pluck('id')->unique();

        if ($indicatorIds->isEmpty()) {
            return [];
        }

        $lastScoredAt = ClinicalIndicatorScore::query()
            ->whereIn('patient_id', $patients->pluck('id'))
            ->whereIn('clinical_indicator_id', $indicatorIds)
            ->groupBy('patient_id', 'clinical_indicator_id')
            ->selectRaw('patient_id, clinical_indicator_id, MAX(recorded_at) AS last_scored_at')
            ->toBase()
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->patient_id . ':' . $row->clinical_indicator_id => Carbon::parse($row->last_scored_at),
            ]);

        $alerts = [];

        foreach ($patients as $patient) {
            $indicators = $monitoredByWard->get($patient->ward_id, collect());

            if ($indicators->isEmpty()) {
                continue;
            }

            $items = $indicators
                ->map(fn (ClinicalIndicator $indicator) => self::status(
                    $indicator,
                    $lastScoredAt->get($patient->id . ':' . $indicator->id),
                    $patient->admitted_at ?? $patient->created_at,
                    $now
                ))
                ->sort(fn ($a, $b) => [self::RANK[$a['state']], $a['due_at']->getTimestamp()]
                    <=> [self::RANK[$b['state']], $b['due_at']->getTimestamp()])
                ->values();

            $alerts[$patient->id] = [
                'monitored' => $items->count(),
                'overdue' => $items->where('state', self::STATE_OVERDUE)->count(),
                'due' => $items->where('state', self::STATE_DUE)->count(),
                'items' => $items->all(),
            ];
        }

        return $alerts;
    }

    /**
     * Where one monitored scale stands for one patient. Before the first score
     * the clock runs from admission, so a scale bound to the ward is expected
     * within the suggested interval of the patient arriving.
     */
    public static function status(
        ClinicalIndicator $indicator,
        ?CarbonInterface $lastScoredAt,
        ?CarbonInterface $admittedAt,
        ?CarbonInterface $now = null
    ): array {
        $now ??= now();
        $since = $lastScoredAt ?? $admittedAt ?? $now;
        $elapsed = max(0, intdiv($now->getTimestamp() - $since->getTimestamp(), 60));
        $suggested = $indicator->monitoring_suggested_minutes;

        $state = match (true) {
            $elapsed >= $indicator->monitoring_warning_minutes => self::STATE_OVERDUE,
            $elapsed >= $suggested => self::STATE_DUE,
            default => self::STATE_OK,
        };

        $ago = self::formatDuration($elapsed) . ' ago';
        $history = $lastScoredAt ? 'last scored ' . $ago : 'not scored since admission ' . $ago;

        return [
            'indicator_id' => $indicator->id,
            'code' => $indicator->code,
            'name' => $indicator->name,
            'state' => $state,
            'label' => match ($state) {
                self::STATE_OVERDUE => 'Overdue · ' . $history,
                self::STATE_DUE => 'Due · ' . $history,
                default => 'Next due in ' . self::formatDuration($suggested - $elapsed),
            },
            'history' => $history,
            'interval' => self::formatInterval($suggested),
            'last_scored_at' => $lastScoredAt,
            'due_at' => Carbon::instance($since)->addMinutes($suggested),
        ];
    }

    /** "30 min", "4 h", "1 h 30 min", "24 h", "7 days". */
    public static function formatInterval(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . ' min';
        }

        if (self::splitInterval($minutes)['unit'] === 'days') {
            return intdiv($minutes, 1440) . ' days';
        }

        $rest = $minutes % 60;

        return intdiv($minutes, 60) . ' h' . ($rest ? ' ' . $rest . ' min' : '');
    }

    /** "25m", "1h 05m", "2d 3h". */
    public static function formatDuration(int $minutes): string
    {
        $minutes = max(0, $minutes);

        if ($minutes < 60) {
            return $minutes . 'm';
        }

        if ($minutes < 1440) {
            return intdiv($minutes, 60) . 'h ' . str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT) . 'm';
        }

        return intdiv($minutes, 1440) . 'd ' . intdiv($minutes % 1440, 60) . 'h';
    }

    /**
     * A stored level as the value and unit the form shows. Whole days from two
     * days up read as days and whole hours as hours, so a daily check stays
     * "24 hours" and a weekly one becomes "7 days".
     *
     * @return array{value: ?int, unit: string}
     */
    public static function splitInterval(?int $minutes): array
    {
        return match (true) {
            !$minutes => ['value' => null, 'unit' => 'hours'],
            $minutes >= 2880 && $minutes % 1440 === 0 => ['value' => intdiv($minutes, 1440), 'unit' => 'days'],
            $minutes % 60 === 0 => ['value' => intdiv($minutes, 60), 'unit' => 'hours'],
            default => ['value' => $minutes, 'unit' => 'minutes'],
        };
    }

    public static function toMinutes(int $value, string $unit): int
    {
        return $value * self::UNITS[$unit];
    }

    private static function monitoredIndicators(Ward $ward): Collection
    {
        $wardType = $ward->wardType;

        if (!$wardType || !$wardType->is_active) {
            return collect();
        }

        return $wardType->clinicalIndicators
            ->filter(fn (ClinicalIndicator $indicator) => $indicator->is_active && $indicator->isMonitored())
            ->values();
    }
}
