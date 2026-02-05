<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Nurse extends Model
{
    const DESIGNATIONS = [
        'Nurse Manager',
        'Nurse Clinician',
        'Assistant Nurse Clinician',
        'SENIOR STAFF NURSE II',
        'STAFF NURSE I',
        'STAFF NURSE II',
        'GRADUATE NURSE',
        'Student nurse',
        'Health Care Assistant',
        'Patient Care Assistant',
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
        'is_tagging',
        'ward_id',
    ];

    public static function getDesignations(): array
    {
        return self::DESIGNATIONS;
    }

    protected $casts = [
        'is_active' => 'boolean',
        'is_tagging' => 'boolean',
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

    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }

    /**
     * The nurses that this nurse is tagging to (many-to-many)
     */
    public function taggingNurses()
    {
        return $this->belongsToMany(Nurse::class, 'nurse_tagging_nurses', 'nurse_id', 'tagging_nurse_id')
            ->withTimestamps();
    }

    /**
     * The nurses that are tagging to this nurse (inverse many-to-many)
     */
    public function taggedByNurses()
    {
        return $this->belongsToMany(Nurse::class, 'nurse_tagging_nurses', 'tagging_nurse_id', 'nurse_id')
            ->withTimestamps();
    }
}
