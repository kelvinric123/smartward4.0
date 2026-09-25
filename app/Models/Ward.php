<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Ward extends Model
{
    use HasFactory;

    protected $fillable = [
        'hospital_id',
        'ward_code',
        'ward_name',
        'ward_type_id',
        'capacity',
        'specialties',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function hospital()
    {
        return $this->belongsTo(Hospital::class);
    }

    public function wardType()
    {
        return $this->belongsTo(WardType::class);
    }

    public function beds()
    {
        return $this->hasMany(Bed::class);
    }

    public function patients()
    {
        return $this->hasMany(Patient::class);
    }

    public function slideshows()
    {
        return $this->hasMany(Slideshow::class);
    }

    /**
     * Wards whose ward type is marked critical care, the ones listed on the
     * Critical Care Ward Dashboard.
     */
    public function scopeCriticalCare(Builder $query): Builder
    {
        return $query->whereHas('wardType', fn (Builder $type) => $type->criticalCare());
    }
}
