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
        'patient_id',
        'linked_at',
        'is_active',
        'last_seen_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
        'linked_at' => 'datetime',
    ];

    /**
     * Get the ward this pump is assigned to.
     */
    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    /**
     * Get the patient this pump is linked to.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Link pump to a patient.
     */
    public function linkToPatient(int $patientId): void
    {
        $this->update([
            'patient_id' => $patientId,
            'linked_at' => now(),
        ]);
    }

    /**
     * Unlink pump from patient.
     */
    public function unlinkFromPatient(): void
    {
        $this->update([
            'patient_id' => null,
            'linked_at' => null,
        ]);
    }

    /**
     * Check if pump is linked to a patient.
     */
    public function isLinked(): bool
    {
        return $this->patient_id !== null;
    }

    /**
     * Scope for pumps linked to a specific patient.
     */
    public function scopeLinkedToPatient($query, int $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    /**
     * Scope for unlinked (available) pumps.
     */
    public function scopeAvailable($query)
    {
        return $query->whereNull('patient_id')->where('is_active', true);
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
































