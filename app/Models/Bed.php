<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Bed extends Model
{
    use HasFactory;

    protected $fillable = [
        'ward_id',
        'bed_number',
        'bed_id',
        'bed_display_name',
        'status',
        'nurse_id',
        'anaesthetist_id',
        'patient_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }

    public function nurse()
    {
        return $this->belongsTo(Nurse::class);
    }

    public function anaesthetist()
    {
        return $this->belongsTo(Anaesthetist::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function consultants()
    {
        return $this->belongsToMany(Consultant::class, 'bed_consultant');
    }
}
