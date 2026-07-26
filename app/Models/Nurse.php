<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class Nurse extends Model
{
    const DESIGNATIONS = [
        'Nurse Manager',
        'Nurse Clinician',
        'Assistant Nurse Clinician',
        'SENIOR STAFF NURSE II',
        'STAFF NURSE I',
        'STAFF NURSE II',
        'GRADUATE NURSE',
        'Student nurse',
        'Health Care Assistant',
        'Patient Care Assistant',
    ];

    const DEFAULT_DESIGNATION = 'STAFF NURSE I';

    protected $fillable = [
        'personnel_code',
        'user_id',
        'name',
        'registration_number',
        'phone',
        'email',
        'department',
        'designation',
        'qualification',
        'years_of_experience',
        'is_active',
        'is_tagging',
        'ward_id',
        'app_username',
        'app_password',
    ];

    protected $hidden = [
        'app_password',
        'app_api_token',
    ];

    public static function getDesignations(): array
    {
        return self::DESIGNATIONS;
    }

    protected $casts = [
        'is_active' => 'boolean',
        'is_tagging' => 'boolean',
        'years_of_experience' => 'integer',
        'app_token_expires_at' => 'datetime',
        'app_last_login_at' => 'datetime',
    ];

    /**
     * Hash the nurse-app password whenever it is set.
     */
    public function setAppPasswordAttribute($value): void
    {
        $this->attributes['app_password'] = ($value !== null && $value !== '')
            ? Hash::make($value)
            : null;
    }

    /**
     * Whether the nurse app login has been configured.
     */
    public function hasAppLogin(): bool
    {
        return !empty($this->app_username) && !empty($this->app_password);
    }

    /**
     * Verify the given nurse-app password.
     */
    public function verifyAppPassword(string $password): bool
    {
        return $this->app_password && Hash::check($password, $this->app_password);
    }

    /**
     * Generate a new nurse-app API token. Returns the plain token
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
     * Find a nurse by a nurse-app API token.
     */
    public static function findByAppToken(string $token): ?self
    {
        return static::where('app_api_token', hash('sha256', $token))
            ->where('is_active', true)
            ->where('app_token_expires_at', '>', now())
            ->first();
    }

    /**
     * Invalidate the current nurse-app token (logout).
     */
    public function invalidateAppToken(): void
    {
        $this->forceFill([
            'app_api_token' => null,
            'app_token_expires_at' => null,
        ])->save();
    }

    /**
     * Find nurse by personnel code (ADT code)
     */
    public static function findByPersonnelCode(string $code): ?self
    {
        return static::where('personnel_code', $code)
            ->where('is_active', true)
            ->first();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }

    /**
     * The nurses that this nurse is tagging to (many-to-many)
     */
    public function taggingNurses()
    {
        return $this->belongsToMany(Nurse::class, 'nurse_tagging_nurses', 'nurse_id', 'tagging_nurse_id')
            ->withTimestamps();
    }

    /**
     * The nurses that are tagging to this nurse (inverse many-to-many)
     */
    public function taggedByNurses()
    {
        return $this->belongsToMany(Nurse::class, 'nurse_tagging_nurses', 'tagging_nurse_id', 'nurse_id')
            ->withTimestamps();
    }
}
