<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WardScheduleAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'ward_id',
        'bed_id',
        'nurse_id',
        'scheduled_date',
        'shift',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
    ];

    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }

    public function bed()
    {
        return $this->belongsTo(Bed::class);
    }

    public function nurse()
    {
        return $this->belongsTo(Nurse::class);
    }
}


