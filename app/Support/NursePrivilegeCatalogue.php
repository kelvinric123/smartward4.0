<?php

namespace App\Support;

use App\Models\NurseCredential;
use Illuminate\Support\Str;

/**
 * The built-in list of nursing privileges, ticked on the Credentialing and
 * Privileging tab of the nurse edit page, by field. Each one is Core (any
 * registered nurse once assessed as competent) or Advanced (extra training
 * and a competency sign-off), and some need a certificate on the nurse's
 * file. The keys are stored on nurse_privileges.code, so a name can be
 * reworded here without losing the nurses who hold it.
 */
class NursePrivilegeCatalogue
{
    /**
     * Certificates a privilege can need, and how to recognise one among the
     * nurse's credentials: any credential but the APC whose title matches.
     * "needs" is how a missing one is asked for.
     */
    public const CERTIFICATES = [
        'bls' => ['label' => 'BLS', 'pattern' => '/\bBLS\b|basic life support/i'],
        'acls' => ['label' => 'ACLS', 'pattern' => '/\bACLS\b|\bALS\b|advanced cardiac life support|adult advanced life support|^advanced life support/i'],
        'pals' => ['label' => 'PALS', 'pattern' => '/\bPALS\b|\bAPLS\b|pa?ediatric advanced life support/i'],
        'nrp' => ['label' => 'NRP', 'pattern' => '/\bNRP\b|neonatal resuscitation/i'],
        'critical_care' => ['label' => 'Post Basic Critical Care', 'pattern' => '/critical care|intensive care/i'],
        'oncology' => ['label' => 'Post Basic Oncology', 'pattern' => '/oncolog|chemotherap/i'],
        'renal' => ['label' => 'Post Basic Renal', 'pattern' => '/renal|nephrolog|dialysis/i'],
        'perioperative' => ['label' => 'Post Basic Perioperative', 'pattern' => '/peri-?operative|operating theatre/i'],
        'anaesthetic' => ['label' => 'Post Basic Anaesthetic', 'pattern' => '/ana?esthe|\bCRNA\b/i'],
        'midwifery' => [
            'label' => 'Midwifery',
            'needs' => 'Registered Midwife or Post Basic Midwifery',
            'pattern' => '/midwi/i',
        ],
    ];

    /**
     * The fields, in the order they are shown. A section's "requires" applies
     * to every privilege in it; "specialty" sections start folded unless the
     * nurse works in that field.
     */
    public const SECTIONS = [
        'resuscitation' => [
            'label' => 'Resuscitation',
            'items' => [
                'adult_bls' => ['name' => 'Adult basic life support (CPR and AED)', 'level' => 'core', 'requires' => 'bls'],
                'airway_bvm' => ['name' => 'Airway adjuncts and bag-valve-mask ventilation', 'level' => 'core'],
                'adult_als' => ['name' => 'Adult advanced life support', 'level' => 'advanced', 'requires' => 'acls'],
                'defibrillation' => ['name' => 'Manual defibrillation and cardioversion', 'level' => 'advanced', 'requires' => 'acls'],
                'resus_leader' => ['name' => 'Resuscitation team leader', 'level' => 'advanced', 'requires' => 'acls'],
                'paediatric_als' => ['name' => 'Paediatric advanced life support', 'level' => 'advanced', 'requires' => 'pals'],
                'neonatal_resus' => ['name' => 'Neonatal resuscitation', 'level' => 'advanced', 'requires' => 'nrp'],
                'emergency_triage' => ['name' => 'Emergency triage', 'level' => 'advanced'],
            ],
        ],
        'medication' => [
            'label' => 'Medication administration',
            'items' => [
                'med_oral' => ['name' => 'Oral, sublingual and enteral tube medications', 'level' => 'core'],
                'med_topical' => ['name' => 'Topical, eye, ear and inhaled medications', 'level' => 'core'],
                'med_injections' => ['name' => 'Subcutaneous and intramuscular injections', 'level' => 'core'],
                'med_iv' => ['name' => 'Intravenous medications (bolus and infusion)', 'level' => 'core'],
                'transfusion' => ['name' => 'Blood and blood product transfusion', 'level' => 'advanced'],
                'high_alert' => ['name' => 'High-alert infusions (heparin, insulin, potassium)', 'level' => 'advanced'],
                'vasopressors' => ['name' => 'Starting and titrating vasopressor infusions (ICU)', 'level' => 'advanced', 'requires' => 'critical_care'],
                'sedation' => ['name' => 'Starting and titrating sedation infusions (ICU)', 'level' => 'advanced', 'requires' => 'critical_care'],
                'chemotherapy' => ['name' => 'Chemotherapy administration', 'level' => 'advanced', 'requires' => 'oncology'],
                'epidural_pca' => ['name' => 'Epidural and PCA monitoring', 'level' => 'advanced'],
                'prescribing' => ['name' => 'Independent prescribing', 'level' => 'advanced', 'hint' => 'Only where local law allows it'],
            ],
        ],
        'procedures' => [
            'label' => 'Clinical procedures',
            'items' => [
                'piv' => ['name' => 'Peripheral IV cannulation', 'level' => 'core'],
                'venepuncture' => ['name' => 'Venepuncture and blood sampling', 'level' => 'core'],
                'catheter_female' => ['name' => 'Urinary catheterisation (female)', 'level' => 'core'],
                'wound_care' => ['name' => 'Wound care and dressing', 'level' => 'core'],
                'ng_tube' => ['name' => 'Nasogastric tube insertion', 'level' => 'core'],
                'ecg' => ['name' => 'ECG recording', 'level' => 'core'],
                'glucose' => ['name' => 'Blood glucose monitoring', 'level' => 'core'],
                'oxygen' => ['name' => 'Oxygen therapy', 'level' => 'core'],
                'suctioning' => ['name' => 'Oral and nasal suctioning', 'level' => 'core'],
                'catheter_male' => ['name' => 'Urinary catheterisation (male)', 'level' => 'advanced'],
                'intubation' => ['name' => 'Endotracheal intubation', 'level' => 'advanced'],
                'cvc_insertion' => ['name' => 'Central venous catheter insertion', 'level' => 'advanced'],
                'cvc_care' => ['name' => 'Central venous catheter care', 'level' => 'advanced'],
                'abg' => ['name' => 'Arterial blood gas sampling', 'level' => 'advanced'],
                'suturing' => ['name' => 'Suturing of minor wounds', 'level' => 'advanced'],
                'chest_drain' => ['name' => 'Chest drain management', 'level' => 'advanced'],
                'tracheostomy' => ['name' => 'Tracheostomy care', 'level' => 'advanced'],
                'ventilation' => ['name' => 'Mechanical ventilation care', 'level' => 'advanced'],
                'haemodialysis' => ['name' => 'Haemodialysis', 'level' => 'advanced', 'requires' => 'renal'],
                'peritoneal_dialysis' => ['name' => 'Peritoneal dialysis', 'level' => 'advanced', 'requires' => 'renal'],
            ],
        ],
        'perioperative' => [
            'label' => 'Perioperative (scrub and circulating)',
            'specialty' => true,
            'requires' => 'perioperative',
            'items' => [
                'circulating' => ['name' => 'Circulating nurse', 'level' => 'core'],
                'scrub' => ['name' => 'Scrub nurse', 'level' => 'core'],
                'surgical_counts' => ['name' => 'Surgical counts and specimen handling', 'level' => 'core'],
                'positioning' => ['name' => 'Patient positioning and diathermy safety', 'level' => 'core'],
                'first_assistant' => ['name' => 'Advanced surgical first assistant', 'level' => 'advanced'],
            ],
        ],
        'anaesthesia' => [
            'label' => 'Anaesthesia (nurse anaesthetist)',
            'specialty' => true,
            'requires' => 'anaesthetic',
            'items' => [
                'anaesthesia_assist' => ['name' => 'Assisting the anaesthetist at induction and emergence', 'level' => 'core'],
                'recovery' => ['name' => 'Recovery room (PACU) care', 'level' => 'core'],
                'sedation_monitoring' => ['name' => 'Procedural sedation monitoring', 'level' => 'core'],
                'lma' => ['name' => 'Laryngeal mask airway insertion', 'level' => 'advanced'],
                'crna' => ['name' => 'Giving anaesthesia as a nurse anaesthetist (CRNA)', 'level' => 'advanced', 'hint' => 'Only where local law allows it'],
            ],
        ],
        'midwifery' => [
            'label' => 'Midwifery and obstetrics',
            'specialty' => true,
            'requires' => 'midwifery',
            'items' => [
                'antenatal' => ['name' => 'Antenatal assessment', 'level' => 'core'],
                'ctg' => ['name' => 'CTG monitoring and interpretation', 'level' => 'core'],
                'postnatal' => ['name' => 'Postnatal care of mother and baby', 'level' => 'core'],
                'breastfeeding' => ['name' => 'Breastfeeding support', 'level' => 'core'],
                'normal_delivery' => ['name' => 'Conducting a normal vaginal delivery', 'level' => 'advanced'],
                'labour_examination' => ['name' => 'Vaginal examination in labour', 'level' => 'advanced'],
                'perineal_repair' => ['name' => 'Episiotomy and perineal repair', 'level' => 'advanced'],
                'oxytocin' => ['name' => 'Oxytocin infusion for induction and augmentation', 'level' => 'advanced'],
            ],
        ],
    ];

    /**
     * Every privilege by code, with its section, level, hint and the
     * certificate it needs (its own, else its section's).
     *
     * @return array<string, array{code: string, section: string, name: string, level: string, hint: ?string, requires: ?string}>
     */
    public static function items(): array
    {
        static $items = null;

        if ($items === null) {
            $items = [];
            foreach (self::SECTIONS as $section => $meta) {
                foreach ($meta['items'] as $code => $item) {
                    $items[$code] = [
                        'code' => $code,
                        'section' => $section,
                        'name' => $item['name'],
                        'level' => $item['level'],
                        'hint' => $item['hint'] ?? null,
                        'requires' => $item['requires'] ?? $meta['requires'] ?? null,
                    ];
                }
            }
        }

        return $items;
    }

    public static function item(?string $code): ?array
    {
        return $code === null ? null : (self::items()[$code] ?? null);
    }

    /**
     * The code of a privilege named as the list names it, whatever the case
     * or spacing, or null for a privilege of the hospital's own.
     */
    public static function codeForName(string $name): ?string
    {
        static $codes = null;
        $codes ??= collect(self::items())->mapWithKeys(fn (array $item) => [Str::lower($item['name']) => $item['code']])->all();

        return $codes[Str::lower(trim(preg_replace('/\s+/', ' ', $name)))] ?? null;
    }

    /**
     * Whether a credential on file is the certificate $certificate asks for.
     */
    public static function satisfies(string $certificate, NurseCredential $credential): bool
    {
        $pattern = self::CERTIFICATES[$certificate]['pattern'] ?? null;

        return $pattern !== null
            && $credential->type !== 'apc'
            && preg_match($pattern, (string) $credential->title) === 1;
    }

    public static function needs(string $certificate): string
    {
        $meta = self::CERTIFICATES[$certificate];

        return $meta['needs'] ?? $meta['label'];
    }
}
