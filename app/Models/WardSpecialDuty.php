<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WardSpecialDuty extends Model
{
    protected $fillable = [
        'ward_id',
        'nurse_id',
        'date',
        'shift',
        'duty_type',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }

    public function nurse()
    {
        return $this->belongsTo(Nurse::class);
    }
}
