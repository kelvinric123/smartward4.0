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
        'device_uuid',
        'pump_model',
        'firmware_version',
        'location',
        'ward_id',
        'patient_id',
        'linked_at',
        'is_active',
        'power_status',
        'battery_percent',
        'battery_minutes_remaining',
        'wifi_strength',
        'device_ip',
        'last_seen_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
        'linked_at' => 'datetime',
        'battery_percent' => 'integer',
        'battery_minutes_remaining' => 'integer',
        'wifi_strength' => 'integer',
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

    /**
     * Check if pump is on battery power.
     */
    public function isOnBattery(): bool
    {
        return $this->power_status === 'battery';
    }

    /**
     * Check if battery is low (< 20%).
     */
    public function isBatteryLow(): bool
    {
        return $this->isOnBattery() && $this->battery_percent !== null && $this->battery_percent < 20;
    }

    /**
     * Check if battery is critical (< 10%).
     */
    public function isBatteryCritical(): bool
    {
        return $this->isOnBattery() && $this->battery_percent !== null && $this->battery_percent < 10;
    }

    /**
     * Get battery status label.
     */
    public function getBatteryStatusLabelAttribute(): string
    {
        if ($this->power_status === 'mains') {
            return 'Plugged In';
        }

        if ($this->battery_percent === null) {
            return 'Unknown';
        }

        if ($this->battery_percent >= 80) {
            return 'Full';
        } elseif ($this->battery_percent >= 50) {
            return 'Good';
        } elseif ($this->battery_percent >= 20) {
            return 'Low';
        } else {
            return 'Critical';
        }
    }

    /**
     * Get formatted battery time remaining.
     */
    public function getFormattedBatteryTimeAttribute(): string
    {
        if ($this->battery_minutes_remaining === null) {
            return '--:--';
        }

        $hours = floor($this->battery_minutes_remaining / 60);
        $mins = $this->battery_minutes_remaining % 60;

        return sprintf('%d:%02d', $hours, $mins);
    }

    /**
     * Get WiFi signal strength label.
     */
    public function getWifiStrengthLabelAttribute(): string
    {
        if ($this->wifi_strength === null) {
            return 'Unknown';
        }

        if ($this->wifi_strength >= 80) {
            return 'Excellent';
        } elseif ($this->wifi_strength >= 60) {
            return 'Good';
        } elseif ($this->wifi_strength >= 40) {
            return 'Fair';
        } else {
            return 'Weak';
        }
    }

    /**
     * Update pump status from HL7 message data.
     */
    public function updateFromHl7Data(array $data): void
    {
        $updateData = [];

        if (!empty($data['pump_model'])) {
            $updateData['pump_model'] = $data['pump_model'];
        }
        if (!empty($data['device_uuid'])) {
            $updateData['device_uuid'] = $data['device_uuid'];
        }
        if (!empty($data['firmware_version'])) {
            $updateData['firmware_version'] = $data['firmware_version'];
        }
        if (!empty($data['power_status'])) {
            $updateData['power_status'] = $data['power_status'];
        }
        if (isset($data['battery_percent'])) {
            $updateData['battery_percent'] = $data['battery_percent'];
        }
        if (isset($data['battery_minutes_remaining'])) {
            $updateData['battery_minutes_remaining'] = $data['battery_minutes_remaining'];
        }
        if (isset($data['wifi_strength'])) {
            $updateData['wifi_strength'] = $data['wifi_strength'];
        }
        if (!empty($data['device_ip'])) {
            $updateData['device_ip'] = $data['device_ip'];
        }

        $updateData['last_seen_at'] = now();
        $updateData['is_active'] = true;

        $this->update($updateData);
    }
}
































