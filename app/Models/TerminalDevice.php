<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A bedside terminal paired with one bed (POST /api/terminal/pair). The
 * terminal sends the plain pairing token as a bearer token; only its SHA-256
 * hash is stored. See AuthenticateTerminalDevice.
 */
class TerminalDevice extends Model
{
    protected $fillable = [
        'bed_id',
        'name',
        'token',
        'paired_by_nurse_id',
        'last_seen_at',
        'last_ip',
        'revoked_at',
    ];

    protected $hidden = [
        'token',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function bed(): BelongsTo
    {
        return $this->belongsTo(Bed::class);
    }

    public function pairedBy(): BelongsTo
    {
        return $this->belongsTo(Nurse::class, 'paired_by_nurse_id');
    }

    /**
     * Pair a terminal with a bed. Returns [device, plain token]; the plain
     * token is handed to the terminal once and not kept.
     */
    public static function pair(Bed $bed, ?Nurse $pairedBy, ?string $name = null): array
    {
        $token = Str::random(64);

        $device = static::create([
            'bed_id' => $bed->id,
            'name' => $name,
            'token' => hash('sha256', $token),
            'paired_by_nurse_id' => $pairedBy?->id,
        ]);

        return [$device, $token];
    }

    /**
     * Find the paired (not revoked) terminal holding this token.
     */
    public static function findByToken(?string $token): ?self
    {
        if (! $token) {
            return null;
        }

        return static::where('token', hash('sha256', $token))
            ->whereNull('revoked_at')
            ->first();
    }

    public function revoke(): void
    {
        $this->forceFill(['revoked_at' => now()])->save();
    }
}
