<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Nurse extends Model
{
    const DESIGNATIONS = [
        'Nurse Manager',
        'Assistant Nurse Clinician',
        'SENIOR STAFF NURSE II',
        'STAFF NURSE I',
        'STAFF NURSE II',
        'GRADUATE NURSE',
        'Health Care Assistant',
    ];

    const DEFAULT_DESIGNATION = 'STAFF NURSE I';

    protected $fillable = [
        'personnel_code',
        'user_id',
        'name',
        'registration_number',
        'phone',
        'email',
        'department',
        'designation',
        'qualification',
        'years_of_experience',
        'is_active',
    ];

    public static function getDesignations(): array
    {
        return self::DESIGNATIONS;
    }

    protected $casts = [
        'is_active' => 'boolean',
        'years_of_experience' => 'integer',
    ];

    /**
     * Find nurse by personnel code (ADT code)
     */
    public static function findByPersonnelCode(string $code): ?self
    {
        return static::where('personnel_code', $code)
            ->where('is_active', true)
            ->first();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
