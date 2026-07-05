<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QmedGateway extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'gateway_id',
        'hostname',
        'location',
        'ward_id',
        'mac_address',
        'cpu_serial',
        'last_ping_at',
        'last_ping_ip',
        'ssh_user',
        'ssh_port',
        'last_heartbeat_at',
        'last_heartbeat',
        'last_stats',
        'last_stats_at',
        'health_status',
        'is_active',
    ];

    protected $casts = [
        'last_ping_at' => 'datetime',
        'last_heartbeat_at' => 'datetime',
        'last_heartbeat' => 'array',
        'last_stats' => 'array',
        'last_stats_at' => 'datetime',
        'ssh_port' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Ready-to-paste SSH command to reach this cart, using its last known IP.
     */
    public function getSshCommandAttribute(): ?string
    {
        if (!$this->last_ping_ip) {
            return null;
        }
        $user = $this->ssh_user ?: 'pi';
        $port = (int) ($this->ssh_port ?: 22);
        return 'ssh ' . $user . '@' . $this->last_ping_ip . ($port !== 22 ? ' -p ' . $port : '');
    }

    // Grace before a silent cart is considered offline (missed heartbeats).
    public const OFFLINE_AFTER_SECONDS = 180;

    // Heartbeat-recency thresholds for the traffic-light connection indicator.
    public const HEARTBEAT_WARN_MINUTES = 60;    // yellow at/after 1 hour
    public const HEARTBEAT_CRIT_MINUTES = 120;   // red at/after 2 hours

    /**
     * Traffic-light status from how long since we last heard from the cart:
     * green (< 1h), yellow (>= 1h), red (>= 2h or never seen).
     */
    public function getConnectionStatusAttribute(): string
    {
        $seen = $this->last_heartbeat_at ?? $this->last_ping_at;
        if (!$seen) {
            return 'red';
        }
        $minutes = (now()->getTimestamp() - $seen->getTimestamp()) / 60;
        if ($minutes >= self::HEARTBEAT_CRIT_MINUTES) {
            return 'red';
        }
        if ($minutes >= self::HEARTBEAT_WARN_MINUTES) {
            return 'yellow';
        }
        return 'green';
    }

    /**
     * Record a heartbeat payload and derive a health status from it.
     */
    public function recordHeartbeat(array $payload, ?string $ip = null): void
    {
        $this->last_heartbeat_at = now();
        $this->last_heartbeat = $payload;
        if ($ip) {
            $this->last_ping_ip = $ip;
        }
        $this->health_status = self::deriveHealth($payload);
        $this->save();
    }

    /**
     * Derive healthy/warning/critical from a heartbeat payload.
     */
    public static function deriveHealth(array $p): string
    {
        $queue = $p['queue'] ?? [];
        $power = $p['power'] ?? [];

        // Critical: a cloned / mis-provisioned Pi is impersonating this cart.
        if (!empty($p['identity_conflict'])) {
            return 'critical';
        }

        // Critical: data is being lost or credentials/power are failing.
        if (($queue['dead'] ?? 0) > 0) {
            return 'critical';
        }
        if (!empty($power['undervoltage_now'])) {
            return 'critical';
        }

        // Warning: degraded but still delivering.
        if (!empty($power['undervoltage_seen'])) {
            return 'warning';
        }
        if (($queue['oldest_pending_age_s'] ?? 0) > 600) {
            return 'warning';
        }
        if (($p['disk_free_pct'] ?? 100) < 10) {
            return 'warning';
        }
        if (array_key_exists('clock_synced', $p) && $p['clock_synced'] === false) {
            return 'warning';
        }

        return 'healthy';
    }

    /**
     * True when the cart has missed enough heartbeats to be considered offline.
     */
    public function getIsOnlineAttribute(): bool
    {
        return $this->last_heartbeat_at
            && $this->last_heartbeat_at->gt(now()->subSeconds(self::OFFLINE_AFTER_SECONDS));
    }

    /**
     * Effective status including the offline (stale heartbeat) case.
     */
    public function getEffectiveHealthAttribute(): string
    {
        if (!$this->is_online) {
            return 'offline';
        }
        return $this->health_status ?? 'unknown';
    }

    /**
     * Get active gateways.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get associated API Users.
     */
    public function apiUsers()
    {
        return $this->belongsToMany(ApiUser::class, 'api_user_qmed_gateway');
    }

    /**
     * The ward this gateway (cart) is assigned to.
     */
    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }

    /**
     * Update ping status.
     */
    public function updatePing(?string $ip = null)
    {
        $this->update([
            'last_ping_at' => now(),
            'last_ping_ip' => $ip,
        ]);
    }
}
