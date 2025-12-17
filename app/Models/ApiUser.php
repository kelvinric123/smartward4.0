<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ApiUser extends Model
{
    protected $fillable = [
        'name',
        'username',
        'password',
        'api_token',
        'description',
        'is_active',
        'token_expires_at',
        'last_login_at',
        'last_login_ip',
        'request_count',
    ];

    protected $hidden = [
        'password',
        'api_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'token_expires_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    /**
     * Generate a new API token for this user.
     */
    public function generateToken(int $expiresInHours = 24): string
    {
        $token = Str::random(64);
        
        $this->update([
            'api_token' => hash('sha256', $token),
            'token_expires_at' => now()->addHours($expiresInHours),
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ]);

        return $token;
    }

    /**
     * Verify the given password against the user's password.
     */
    public function verifyPassword(string $password): bool
    {
        return Hash::check($password, $this->password);
    }

    /**
     * Check if the API token is valid.
     */
    public function hasValidToken(): bool
    {
        if (!$this->api_token || !$this->token_expires_at) {
            return false;
        }

        return $this->token_expires_at->isFuture();
    }

    /**
     * Invalidate the current token.
     */
    public function invalidateToken(): void
    {
        $this->update([
            'api_token' => null,
            'token_expires_at' => null,
        ]);
    }

    /**
     * Increment the request count.
     */
    public function incrementRequestCount(): void
    {
        $this->increment('request_count');
    }

    /**
     * Get the API logs for this user.
     */
    public function apiLogs(): HasMany
    {
        return $this->hasMany(VitalSignApiLog::class);
    }

    /**
     * Find a user by their API token.
     */
    public static function findByToken(string $token): ?self
    {
        $hashedToken = hash('sha256', $token);
        
        return static::where('api_token', $hashedToken)
            ->where('is_active', true)
            ->where('token_expires_at', '>', now())
            ->first();
    }

    /**
     * Set the password attribute with hashing.
     */
    public function setPasswordAttribute($value): void
    {
        $this->attributes['password'] = Hash::make($value);
    }
}
































