<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Slideshow extends Model
{
    protected $fillable = [
        'hospital_id',
        'ward_id',
        'file_path',
        'file_type',
        'order',
        'is_active',
    ];

    public function hospital()
    {
        return $this->belongsTo(Hospital::class);
    }

    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }
}
