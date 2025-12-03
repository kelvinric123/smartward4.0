<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consultant extends Model
{
    protected $fillable = [
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
}
