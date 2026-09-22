<?php

namespace Database\Seeders;

use App\Models\Medication;
use Illuminate\Database\Seeder;

class MedicationSeeder extends Seeder
{
    /**
     * Demo formulary: 30 medications commonly used on adult inpatient wards,
     * each with the dose, route and frequency it is typically ordered at.
     * These only pre-fill the "Add medication" form; the order entered for
     * the patient is what counts. Safe to re-run (matched on name).
     *
     * php artisan db:seed --class=MedicationSeeder
     */
    public function run(): void
    {
        $medications = [
            // Analgesic
            ['Paracetamol', 'Analgesic', 1, 'g', 'PO', 'qid', null, false, 'Max 4 g in 24 h. Check for other paracetamol-containing products.'],
            ['Tramadol', 'Analgesic', 50, 'mg', 'PO', 'tds', null, false, 'Can cause drowsiness and nausea. Lowers the seizure threshold.'],
            ['Morphine', 'Analgesic', 2.5, 'mg', 'IV', 'prn', 4, true, 'Check respiratory rate and sedation before each dose.'],
            ['Diclofenac', 'Analgesic', 50, 'mg', 'PO', 'tds', null, false, 'Give with food. Avoid in renal impairment or GI bleeding.'],

            // Antibiotic
            ['Ceftriaxone', 'Antibiotic', 1, 'g', 'IV', 'od', null, false, 'Check penicillin / cephalosporin allergy before the first dose.'],
            ['Amoxicillin/Clavulanate', 'Antibiotic', 1.2, 'g', 'IV', 'tds', null, false, 'Penicillin. Check allergy status before the first dose.'],
            ['Piperacillin/Tazobactam', 'Antibiotic', 4.5, 'g', 'IV', 'tds', null, false, 'Penicillin. Check allergy status before the first dose.'],
            ['Metronidazole', 'Antibiotic', 500, 'mg', 'IV', 'tds', null, false, 'Avoid alcohol during treatment.'],
            ['Cefuroxime', 'Antibiotic', 750, 'mg', 'IV', 'tds', null, false, 'Check penicillin / cephalosporin allergy before the first dose.'],
            ['Meropenem', 'Antibiotic', 1, 'g', 'IV', 'tds', null, false, 'Dose may need adjusting in renal impairment.'],
            ['Vancomycin', 'Antibiotic', 1, 'g', 'IV', 'bd', null, false, 'Infuse over at least 60 min. Monitor trough levels and renal function.'],
            ['Azithromycin', 'Antibiotic', 500, 'mg', 'PO', 'od', null, false, 'Can prolong the QT interval.'],

            // Gastrointestinal
            ['Pantoprazole', 'Gastrointestinal', 40, 'mg', 'IV', 'od', null, false, null],
            ['Omeprazole', 'Gastrointestinal', 20, 'mg', 'PO', 'od', null, false, 'Give before breakfast.'],
            ['Ondansetron', 'Gastrointestinal', 4, 'mg', 'IV', 'tds', null, false, 'Can prolong the QT interval.'],
            ['Metoclopramide', 'Gastrointestinal', 10, 'mg', 'IV', 'tds', null, false, 'Use for up to 5 days. Watch for abnormal movements.'],
            ['Lactulose', 'Gastrointestinal', 15, 'mL', 'PO', 'bd', null, false, 'Adjust to 2-3 soft stools a day.'],

            // Cardiovascular
            ['Amlodipine', 'Cardiovascular', 5, 'mg', 'PO', 'od', null, false, 'Check BP before giving.'],
            ['Perindopril', 'Cardiovascular', 4, 'mg', 'PO', 'od', null, false, 'Check BP. Monitor potassium and renal function.'],
            ['Bisoprolol', 'Cardiovascular', 2.5, 'mg', 'PO', 'od', null, false, 'Check pulse and BP before giving.'],
            ['Furosemide', 'Cardiovascular', 40, 'mg', 'IV', 'bd', null, false, 'Monitor fluid balance, potassium and renal function.'],
            ['Atorvastatin', 'Cardiovascular', 40, 'mg', 'PO', 'on', null, false, null],
            ['Aspirin', 'Cardiovascular', 100, 'mg', 'PO', 'od', null, false, 'Give with food. Watch for bleeding.'],
            ['Clopidogrel', 'Cardiovascular', 75, 'mg', 'PO', 'od', null, false, 'Watch for bleeding.'],

            // Anticoagulant
            ['Enoxaparin', 'Anticoagulant', 40, 'mg', 'SC', 'od', null, true, 'Check platelets and renal function. Watch for bleeding.'],

            // Diabetes
            ['Insulin Soluble (Actrapid)', 'Diabetes', 6, 'units', 'SC', 'tds', null, true, 'Check HGT before each dose and give with meals.'],
            ['Metformin', 'Diabetes', 500, 'mg', 'PO', 'bd', null, false, 'Give with meals. Review before contrast or in renal impairment.'],

            // Respiratory
            ['Salbutamol', 'Respiratory', 2.5, 'mg', 'NEB', 'qid', null, false, 'Can cause tremor and a fast heart rate.'],

            // Steroid
            ['Hydrocortisone', 'Steroid', 100, 'mg', 'IV', 'qid', null, false, 'Monitor blood glucose.'],

            // Electrolyte
            ['Potassium Chloride SR', 'Electrolyte', 600, 'mg', 'PO', 'bd', null, false, 'Check serum potassium. Swallow whole with plenty of water.'],
        ];

        foreach ($medications as [$name, $category, $dose, $unit, $route, $frequency, $intervalHours, $highAlert, $caution]) {
            Medication::updateOrCreate(
                ['name' => $name],
                [
                    'category' => $category,
                    'default_dose' => $dose,
                    'dose_unit' => $unit,
                    'default_route' => $route,
                    'default_frequency' => $frequency,
                    'default_interval_hours' => $intervalHours,
                    'is_high_alert' => $highAlert,
                    'caution' => $caution,
                    'is_active' => true,
                ]
            );
        }

        $this->command?->info('Medications seeded: ' . count($medications) . ' created/updated');
    }
}
