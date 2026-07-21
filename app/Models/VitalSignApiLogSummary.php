<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VitalSignApiLogSummary extends Model
{
    protected $fillable = [
        'summary_date',
        'api_user_id',
        'endpoint',
        'method',
        'total_requests',
        'success_count',
        'error_count',
        'avg_response_time_ms',
        'max_response_time_ms',
    ];

    protected $casts = [
        'summary_date' => 'date',
    ];

    /**
     * Get the API user this summary belongs to.
     */
    public function apiUser(): BelongsTo
    {
        return $this->belongsTo(ApiUser::class);
    }
}
