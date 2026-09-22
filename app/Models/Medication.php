<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Formulary entry: a medication the "Add medication" form can pick, with the
 * dose, route and frequency it is usually ordered at. Picking one only fills
 * the form in; the nurse can change any of it for the actual order.
 */
class Medication extends Model
{
    protected $fillable = [
        'name',
        'category',
        'default_dose',
        'dose_unit',
        'default_route',
        'default_frequency',
        'default_interval_hours',
        'is_high_alert',
        'caution',
        'is_active',
    ];

    protected $casts = [
        'default_dose' => 'decimal:3',
        'default_interval_hours' => 'decimal:1',
        'is_high_alert' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * The usual order, e.g. "1 g PO QID".
     */
    public function defaultsLabel(): string
    {
        return trim(implode(' ', array_filter([
            $this->default_dose !== null ? PatientMedication::formatAmount($this->default_dose) . ' ' . $this->dose_unit : null,
            $this->default_route,
            PatientMedication::FREQUENCIES[$this->default_frequency]['short'] ?? null,
        ])));
    }
}
