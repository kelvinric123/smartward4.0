<?php

namespace App\Services\NurseScheduling;

use App\Models\Infusion;
use App\Services\EngineInfusionService;
use App\Services\InfusionEngineClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Which patients have an infusion running, for the workload score and the
 * acuity indicators. With the Qmed Infusion Engine in use the local infusions
 * table holds only snapshots of finished infusions, so the live state comes
 * from the engine (the pumps bound to patients), as the ward dashboard's
 * infusion panel reads it. Without the engine, or when it cannot be reached,
 * it is the local table.
 *
 * The engine's answer is kept for a minute, and so is a failure: a page that
 * builds the schedule more than once, or an engine that is down, costs at most
 * one call (and one connect timeout) a minute.
 */
final class ActiveInfusions
{
    /** Running, paused or alarming: the same as Infusion::active(). */
    public const STATUSES = [Infusion::STATUS_RUNNING, Infusion::STATUS_PAUSED, Infusion::STATUS_ALARMING];

    public const CACHE_KEY = 'nurse-scheduling:engine-infusion-patients';

    public const CACHE_SECONDS = 60;

    /**
     * The patients among these with an infusion running.
     *
     * @param  iterable<int|string>  $patientIds
     * @return array<int, true> keyed by patient id
     */
    public static function patientIds(iterable $patientIds): array
    {
        $ids = collect($patientIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $engine = InfusionEngineClient::engineModeActive() ? self::fromEngine() : null;
        if ($engine !== null) {
            return $ids->filter(fn (int $id) => isset($engine[$id]))->mapWithKeys(fn (int $id) => [$id => true])->all();
        }

        return Infusion::whereIn('patient_id', $ids)->active()
            ->pluck('patient_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }

    /**
     * Every patient the engine has an active infusion for, or null when the
     * engine could not be read (the caller then uses the local table).
     *
     * @return array<int, true>|null
     */
    private static function fromEngine(): ?array
    {
        $result = Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, function () {
            try {
                $patients = EngineInfusionService::make()->infusions()
                    ->filter(fn (Infusion $infusion) => in_array($infusion->status, self::STATUSES, true))
                    ->map(fn (Infusion $infusion) => (int) $infusion->patient_id)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                return ['ok' => true, 'patients' => $patients];
            } catch (\Throwable $e) {
                Log::warning('Nurse scheduling: infusion engine unreachable, using local infusion data: ' . $e->getMessage());

                return ['ok' => false, 'patients' => []];
            }
        });

        return ($result['ok'] ?? false) ? array_fill_keys($result['patients'] ?? [], true) : null;
    }
}
