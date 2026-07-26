<?php

namespace App\Services;

use App\Models\Infusion;
use App\Models\InfusionPump;
use Illuminate\Support\Collection;

/**
 * Builds SmartWard infusion data from the Qmed Infusion Engine.
 *
 * The engine only knows pumps (HL7 from the devices carries no usable patient
 * identity), so the pump -> patient mapping lives in SmartWard's own
 * infusion_pumps registry (the "Link Pump" flow). This service queries the
 * engine when a page opens, pairs each engine pump with the local registry,
 * and produces non-persisted Infusion models so the existing ward/patient
 * views render unchanged.
 */
class EngineInfusionService
{
    public function __construct(protected InfusionEngineClient $client)
    {
    }

    public static function make(): self
    {
        return new self(InfusionEngineClient::fromSettings());
    }

    /**
     * Engine pumps paired with the local pump registry.
     * Each item: ['engine' => array, 'local' => ?InfusionPump]
     */
    public function pumpPairs(): Collection
    {
        $enginePumps = collect($this->client->pumps());
        $localPumps = InfusionPump::with(['patient', 'ward'])->get();

        return $enginePumps->map(fn (array $engine) => [
            'engine' => $engine,
            'local' => $this->matchLocalPump($engine, $localPumps),
        ]);
    }

    /**
     * Match one engine pump to the local registry.
     *
     * The engine's device_id is the pump's MDC_ATTR_SYS_ID uuid when the pump
     * reports one (that same uuid is what the B.Braun listener stores as the
     * local device_id), with station:label as fallback. The registry may also
     * be keyed by bare serial (pump label I51559 -> serial 51559).
     */
    protected function matchLocalPump(array $engine, Collection $localPumps): ?InfusionPump
    {
        $uuid = $engine['device_uuid'] ?? null;
        $key = $engine['device_id'] ?? null;
        $label = $engine['pump_label'] ?? null;
        $serial = $label ? preg_replace('/^[A-Za-z]+/', '', $label) : null;

        // Strongest identifier first; registries accumulate duplicate rows for
        // the same physical pump, so within a tier prefer a patient-linked row.
        $tiers = [
            fn (InfusionPump $p) => $uuid && ($p->device_uuid === $uuid || $p->device_id === $uuid),
            fn (InfusionPump $p) => $key && $p->device_id === $key,
            fn (InfusionPump $p) => $label && ($p->serial_no === $label || $p->device_id === $label),
            fn (InfusionPump $p) => $serial && $serial !== '' && $p->serial_no === $serial,
        ];

        foreach ($tiers as $matches) {
            $candidates = $localPumps->filter($matches);
            if ($candidates->isNotEmpty()) {
                return $candidates->whereNotNull('patient_id')->first() ?? $candidates->first();
            }
        }

        return null;
    }

    /**
     * Infusion pseudo-models for every engine pump that is linked to a patient.
     */
    public function infusions(): Collection
    {
        return $this->pumpPairs()
            ->filter(fn (array $pair) => $pair['local'] && $pair['local']->patient)
            ->map(fn (array $pair) => $this->toInfusion($pair['engine'], $pair['local']))
            ->values();
    }

    /**
     * Infusions for one patient (via the pumps linked to that patient).
     */
    public function infusionsForPatient(int $patientId): Collection
    {
        return $this->pumpPairs()
            ->filter(fn (array $pair) => $pair['local'] && (int) $pair['local']->patient_id === $patientId)
            ->map(fn (array $pair) => $this->toInfusion($pair['engine'], $pair['local']))
            ->values();
    }

    /**
     * Build a non-persisted Infusion model from an engine pump snapshot so the
     * existing blade views (accessors included) work without modification.
     */
    protected function toInfusion(array $engine, InfusionPump $local): Infusion
    {
        $status = $this->mapStatus($engine);

        $remainingMinutes = null;
        if (isset($engine['time_remaining_sec']) && $engine['time_remaining_sec'] !== null) {
            $remainingMinutes = (int) round($engine['time_remaining_sec'] / 60);
        }

        $infusion = new Infusion([
            'patient_id' => $local->patient_id,
            'infusion_pump_id' => $local->id,
            'medication_name' => $engine['drug_name'] ?? $engine['medication'] ?? 'Unknown medication',
            'care_area' => $engine['care_area'] ?? null,
            'total_volume' => $engine['vtbi'] ?? null,
            'infused_volume' => $engine['volume_infused'] ?? 0,
            'remaining_volume' => $engine['volume_remaining'] ?? null,
            'flow_rate' => $engine['flow_rate'] ?? null,
            'remaining_minutes' => $remainingMinutes,
            'status' => $status,
            'alarm_type' => $engine['active_alarm'] ?? null,
            'alarm_message' => $engine['active_alarm'] ?? null,
            'alarm_priority' => $this->mapAlarmPriority($engine['alarm_priority'] ?? null),
            'is_warning' => $status === Infusion::STATUS_RUNNING
                && $remainingMinutes !== null
                && $remainingMinutes <= 15,
            'last_updated_at' => $engine['last_seen_at'] ?? null,
            'completed_at' => $status === Infusion::STATUS_COMPLETED
                ? ($engine['last_observed_at'] ?? $engine['last_seen_at'] ?? null)
                : null,
        ]);

        $infusion->setRelation('patient', $local->patient);
        $infusion->setRelation('infusionPump', $local);

        return $infusion;
    }

    protected function mapStatus(array $engine): string
    {
        if (!empty($engine['active_alarm'])) {
            return Infusion::STATUS_ALARMING;
        }

        $pumpStatus = $engine['pump_status'] ?? '';
        if ($pumpStatus === 'infusing' || ($engine['delivery_status'] ?? '') === 'delivering') {
            return Infusion::STATUS_RUNNING;
        }

        // Not infusing with nothing left of the programmed volume => completed
        $vtbi = $engine['vtbi'] ?? null;
        $remaining = $engine['volume_remaining'] ?? null;
        if ($vtbi !== null && $vtbi > 0 && $remaining !== null && $remaining <= 0.01) {
            return Infusion::STATUS_COMPLETED;
        }

        return Infusion::STATUS_STOPPED;
    }

    protected function mapAlarmPriority(?string $priority): ?string
    {
        return match ($priority) {
            'PH' => 'high',
            'PM' => 'medium',
            'PL' => 'low',
            'ST' => 'technical',
            default => $priority ? strtolower($priority) : null,
        };
    }

    /**
     * Sync pumps seen by the engine into SmartWard's pump registry
     * ("Registered Pump Users"), so the ward dashboard can bind them to
     * patients. Existing rows are refreshed (last seen, battery, model);
     * unknown pumps are auto-registered - same behaviour as the local
     * listener's auto-registration, but sourced from the engine.
     *
     * Returns [registered => n, updated => n].
     */
    public function syncRegistry(): array
    {
        $pairs = $this->pumpPairs();
        $wards = \App\Models\Ward::pluck('id', 'ward_name')
            ->mapWithKeys(fn($id, $name) => [strtoupper(trim($name)) => $id]);
        $registered = 0;
        $updated = 0;

        foreach ($pairs as $pair) {
            $engine = $pair['engine'];
            $local = $pair['local'];

            $seenAt = $engine['last_seen_at'] ?? null;
            $state = array_filter([
                'device_uuid' => $engine['device_uuid'] ?? null,
                'pump_model' => $engine['pump_model'] ?? null,
                'firmware_version' => $engine['firmware_version'] ?? null,
                'power_status' => $engine['power_status'] ?? null,
                'battery_percent' => isset($engine['battery_percent'])
                    ? (int) $engine['battery_percent'] : null,
                'device_ip' => $engine['device_ip'] ?? null,
                'last_seen_at' => $seenAt,
            ], fn($v) => $v !== null);

            if ($local) {
                $local->fill($state)->save();
                $updated++;
                continue;
            }

            $engineWard = $engine['ward'] ?? null;
            $label = $engine['pump_label'] ?? null;

            InfusionPump::create($state + [
                'device_id' => $engine['device_id'],
                'serial_no' => $label,
                'device_name' => $label ?: ($engine['pump_model'] ?? $engine['device_id']),
                'device_type' => ($engine['pump_type'] ?? null) === 'syringe'
                    ? 'syringe_pump' : 'volumetric_pump',
                'ward_id' => $engineWard ? ($wards[strtoupper(trim($engineWard))] ?? null) : null,
                'is_active' => true,
            ]);
            $registered++;
        }

        return ['registered' => $registered, 'updated' => $updated];
    }

    /**
     * Summary stats in the shape the ward overview expects.
     */
    public function stats(Collection $infusions): array
    {
        return [
            'running' => $infusions->where('status', Infusion::STATUS_RUNNING)->count(),
            'paused' => $infusions->where('status', Infusion::STATUS_PAUSED)->count(),
            'completed' => $infusions->where('status', Infusion::STATUS_COMPLETED)->count(),
            'warnings' => $infusions
                ->where('status', Infusion::STATUS_RUNNING)
                ->where('is_warning', true)
                ->count(),
            'alarms' => $infusions->where('status', Infusion::STATUS_ALARMING)->count(),
        ];
    }
}
