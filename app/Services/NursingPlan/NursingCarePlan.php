<?php

namespace App\Services\NursingPlan;

use App\Models\BloodTransfusion;
use App\Models\ClinicalIndicatorScore;
use App\Models\FluidBalanceEntry;
use App\Models\FluidBalancePlan;
use App\Models\FluidOverloadAssessment;
use App\Models\NursingCarePlanEvaluation;
use App\Models\NursingCarePlanItem;
use App\Models\Patient;
use App\Models\PatientMedication;
use App\Models\VitalSign;
use App\Services\ShiftHandover;
use App\Support\NursingCarePlanLibrary;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * A patient's nursing care plan: nursing diagnoses with goals and
 * interventions, evaluated each shift. Shared by the ward dashboard's Nursing
 * Plan tab and the nurse app, so both read and write the same plan the same
 * way.
 */
class NursingCarePlan
{
    /** How many past evaluations travel with each item. */
    private const HISTORY = 5;

    /**
     * Everything the Nursing Plan shows about the care plan.
     */
    public static function forPatient(Patient $patient, ?CarbonInterface $now = null): array
    {
        $slot = self::currentSlot($patient, $now);

        // Active items whenever they were started; closed ones from this stay
        $items = NursingCarePlanItem::with([
            'evaluations' => fn($query) => $query->with('evaluatedBy:id,name'),
            'createdBy:id,name',
            'resolvedBy:id,name',
        ])
            ->where('patient_id', $patient->id)
            ->where(fn($query) => $query
                ->where('status', NursingCarePlanItem::STATUS_ACTIVE)
                ->when($patient->admitted_at, fn($q) => $q->orWhere('resolved_at', '>=', $patient->admitted_at)))
            ->orderBy('id')
            ->get();

        $active = $items->filter(fn(NursingCarePlanItem $item) => $item->isActive())->values();
        $payload = fn(NursingCarePlanItem $item) => self::item($item, $slot);

        return [
            'active' => $active->map($payload)->all(),
            'closed' => $items->reject(fn(NursingCarePlanItem $item) => $item->isActive())
                ->sortByDesc(fn(NursingCarePlanItem $item) => $item->resolved_at?->getTimestamp() ?? 0)
                ->map($payload)->values()->all(),
            'suggestions' => self::suggestionsFor($patient, $active),
            'templates' => collect(NursingCarePlanLibrary::TEMPLATES)
                ->map(fn(array $template, string $key) => [
                    'key' => $key,
                    'diagnosis' => $template['diagnosis'],
                    'category' => $template['category'],
                    'category_label' => NursingCarePlanLibrary::categoryLabel($template['category']),
                    'related_to' => $template['related_to'],
                    'goal' => $template['goal'],
                    'interventions' => $template['interventions'],
                    'in_plan' => $active->contains('template_key', $key),
                ])
                ->values()
                ->all(),
            'outcomes' => NursingCarePlanEvaluation::OUTCOMES,
            'shift' => $slot ? ['code' => $slot['code'], 'label' => $slot['label'], 'name' => $slot['name'], 'date' => $slot['date']] : null,
            'due_evaluations' => $active->filter(fn(NursingCarePlanItem $item) => !self::evaluatedIn($item, $slot))->count(),
        ];
    }

    // ------------------------------------------------------------- actions

    /**
     * Add a diagnosis to the plan: from a library template (its goal and
     * interventions, as the nurse edited them) or written from scratch.
     */
    public static function add(Patient $patient, array $data, ?int $userId): NursingCarePlanItem
    {
        $template = NursingCarePlanLibrary::template($data['template_key'] ?? null);

        // A field sent empty was cleared on purpose; one not sent keeps the template's
        $given = fn(string $key) => array_key_exists($key, $data);

        return NursingCarePlanItem::create([
            'patient_id' => $patient->id,
            'ward_id' => $patient->ward_id,
            'template_key' => $template ? $data['template_key'] : null,
            'category' => $template['category'] ?? ($data['category'] ?? 'other'),
            'diagnosis' => trim($data['diagnosis'] ?? $template['diagnosis'] ?? ''),
            'related_to' => self::clean($given('related_to') ? $data['related_to'] : ($template['related_to'] ?? null)),
            'goal' => trim($data['goal'] ?? $template['goal'] ?? ''),
            'interventions' => self::interventions($given('interventions') ? ($data['interventions'] ?? []) : ($template['interventions'] ?? [])),
            'status' => NursingCarePlanItem::STATUS_ACTIVE,
            'started_at' => now(),
            'created_by' => $userId,
        ]);
    }

    public static function update(NursingCarePlanItem $item, array $data): NursingCarePlanItem
    {
        $item->fill(array_filter([
            'diagnosis' => isset($data['diagnosis']) ? trim($data['diagnosis']) : null,
            'goal' => isset($data['goal']) ? trim($data['goal']) : null,
        ], fn($value) => $value !== null && $value !== ''));

        if (array_key_exists('related_to', $data)) {
            $item->related_to = self::clean($data['related_to']);
        }
        if (array_key_exists('interventions', $data)) {
            $item->interventions = self::interventions($data['interventions'] ?? []);
        }

        $item->save();

        return $item;
    }

    /**
     * Record this shift's evaluation. Evaluating again in the same shift
     * replaces that shift's evaluation rather than adding a second one.
     */
    public static function evaluate(NursingCarePlanItem $item, string $outcome, ?string $note, ?int $userId): NursingCarePlanEvaluation
    {
        $slot = self::currentSlot($item->patient);

        $existing = $slot
            ? $item->evaluations()->where('shift_date', $slot['date'])->where('shift_code', $slot['code'])->first()
            : null;

        $values = [
            'patient_id' => $item->patient_id,
            'outcome' => $outcome,
            'note' => self::clean($note),
            'shift_date' => $slot['date'] ?? now()->toDateString(),
            'shift_code' => $slot['code'] ?? null,
            'evaluated_at' => now(),
            'evaluated_by' => $userId,
        ];

        if ($existing) {
            $existing->update($values);

            return $existing;
        }

        return $item->evaluations()->create($values);
    }

    /** Resolve (goal reached) or discontinue (no longer relevant) an item. */
    public static function close(NursingCarePlanItem $item, string $status, ?string $note, ?int $userId): NursingCarePlanItem
    {
        $item->update([
            'status' => $status,
            'resolved_at' => now(),
            'resolved_by' => $userId,
            'resolve_note' => self::clean($note),
        ]);

        return $item;
    }

    // ---------------------------------------------------------- suggestions

    /**
     * Library templates the patient's record points to and the plan does not
     * cover yet, each with the reason in plain words.
     */
    public static function suggestionsFor(Patient $patient, Collection $activeItems): array
    {
        $since = $patient->admitted_at;
        $vital = VitalSign::where('patient_id', $patient->id)->orderByDesc('recorded_at')->first();
        $scores = ClinicalIndicatorScore::with('clinicalIndicator:id,code,name')
            ->where('patient_id', $patient->id)
            ->when($since, fn($query) => $query->where('recorded_at', '>=', $since))
            ->orderByDesc('recorded_at')
            ->get()
            ->filter(fn(ClinicalIndicatorScore $score) => $score->clinicalIndicator)
            ->unique(fn(ClinicalIndicatorScore $score) => $score->clinicalIndicator->code)
            ->keyBy(fn(ClinicalIndicatorScore $score) => $score->clinicalIndicator->code);
        $plan = FluidBalancePlan::currentFor($patient);
        $overload = FluidOverloadAssessment::where('patient_id', $patient->id)
            ->when($since, fn($query) => $query->where('assessed_at', '>=', $since))
            ->orderByDesc('assessed_at')
            ->first();
        $unit = BloodTransfusion::where('patient_id', $patient->id)
            ->whereIn('status', [BloodTransfusion::STATUS_PENDING, BloodTransfusion::STATUS_IN_PROGRESS])
            ->orderByDesc('id')
            ->first();
        $insulin = PatientMedication::where('patient_id', $patient->id)
            ->where('status', PatientMedication::STATUS_ACTIVE)
            ->where('medication_name', 'like', '%insulin%')
            ->exists();

        $risky = fn(?string $code) => ($score = $scores->get($code)) && in_array($score->band_tone, ['moderate', 'high'], true);
        $scoreReason = fn(string $code) => ($score = $scores->get($code))
            ? $score->clinicalIndicator->name . ' ' . $score->score . ($score->band_label ? ' - ' . $score->band_label : '')
            : null;
        $dietCodes = collect($patient->diet_types ?? [])->filter(fn($code) => is_string($code))->map(fn($code) => strtoupper($code));
        $onOxygen = $vital?->oxygen_delivery && $vital->oxygen_delivery !== VitalSign::OXYGEN_ROOM_AIR;

        $reasons = [
            'falls' => match (true) {
                in_array($patient->fall_risk, ['high', 'alert_active'], true) => 'Fall risk on record: ' . ($patient->fall_risk === 'high' ? 'High' : 'FR Alert Active'),
                $risky('MORSE') => $scoreReason('MORSE'),
                $risky('HUMPTY') => $scoreReason('HUMPTY'),
                $patient->fall_risk === 'moderate' => 'Fall risk on record: Moderate',
                default => null,
            },
            'pain' => $risky('PAIN') ? $scoreReason('PAIN') : ($risky('FLACC') ? $scoreReason('FLACC') : null),
            'fluid_excess' => match (true) {
                (bool) $plan?->intake_limit_ml => 'Fluid limit ' . FluidBalanceEntry::formatMl((int) $plan->intake_limit_ml) . ' per day',
                $overload && ($overload->hasEdema() || $overload->hasOverloadSigns()) => $overload->edemaSummary(),
                default => null,
            },
            'fluid_deficit' => match (true) {
                (bool) $plan?->urine_min_ml_per_hour && !$plan?->intake_limit_ml => 'Urine target ' . $plan->urine_min_ml_per_hour . ' mL/h',
                $dietCodes->intersect(Patient::NBM_DIET_CODES)->isNotEmpty() => 'Nil by mouth',
                default => null,
            },
            'skin' => match (true) {
                $risky('BRADEN') => $scoreReason('BRADEN'),
                in_array($patient->nursing_level, ['level_3', 'level_4'], true) => 'Level of care: ' . ucfirst(str_replace('_', ' ', $patient->nursing_level)),
                default => null,
            },
            'infection' => match (true) {
                $patient->isolation_type && $patient->isolation_type !== 'none' => 'Isolation: ' . ucfirst(str_replace('_', ' ', $patient->isolation_type)),
                $vital && $vital->temperature !== null && (float) $vital->temperature >= 38.0 => 'Temperature ' . number_format((float) $vital->temperature, 1) . ' °C',
                default => null,
            },
            'breathing' => match (true) {
                $onOxygen => 'On oxygen (' . $vital->oxygenShortLabel() . ')',
                $vital && $vital->spo2 !== null && $vital->spo2 < 94 => 'SpO2 ' . $vital->spo2 . '%',
                $vital && $vital->respiratory_rate !== null && $vital->respiratory_rate > 20 => 'Respiratory rate ' . $vital->respiratory_rate,
                default => null,
            },
            'nutrition' => $risky('MUST') ? $scoreReason('MUST') : ($risky('STAMP') ? $scoreReason('STAMP') : null),
            'glucose' => match (true) {
                (bool) $patient->hgt_enabled => 'HGT monitoring on',
                $insulin => 'Insulin charted',
                $dietCodes->contains(fn($code) => str_contains($code, 'DIAB')) => 'Diabetic diet',
                default => null,
            },
            'confusion' => match (true) {
                $risky('4AT') => $scoreReason('4AT'),
                $scores->has('GCS') && (int) $scores->get('GCS')->score < 15 => $scoreReason('GCS'),
                $scores->has('AVPU') && (int) $scores->get('AVPU')->score > 0 => $scoreReason('AVPU'),
                default => null,
            },
            'transfusion' => $unit
                ? 'Blood unit ' . $unit->unit_number . ($unit->isRunning() ? ' running' : ' registered')
                : null,
        ];

        $inPlan = $activeItems->pluck('template_key')->filter()->all();

        return collect($reasons)
            ->filter(fn($reason, string $key) => $reason !== null && !in_array($key, $inPlan, true))
            ->map(fn(string $reason, string $key) => [
                'key' => $key,
                'diagnosis' => NursingCarePlanLibrary::TEMPLATES[$key]['diagnosis'],
                'category_label' => NursingCarePlanLibrary::categoryLabel(NursingCarePlanLibrary::TEMPLATES[$key]['category']),
                'reason' => $reason,
            ])
            ->values()
            ->all();
    }

    // -------------------------------------------------------------- helpers

    /** The roster slot on now for the patient's ward (date + shift code). */
    public static function currentSlot(?Patient $patient, ?CarbonInterface $now = null): ?array
    {
        return $patient ? (ShiftHandover::slotsFor($patient, $now)['current'] ?? null) : null;
    }

    public static function evaluatedIn(NursingCarePlanItem $item, ?array $slot): bool
    {
        if (!$slot) {
            return false;
        }

        return $item->evaluations->contains(fn(NursingCarePlanEvaluation $evaluation) => $evaluation->shift_code === $slot['code']
            && $evaluation->shift_date?->toDateString() === $slot['date']);
    }

    private static function item(NursingCarePlanItem $item, ?array $slot): array
    {
        $evaluation = fn(NursingCarePlanEvaluation $e) => [
            'id' => $e->id,
            'outcome' => $e->outcome,
            'outcome_label' => $e->outcomeLabel(),
            'note' => $e->note,
            'time_label' => self::when($e->evaluated_at),
            'shift_label' => $e->shift_code
                ? ShiftHandover::label($e->shift_code, $e->shift_date?->toDateString())
                : null,
            'by' => $e->evaluatedBy?->name,
        ];

        return [
            'id' => $item->id,
            'template_key' => $item->template_key,
            'category' => $item->category,
            'category_label' => NursingCarePlanLibrary::categoryLabel($item->category),
            'diagnosis' => $item->diagnosis,
            'related_to' => $item->related_to,
            'goal' => $item->goal,
            'interventions' => array_values($item->interventions ?? []),
            'status' => $item->status,
            'status_label' => $item->statusLabel(),
            'started_label' => $item->started_at ? self::when($item->started_at) : null,
            'created_by' => $item->createdBy?->name,
            'evaluated_this_shift' => self::evaluatedIn($item, $slot),
            'latest' => ($latest = $item->evaluations->first()) ? $evaluation($latest) : null,
            'history' => $item->evaluations->take(self::HISTORY)->map($evaluation)->values()->all(),
            'resolved_label' => $item->resolved_at ? self::when($item->resolved_at) : null,
            'resolved_by' => $item->resolvedBy?->name,
            'resolve_note' => $item->resolve_note,
        ];
    }

    private static function interventions($list): array
    {
        return collect(is_array($list) ? $list : [])
            ->map(fn($line) => is_string($line) ? trim($line) : '')
            ->filter(fn(string $line) => $line !== '')
            ->unique()
            ->take(20)
            ->values()
            ->all();
    }

    private static function clean(?string $text): ?string
    {
        $text = $text !== null ? trim($text) : null;

        return $text !== '' ? $text : null;
    }

    /** "14:05" today, "Yest 14:05", or "21 Sep 14:05". */
    public static function when(CarbonInterface $at): string
    {
        if ($at->isToday()) {
            return $at->format('H:i');
        }

        return ($at->isYesterday() ? 'Yest' : $at->format('d M')) . ' ' . $at->format('H:i');
    }
}
