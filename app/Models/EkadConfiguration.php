<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Crypt;

class EkadConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'base_url',
        'username',
        'password',
        'template_id',
        'bearer_token',
        'token_expires_at',
        'auto_push_enabled',
        'mask_patient_name',
        'mask_style',
        'is_active',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'auto_push_enabled' => 'boolean',
        'mask_patient_name' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Mask styles for patient names
     */
    const MASK_PARTIAL = 'partial';
    const MASK_FULL = 'full';
    const MASK_INITIALS = 'initials';

    /**
     * Get the active configuration
     */
    public static function getActive(): ?self
    {
        return static::where('is_active', true)->first();
    }

    /**
     * Check if the token is valid (not expired)
     */
    public function isTokenValid(): bool
    {
        if (empty($this->bearer_token)) {
            return false;
        }

        if (empty($this->token_expires_at)) {
            // Assume token is valid for 24 hours if no expiry set
            return true;
        }

        return $this->token_expires_at->isFuture();
    }

    /**
     * Apply masking to a patient name based on configuration
     */
    public function maskPatientName(string $name): string
    {
        if (!$this->mask_patient_name) {
            return $name;
        }

        return match ($this->mask_style) {
            self::MASK_PARTIAL => $this->maskPartial($name),
            self::MASK_FULL => $this->maskFull($name),
            self::MASK_INITIALS => $this->maskInitials($name),
            default => $name,
        };
    }

    /**
     * Partial masking: A*** G******
     */
    protected function maskPartial(string $name): string
    {
        $parts = explode(' ', $name);
        $masked = array_map(function ($part) {
            if (strlen($part) <= 1) {
                return $part;
            }
            return substr($part, 0, 1) . str_repeat('*', strlen($part) - 1);
        }, $parts);

        return implode(' ', $masked);
    }

    /**
     * Full masking: ************
     */
    protected function maskFull(string $name): string
    {
        return str_repeat('*', strlen($name));
    }

    /**
     * Initials only: A.G.
     */
    protected function maskInitials(string $name): string
    {
        $parts = explode(' ', $name);
        $initials = array_map(function ($part) {
            return strtoupper(substr($part, 0, 1)) . '.';
        }, array_filter($parts));

        return implode('', $initials);
    }

    /**
     * Store the bearer token with expiry time
     * Default expiry is 24 hours from now
     */
    public function setToken(string $token, ?int $expiresInSeconds = null): void
    {
        $this->bearer_token = $token;
        $this->token_expires_at = now()->addSeconds($expiresInSeconds ?? 86400); // 24 hours default
        $this->save();
    }

    /**
     * Clear the stored token
     */
    public function clearToken(): void
    {
        $this->bearer_token = null;
        $this->token_expires_at = null;
        $this->save();
    }
}
