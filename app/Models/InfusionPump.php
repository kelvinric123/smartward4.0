<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InfusionPump extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'device_name',
        'device_type',
        'location',
        'ward_id',
        'is_active',
        'last_seen_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    /**
     * Get the ward this pump is assigned to.
     */
    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    /**
     * Get all infusions from this pump.
     */
    public function infusions(): HasMany
    {
        return $this->hasMany(Infusion::class);
    }

    /**
     * Get active infusions from this pump.
     */
    public function activeInfusions(): HasMany
    {
        return $this->hasMany(Infusion::class)->whereIn('status', ['running', 'paused', 'alarming']);
    }

    /**
     * Update last seen timestamp.
     */
    public function updateLastSeen(): void
    {
        $this->update(['last_seen_at' => now()]);
    }

    /**
     * Find or create pump by device ID.
     */
    public static function findOrCreateByDeviceId(string $deviceId, array $attributes = []): self
    {
        return static::firstOrCreate(
            ['device_id' => $deviceId],
            array_merge(['device_name' => $deviceId], $attributes)
        );
    }
}












