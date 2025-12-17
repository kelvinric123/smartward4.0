<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VitalSignMonitorDevice extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'ip_address',
        'port',
        'location',
        'description',
        'is_active',
        'last_connected_at',
        'last_status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'port' => 'integer',
        'last_connected_at' => 'datetime',
    ];

    /**
     * Get active devices only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Update the last connected status.
     */
    public function updateConnectionStatus(string $status, bool $connected = true): void
    {
        $this->update([
            'last_status' => $status,
            'last_connected_at' => $connected ? now() : $this->last_connected_at,
        ]);
    }
}





