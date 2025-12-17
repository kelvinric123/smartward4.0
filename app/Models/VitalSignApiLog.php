<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VitalSignApiLog extends Model
{
    protected $fillable = [
        'api_user_id',
        'endpoint',
        'method',
        'request_data',
        'response_data',
        'status_code',
        'ip_address',
        'response_time_ms',
    ];

    protected $casts = [
        'request_data' => 'array',
        'response_data' => 'array',
    ];

    /**
     * Get the API user that made this request.
     */
    public function apiUser(): BelongsTo
    {
        return $this->belongsTo(ApiUser::class);
    }
}
































