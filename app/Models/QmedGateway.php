<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QmedGateway extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'location',
        'mac_address',
        'last_ping_at',
        'last_ping_ip',
        'is_active',
    ];

    protected $casts = [
        'last_ping_at' => 'datetime',
        'is_active' => 'boolean',
    ];

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
