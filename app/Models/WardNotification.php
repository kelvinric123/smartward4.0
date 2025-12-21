<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WardNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'ward_id',
        'patient_id',
        'bed_number',
        'type',
        'severity',
        'message',
        'ews_score',
        'status',
        'responded_at',
        'responded_by',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
        'ews_score' => 'integer',
    ];

    // Severity constants
    const SEVERITY_NORMAL = 'normal';
    const SEVERITY_WARNING = 'warning';
    const SEVERITY_URGENT = 'urgent';

    // Type constants
    const TYPE_EWS = 'ews';

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_RESPONDED = 'responded';

    /**
     * Get the ward that owns the notification.
     */
    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    /**
     * Get the patient that owns the notification.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the user who responded to the notification.
     */
    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    /**
     * Scope for pending notifications.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for responded notifications.
     */
    public function scopeResponded($query)
    {
        return $query->where('status', self::STATUS_RESPONDED);
    }

    /**
     * Scope for a specific ward.
     */
    public function scopeForWard($query, $wardId)
    {
        return $query->where('ward_id', $wardId);
    }

    /**
     * Scope for a specific type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Get the background class based on severity.
     */
    public function getSeverityBgClassAttribute(): string
    {
        return match ($this->severity) {
            self::SEVERITY_URGENT => 'bg-red-500',
            self::SEVERITY_WARNING => 'bg-yellow-500',
            default => 'bg-green-500',
        };
    }

    /**
     * Get the border class based on severity.
     */
    public function getSeverityBorderClassAttribute(): string
    {
        return match ($this->severity) {
            self::SEVERITY_URGENT => 'border-red-500',
            self::SEVERITY_WARNING => 'border-yellow-500',
            default => 'border-green-500',
        };
    }

    /**
     * Get the text class based on severity.
     */
    public function getSeverityTextClassAttribute(): string
    {
        return match ($this->severity) {
            self::SEVERITY_URGENT => 'text-red-600',
            self::SEVERITY_WARNING => 'text-yellow-600',
            default => 'text-green-600',
        };
    }

    /**
     * Get the severity label.
     */
    public function getSeverityLabelAttribute(): string
    {
        return match ($this->severity) {
            self::SEVERITY_URGENT => 'Urgent',
            self::SEVERITY_WARNING => 'Needs Attention',
            default => 'Normal',
        };
    }

    /**
     * Calculate severity from EWS score.
     */
    public static function getSeverityFromEws(int $ewsScore): string
    {
        if ($ewsScore >= 5) {
            return self::SEVERITY_URGENT;
        } elseif ($ewsScore >= 3) {
            return self::SEVERITY_WARNING;
        }
        return self::SEVERITY_NORMAL;
    }

    /**
     * Check if an active (pending) notification exists for this patient and type.
     */
    public static function hasActiveNotification(int $patientId, string $type): bool
    {
        return self::where('patient_id', $patientId)
            ->where('type', $type)
            ->where('status', self::STATUS_PENDING)
            ->exists();
    }

    /**
     * Create or update EWS notification for a patient.
     */
    public static function createOrUpdateEwsNotification(
        int $wardId,
        int $patientId,
        string $bedNumber,
        int $ewsScore,
        string $patientName
    ): ?self {
        // Only create notifications for abnormal EWS (score >= 3)
        if ($ewsScore < 3) {
            // If EWS is now normal, resolve any pending notifications
            self::where('patient_id', $patientId)
                ->where('type', self::TYPE_EWS)
                ->where('status', self::STATUS_PENDING)
                ->update([
                    'status' => self::STATUS_RESPONDED,
                    'responded_at' => now(),
                ]);
            return null;
        }

        $severity = self::getSeverityFromEws($ewsScore);
        $message = $ewsScore >= 5
            ? "URGENT: {$patientName} (Bed {$bedNumber}) has critical EWS of {$ewsScore}"
            : "{$patientName} (Bed {$bedNumber}) has elevated EWS of {$ewsScore}";

        // Check for existing pending notification for this patient
        $existing = self::where('patient_id', $patientId)
            ->where('type', self::TYPE_EWS)
            ->where('status', self::STATUS_PENDING)
            ->first();

        if ($existing) {
            // Update existing notification if score changed
            if ($existing->ews_score !== $ewsScore) {
                $existing->update([
                    'ews_score' => $ewsScore,
                    'severity' => $severity,
                    'message' => $message,
                    'bed_number' => $bedNumber,
                ]);
            }
            return $existing;
        }

        // Create new notification
        return self::create([
            'ward_id' => $wardId,
            'patient_id' => $patientId,
            'bed_number' => $bedNumber,
            'type' => self::TYPE_EWS,
            'severity' => $severity,
            'message' => $message,
            'ews_score' => $ewsScore,
            'status' => self::STATUS_PENDING,
        ]);
    }
}
