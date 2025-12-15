<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InfusionApiUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'username',
        'password',
        'description',
        'is_active',
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
     * Automatically hash password when setting.
     */
    public function setPasswordAttribute($value): void
    {
        $this->attributes['password'] = Hash::make($value);
    }

    /**
     * Verify password.
     */
    public function verifyPassword(string $password): bool
    {
        return Hash::check($password, $this->password);
    }

    /**
     * Generate a new API token.
     */
    public function generateToken(int $hoursValid = 24): string
    {
        $token = Str::random(64);
        
        $this->update([
            'api_token' => hash('sha256', $token),
            'token_expires_at' => now()->addHours($hoursValid),
            'last_login_at' => now(),
        ]);

        return $token;
    }

    /**
     * Check if the current token is valid.
     */
    public function hasValidToken(): bool
    {
        return $this->api_token && 
               $this->token_expires_at && 
               $this->token_expires_at->isFuture();
    }

    /**
     * Find user by token.
     */
    public static function findByToken(string $token): ?self
    {
        $hashedToken = hash('sha256', $token);
        
        return static::where('api_token', $hashedToken)
            ->where('token_expires_at', '>', now())
            ->where('is_active', true)
            ->first();
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
     * Increment request count.
     */
    public function incrementRequestCount(): void
    {
        $this->increment('request_count');
    }

    /**
     * Get API logs for this user.
     */
    public function apiLogs(): HasMany
    {
        return $this->hasMany(InfusionApiLog::class);
    }
}


























