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
        'cplus_facility_id',
        'cplus_location_id',
        'cplus_synced_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'cplus_synced_at' => 'datetime',
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

    /**
     * Wards whose ward type is marked emergency (the ED's zones), the ones on
     * Command Center V2 (ED).
     */
    public function scopeEmergency(Builder $query): Builder
    {
        return $query->whereHas('wardType', fn (Builder $type) => $type->emergency());
    }

    /** Every other ward, including those without a type: the hospital-wide Command Center V2's. */
    public function scopeNotEmergency(Builder $query): Builder
    {
        return $query->whereDoesntHave('wardType', fn (Builder $type) => $type->emergency());
    }

    public function isEmergency(): bool
    {
        return (bool) $this->wardType?->is_emergency;
    }
}
