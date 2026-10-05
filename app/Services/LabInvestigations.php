<?php

namespace App\Services;

use App\Models\IntegrationSetting;
use App\Models\LabInvestigation;
use App\Models\Patient;
use Illuminate\Support\Carbon;

/**
 * Lab Investigations in Patient Details: the system-wide switches (Settings > Patient
 * Additional Info), the HIS feed, and the sample data that demonstrates it.
 */
class LabInvestigations
{
    public const KEY = 'lab_investigations.settings';

    /** Tab on, sample data off: a live system never shows demo results unless asked to. */
    public const DEFAULTS = ['enabled' => true, 'sample' => false];

    public static function settings(): array
    {
        $saved = IntegrationSetting::get(self::KEY, []);
        $saved = is_array($saved) ? $saved : [];

        return [
            'enabled' => (bool) ($saved['enabled'] ?? self::DEFAULTS['enabled']),
            'sample' => (bool) ($saved['sample'] ?? self::DEFAULTS['sample']),
        ];
    }

    public static function enabled(): bool
    {
        return self::settings()['enabled'];
    }

    public static function sampleData(): bool
    {
        return self::settings()['sample'];
    }

    /** Switching sample data off removes it, so switching it back on starts a fresh, current set. */
    public static function save(bool $enabled, bool $sample): void
    {
        IntegrationSetting::put(self::KEY, ['enabled' => $enabled, 'sample' => $sample]);

        if (! $sample) {
            LabInvestigation::where('source', LabInvestigation::SOURCE_SAMPLE)->delete();
        }
    }

    /**
     * Store one HIS lab order (already validated). Each investigation is matched on order
     * number + test name, so the HIS can resend an order as it moves from ordered to resulted.
     *
     * @return array{created: int, updated: int}
     */
    public static function ingest(Patient $patient, array $order): array
    {
        $counts = ['created' => 0, 'updated' => 0];

        foreach ($order['investigations'] as $item) {
            $lab = LabInvestigation::firstOrNew([
                'source' => LabInvestigation::SOURCE_HIS,
                'order_no' => $order['order_no'],
                'test_name' => $item['name'],
            ]);

            $status = $item['status'] ?? 'ordered';
            $results = $item['results'] ?? null;

            // An amended result has to be reviewed again
            $amended = $lab->exists && $lab->reviewed_at && $results !== null && $results != $lab->results;

            $lab->fill([
                'patient_id' => $patient->id,
                'test_code' => $item['code'] ?? $lab->test_code,
                'category' => $item['category'] ?? $lab->category ?? 'other',
                'specimen' => $item['specimen'] ?? $lab->specimen,
                'priority' => $order['priority'] ?? $lab->priority ?? 'routine',
                'ordered_by' => $order['ordered_by'] ?? $lab->ordered_by,
                'ordered_at' => $order['ordered_at'] ?? $lab->ordered_at ?? now(),
                'status' => $status,
                'collected_at' => $item['collected_at'] ?? $lab->collected_at,
                'resulted_at' => $item['resulted_at'] ?? $lab->resulted_at ?? ($status === 'resulted' ? now() : null),
                'results' => $results ?? $lab->results,
                'comment' => $item['comment'] ?? $lab->comment,
                'review_due_at' => $item['review_due_at'] ?? $order['review_due_at'] ?? $lab->review_due_at,
            ]);

            if ($amended) {
                $lab->fill(['reviewed_at' => null, 'reviewed_by' => null, 'reviewed_by_name' => null]);
            }

            // A review already done in the HIS
            if (! empty($item['reviewed_at'])) {
                $lab->fill(['reviewed_at' => $item['reviewed_at'], 'reviewed_by_name' => $item['reviewed_by'] ?? null]);
            }

            $counts[$lab->exists ? 'updated' : 'created']++;
            $lab->save();
        }

        return $counts;
    }

    /**
     * Give a patient the demo set once, timed around now so every review state shows:
     * overdue, due soon, due later, awaiting result and reviewed.
     */
    public static function ensureSamples(Patient $patient): void
    {
        if (LabInvestigation::where('patient_id', $patient->id)->where('source', LabInvestigation::SOURCE_SAMPLE)->exists()) {
            return;
        }

        $now = now()->startOfMinute();
        $doctor = $patient->consultant?->name ?: 'Dr. On-call';
        $order = fn (int $n) => 'LAB' . $now->format('ymd') . str_pad((string) ($patient->id * 10 + $n), 5, '0', STR_PAD_LEFT);
        $ago = fn (int $minutes) => $now->copy()->subMinutes($minutes);

        $samples = [
            // Morning bloods from yesterday: FBC still unreviewed (overdue), coagulation reviewed in the HIS
            [
                'order_no' => $order(1), 'test_code' => 'FBC', 'test_name' => 'Full Blood Count',
                'category' => 'haematology', 'specimen' => 'Blood (EDTA)', 'priority' => 'routine',
                'ordered_at' => $ago(30 * 60), 'collected_at' => $ago(29 * 60 + 30), 'resulted_at' => $ago(26 * 60),
                'status' => 'resulted',
                'results' => [
                    ['name' => 'Haemoglobin', 'value' => '9.8', 'unit' => 'g/dL', 'range' => '12.0-15.0', 'flag' => 'L'],
                    ['name' => 'White cell count', 'value' => '13.2', 'unit' => 'x10^9/L', 'range' => '4.0-11.0', 'flag' => 'H'],
                    ['name' => 'Platelets', 'value' => '245', 'unit' => 'x10^9/L', 'range' => '150-400', 'flag' => ''],
                    ['name' => 'Haematocrit', 'value' => '0.30', 'unit' => 'L/L', 'range' => '0.36-0.46', 'flag' => 'L'],
                ],
            ],
            [
                'order_no' => $order(1), 'test_code' => 'COAG', 'test_name' => 'Coagulation Profile (PT/INR/APTT)',
                'category' => 'coagulation', 'specimen' => 'Blood (Citrate)', 'priority' => 'routine',
                'ordered_at' => $ago(30 * 60), 'collected_at' => $ago(29 * 60 + 30), 'resulted_at' => $ago(25 * 60),
                'status' => 'resulted', 'reviewed_at' => $ago(22 * 60), 'reviewed_by_name' => $doctor,
                'results' => [
                    ['name' => 'PT', 'value' => '12.8', 'unit' => 's', 'range' => '11.0-13.5', 'flag' => ''],
                    ['name' => 'INR', 'value' => '1.1', 'unit' => '', 'range' => '0.8-1.2', 'flag' => ''],
                    ['name' => 'APTT', 'value' => '31', 'unit' => 's', 'range' => '25-37', 'flag' => ''],
                ],
            ],
            // Urgent renal profile with a critical potassium, due for review later today
            [
                'order_no' => $order(2), 'test_code' => 'RP', 'test_name' => 'Renal Profile (BUSE)',
                'category' => 'biochemistry', 'specimen' => 'Blood (Plain)', 'priority' => 'urgent',
                'ordered_at' => $ago(4 * 60), 'collected_at' => $ago(3 * 60 + 45), 'resulted_at' => $ago(2 * 60),
                'status' => 'resulted',
                'results' => [
                    ['name' => 'Sodium', 'value' => '134', 'unit' => 'mmol/L', 'range' => '135-145', 'flag' => 'L'],
                    ['name' => 'Potassium', 'value' => '6.2', 'unit' => 'mmol/L', 'range' => '3.5-5.1', 'flag' => 'HH'],
                    ['name' => 'Urea', 'value' => '12.4', 'unit' => 'mmol/L', 'range' => '2.5-7.1', 'flag' => 'H'],
                    ['name' => 'Creatinine', 'value' => '168', 'unit' => 'umol/L', 'range' => '49-90', 'flag' => 'H'],
                ],
                'comment' => 'Critical potassium phoned to ward by lab.',
            ],
            // STAT blood gas, due for review within the hour
            [
                'order_no' => $order(3), 'test_code' => 'ABG', 'test_name' => 'Arterial Blood Gas',
                'category' => 'blood_gas', 'specimen' => 'Arterial blood', 'priority' => 'stat',
                'ordered_at' => $ago(70), 'collected_at' => $ago(65), 'resulted_at' => $ago(40),
                'status' => 'resulted',
                'results' => [
                    ['name' => 'pH', 'value' => '7.31', 'unit' => '', 'range' => '7.35-7.45', 'flag' => 'L'],
                    ['name' => 'pCO2', 'value' => '6.8', 'unit' => 'kPa', 'range' => '4.7-6.0', 'flag' => 'H'],
                    ['name' => 'pO2', 'value' => '9.2', 'unit' => 'kPa', 'range' => '10.0-13.3', 'flag' => 'L'],
                    ['name' => 'HCO3', 'value' => '23', 'unit' => 'mmol/L', 'range' => '22-26', 'flag' => ''],
                    ['name' => 'Lactate', 'value' => '2.6', 'unit' => 'mmol/L', 'range' => '0.5-2.0', 'flag' => 'H'],
                ],
            ],
            // Still in the lab
            [
                'order_no' => $order(4), 'test_code' => 'CRP', 'test_name' => 'C-Reactive Protein',
                'category' => 'biochemistry', 'specimen' => 'Blood (Plain)', 'priority' => 'stat',
                'ordered_at' => $ago(25), 'status' => 'ordered',
            ],
            [
                'order_no' => $order(5), 'test_code' => 'HBA1C', 'test_name' => 'HbA1c',
                'category' => 'biochemistry', 'specimen' => 'Blood (EDTA)', 'priority' => 'routine',
                'ordered_at' => $ago(2 * 60), 'collected_at' => $ago(60), 'status' => 'collected',
            ],
            // Cultures take days, so the HIS sends its own review time
            [
                'order_no' => $order(6), 'test_code' => 'BCS', 'test_name' => 'Blood Culture & Sensitivity',
                'category' => 'microbiology', 'specimen' => 'Blood culture bottles x2', 'priority' => 'urgent',
                'ordered_at' => $ago(20 * 60), 'collected_at' => $ago(19 * 60 + 30), 'status' => 'in_progress',
                'review_due_at' => $ago(20 * 60)->addDays(5),
                'comment' => 'Preliminary: no growth at 18 hours. Final report at 5 days.',
            ],
        ];

        foreach ($samples as $sample) {
            LabInvestigation::create($sample + [
                'patient_id' => $patient->id,
                'source' => LabInvestigation::SOURCE_SAMPLE,
                'ordered_by' => $doctor,
            ]);
        }
    }

    /** Example request body for the HIS feed, shown in Settings. */
    public static function examplePayload(): array
    {
        $now = Carbon::parse('2026-10-05 08:00');

        return [
            'patient' => ['mrn' => 'MRN000123', 'rn' => 'RN000456'],
            'order' => [
                'order_no' => 'LAB2610050001',
                'ordered_at' => $now->toIso8601String(),
                'ordered_by' => 'Dr. Lim Wei Ming',
                'priority' => 'urgent',
                'investigations' => [[
                    'code' => 'RP',
                    'name' => 'Renal Profile (BUSE)',
                    'category' => 'biochemistry',
                    'specimen' => 'Blood (Plain)',
                    'status' => 'resulted',
                    'collected_at' => $now->copy()->addMinutes(15)->toIso8601String(),
                    'resulted_at' => $now->copy()->addHours(2)->toIso8601String(),
                    'review_due_at' => $now->copy()->addHours(4)->toIso8601String(),
                    'results' => [
                        ['name' => 'Potassium', 'value' => '6.2', 'unit' => 'mmol/L', 'range' => '3.5-5.1', 'flag' => 'HH'],
                    ],
                ]],
            ],
        ];
    }
}
