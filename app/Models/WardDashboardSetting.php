<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WardDashboardSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'patient_details_tabs',
        'bed_box_display',
        'patient_info_display',
        'clinical_indicator_options',
        'dashboard_display',
        'clinical_settings',
    ];

    protected $casts = [
        'patient_details_tabs' => 'array',
        'bed_box_display' => 'array',
        'patient_info_display' => 'array',
        'clinical_indicator_options' => 'array',
        'dashboard_display' => 'array',
        'clinical_settings' => 'array',
    ];
}


