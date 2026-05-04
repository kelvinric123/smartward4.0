<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Ward extends Model
{
    use HasFactory;

    protected $fillable = [
        'hospital_id',
        'ward_code',
        'ward_name',
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
}
