<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Anaesthetist extends Model
{
    protected $fillable = [
        'personnel_code',
        'name',
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

    /**
     * Find anaesthetist by personnel code (ADT code)
     */
    public static function findByPersonnelCode(string $code): ?self
    {
        return static::where('personnel_code', $code)
            ->where('is_active', true)
            ->first();
    }
}
