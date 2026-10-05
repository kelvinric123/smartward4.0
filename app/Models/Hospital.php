<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hospital extends Model
{
    protected $fillable = [
        'name',
        'address',
        'phone',
        'email',
        'description',
        'is_active',
        'logo_path',
        'login_logo_path',
        'navbar_logo_path',
        'theme_primary_color',
        'theme_secondary_color',
        'hidden_nav_items',
        'ed_target_minutes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'hidden_nav_items' => 'array',
        'ed_target_minutes' => 'integer',
    ];

    public function wards()
    {
        return $this->hasMany(Ward::class);
    }

    public function slideshows()
    {
        return $this->hasMany(Slideshow::class);
    }
}
