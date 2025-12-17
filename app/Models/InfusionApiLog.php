<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InfusionApiLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'infusion_api_user_id',
        'endpoint',
        'method',
        'hl7_message_type',
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
     * Get the API user for this log.
     */
    public function apiUser(): BelongsTo
    {
        return $this->belongsTo(InfusionApiUser::class, 'infusion_api_user_id');
    }
}






























