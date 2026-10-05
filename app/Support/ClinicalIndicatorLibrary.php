<?php

namespace App\Support;

use App\Models\ClinicalIndicator;
use Illuminate\Support\Collection;

/**
 * The assessment scales wards score patients against, defined here in code
 * rather than in a seeder so the clinical content ships with the application
 * and is versioned alongside it.
 *
 * Each entry carries the real instrument: what it is for, who it applies to,
 * the items that make up the score, the possible total, and the risk bands the
 * total falls into. Wards bind these to ward types, and the patient details
 * modal on the ward dashboard scores them item by item.
 *
 * An item is scored when it carries `options` (a label and the points it is
 * worth); the options of every item must add up to the declared score_min and
 * score_max. An item with prose in `scoring` instead is descriptive only, which
 * is how Pain Score lists which tool to use rather than components to total.
 *
 * An item may also carry a short `abbr`. When every item of a scale has one, a
 * recorded score is shown with its breakdown as well as its total, which is how
 * GCS reads as E3 V4 M6.
 *
 * An item with a `unit` and a `normal` range is a reading typed in from the
 * monitor instead, which is how the invasive hemodynamic numerics and the
 * ventilator settings are recorded. ClinicalIndicatorReadings describes those
 * items and flags them.
 *
 * A scale with `result => 'highest'` is a screen rather than a total: each
 * option's value is the level of risk that answer points to, and the most
 * serious answer sets the result, which is how the C-SSRS triages. An item
 * with `asked_when => [abbr, label]` is asked only after that answer to an
 * earlier item, and each band carries the `action` it calls for.
 * ClinicalIndicatorScreen works the answers out.
 *
 * Every scale names one of CATEGORIES as its category, which is what it is
 * listed under. Scales appear category by category in CATEGORIES order, and in
 * INDICATORS order within a category.
 *
 * Adding or amending a scale: edit INDICATORS, then run
 *
 *     php artisan clinical-indicators:sync
 *
 * which upserts the rows by code. Existing ward-type links are keyed on the
 * row id and are left alone.
 *
 * `confirmed => false` marks a scale whose local variant is still being
 * settled: the row exists and can be bound, but the detail is deliberately
 * blank rather than guessed at.
 */
final class ClinicalIndicatorLibrary
{
    public const TONE_LOW = 'low';
    public const TONE_MODERATE = 'moderate';
    public const TONE_HIGH = 'high';

    public const OTHER_CATEGORY = 'Other';

    /** A screen's result: the most serious answer, not a total. */
    public const RESULT_HIGHEST = 'highest';

    /**
     * The categories scales are listed under, in display order, each with the
     * line shown beneath its heading. Other is never named by a scale here: it
     * collects the ones a hospital added itself, which have no library entry.
     */
    public const CATEGORIES = [
        'Fall risk' => 'Who is likely to fall, so precautions can be put in place',
        'Pain' => 'How much pain the patient is in, self-reported or observed',
        'Pressure injury risk' => 'Risk of developing a pressure injury, so prevention can be matched to it',
        'Nutrition' => 'Screening for malnutrition and the risk of it',
        'Consciousness' => 'Level of consciousness, to pick up neurological deterioration',
        'Deterioration' => 'Early warning of a patient getting worse, from routine observations',
        'Hemodynamics' => 'Invasive pressures and cardiac output from the bedside monitor, for critical care',
        'Ventilation' => 'Ventilator settings and airway readings for intubated patients, for critical care',
        'Delirium' => 'Screening for delirium and cognitive impairment',
        'Mental state' => 'Screening for suicidal thoughts and behaviour, so a patient at risk is referred and kept safe',
        'Frailty' => 'Screening older adults for frailty',
        self::OTHER_CATEGORY => 'Added locally, with no clinical detail in the library yet',
    ];

    public const INDICATORS = [
        [
            'code' => 'HUMPTY',
            'name' => 'Humpty Dumpty Falls Scale',
            'category' => 'Fall risk',
            'population' => 'Paediatric inpatients',
            'purpose' => 'Identifies children at risk of falling in hospital so that fall precautions can be put in place.',
            'items' => [
                ['name' => 'Age',
                    'options' => [
                        ['label' => 'Under 3 yr', 'value' => 4],
                        ['label' => '3 to 6 yr', 'value' => 3],
                        ['label' => '7 to 12 yr', 'value' => 2],
                        ['label' => '13 yr and over', 'value' => 1],
                    ]],
                ['name' => 'Gender',
                    'options' => [
                        ['label' => 'Male', 'value' => 2],
                        ['label' => 'Female', 'value' => 1],
                    ]],
                ['name' => 'Diagnosis',
                    'options' => [
                        ['label' => 'Neurological', 'value' => 4],
                        ['label' => 'Alteration in oxygenation', 'value' => 3],
                        ['label' => 'Psychiatric or behavioural', 'value' => 2],
                        ['label' => 'Other', 'value' => 1],
                    ]],
                ['name' => 'Cognitive impairment',
                    'options' => [
                        ['label' => 'Not aware of limitations', 'value' => 3],
                        ['label' => 'Forgets limitations', 'value' => 2],
                        ['label' => 'Oriented to own ability', 'value' => 1],
                    ]],
                ['name' => 'Environmental factors',
                    'options' => [
                        ['label' => 'History of falls or infant in adult bed', 'value' => 4],
                        ['label' => 'Uses assistive devices or infant in cot', 'value' => 3],
                        ['label' => 'Patient in bed', 'value' => 2],
                        ['label' => 'Outpatient area', 'value' => 1],
                    ]],
                ['name' => 'Response to surgery, sedation or anaesthesia',
                    'options' => [
                        ['label' => 'Within 24 hr', 'value' => 3],
                        ['label' => 'Within 48 hr', 'value' => 2],
                        ['label' => 'Over 48 hr or none', 'value' => 1],
                    ]],
                ['name' => 'Medication usage',
                    'options' => [
                        ['label' => 'Two or more of sedatives, hypnotics, barbiturates, phenothiazines, antidepressants, laxatives, diuretics or narcotics', 'value' => 3],
                        ['label' => 'One of these', 'value' => 2],
                        ['label' => 'Other or none', 'value' => 1],
                    ]],
            ],
            'score_min' => 7,
            'score_max' => 23,
            'bands' => [
                ['label' => 'Low risk', 'range' => '7 to 11', 'min' => 7, 'max' => 11, 'tone' => self::TONE_LOW],
                ['label' => 'High risk', 'range' => '12 and above', 'min' => 12, 'max' => null, 'tone' => self::TONE_HIGH],
            ],
            'reference' => 'Hill-Rodriguez et al., 2009',
            'confirmed' => true,
        ],
        [
            'code' => 'FLACC',
            'name' => 'FLACC Pain Scale',
            'category' => 'Pain',
            'population' => 'Preverbal children (roughly 2 months to 7 years) and any patient unable to self-report',
            'purpose' => 'Behavioural pain assessment by observation, for patients who cannot give a pain score themselves.',
            'items' => [
                ['name' => 'Face',
                    'options' => [
                        ['label' => 'No particular expression or smile', 'value' => 0],
                        ['label' => 'Occasional grimace or frown, withdrawn', 'value' => 1],
                        ['label' => 'Frequent to constant frown, clenched jaw, quivering chin', 'value' => 2],
                    ]],
                ['name' => 'Legs',
                    'options' => [
                        ['label' => 'Normal position or relaxed', 'value' => 0],
                        ['label' => 'Uneasy, restless, tense', 'value' => 1],
                        ['label' => 'Kicking or legs drawn up', 'value' => 2],
                    ]],
                ['name' => 'Activity',
                    'options' => [
                        ['label' => 'Lying quietly, moves easily', 'value' => 0],
                        ['label' => 'Squirming, shifting, tense', 'value' => 1],
                        ['label' => 'Arched, rigid or jerking', 'value' => 2],
                    ]],
                ['name' => 'Cry',
                    'options' => [
                        ['label' => 'No cry', 'value' => 0],
                        ['label' => 'Moans or whimpers, occasional complaint', 'value' => 1],
                        ['label' => 'Crying steadily, screams or sobs', 'value' => 2],
                    ]],
                ['name' => 'Consolability',
                    'options' => [
                        ['label' => 'Content, relaxed', 'value' => 0],
                        ['label' => 'Reassured by touching or talking, distractible', 'value' => 1],
                        ['label' => 'Difficult to console or comfort', 'value' => 2],
                    ]],
            ],
            'score_min' => 0,
            'score_max' => 10,
            'bands' => [
                ['label' => 'Relaxed and comfortable', 'range' => '0', 'min' => 0, 'max' => 0, 'tone' => self::TONE_LOW],
                ['label' => 'Mild discomfort', 'range' => '1 to 3', 'min' => 1, 'max' => 3, 'tone' => self::TONE_LOW],
                ['label' => 'Moderate pain', 'range' => '4 to 6', 'min' => 4, 'max' => 6, 'tone' => self::TONE_MODERATE],
                ['label' => 'Severe discomfort or pain', 'range' => '7 to 10', 'min' => 7, 'max' => null, 'tone' => self::TONE_HIGH],
            ],
            'reference' => 'Merkel et al., 1997',
            'confirmed' => true,
        ],
        [
            'code' => 'MORSE',
            'name' => 'Morse Fall Scale',
            'category' => 'Fall risk',
            'population' => 'Adult inpatients',
            'purpose' => 'Identifies adults at risk of falling in hospital so that fall precautions can be put in place.',
            'items' => [
                ['name' => 'History of falling',
                    'options' => [
                        ['label' => 'No', 'value' => 0],
                        ['label' => 'Yes', 'value' => 25],
                    ]],
                ['name' => 'Secondary diagnosis',
                    'options' => [
                        ['label' => 'No', 'value' => 0],
                        ['label' => 'Yes', 'value' => 15],
                    ]],
                ['name' => 'Ambulatory aid',
                    'options' => [
                        ['label' => 'None, bed rest or nurse assist', 'value' => 0],
                        ['label' => 'Crutches, cane or walker', 'value' => 15],
                        ['label' => 'Furniture', 'value' => 30],
                    ]],
                ['name' => 'Intravenous therapy or heparin lock',
                    'options' => [
                        ['label' => 'No', 'value' => 0],
                        ['label' => 'Yes', 'value' => 20],
                    ]],
                ['name' => 'Gait and transferring',
                    'options' => [
                        ['label' => 'Normal, bed rest or wheelchair', 'value' => 0],
                        ['label' => 'Weak', 'value' => 10],
                        ['label' => 'Impaired', 'value' => 20],
                    ]],
                ['name' => 'Mental status',
                    'options' => [
                        ['label' => 'Oriented to own ability', 'value' => 0],
                        ['label' => 'Overestimates or forgets limitations', 'value' => 15],
                    ]],
            ],
            'score_min' => 0,
            'score_max' => 125,
            'bands' => [
                ['label' => 'No risk', 'range' => '0 to 24', 'min' => 0, 'max' => 24, 'tone' => self::TONE_LOW],
                ['label' => 'Low risk', 'range' => '25 to 44', 'min' => 25, 'max' => 44, 'tone' => self::TONE_MODERATE],
                ['label' => 'High risk', 'range' => '45 and above', 'min' => 45, 'max' => null, 'tone' => self::TONE_HIGH],
            ],
            'reference' => 'Morse et al., 1989',
            'note' => 'Band cut-offs are commonly adjusted locally. Confirm these against your own falls policy before use.',
            'confirmed' => true,
        ],
        [
            'code' => '4AT',
            'name' => '4AT Rapid Delirium Screening',
            'category' => 'Delirium',
            'population' => 'Adults, particularly 65 and over, on admission or when delirium is suspected',
            'purpose' => 'Bedside screen for delirium and cognitive impairment. Needs no special training and takes about two minutes.',
            'items' => [
                ['name' => 'Alertness',
                    'options' => [
                        ['label' => 'Normal, or mildly sleepy for under 10 seconds', 'value' => 0],
                        ['label' => 'Clearly abnormal', 'value' => 4],
                    ]],
                ['name' => 'AMT4: age, date of birth, place, current year',
                    'options' => [
                        ['label' => 'No mistakes', 'value' => 0],
                        ['label' => 'One mistake', 'value' => 1],
                        ['label' => 'Two or more mistakes, or untestable', 'value' => 2],
                    ]],
                ['name' => 'Attention: months of the year backwards',
                    'options' => [
                        ['label' => 'Seven or more correct', 'value' => 0],
                        ['label' => 'Starts but under seven, or refuses', 'value' => 1],
                        ['label' => 'Untestable', 'value' => 2],
                    ]],
                ['name' => 'Acute change or fluctuating course',
                    'options' => [
                        ['label' => 'No', 'value' => 0],
                        ['label' => 'Yes', 'value' => 4],
                    ]],
            ],
            'score_min' => 0,
            'score_max' => 12,
            'bands' => [
                ['label' => 'Delirium or severe cognitive impairment unlikely', 'range' => '0', 'min' => 0, 'max' => 0, 'tone' => self::TONE_LOW],
                ['label' => 'Possible cognitive impairment', 'range' => '1 to 3', 'min' => 1, 'max' => 3, 'tone' => self::TONE_MODERATE],
                ['label' => 'Possible delirium, with or without cognitive impairment', 'range' => '4 and above', 'min' => 4, 'max' => null, 'tone' => self::TONE_HIGH],
            ],
            'reference' => 'MacLullich et al., the4AT.com',
            'confirmed' => true,
        ],
        [
            'code' => 'MUST',
            'name' => 'Malnutrition Universal Screening Tool',
            'category' => 'Nutrition',
            'population' => 'Adults',
            'purpose' => 'Five-step screen for adults who are malnourished or at risk of malnutrition, leading to a care plan.',
            'items' => [
                ['name' => 'Step 1: BMI',
                    'options' => [
                        ['label' => 'Over 20', 'value' => 0],
                        ['label' => '18.5 to 20', 'value' => 1],
                        ['label' => 'Under 18.5', 'value' => 2],
                    ]],
                ['name' => 'Step 2: unplanned weight loss over 3 to 6 months',
                    'options' => [
                        ['label' => 'Under 5 percent', 'value' => 0],
                        ['label' => '5 to 10 percent', 'value' => 1],
                        ['label' => 'Over 10 percent', 'value' => 2],
                    ]],
                ['name' => 'Step 3: acute disease effect',
                    'options' => [
                        ['label' => 'Not acutely ill, or intake maintained', 'value' => 0],
                        ['label' => 'Acutely ill and no nutritional intake, or likely none, for over 5 days', 'value' => 2],
                    ]],
            ],
            'score_min' => 0,
            'score_max' => 6,
            'bands' => [
                ['label' => 'Low risk, routine care', 'range' => '0', 'min' => 0, 'max' => 0, 'tone' => self::TONE_LOW],
                ['label' => 'Medium risk, observe', 'range' => '1', 'min' => 1, 'max' => 1, 'tone' => self::TONE_MODERATE],
                ['label' => 'High risk, treat', 'range' => '2 and above', 'min' => 2, 'max' => null, 'tone' => self::TONE_HIGH],
            ],
            'reference' => 'BAPEN Malnutrition Advisory Group',
            'confirmed' => true,
        ],
        [
            'code' => 'BRADEN',
            'name' => 'Braden Scale for Predicting Pressure Sore Risk',
            'category' => 'Pressure injury risk',
            'population' => 'Adult inpatients',
            'purpose' => 'Rates the risk of developing a pressure injury so that prevention can be matched to the level of risk.',
            'items' => [
                ['name' => 'Sensory perception',
                    'options' => [
                        ['label' => 'Completely limited', 'value' => 1],
                        ['label' => 'Very limited', 'value' => 2],
                        ['label' => 'Slightly limited', 'value' => 3],
                        ['label' => 'No impairment', 'value' => 4],
                    ]],
                ['name' => 'Moisture',
                    'options' => [
                        ['label' => 'Constantly moist', 'value' => 1],
                        ['label' => 'Very moist', 'value' => 2],
                        ['label' => 'Occasionally moist', 'value' => 3],
                        ['label' => 'Rarely moist', 'value' => 4],
                    ]],
                ['name' => 'Activity',
                    'options' => [
                        ['label' => 'Bedfast', 'value' => 1],
                        ['label' => 'Chairfast', 'value' => 2],
                        ['label' => 'Walks occasionally', 'value' => 3],
                        ['label' => 'Walks frequently', 'value' => 4],
                    ]],
                ['name' => 'Mobility',
                    'options' => [
                        ['label' => 'Completely immobile', 'value' => 1],
                        ['label' => 'Very limited', 'value' => 2],
                        ['label' => 'Slightly limited', 'value' => 3],
                        ['label' => 'No limitation', 'value' => 4],
                    ]],
                ['name' => 'Nutrition',
                    'options' => [
                        ['label' => 'Very poor', 'value' => 1],
                        ['label' => 'Probably inadequate', 'value' => 2],
                        ['label' => 'Adequate', 'value' => 3],
                        ['label' => 'Excellent', 'value' => 4],
                    ]],
                ['name' => 'Friction and shear',
                    'options' => [
                        ['label' => 'Problem', 'value' => 1],
                        ['label' => 'Potential problem', 'value' => 2],
                        ['label' => 'No apparent problem', 'value' => 3],
                    ]],
            ],
            'score_min' => 6,
            'score_max' => 23,
            'bands' => [
                ['label' => 'Very high risk', 'range' => '9 and below', 'min' => null, 'max' => 9, 'tone' => self::TONE_HIGH],
                ['label' => 'High risk', 'range' => '10 to 12', 'min' => 10, 'max' => 12, 'tone' => self::TONE_HIGH],
                ['label' => 'Moderate risk', 'range' => '13 to 14', 'min' => 13, 'max' => 14, 'tone' => self::TONE_MODERATE],
                ['label' => 'Mild risk', 'range' => '15 to 18', 'min' => 15, 'max' => 18, 'tone' => self::TONE_MODERATE],
                ['label' => 'Not at risk', 'range' => '19 to 23', 'min' => 19, 'max' => null, 'tone' => self::TONE_LOW],
            ],
            'reference' => 'Bergstrom and Braden, 1987',
            'note' => 'A lower total means higher risk. The Braden Q is the paediatric version and is scored differently.',
            'confirmed' => true,
        ],
        [
            'code' => 'STAMP',
            'name' => 'Screening Tool for the Assessment of Malnutrition in Paediatrics',
            'category' => 'Nutrition',
            'population' => 'Paediatric inpatients, roughly 2 to 17 years',
            'purpose' => 'Screens children on admission for malnutrition risk and sets the level of dietetic involvement.',
            'items' => [
                ['name' => 'Clinical diagnosis with nutritional implications',
                    'options' => [
                        ['label' => 'Definite', 'value' => 3],
                        ['label' => 'Possible', 'value' => 2],
                        ['label' => 'None', 'value' => 0],
                    ]],
                ['name' => 'Nutritional intake',
                    'options' => [
                        ['label' => 'None', 'value' => 3],
                        ['label' => 'Recently decreased or poor', 'value' => 2],
                        ['label' => 'No change and adequate', 'value' => 0],
                    ]],
                ['name' => 'Weight and height centiles',
                    'options' => [
                        ['label' => 'Over 3 centile spaces apart', 'value' => 3],
                        ['label' => '2 centile spaces apart', 'value' => 1],
                        ['label' => '0 to 1 centile space apart', 'value' => 0],
                    ]],
            ],
            'score_min' => 0,
            'score_max' => 9,
            'bands' => [
                ['label' => 'Low risk', 'range' => '0 to 1', 'min' => 0, 'max' => 1, 'tone' => self::TONE_LOW],
                ['label' => 'Medium risk', 'range' => '2 to 3', 'min' => 2, 'max' => 3, 'tone' => self::TONE_MODERATE],
                ['label' => 'High risk', 'range' => '4 and above', 'min' => 4, 'max' => null, 'tone' => self::TONE_HIGH],
            ],
            'reference' => 'McCarthy et al., 2008',
            'confirmed' => true,
        ],
        [
            'code' => 'GCS',
            'name' => 'Glasgow Coma Scale',
            'category' => 'Consciousness',
            'population' => 'Adults and older children',
            'purpose' => 'Measures level of consciousness from the best eye, verbal and motor response, to track neurological status and pick up deterioration early.',
            'items' => [
                ['name' => 'Eye opening', 'abbr' => 'E',
                    'options' => [
                        ['label' => 'Spontaneous', 'value' => 4],
                        ['label' => 'To sound or speech', 'value' => 3],
                        ['label' => 'To pressure or pain', 'value' => 2],
                        ['label' => 'None', 'value' => 1],
                    ]],
                ['name' => 'Verbal response', 'abbr' => 'V',
                    'options' => [
                        ['label' => 'Orientated', 'value' => 5],
                        ['label' => 'Confused', 'value' => 4],
                        ['label' => 'Inappropriate words', 'value' => 3],
                        ['label' => 'Incomprehensible sounds', 'value' => 2],
                        ['label' => 'None', 'value' => 1],
                    ]],
                ['name' => 'Motor response', 'abbr' => 'M',
                    'options' => [
                        ['label' => 'Obeys commands', 'value' => 6],
                        ['label' => 'Localises to pain', 'value' => 5],
                        ['label' => 'Normal flexion (withdraws from pain)', 'value' => 4],
                        ['label' => 'Abnormal flexion (decorticate)', 'value' => 3],
                        ['label' => 'Extension (decerebrate)', 'value' => 2],
                        ['label' => 'None', 'value' => 1],
                    ]],
            ],
            'score_min' => 3,
            'score_max' => 15,
            'bands' => [
                ['label' => 'Severe impairment', 'range' => '3 to 8', 'min' => 3, 'max' => 8, 'tone' => self::TONE_HIGH],
                ['label' => 'Moderate impairment', 'range' => '9 to 12', 'min' => 9, 'max' => 12, 'tone' => self::TONE_HIGH],
                ['label' => 'Mild impairment', 'range' => '13 to 14', 'min' => 13, 'max' => 14, 'tone' => self::TONE_MODERATE],
                ['label' => 'Fully conscious', 'range' => '15', 'min' => 15, 'max' => 15, 'tone' => self::TONE_LOW],
            ],
            'reference' => 'Teasdale and Jennett, 1974; Teasdale et al., 2014',
            'note' => 'A lower total means worse consciousness. Read the three responses alongside the total (for example E3 V4 M6), since the same total can hide very different pictures, and treat any fall as a reason for review. A total of 8 or below usually means the airway is at risk. If a response cannot be tested, such as verbal when intubated, the total is not valid and should not be recorded here. Young and preverbal children need the paediatric version.',
            'confirmed' => true,
        ],
        [
            'code' => 'AVPU',
            'name' => 'AVPU Consciousness Scale',
            'category' => 'Consciousness',
            'population' => 'All ages',
            'purpose' => 'Quick bedside check of level of consciousness by the most the patient responds to: awake and alert, only to voice, only to pain, or not at all.',
            'items' => [
                ['name' => 'Level of consciousness',
                    'options' => [
                        ['label' => 'Alert', 'value' => 0],
                        ['label' => 'Responds to voice', 'value' => 1],
                        ['label' => 'Responds to pain', 'value' => 2],
                        ['label' => 'Unresponsive', 'value' => 3],
                    ]],
            ],
            'score_min' => 0,
            'score_max' => 3,
            'bands' => [
                ['label' => 'Alert', 'range' => '0', 'min' => 0, 'max' => 0, 'tone' => self::TONE_LOW],
                ['label' => 'Responds to voice', 'range' => '1', 'min' => 1, 'max' => 1, 'tone' => self::TONE_MODERATE],
                ['label' => 'Responds to pain', 'range' => '2', 'min' => 2, 'max' => 2, 'tone' => self::TONE_HIGH],
                ['label' => 'Unresponsive', 'range' => '3', 'min' => 3, 'max' => 3, 'tone' => self::TONE_HIGH],
            ],
            'reference' => 'Resuscitation Council UK, ABCDE approach; points as scored in MEWS, Subbe et al., 2001',
            'note' => 'Any new drop from Alert needs prompt review: in NEWS2 a new V, P or U scores 3, enough on its own to trigger an urgent response. Responding only to pain is roughly a GCS of 8, where the airway may be at risk. AVPU is coarse, so an alert but newly confused patient still scores A, which is why NEWS2 adds C for new confusion (ACVPU). Use the Glasgow Coma Scale where finer tracking is needed.',
            'confirmed' => true,
        ],
        [
            'code' => 'HEMO',
            'name' => 'Advanced Hemodynamics (Numerics)',
            'category' => 'Hemodynamics',
            'population' => 'Adults in critical care with invasive monitoring: arterial line, central venous catheter or cardiac output monitor',
            'purpose' => 'Records the hemodynamic numerics from the bedside monitor and flags each one against its adult normal range and the level at which the ICU doctor should be told, so falling perfusion pressure or a low-output state is picked up between rounds.',
            // Readings typed in from the monitor, not options: see ClinicalIndicatorReadings.
            // Only what the patient is monitored for is entered; SBP, DBP and MAP go together.
            'groups' => [
                'ABP' => ['label' => 'Invasive arterial pressure', 'format' => '{SBP}/{DBP} ({MAP})'],
            ],
            'items' => [
                ['name' => 'Arterial systolic pressure', 'abbr' => 'SBP', 'group' => 'ABP', 'chart' => 'pressure', 'unit' => 'mmHg', 'decimals' => 0,
                    'limits' => [20, 300], 'normal' => [90, 140], 'escalate_below' => 90, 'escalate_above' => 180],
                ['name' => 'Arterial diastolic pressure', 'abbr' => 'DBP', 'group' => 'ABP', 'chart' => 'pressure', 'unit' => 'mmHg', 'decimals' => 0,
                    'limits' => [10, 200], 'normal' => [60, 90], 'escalate_below' => 40, 'escalate_above' => 120],
                ['name' => 'Mean arterial pressure', 'abbr' => 'MAP', 'group' => 'ABP', 'chart' => 'pressure', 'unit' => 'mmHg', 'decimals' => 0,
                    'limits' => [15, 250], 'normal' => [70, 105], 'escalate_below' => 65, 'trend' => 'line'],
                ['name' => 'Central venous pressure', 'abbr' => 'CVP', 'chart' => 'pressure', 'unit' => 'mmHg', 'decimals' => 0,
                    'limits' => [-10, 40], 'normal' => [2, 8], 'escalate_above' => 15],
                ['name' => 'Cardiac output', 'abbr' => 'CO', 'chart' => 'flow', 'unit' => 'L/min', 'decimals' => 2,
                    'limits' => [0.5, 20], 'normal' => [4.0, 8.0]],
                ['name' => 'Cardiac index', 'abbr' => 'CI', 'chart' => 'flow', 'unit' => 'L/min/m²', 'decimals' => 2,
                    'limits' => [0.3, 10], 'normal' => [2.5, 4.0], 'escalate_below' => 2.2, 'trend' => 'band'],
            ],
            'checks' => [
                ['DBP', '<', 'SBP', 'Diastolic pressure must be lower than systolic.'],
                ['MAP', '>', 'DBP', 'MAP must lie between the diastolic and systolic pressures.'],
                ['MAP', '<', 'SBP', 'MAP must lie between the diastolic and systolic pressures.'],
            ],
            'charts' => [
                'pressure' => 'Arterial and central venous pressure',
                'flow' => 'Cardiac output and index',
            ],
            'notes_example' => 'e.g. on noradrenaline 0.1 mcg/kg/min, PEEP 8, trace damped',
            // The worst reading sets the status (see ClinicalIndicatorReadings), not a total
            'score_min' => 0,
            'score_max' => 2,
            'bands' => [
                ['label' => 'Within normal range', 'range' => 'Every reading inside its normal range', 'min' => 0, 'max' => 0, 'tone' => self::TONE_LOW],
                ['label' => 'Outside normal range', 'range' => 'A reading outside its normal range, none at an escalation level', 'min' => 1, 'max' => 1, 'tone' => self::TONE_MODERATE],
                ['label' => 'Escalate', 'range' => 'A reading at an escalation level: inform the ICU doctor', 'min' => 2, 'max' => 2, 'tone' => self::TONE_HIGH],
            ],
            'reference' => 'Normal ranges: Edwards Lifesciences, Normal Hemodynamic Parameters (adult), with CVP taken as 2 to 8 mmHg. Escalation: MAP 65, Surviving Sepsis Campaign, Evans et al., 2021; SBP 90 and CI 2.2, cardiogenic shock criteria, SHOCK trial (Hochman et al., 1999) and SCAI shock classification, 2019; DBP 40, Hernández, Teboul and Bakker, 2019; SBP 180 and DBP 120, ACC/AHA hypertension guideline, 2017; CVP 15, common ICU practice',
            'note' => 'Copy the values the monitor displays, with the transducer levelled at the phlebostatic axis (4th intercostal space, mid-axillary line) and zeroed, and pressures read at end-expiration. Steer by MAP: below 65 mmHg organ perfusion is at risk, which is why most vasopressor targets start there. CVP alone does not show whether the patient will respond to fluid, so read it as a trend and against PEEP; a sudden rise can mean fluid overload, right ventricular failure, tamponade or tension pneumothorax. CI adjusts cardiac output for body size, so escalation is on CI rather than CO: below 2.2 L/min/m² is a low-output state. These are adult defaults: where the ICU team has set a target for the patient, such as a higher MAP in chronic hypertension, that target applies.',
            'confirmed' => true,
        ],
        [
            'code' => 'VENT',
            'name' => 'Ventilator & Airway Parameters',
            'category' => 'Ventilation',
            'population' => 'Adults on invasive mechanical ventilation through an endotracheal tube or tracheostomy, with continuous capnography',
            'purpose' => 'Records the ventilator settings and airway readings at the bedside and flags each one against its usual adult range and the level at which the ICU doctor should be told, so a rising oxygen or pressure need, CO2 drifting out of range or a change in tidal volume is picked up between rounds.',
            // Readings typed in from the ventilator and capnograph, not options: see ClinicalIndicatorReadings.
            // Only what the patient is on is entered; none of them has to go with another.
            'items' => [
                ['name' => 'End-tidal carbon dioxide', 'abbr' => 'EtCO2', 'chart' => 'gas', 'unit' => 'mmHg', 'decimals' => 0,
                    'limits' => [0, 150], 'normal' => [35, 45], 'escalate_below' => 30, 'escalate_above' => 50, 'trend' => 'band'],
                // FiO2 cannot fall below room air, so its normal range starts at the limit and it is never low
                ['name' => 'Fraction of inspired oxygen', 'abbr' => 'FiO2', 'chart' => 'gas', 'unit' => '%', 'decimals' => 0,
                    'limits' => [21, 100], 'normal' => [21, 40], 'escalate_above' => 60],
                ['name' => 'Positive end-expiratory pressure', 'abbr' => 'PEEP', 'chart' => 'airway', 'unit' => 'cmH₂O', 'decimals' => 0,
                    'limits' => [0, 30], 'normal' => [5, 8]],
                ['name' => 'Peak inspiratory pressure', 'abbr' => 'PIP', 'chart' => 'airway', 'unit' => 'cmH₂O', 'decimals' => 0,
                    'limits' => [0, 80], 'normal' => [10, 30], 'escalate_above' => 35, 'trend' => 'line'],
                ['name' => 'Tidal volume', 'abbr' => 'Vt', 'chart' => 'airway', 'unit' => 'mL', 'decimals' => 0,
                    'limits' => [0, 2000], 'normal' => [300, 500]],
            ],
            'checks' => [
                ['PIP', '>', 'PEEP', 'Peak inspiratory pressure must be higher than PEEP.'],
            ],
            'charts' => [
                'gas' => 'End-tidal CO2 and inspired oxygen',
                'airway' => 'Airway pressures and tidal volume',
            ],
            // The level of support at a glance on the critical care bed cards
            'dashboard' => ['FiO2', 'PEEP'],
            'notes_example' => 'e.g. SIMV-PC rate 14, plateau 26, ETT 7.5 at 22 cm, suctioned',
            // The worst reading sets the status (see ClinicalIndicatorReadings), not a total
            'score_min' => 0,
            'score_max' => 2,
            'bands' => [
                ['label' => 'Within normal range', 'range' => 'Every reading inside its normal range', 'min' => 0, 'max' => 0, 'tone' => self::TONE_LOW],
                ['label' => 'Outside normal range', 'range' => 'A reading outside its normal range, none at an escalation level', 'min' => 1, 'max' => 1, 'tone' => self::TONE_MODERATE],
                ['label' => 'Escalate', 'range' => 'A reading at an escalation level: inform the ICU doctor', 'min' => 2, 'max' => 2, 'tone' => self::TONE_HIGH],
            ],
            'reference' => 'Normal ranges: EtCO2 35 to 45 mmHg, the normal adult range; FiO2 up to 40% and PEEP 5 to 8 cmH₂O, within the oxygenation criteria for readiness to wean, MacIntyre et al., 2001; tidal volume set by predicted body weight with plateau pressure 30 cmH₂O or below, ARDS Network, 2000. Capnography for every intubated patient: NAP4, Cook et al., 2011. Escalation at EtCO2 30 and 50 mmHg, FiO2 60% and PIP 35 cmH₂O: common ICU practice',
            'note' => 'Copy what the ventilator and capnograph display: the set FiO2 and PEEP, the measured peak pressure and the exhaled tidal volume. A sudden fall in EtCO2 or a lost waveform means a displaced, disconnected or blocked tube, or falling cardiac output, until proven otherwise: check the patient and the tube at once. When PIP rises, check the plateau pressure: a high PIP with an unchanged plateau points to the airway (secretions, bronchospasm, a kinked or bitten tube), a rising plateau to stiffer lungs or chest wall (pneumothorax, atelectasis, pulmonary oedema, abdominal distension); keep the plateau at 30 cmH₂O or below. The right tidal volume depends on height and sex, about 6 to 8 mL/kg of predicted body weight, so the mL range here is only a prompt to check it. FiO2 above 40% or PEEP above 8 cmH₂O is more support than a patient ready to wean usually needs, and FiO2 above 60% for long risks oxygen toxicity. These are adult defaults: where the ICU team has set targets for the patient, such as permissive hypercapnia in ARDS or tight CO2 control after brain injury, those targets apply.',
            'confirmed' => true,
        ],
        [
            'code' => 'CSSRS',
            'name' => 'Mental State Assessment (C-SSRS)',
            'category' => 'Mental state',
            'population' => 'Adolescents and adults on medical and surgical wards who can answer for themselves',
            'purpose' => 'Screens for suicidal thoughts and behaviour with the six questions of the Columbia-Suicide Severity Rating Scale (C-SSRS) screener, and gives the response the answers call for: a behavioural health referral at discharge, or a psychiatric consultation with patient safety precautions.',
            // A screen, not a total: each answer's value is the risk it points to, and the most
            // serious answer sets the result. See ClinicalIndicatorScreen.
            'result' => self::RESULT_HIGHEST,
            'form_title' => 'Columbia-Suicide Severity Rating Scale: screen with triage points for medical and surgical inpatients',
            'how_to_ask' => 'Ask the questions in bold, in order and in these words.',
            // Question wording as on the Columbia form. Values: 1 low (yellow), 2 moderate (orange), 3 high (red)
            'items' => [
                ['name' => 'Wish to be dead', 'abbr' => 'Q1', 'period' => 'Past month',
                    'before' => 'Ask questions 1 and 2.',
                    'question' => 'Have you wished you were dead or wished you could go to sleep and not wake up?',
                    'options' => [
                        ['label' => 'No', 'value' => 0],
                        ['label' => 'Yes', 'value' => 1],
                    ]],
                ['name' => 'Suicidal thoughts', 'abbr' => 'Q2', 'period' => 'Past month',
                    'question' => 'Have you actually had any thoughts of killing yourself?',
                    'options' => [
                        ['label' => 'No', 'value' => 0],
                        ['label' => 'Yes', 'value' => 1],
                    ]],
                ['name' => 'Suicidal thoughts with a method', 'abbr' => 'Q3', 'period' => 'Past month',
                    'before' => 'If YES to 2, ask questions 3, 4, 5, and 6. If NO to 2, go directly to question 6.',
                    'asked_when' => ['Q2', 'Yes'],
                    'question' => 'Have you been thinking about how you might do this?',
                    'prompt' => 'E.g. “I thought about taking an overdose but I never made a specific plan as to when where or how I would actually do it... and I would never go through with it.”',
                    'options' => [
                        ['label' => 'No', 'value' => 0],
                        ['label' => 'Yes', 'value' => 2],
                    ]],
                ['name' => 'Suicidal intent', 'abbr' => 'Q4', 'period' => 'Past month',
                    'asked_when' => ['Q2', 'Yes'],
                    'question' => 'Have you had these thoughts and had some intention of acting on them?',
                    'prompt' => 'As opposed to “I have the thoughts but I definitely will not do anything about them.”',
                    'options' => [
                        ['label' => 'No', 'value' => 0],
                        ['label' => 'Yes', 'value' => 3],
                    ]],
                ['name' => 'Suicidal intent with a plan', 'abbr' => 'Q5', 'period' => 'Past month',
                    'asked_when' => ['Q2', 'Yes'],
                    'question' => 'Have you started to work out or worked out the details of how to kill yourself? Did you intend to carry out this plan?',
                    'options' => [
                        ['label' => 'No', 'value' => 0],
                        ['label' => 'Yes', 'value' => 3],
                    ]],
                ['name' => 'Suicidal behaviour', 'abbr' => 'Q6', 'period' => 'Lifetime, then the past 3 months',
                    'question' => 'Have you ever done anything, started to do anything, or prepared to do anything to end your life?',
                    'prompt' => 'Examples: Took pills, tried to shoot yourself, cut yourself, or hang yourself, took out pills but didn’t swallow any, held a gun but changed your mind or it was grabbed from your hand, went to the roof but didn’t jump, collected pills, obtained a gun, gave away valuables, wrote a will or suicide note, etc.',
                    'follow_up' => 'If YES, ask: Was this within the past 3 months?',
                    'options' => [
                        ['label' => 'No', 'value' => 0],
                        ['label' => 'Yes, over 3 months ago', 'short' => 'over 3 months ago', 'value' => 2],
                        ['label' => 'Yes, within the past 3 months', 'short' => 'within the past 3 months', 'value' => 3],
                    ]],
            ],
            'notes_example' => 'e.g. who was informed and which precautions were started',
            // The most serious answer sets the risk (see ClinicalIndicatorScreen), not a total
            'score_min' => 0,
            'score_max' => 3,
            'bands' => [
                ['label' => 'No risk identified', 'range' => 'No to every question asked', 'min' => 0, 'max' => 0, 'tone' => self::TONE_LOW,
                    'action' => 'Nothing to act on from this screen. Screen again whenever there is concern.'],
                ['label' => 'Low risk', 'range' => 'Yes to question 1 or 2', 'min' => 1, 'max' => 1, 'tone' => self::TONE_MODERATE,
                    'action' => 'Behavioural health referral at discharge.'],
                ['label' => 'Moderate risk', 'range' => 'Yes to question 3, or to 6 over 3 months ago', 'min' => 2, 'max' => 2, 'tone' => self::TONE_MODERATE,
                    'action' => 'Behavioural health referral at discharge.'],
                ['label' => 'High risk', 'range' => 'Yes to question 4 or 5, or to 6 within the past 3 months', 'min' => 3, 'max' => 3, 'tone' => self::TONE_HIGH,
                    'action' => 'Psychiatric consultation and patient safety precautions.'],
            ],
            'reference' => 'Posner et al., 2011; C-SSRS Screen with Triage Points for Medical/Surgery Inpatient, The Columbia Lighthouse Project, 2026',
            'note' => 'Questions 1 to 5 ask about the past month and question 6 about the patient’s lifetime. The most serious answer sets the risk, and the response follows Columbia’s triage for medical and surgical inpatients: confirm it against your hospital’s suicide risk policy, which sets the patient safety precautions. For repeat screens in the same stay, Columbia’s Since Last Asked and frequent-monitoring screeners ask about the time since the last screen instead. Children and patients with cognitive impairment have their own C-SSRS versions. A screen supports clinical judgement and does not replace it.',
            'confirmed' => true,
        ],

        // --- Awaiting confirmation of the local variant ---------------------
        // These exist so ward types can already be bound to them, but the
        // detail is left blank rather than guessed at, because each has more
        // than one version in common use and the wrong one would be worse
        // than none.
        [
            'code' => 'EWS',
            'name' => 'Early Warning Score',
            'category' => 'Deterioration',
            'population' => 'Inpatients',
            'purpose' => 'Aggregate score from routine observations used to flag clinical deterioration and trigger escalation.',
            'items' => [],
            'score_min' => null,
            'score_max' => null,
            'bands' => [],
            'reference' => null,
            'note' => 'Version deliberately left open. It only starts to matter once the score is wired to the ward dashboard, and PHKL wards sit under no ward type for now, so nothing depends on it yet. NEWS2, MEWS and PEWS differ in both parameters and escalation triggers, so the choice is made then rather than guessed at now.',
            'confirmed' => false,
        ],
        [
            'code' => 'FRAI',
            'name' => 'FRAIL Scale',
            'category' => 'Frailty',
            'population' => 'Older adults',
            'purpose' => 'Five-question screen for frailty, used to flag patients who need comprehensive geriatric assessment and earlier discharge planning.',
            'items' => [
                ['name' => 'Fatigue',
                    'options' => [
                        ['label' => 'Tired most or all of the time in the last 4 weeks', 'value' => 1],
                        ['label' => 'Not fatigued', 'value' => 0],
                    ]],
                ['name' => 'Resistance',
                    'options' => [
                        ['label' => 'Unable to climb a flight of stairs without help or aids', 'value' => 1],
                        ['label' => 'Able to climb a flight of stairs', 'value' => 0],
                    ]],
                ['name' => 'Ambulation',
                    'options' => [
                        ['label' => 'Unable to walk one block without help or aids', 'value' => 1],
                        ['label' => 'Able to walk one block', 'value' => 0],
                    ]],
                ['name' => 'Illnesses',
                    'options' => [
                        ['label' => 'Five or more of the eleven listed illnesses', 'value' => 1],
                        ['label' => 'Fewer than five', 'value' => 0],
                    ]],
                ['name' => 'Loss of weight',
                    'options' => [
                        ['label' => 'More than 5 percent lost in the past year', 'value' => 1],
                        ['label' => '5 percent or less', 'value' => 0],
                    ]],
            ],
            'score_min' => 0,
            'score_max' => 5,
            'bands' => [
                ['label' => 'Robust', 'range' => '0', 'min' => 0, 'max' => 0, 'tone' => self::TONE_LOW],
                ['label' => 'Pre-frail', 'range' => '1 to 2', 'min' => 1, 'max' => 2, 'tone' => self::TONE_MODERATE],
                ['label' => 'Frail', 'range' => '3 to 5', 'min' => 3, 'max' => null, 'tone' => self::TONE_HIGH],
            ],
            'reference' => 'Morley et al., 2012',
            'confirmed' => true,
        ],
        [
            'code' => 'FARNEO',
            'name' => 'FARNeo Neonatal Fall and Drop Risk',
            'category' => 'Fall risk',
            'population' => 'Neonates on the neonatal unit and postnatal ward',
            'purpose' => 'Assesses the risk of a neonatal fall or infant drop, typically where a parent may fall asleep while holding or feeding the baby.',
            'items' => [],
            'score_min' => null,
            'score_max' => null,
            'bands' => [],
            'reference' => null,
            'note' => 'Confirmed as a neonatal fall and drop risk assessment, but it has no single published definition. Send the form and the items, their points, the total range and the risk bands will be filled in here.',
            'confirmed' => false,
        ],
        [
            'code' => 'PAIN',
            'name' => 'Pain Score',
            'category' => 'Pain',
            'population' => 'All ages, using the tool that suits the patient',
            'purpose' => 'Pain intensity recorded with the observations. Which tool is used depends on the age of the patient and whether they can self-report, but all of them give a score out of 10 so the numbers stay comparable.',
            'items' => [
                ['name' => 'Adults and older children who can self-report', 'scoring' => 'Numeric Rating Scale: the patient states a number from 0 (no pain) to 10 (worst imaginable)'],
                ['name' => 'Children roughly 3 years and over', 'scoring' => 'Wong-Baker FACES: six faces scored 0, 2, 4, 6, 8, 10 from No hurt to Hurts worst'],
                ['name' => 'Preverbal or non-verbal patients', 'scoring' => 'FLACC observed behaviour, 0 to 10. Recorded under the FLACC indicator where a ward tracks it separately'],
            ],
            'score_min' => 0,
            'score_max' => 10,
            'bands' => [
                ['label' => 'No pain', 'range' => '0', 'min' => 0, 'max' => 0, 'tone' => self::TONE_LOW],
                ['label' => 'Mild', 'range' => '1 to 3', 'min' => 1, 'max' => 3, 'tone' => self::TONE_LOW],
                ['label' => 'Moderate', 'range' => '4 to 6', 'min' => 4, 'max' => 6, 'tone' => self::TONE_MODERATE],
                ['label' => 'Severe', 'range' => '7 to 10', 'min' => 7, 'max' => null, 'tone' => self::TONE_HIGH],
            ],
            'reference' => 'Wong-Baker FACES Foundation; numeric rating scale, standard practice',
            'note' => 'All three tools map onto the same 0 to 10 total, so a ward can switch tool by patient without breaking comparison.',
            'confirmed' => true,
        ],
    ];

    /**
     * @return array<string, array<string, mixed>> keyed by code
     */
    public static function all(): array
    {
        return array_column(self::INDICATORS, null, 'code');
    }

    public static function find(?string $code): ?array
    {
        if ($code === null) {
            return null;
        }

        return self::all()[strtoupper(trim($code))] ?? null;
    }

    public static function has(?string $code): bool
    {
        return self::find($code) !== null;
    }

    /**
     * INDICATORS in the order they are listed: category by category, keeping
     * the order they are written in within each.
     */
    public static function ordered(): array
    {
        $indicators = self::INDICATORS;

        usort($indicators, fn ($a, $b) => self::categoryRank($a['category']) <=> self::categoryRank($b['category']));

        return $indicators;
    }

    /**
     * Clinical indicator rows grouped under their categories, in CATEGORIES
     * order, each group keeping the order the rows came in.
     *
     * @param  Collection<int, ClinicalIndicator>  $indicators
     * @return Collection<string, Collection<int, ClinicalIndicator>> keyed by category
     */
    public static function groupByCategory(Collection $indicators): Collection
    {
        return $indicators
            ->groupBy(fn (ClinicalIndicator $indicator) => $indicator->category())
            ->sortBy(fn ($group, string $category) => self::categoryRank($category));
    }

    /**
     * A category missing from CATEGORIES, most likely a typo, is still listed
     * rather than lost: after the known ones, ahead of Other.
     */
    private static function categoryRank(string $category): float
    {
        $rank = array_search($category, array_keys(self::CATEGORIES), true);

        return $rank === false ? count(self::CATEGORIES) - 1.5 : $rank;
    }

    /**
     * Whether the scale can be scored item by item into a total, which needs
     * every item to carry options. False for Pain Score, whose items name
     * which tool to use, for the scales still awaiting their local variant,
     * and for screens, whose answers are not added up (see isScreen()).
     */
    public static function isScorable(?array $definition): bool
    {
        return self::everyItemHasOptions($definition) && !self::isScreen($definition);
    }

    /**
     * Whether the scale is a screen (the C-SSRS): asked question by question,
     * some only after a given answer to an earlier one, with the most serious
     * answer setting the result. See ClinicalIndicatorScreen.
     */
    public static function isScreen(?array $definition): bool
    {
        return ($definition['result'] ?? null) === self::RESULT_HIGHEST && self::everyItemHasOptions($definition);
    }

    private static function everyItemHasOptions(?array $definition): bool
    {
        if (empty($definition['items'])) {
            return false;
        }

        foreach ($definition['items'] as $item) {
            if (empty($item['options'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether the scale is recorded as readings typed in from the monitor
     * (the hemodynamic numerics), which needs every item to carry a unit and
     * a normal range. See ClinicalIndicatorReadings.
     */
    public static function takesReadings(?array $definition): bool
    {
        if (empty($definition['items'])) {
            return false;
        }

        foreach ($definition['items'] as $item) {
            if (!isset($item['unit'], $item['normal'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * The scoring rule for one item as readable text, derived from its options
     * so the reference panel and the scoring form cannot disagree. A reading
     * gives its normal range and escalation levels instead.
     */
    public static function itemScoringText(array $item): string
    {
        if (isset($item['unit'], $item['normal'])) {
            return ClinicalIndicatorReadings::rangeText($item);
        }

        if (empty($item['options'])) {
            return $item['scoring'] ?? '';
        }

        return collect($item['options'])
            ->map(fn ($option) => $option['label'] . ' = ' . $option['value'])
            ->implode(', ');
    }

    /**
     * The risk band a total falls into, or null when the scale has no bands
     * recorded (the ones still awaiting confirmation).
     *
     * A band with a null min or max is open-ended on that side, which is how
     * "45 and above" and Braden's "9 and below" are expressed.
     */
    public static function bandFor(?string $code, ?int $score): ?array
    {
        $definition = self::find($code);

        if ($definition === null || $score === null || empty($definition['bands'])) {
            return null;
        }

        foreach ($definition['bands'] as $band) {
            $aboveFloor = $band['min'] === null || $score >= $band['min'];
            $belowCeiling = $band['max'] === null || $score <= $band['max'];

            if ($aboveFloor && $belowCeiling) {
                return $band;
            }
        }

        return null;
    }

    /**
     * Upsert the library into the clinical_indicators table, matching on code.
     * Ids are preserved, so existing ward-type links survive untouched.
     *
     * sort_order follows ordered(), so every list sorted by it, such as a ward
     * type's indicators, comes out grouped by category.
     *
     * @return array{created: int, updated: int}
     */
    public static function sync(): array
    {
        $created = 0;
        $updated = 0;

        foreach (self::ordered() as $index => $definition) {
            $existing = ClinicalIndicator::where('code', $definition['code'])->first();

            $attributes = [
                'name' => $definition['name'],
                'description' => self::summarise($definition),
                'sort_order' => $index + 1,
            ];

            if ($existing) {
                $existing->update($attributes);
                $updated++;
                continue;
            }

            ClinicalIndicator::create($attributes + [
                'code' => $definition['code'],
                'is_active' => true,
            ]);
            $created++;
        }

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * The one-line summary stored on the row, so anything reading the table
     * directly still gets something meaningful.
     */
    private static function summarise(array $definition): string
    {
        $parts = array_filter([
            $definition['category'] ?? null,
            $definition['population'] ?? null,
        ]);

        $summary = implode(' - ', $parts);

        if (self::takesReadings($definition)) {
            $summary .= ' (readings: ' . implode(', ', array_column($definition['items'], 'abbr')) . ')';
        } elseif (self::isScreen($definition)) {
            $summary .= ' (screen: ' . count($definition['items']) . ' questions)';
        } elseif ($definition['score_min'] !== null && $definition['score_max'] !== null) {
            $summary .= ' (score ' . $definition['score_min'] . ' to ' . $definition['score_max'] . ')';
        }

        return mb_substr($summary, 0, 255);
    }
}
