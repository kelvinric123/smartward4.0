<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A public holiday, hospital-wide. The AI Nurse Schedule marks it on the
 * roster and shares holiday duty out fairly between nurses.
 */
class PublicHoliday extends Model
{
    protected $fillable = [
        'holiday_date',
        'name',
    ];

    protected $casts = [
        'holiday_date' => 'date',
    ];
}
