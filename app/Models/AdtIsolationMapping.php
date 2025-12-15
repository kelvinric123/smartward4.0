<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdtIsolationMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'adt_configuration_id',
        'adt_isolation_code',
        'adt_isolation_name',
        'mapped_isolation',
        'is_active',
    ];

    public function configuration()
    {
        return $this->belongsTo(AdtConfiguration::class, 'adt_configuration_id');
    }
}















