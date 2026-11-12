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
        'title',
        'order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    public function hospital()
    {
        return $this->belongsTo(Hospital::class);
    }

    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }

    /** The slide's name in the slideshow's picker: its title, else its stored file's name. */
    public function displayName(): string
    {
        return filled($this->title) ? $this->title : pathinfo($this->file_path, PATHINFO_FILENAME);
    }
}
