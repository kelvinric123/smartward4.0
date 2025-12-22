<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $ward_id
 * @property int $bed_id
 * @property int $nurse_id
 * @property \Illuminate\Support\Carbon $scheduled_date
 * @property string $shift
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Ward $ward
 * @property-read \App\Models\Bed $bed
 * @property-read \App\Models\Nurse $nurse
 */
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
















