<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A view-only link to a ward's AI Nurse Schedule, for staff without a
 * SmartWard sign-in (see AiNurseScheduleShareController). Whoever has the
 * link can open it until it expires or a manager revokes it.
 *
 * The token is looked up by its sha256 only. It is also kept encrypted with
 * the app key so the manager can copy the link or show its QR code again; if
 * the key changes, the link still works but can no longer be shown.
 */
class WardRosterShareLink extends Model
{
    /** What a link can show, in the order the shared page lists them. */
    public const SECTIONS = [
        'roster' => ['label' => 'Roster', 'description' => 'Every nurse\'s shifts for the week, with staffing per shift'],
        'nurse' => ['label' => 'Duty list', 'description' => 'Each nurse\'s week on its own, easy to find on a phone'],
        'assign' => ['label' => 'Bed assignment', 'description' => 'Which nurse has which beds on a shift'],
        'acuity' => ['label' => 'Patient acuity', 'description' => 'Patients by care level and the nurses each shift needs'],
        'workload' => ['label' => 'Workload', 'description' => 'How evenly shifts, nights and weekends are shared'],
    ];

    /** Always the current week (the viewer may also open next week), or one week only. */
    public const WEEK_ROLLING = 'rolling';

    public const WEEK_FIXED = 'fixed';

    public const WEEK_MODES = [self::WEEK_ROLLING, self::WEEK_FIXED];

    /** Patients as the bed only or as initials: a link never shows full names or MRNs. */
    public const NAME_MODES = [
        'hidden' => 'Bed only',
        'initials' => 'Initials',
    ];

    /** How long a new link lasts, in days. */
    public const EXPIRY_DAYS = [1, 7, 30, 90];

    protected $fillable = [
        'ward_id',
        'token_hash',
        'token',
        'label',
        'sections',
        'week_mode',
        'week_start',
        'names',
        'expires_at',
        'revoked_at',
        'revoked_by',
        'created_by',
        'created_by_name',
        'view_count',
        'last_viewed_at',
    ];

    protected $hidden = [
        'token_hash',
        'token',
    ];

    protected $casts = [
        'token' => 'encrypted',
        'sections' => 'array',
        'week_start' => 'date',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'view_count' => 'integer',
        'last_viewed_at' => 'datetime',
    ];

    /**
     * Make a link with a new token. The token is returned too: it is the link,
     * and the database keeps only its hash and an encrypted copy.
     *
     * @return array{0: self, 1: string}
     */
    public static function issue(Ward $ward, array $attributes): array
    {
        // Letters and digits only, so the link survives being pasted into chats and QR codes
        $token = Str::random(48);

        $link = static::create(array_merge($attributes, [
            'ward_id' => $ward->id,
            'token_hash' => self::hashToken($token),
            'token' => $token,
        ]));

        return [$link, $token];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** The link for a token, while it has not expired or been revoked. */
    public static function findActive(string $token): ?self
    {
        return static::active()->where('token_hash', self::hashToken($token))->first();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function isActive(): bool
    {
        return !$this->revoked_at && $this->expires_at?->isFuture();
    }

    /** The token, or null when it can no longer be read because the app key changed. */
    public function plainToken(): ?string
    {
        try {
            return $this->token;
        } catch (DecryptException) {
            return null;
        }
    }

    /** The link to give out, or null when the token can no longer be read. */
    public function url(): ?string
    {
        $token = $this->plainToken();

        return $token ? route('ward.ai-schedule.shared', $token) : null;
    }

    /** The link's name, or a plain one when it was given none. */
    public function title(): string
    {
        return $this->label ?: 'View-only link';
    }

    /** The sections the link shows, known ones only, in the shared page's order. */
    public function sectionKeys(): array
    {
        return array_values(array_intersect(array_keys(self::SECTIONS), (array) $this->sections));
    }

    public function isRolling(): bool
    {
        return $this->week_mode !== self::WEEK_FIXED || !$this->week_start;
    }

    /**
     * The Monday of the week the link shows: its own week, or for a link that
     * follows the current week, this week or the next.
     */
    public function weekStart(bool $next = false): Carbon
    {
        $start = $this->isRolling() ? now()->addWeeks($next ? 1 : 0) : $this->week_start->copy();

        return $start->startOfWeek(Carbon::MONDAY)->startOfDay();
    }

    /** One more view, without counting it as a change to the link. */
    public function recordView(): void
    {
        static::withoutTimestamps(fn () => $this->increment('view_count', 1, ['last_viewed_at' => now()]));
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }
}
