<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EkadBedMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'bed_id',
        'mac_address',
        'template_id',
        'device_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the bed associated with this mapping
     */
    public function bed(): BelongsTo
    {
        return $this->belongsTo(Bed::class);
    }

    /**
     * Get the ward through the bed relationship
     */
    public function ward()
    {
        return $this->bed?->ward;
    }

    /**
     * Format MAC address (remove colons if present)
     */
    public static function formatMac(string $mac): string
    {
        return strtoupper(str_replace([':', '-'], '', $mac));
    }

    /**
     * Get active mapping for a specific bed
     */
    public static function getForBed(int $bedId): ?self
    {
        return static::where('bed_id', $bedId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Scope to get only active mappings
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Set MAC address (automatically format)
     */
    public function setMacAddressAttribute($value)
    {
        $this->attributes['mac_address'] = self::formatMac($value);
    }

    /**
     * Set the screen's own template ID; blank means the default one
     */
    public function setTemplateIdAttribute($value)
    {
        $value = trim((string) $value);
        $this->attributes['template_id'] = $value === '' ? null : $value;
    }

    /**
     * The template this screen is painted with: its own, else the Template ID
     * under Configuration & Login
     */
    public function templateIdOr(?string $defaultTemplateId): ?string
    {
        return $this->template_id ?? $defaultTemplateId;
    }
}
