<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdtDietMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'adt_configuration_id',
        'adt_diet_code',
        'adt_diet_name',
        'mapped_diet',
        'is_active',
    ];

    public function configuration()
    {
        return $this->belongsTo(AdtConfiguration::class, 'adt_configuration_id');
    }
}




















