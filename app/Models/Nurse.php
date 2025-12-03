<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Nurse extends Model
{
    protected $fillable = [
        'name',
        'registration_number',
        'phone',
        'email',
        'qualification',
        'years_of_experience',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'years_of_experience' => 'integer',
    ];
}
