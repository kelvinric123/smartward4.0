<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class Consultant extends Model
{
    protected $fillable = [
        'personnel_code',
        'name',
        'specialty_id',
        'registration_number',
        'phone',
        'email',
        'qualifications',
        'years_of_experience',
        'is_active',
        'app_username',
        'app_password',
    ];

    protected $hidden = [
        'app_password',
        'app_api_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'years_of_experience' => 'integer',
        'app_token_expires_at' => 'datetime',
        'app_last_login_at' => 'datetime',
    ];

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    public function beds(): BelongsToMany
    {
        return $this->belongsToMany(Bed::class, 'bed_consultant');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ConsultantNote::class);
    }

    /**
     * Find consultant by personnel code (ADT code)
     */
    public static function findByPersonnelCode(string $code): ?self
    {
        return static::where('personnel_code', $code)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Hash the doctor-app password whenever it is set.
     */
    public function setAppPasswordAttribute($value): void
    {
        $this->attributes['app_password'] = ($value !== null && $value !== '')
            ? Hash::make($value)
            : null;
    }

    /**
     * Whether the doctor app login has been configured for this consultant.
     */
    public function hasAppLogin(): bool
    {
        return !empty($this->app_username) && !empty($this->app_password);
    }

    /**
     * Verify the given doctor-app password.
     */
    public function verifyAppPassword(string $password): bool
    {
        return $this->app_password && Hash::check($password, $this->app_password);
    }

    /**
     * Generate a new doctor-app API token. Returns the plain token
     * (only the SHA-256 hash is stored).
     */
    public function generateAppToken(int $expiresInHours = 72): string
    {
        $token = Str::random(64);

        $this->forceFill([
            'app_api_token' => hash('sha256', $token),
            'app_token_expires_at' => now()->addHours($expiresInHours),
            'app_last_login_at' => now(),
        ])->save();

        return $token;
    }

    /**
     * Find a consultant by a doctor-app API token.
     */
    public static function findByAppToken(string $token): ?self
    {
        return static::where('app_api_token', hash('sha256', $token))
            ->where('is_active', true)
            ->where('app_token_expires_at', '>', now())
            ->first();
    }

    /**
     * Invalidate the current doctor-app token (logout).
     */
    public function invalidateAppToken(): void
    {
        $this->forceFill([
            'app_api_token' => null,
            'app_token_expires_at' => null,
        ])->save();
    }

    /**
     * Check if consultant is an anaesthetist based on specialty
     */
    public function isAnaesthetist(): bool
    {
        if (!$this->specialty) {
            return false;
        }

        $anaesthetistKeywords = ['anaesth', 'anesth', 'anesthesi'];
        $specialtyName = strtolower($this->specialty->name ?? '');
        $specialtyCode = strtolower($this->specialty->code ?? '');

        foreach ($anaesthetistKeywords as $keyword) {
            if (str_contains($specialtyName, $keyword) || str_contains($specialtyCode, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
