<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A SEEKINK template's fields, as edited on /ekad: each field the template
 * was designed with (its name there, e.g. "bed no") and what SmartWard puts
 * in it. Templates without a row get DEFAULT_FIELDS.
 *
 * Its isolation templates are the SEEKINK templates its screens are painted
 * with instead for a patient with an isolation precaution, each one this
 * template's layout with that precaution's picture: Isolation Types code =>
 * template ID. They are sent this template's fields.
 */
class EkadTemplate extends Model
{
    protected $fillable = [
        'template_id',
        'name',
        'fields',
        'isolation_templates',
    ];

    protected $casts = [
        'fields' => 'array',
        'isolation_templates' => 'array',
    ];

    /**
     * What SmartWard can put in a field. An empty bed shows its bed number,
     * ward and fixed texts, "Vacant" for the MRN and "-" for the rest.
     */
    public const SOURCES = [
        'bed_number' => 'Bed number',
        'mrn' => 'MRN',
        'patient_name' => 'Patient name',
        'diet' => 'Diet',
        'doctor' => 'Doctor',
        'nurse' => 'Nurse on shift',
        'anaesthetist' => 'Anaesthetist',
        'isolation' => 'Isolation',
        'allergies' => 'Allergies',
        'ward' => 'Ward',
        'rn' => 'RN (visit number)',
        'gender' => 'Gender',
        'age' => 'Age',
        'admitted_at' => 'Admission date',
        'text' => 'Fixed text',
    ];

    /** The fields every template had before templates could be edited */
    public const DEFAULT_FIELDS = [
        ['key' => 'bed no', 'source' => 'bed_number'],
        ['key' => 'MRN', 'source' => 'mrn'],
        ['key' => 'patient_name', 'source' => 'patient_name'],
        ['key' => 'diet_type', 'source' => 'diet'],
        ['key' => 'doctor', 'source' => 'doctor'],
        ['key' => 'nurse', 'source' => 'nurse'],
        ['key' => 'anaesthetist', 'source' => 'anaesthetist'],
        ['key' => 'isolation_type', 'source' => 'isolation'],
    ];

    /**
     * The fields of a SEEKINK template: its own when saved, else the default ones
     */
    public static function fieldsFor(?string $templateId): array
    {
        $fields = $templateId ? static::where('template_id', $templateId)->first()?->fields : null;

        return $fields ?: self::DEFAULT_FIELDS;
    }

    /**
     * The template a screen on $templateId is painted with for a patient with
     * this isolation precaution (an Isolation Types code): its isolation
     * template for that precaution, else $templateId itself
     */
    public static function forIsolation(string $templateId, ?string $isolationCode): string
    {
        if ($isolationCode === null || in_array(strtolower(trim($isolationCode)), ['', 'none'], true)) {
            return $templateId;
        }

        $isolationTemplates = array_change_key_case(
            static::where('template_id', $templateId)->first()?->isolation_templates ?? [],
            CASE_UPPER
        );

        return $isolationTemplates[strtoupper(trim($isolationCode))] ?? $templateId;
    }
}
