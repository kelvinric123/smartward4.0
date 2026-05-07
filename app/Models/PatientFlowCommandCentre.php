<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PatientFlowCommandCentre extends Model
{
    use HasFactory;

    protected $table = 'patient_flow_command_centres';

    protected $fillable = [
        'name',
        'description',
        'login_username',
        'login_password',
        'settings',
        'is_active',
    ];

    protected $hidden = [
        'login_password',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    /**
     * Get a setting value with a default fallback.
     */
    public function getSetting(string $key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Default settings for new command centres.
     */
    public static function defaultSettings(): array
    {
        return [
            'show_bed_details' => true,
            'show_summary_cards' => true,
            'show_patient_name' => false,
            'show_consultant' => true,
            'show_admitting_section' => true,
            'show_pending_discharge_section' => true,
            'show_discharged_section' => true,
            'show_prebooked_section' => true,
            'refresh_interval' => 30,
        ];
    }

    /**
     * Get the wards assigned to this command centre.
     */
    public function wards()
    {
        return $this->belongsToMany(Ward::class, 'command_centre_ward', 'command_centre_id', 'ward_id')
                    ->withTimestamps();
    }
}
