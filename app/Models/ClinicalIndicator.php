<?php

namespace App\Models;

use App\Support\ClinicalIndicatorLibrary;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClinicalIndicator extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'sort_order',
        'is_active',
        'monitoring_enabled',
        'monitoring_suggested_minutes',
        'monitoring_warning_minutes',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'monitoring_enabled' => 'boolean',
        'monitoring_suggested_minutes' => 'integer',
        'monitoring_warning_minutes' => 'integer',
    ];

    public function wardTypes()
    {
        return $this->belongsToMany(WardType::class, 'clinical_indicator_ward_type')
            ->withTimestamps();
    }

    /**
     * The clinical content for this scale, or null when it is one a hospital
     * added itself rather than one shipped in the library.
     */
    public function definition(): ?array
    {
        return ClinicalIndicatorLibrary::find($this->code);
    }

    /**
     * The category it is listed under. One a hospital added itself has no
     * library entry, so it goes under Other.
     */
    public function category(): string
    {
        return $this->definition()['category'] ?? ClinicalIndicatorLibrary::OTHER_CATEGORY;
    }

    /**
     * Shipped in code, so deleting it would only invite the next sync to put
     * it back. Deactivate those instead.
     */
    public function isStandard(): bool
    {
        return ClinicalIndicatorLibrary::has($this->code);
    }

    /**
     * Standard scale whose local variant has not been settled yet, so its
     * detail is deliberately empty.
     */
    public function awaitingDetail(): bool
    {
        $definition = $this->definition();

        return $definition !== null && !($definition['confirmed'] ?? false);
    }

    /**
     * Whether patients get flagged when this scale goes unscored: switched on,
     * with both levels set.
     */
    public function isMonitored(): bool
    {
        return $this->monitoring_enabled
            && $this->monitoring_suggested_minutes
            && $this->monitoring_warning_minutes;
    }

    /**
     * Get clinical indicator by code
     */
    public static function findByCode(string $code): ?self
    {
        return static::where('code', strtoupper(trim($code)))
            ->where('is_active', true)
            ->first();
    }
}
