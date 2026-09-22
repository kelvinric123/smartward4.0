<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WardType extends Model
{
    use HasFactory;

    protected $fillable = [
        'hospital_id',
        'code',
        'name',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Null for the system ward types seeded in code, which every hospital gets.
     */
    public function hospital()
    {
        return $this->belongsTo(Hospital::class);
    }

    /**
     * A ward type can be scored against several scales at once.
     */
    public function clinicalIndicators()
    {
        return $this->belongsToMany(ClinicalIndicator::class, 'clinical_indicator_ward_type')
            ->withTimestamps()
            ->orderBy('clinical_indicators.sort_order')
            ->orderBy('clinical_indicators.name');
    }

    public function wards()
    {
        return $this->hasMany(Ward::class);
    }

    public function isSystemType(): bool
    {
        return $this->hospital_id === null;
    }

    /**
     * The ward types a given hospital may pick from: the shared system ones
     * plus anything that hospital added for itself.
     */
    public function scopeAvailableTo(Builder $query, $hospitalId): Builder
    {
        return $query->where(function (Builder $q) use ($hospitalId) {
            $q->whereNull('hospital_id');
            if ($hospitalId) {
                $q->orWhere('hospital_id', $hospitalId);
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Get ward type by code
     */
    public static function findByCode(string $code): ?self
    {
        return static::where('code', strtoupper(trim($code)))
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get display name for a ward type code
     */
    public static function getDisplayName(string $code): string
    {
        $wardType = static::findByCode($code);
        return $wardType ? $wardType->name : ucfirst(str_replace('_', ' ', $code));
    }
}
