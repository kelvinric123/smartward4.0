<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class GatewayNurseBinding extends Model
{
    protected $fillable = [
        'api_user_id',
        'nurse_id',
        'start_at',
        'end_at',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function apiUser(): BelongsTo
    {
        return $this->belongsTo(ApiUser::class);
    }

    public function nurse(): BelongsTo
    {
        return $this->belongsTo(Nurse::class);
    }
}
