<?php

namespace App\Support;

use App\Models\AdmissionLog;
use Carbon\CarbonInterface;

/**
 * One admission of one patient: the admit/check-in log row, the discharge log
 * row that closed it (if any), and the time window everything recorded during
 * that stay falls into.
 *
 * SmartWard has no admissions table - a stay only exists as the pair of
 * admission_logs rows that bracket it - so this object is what the discharge
 * summary treats as "the admission".
 */
class AdmissionEpisode
{
    public function __construct(
        public readonly AdmissionLog $admissionLog,
        public readonly ?AdmissionLog $dischargeLog,
        public readonly CarbonInterface $admittedAt,
        public readonly ?CarbonInterface $dischargedAt,
        public readonly CarbonInterface $windowStart,
        public readonly CarbonInterface $windowEnd,
    ) {
    }

    public function isDischarged(): bool
    {
        return $this->dischargedAt !== null;
    }

    /**
     * The identifier the rest of SmartWard already uses for an admission
     * (vital signs store it as admission_id).
     */
    public function reference(): string
    {
        return 'ADM-' . $this->admissionLog->patient_id . '-' . $this->admittedAt->format('YmdHis');
    }

    /**
     * Stay length so far, or the full stay once discharged.
     */
    public function lengthOfStay(): string
    {
        $until = $this->dischargedAt ?? now();
        $minutes = (int) round(abs($this->admittedAt->diffInMinutes($until)));

        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $mins = $minutes % 60;

        $parts = [];
        if ($days) {
            $parts[] = $days . ' day' . ($days === 1 ? '' : 's');
        }
        if ($hours) {
            $parts[] = $hours . ' hour' . ($hours === 1 ? '' : 's');
        }
        if (!$days && ($mins || !$parts)) {
            $parts[] = $mins . ' min' . ($mins === 1 ? '' : 's');
        }

        return implode(' ', $parts);
    }
}
