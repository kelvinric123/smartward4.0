<?php

namespace App\Support;

/**
 * Ready-made nursing care plan entries: the nursing diagnoses a general ward
 * plans for most often, each with a starting goal and interventions the nurse
 * can keep, edit or add to. They are a starting point, not a protocol - the
 * plan saved is whatever the nurse settles on.
 *
 * Each template names what in the record suggests it (see
 * NursingCarePlan::suggestionsFor), so the plan can prompt "fall risk is
 * High - add Risk for falls?" instead of the nurse starting from blank.
 */
class NursingCarePlanLibrary
{
    public const CATEGORIES = [
        'safety' => 'Safety',
        'comfort' => 'Comfort',
        'fluid' => 'Fluid balance',
        'skin' => 'Skin',
        'infection' => 'Infection',
        'respiratory' => 'Breathing',
        'nutrition' => 'Nutrition',
        'metabolic' => 'Blood glucose',
        'neuro' => 'Cognition',
        'treatment' => 'Treatment',
        'psychosocial' => 'Psychosocial',
        'other' => 'Other',
    ];

    public const TEMPLATES = [
        'falls' => [
            'category' => 'safety',
            'diagnosis' => 'Risk for falls',
            'related_to' => 'Impaired mobility, medication or confusion',
            'goal' => 'No fall during this admission.',
            'interventions' => [
                'Bed at its lowest position with brakes on',
                'Call bell and personal items within reach',
                'Hourly rounding',
                'Non-slip footwear when mobilising',
                'Assist with toileting and walking',
                'Review sedating medicines with the doctor',
            ],
        ],
        'pain' => [
            'category' => 'comfort',
            'diagnosis' => 'Acute pain',
            'related_to' => 'Illness, surgery or procedure',
            'goal' => 'Pain score 3/10 or less, and the patient reports being comfortable.',
            'interventions' => [
                'Assess the pain score every 4 hours and after analgesia',
                'Give prescribed analgesia on time',
                'Comfort measures: positioning, heat or cold',
                'Reassess 30 to 60 minutes after analgesia',
                'Escalate uncontrolled pain to the doctor',
            ],
        ],
        'fluid_excess' => [
            'category' => 'fluid',
            'diagnosis' => 'Excess fluid volume',
            'related_to' => 'Heart, kidney or liver failure, or IV fluids',
            'goal' => 'Intake within the fluid plan and no signs of fluid overload.',
            'interventions' => [
                'Strict intake and output chart',
                'Keep to the fluid limit',
                'Weigh daily at the same time',
                'Check for edema and breathlessness each shift',
                'Report urine output below target',
            ],
        ],
        'fluid_deficit' => [
            'category' => 'fluid',
            'diagnosis' => 'Risk for deficient fluid volume',
            'related_to' => 'Poor intake, losses or nil by mouth',
            'goal' => 'Urine output at target, moist mucous membranes, stable blood pressure.',
            'interventions' => [
                'Encourage or give prescribed fluids',
                'Strict intake and output chart',
                'Monitor urine output against the target',
                'Watch for dizziness, low blood pressure and a fast pulse',
            ],
        ],
        'skin' => [
            'category' => 'skin',
            'diagnosis' => 'Risk for impaired skin integrity',
            'related_to' => 'Immobility, incontinence or poor nutrition',
            'goal' => 'Skin intact with no new pressure injury.',
            'interventions' => [
                'Reposition at least every 2 hours',
                'Pressure-relieving mattress',
                'Keep the skin clean and dry',
                'Check pressure areas each shift',
                'Support nutrition and hydration',
            ],
        ],
        'infection' => [
            'category' => 'infection',
            'diagnosis' => 'Risk for infection',
            'related_to' => 'Invasive lines, wounds or reduced immunity',
            'goal' => 'No signs of infection; temperature within normal range.',
            'interventions' => [
                'Hand hygiene before and after every contact',
                'Aseptic care of lines, catheters and wounds',
                'Monitor temperature and line or wound sites',
                'Isolation precautions as required',
                'Give antibiotics on time',
            ],
        ],
        'breathing' => [
            'category' => 'respiratory',
            'diagnosis' => 'Ineffective breathing pattern',
            'related_to' => 'Infection, fluid or reduced lung expansion',
            'goal' => 'SpO2 at target and respiratory rate 12 to 20.',
            'interventions' => [
                'Sit upright or position for comfort',
                'Oxygen as prescribed; check the delivery device',
                'Monitor SpO2 and respiratory rate each round',
                'Encourage deep breathing and coughing',
                'Escalate if the EWS rises',
            ],
        ],
        'nutrition' => [
            'category' => 'nutrition',
            'diagnosis' => 'Imbalanced nutrition: less than body requirements',
            'related_to' => 'Poor appetite, nausea or nil by mouth',
            'goal' => 'Eats at least half of each meal; weight stable.',
            'interventions' => [
                'Food chart',
                'Assist with meals',
                'Refer to the dietitian',
                'Weigh weekly',
            ],
        ],
        'glucose' => [
            'category' => 'metabolic',
            'diagnosis' => 'Risk for unstable blood glucose',
            'related_to' => 'Diabetes, steroids or poor intake',
            'goal' => 'HGT between 4 and 10 mmol/L.',
            'interventions' => [
                'HGT as ordered',
                'Give insulin or diabetic medicines as prescribed',
                'Treat HGT below 4 by the hypoglycaemia protocol',
                'Diabetic diet',
            ],
        ],
        'confusion' => [
            'category' => 'neuro',
            'diagnosis' => 'Risk for acute confusion',
            'related_to' => 'Illness, medication, age or sleep loss',
            'goal' => 'Stays oriented and safe; no new delirium.',
            'interventions' => [
                'Reorientate regularly',
                'Glasses and hearing aids on',
                'Keep a sleep routine; low light at night',
                'Frequent safety checks',
                'Report new confusion to the doctor',
            ],
        ],
        'transfusion' => [
            'category' => 'treatment',
            'diagnosis' => 'Risk for transfusion reaction',
            'related_to' => 'Blood product transfusion',
            'goal' => 'Unit completed with no reaction.',
            'interventions' => [
                'Baseline observations before starting',
                'Observations 15 minutes after the start, then per protocol',
                'Stay with the patient for the first 15 minutes',
                'Stop and escalate at any sign of a reaction',
            ],
        ],
        'anxiety' => [
            'category' => 'psychosocial',
            'diagnosis' => 'Anxiety',
            'related_to' => 'Illness, hospital stay or uncertainty',
            'goal' => 'Patient says they feel calmer and understands the plan.',
            'interventions' => [
                'Explain each procedure beforehand',
                'Encourage questions and involve the family',
                'Offer a quiet environment',
                'Refer for counselling if needed',
            ],
        ],
    ];

    public static function template(?string $key): ?array
    {
        return $key !== null ? (self::TEMPLATES[$key] ?? null) : null;
    }

    public static function categoryLabel(?string $category): string
    {
        return self::CATEGORIES[$category] ?? self::CATEGORIES['other'];
    }
}
