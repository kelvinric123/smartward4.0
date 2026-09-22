<?php

namespace App\Services;

use App\Models\BloodTransfusion;
use App\Models\ConsultantOrder;
use App\Models\FluidBalanceEntry;
use App\Models\FluidBalancePlan;
use App\Models\Infusion;
use App\Models\MedicationAdministration;
use App\Models\Patient;
use App\Models\PatientMedication;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Fills the I/O chart from the rest of the ward, so nurses do not copy the
 * same volume from one screen to another:
 *
 * - a blood unit that finishes adds its volume as blood-product intake (a
 *   unit stopped early adds an estimate from how long it ran, and says so);
 * - a dose given of an IV medication with a volume (its "IV volume per dose",
 *   or the dose itself when it is in mL) adds IV-medication intake, struck
 *   out again if the dose record is undone;
 * - a pump infusion adds what the pump has infused as IV-fluid intake, at
 *   most once an hour and once more when it ends;
 * - a consultant order with a fluid restriction becomes the fluid plan, and
 *   cancelling the order lifts it again while it is still the plan in force.
 *
 * Entries made here carry a source and source_id, are counted like any
 * other, and can be struck out on the I/O chart like any other. Called from
 * the observers in App\Observers\FluidBalance.
 */
class FluidBalanceLinks
{
    public const SOURCE_TRANSFUSION = 'transfusion';
    public const SOURCE_DOSE = 'dose';
    public const SOURCE_INFUSION = 'infusion';

    public const SOURCE_LABELS = [
        self::SOURCE_TRANSFUSION => 'blood unit',
        self::SOURCE_DOSE => 'medication dose',
        self::SOURCE_INFUSION => 'infusion pump',
    ];

    /** A running pump infusion is charted at most this often. */
    public const INFUSION_BATCH_MINUTES = 60;

    /** The route whose doses can carry fluid. */
    public const IV_ROUTE = 'IV';

    // ------------------------------------------------------ blood units

    /**
     * Chart a finished unit once. A unit whose entry was struck out is not
     * charted again.
     */
    public static function recordTransfusion(BloodTransfusion $unit): ?FluidBalanceEntry
    {
        if (!$unit->isFinished() || !$unit->volume_ml || self::linked(self::SOURCE_TRANSFUSION, $unit->id)->exists()) {
            return null;
        }

        $patient = Patient::find($unit->patient_id);
        if (!$patient) {
            return null;
        }

        $description = trim($unit->product_type . ', unit ' . $unit->unit_number);
        $volume = (int) $unit->volume_ml;

        if ($unit->status === BloodTransfusion::STATUS_STOPPED) {
            $ran = self::minutesBetween($unit->started_at, $unit->completed_at);
            if ($ran === null || !$unit->prescribed_minutes) {
                return null;
            }

            $volume = (int) round(min($volume, $volume * $ran / $unit->prescribed_minutes));
            $description .= " (stopped early: about {$volume} mL, estimated from {$ran} of {$unit->prescribed_minutes} min)";
        }

        if ($volume < 1) {
            return null;
        }

        return self::createEntry($patient, [
            'category' => 'blood',
            'volume_ml' => $volume,
            'description' => $description,
            'recorded_at' => $unit->completed_at ?? now(),
            'recorded_by' => $unit->checked_by ?? $unit->created_by,
            'source' => self::SOURCE_TRANSFUSION,
            'source_id' => $unit->id,
        ], $unit->ward_id);
    }

    // ------------------------------------------------------------ doses

    /**
     * The volume one dose of this order is given in, or null when it carries
     * no fluid worth charting (not IV, or a dose in mg with no volume set).
     */
    public static function doseVolume(PatientMedication $order, $doseAmount = null, ?string $doseUnit = null): ?int
    {
        if ($order->route !== self::IV_ROUTE) {
            return null;
        }

        if ($order->infusion_volume_ml) {
            return (int) $order->infusion_volume_ml;
        }

        $amount = $doseAmount ?? $order->dose_amount;
        $unit = $doseUnit ?? $order->dose_unit;

        return $unit === 'mL' && $amount > 0 ? (int) round($amount) : null;
    }

    public static function recordDose(MedicationAdministration $dose): ?FluidBalanceEntry
    {
        if ($dose->status !== MedicationAdministration::STATUS_GIVEN || self::linked(self::SOURCE_DOSE, $dose->id)->exists()) {
            return null;
        }

        $order = PatientMedication::find($dose->patient_medication_id);
        $volume = $order ? self::doseVolume($order, $dose->dose_amount, $dose->dose_unit) : null;
        $patient = $order ? Patient::find($order->patient_id) : null;
        if (!$volume || !$patient) {
            return null;
        }

        $amount = PatientMedication::formatAmount($dose->dose_amount ?? $order->dose_amount);
        $description = $order->medication_name . ' ' . $amount . ' ' . ($dose->dose_unit ?? $order->dose_unit) . ' IV';
        if ($order->infusion_volume_ml) {
            $description .= ' in ' . number_format($order->infusion_volume_ml) . ' mL';
        }

        return self::createEntry($patient, [
            'category' => 'iv_med',
            'volume_ml' => $volume,
            'description' => $description,
            'recorded_at' => $dose->administered_at ?? now(),
            'recorded_by' => $dose->recorded_by,
            'source' => self::SOURCE_DOSE,
            'source_id' => $dose->id,
        ], $order->ward_id);
    }

    /** Strike out the entry of a dose whose record was undone. */
    public static function voidDose(MedicationAdministration $dose, ?int $userId = null): int
    {
        return self::linked(self::SOURCE_DOSE, $dose->id)
            ->whereNull('voided_at')
            ->update([
                'voided_at' => now(),
                'voided_by' => $userId,
                'void_reason' => 'The dose record was undone',
            ]);
    }

    // --------------------------------------------------- pump infusions

    /**
     * Chart what the pump has infused since the last entry for this
     * infusion: once an hour while it runs, and once more when it ends.
     * A counter that goes backwards (cleared on the pump, or a new bag)
     * starts again from zero.
     */
    public static function recordInfusion(Infusion $infusion): ?FluidBalanceEntry
    {
        if (!$infusion->patient_id) {
            return null;
        }

        $reading = (float) ($infusion->infused_volume ?? 0);
        $last = self::linked(self::SOURCE_INFUSION, $infusion->id)->orderByDesc('id')->first();
        $lastReading = $last?->source_reading !== null ? (float) $last->source_reading : 0.0;
        if ($reading < $lastReading) {
            $lastReading = 0.0;
        }

        $volume = (int) floor($reading - $lastReading);
        $ended = in_array($infusion->status, [Infusion::STATUS_COMPLETED, Infusion::STATUS_STOPPED], true);
        $due = !$last || $last->recorded_at->lte(now()->subMinutes(self::INFUSION_BATCH_MINUTES));

        if ($volume < 1 || (!$ended && !$due)) {
            return null;
        }

        $patient = Patient::find($infusion->patient_id);
        if (!$patient) {
            return null;
        }

        $pump = $infusion->infusionPump?->device_name ?: $infusion->infusionPump?->device_id;
        $description = 'Pump: ' . ($infusion->medication_name ?: 'infusion')
            . ($pump ? ' (' . $pump . ')' : '')
            . ($ended ? ', infusion ended' : '');

        return self::createEntry($patient, [
            'category' => 'iv',
            'volume_ml' => min($volume, FluidBalanceEntry::VOLUME_MAX),
            'description' => $description,
            'recorded_at' => now(),
            'recorded_by' => null,
            'source' => self::SOURCE_INFUSION,
            'source_id' => $infusion->id,
            'source_reading' => $reading,
        ], $patient->ward_id);
    }

    // ------------------------------------------------- consultant orders

    public static function hasFluidRestriction(ConsultantOrder $order): bool
    {
        return $order->fluid_limit_ml !== null || $order->urine_min_ml_per_hour !== null;
    }

    /**
     * "Fluid restriction 1,500 mL per day, urine at least 30 mL/h".
     */
    public static function restrictionSummary(ConsultantOrder $order): ?string
    {
        if (!self::hasFluidRestriction($order)) {
            return null;
        }

        $parts = [];
        if ($order->fluid_limit_ml !== null) {
            $parts[] = 'Fluid restriction ' . number_format($order->fluid_limit_ml) . ' mL per day';
        }
        if ($order->urine_min_ml_per_hour !== null) {
            $parts[] = ($parts ? 'urine' : 'Urine') . ' at least ' . $order->urine_min_ml_per_hour . ' mL/h';
        }

        return implode(', ', $parts);
    }

    /**
     * Make an order's fluid restriction the plan in force. What the order
     * leaves out is kept from the current plan.
     */
    public static function applyOrder(ConsultantOrder $order): ?FluidBalancePlan
    {
        $patient = Patient::find($order->patient_id);
        if (!$patient || !$order->isOpen() || !self::hasFluidRestriction($order)) {
            return null;
        }

        $current = FluidBalancePlan::currentFor($patient);

        return FluidBalancePlan::create([
            'patient_id' => $patient->id,
            'ward_id' => $patient->ward_id,
            'intake_limit_ml' => $order->fluid_limit_ml ?? $current?->intake_limit_ml,
            'urine_min_ml_per_hour' => $order->urine_min_ml_per_hour ?? $current?->urine_min_ml_per_hour,
            'notes' => Str::limit('Consultant order' . ($order->consultant_name ? ' by ' . $order->consultant_name : '')
                . ': ' . $order->instruction, 1000),
            'consultant_order_id' => $order->id,
            'set_by' => $order->created_by,
        ]);
    }

    /**
     * A cancelled order's restriction is lifted, back to the plan before it,
     * but only while it is still the plan in force: a plan someone has set
     * since is left alone.
     */
    public static function liftOrder(ConsultantOrder $order): ?FluidBalancePlan
    {
        $patient = Patient::find($order->patient_id);
        $current = $patient ? FluidBalancePlan::currentFor($patient) : null;
        if (!$current || (int) $current->consultant_order_id !== (int) $order->id) {
            return null;
        }

        $previous = FluidBalancePlan::where('patient_id', $patient->id)
            ->when($patient->admitted_at, fn ($query) => $query->where('created_at', '>=', $patient->admitted_at))
            ->where('id', '<', $current->id)
            ->orderByDesc('id')
            ->first();

        // Still in force only if the order that set it is still open
        $previousOrder = $previous?->consultant_order_id ? ConsultantOrder::find($previous->consultant_order_id) : null;

        return FluidBalancePlan::create([
            'patient_id' => $patient->id,
            'ward_id' => $patient->ward_id,
            'intake_limit_ml' => $previous?->intake_limit_ml,
            'urine_min_ml_per_hour' => $previous?->urine_min_ml_per_hour,
            'notes' => Str::limit('Fluid restriction lifted: the consultant order was cancelled'
                . ($order->outcome_note ? ' (' . $order->outcome_note . ')' : ''), 1000),
            'consultant_order_id' => $previousOrder?->isOpen() ? $previousOrder->id : null,
            'set_by' => $order->closed_by,
        ]);
    }

    // ------------------------------------------------------------ shared

    private static function linked(string $source, int $sourceId): Builder
    {
        return FluidBalanceEntry::where('source', $source)->where('source_id', $sourceId);
    }

    private static function createEntry(Patient $patient, array $attributes, ?int $wardId = null): FluidBalanceEntry
    {
        return FluidBalanceEntry::create($attributes + [
            'patient_id' => $patient->id,
            'ward_id' => $wardId ?? $patient->ward_id,
            'direction' => FluidBalanceEntry::DIRECTION_INTAKE,
        ]);
    }

    private static function minutesBetween($from, $to): ?int
    {
        if (!$from || !$to) {
            return null;
        }

        return max(0, (int) round(($to->getTimestamp() - $from->getTimestamp()) / 60));
    }
}
