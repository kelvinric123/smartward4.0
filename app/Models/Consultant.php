<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consultant extends Model
{
    protected $fillable = [
        'personnel_code',
        'name',
        'specialty_id',
        'registration_number',
        'phone',
        'email',
        'qualifications',
        'years_of_experience',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'years_of_experience' => 'integer',
    ];

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    /**
     * Find consultant by personnel code (ADT code)
     */
    public static function findByPersonnelCode(string $code): ?self
    {
        return static::where('personnel_code', $code)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Check if consultant is an anaesthetist based on specialty
     */
    public function isAnaesthetist(): bool
    {
        if (!$this->specialty) {
            return false;
        }
        
        $anaesthetistKeywords = ['anaesth', 'anesth', 'anesthesi'];
        $specialtyName = strtolower($this->specialty->name ?? '');
        $specialtyCode = strtolower($this->specialty->code ?? '');
        
        foreach ($anaesthetistKeywords as $keyword) {
            if (str_contains($specialtyName, $keyword) || str_contains($specialtyCode, $keyword)) {
                return true;
            }
        }
        
        return false;
    }
}
