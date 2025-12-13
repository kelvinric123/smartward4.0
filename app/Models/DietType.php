<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DietType extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get diet type by code
     */
    public static function findByCode(string $code): ?self
    {
        return static::where('code', strtoupper(trim($code)))
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get display name for a diet code
     */
    public static function getDisplayName(string $code): string
    {
        $dietType = static::findByCode($code);
        return $dietType ? $dietType->name : ucfirst(str_replace('_', ' ', $code));
    }

    /**
     * Get short label for dashboard display (max 4 chars)
     */
    public function getShortLabel(): string
    {
        return strtoupper(substr($this->code, 0, 4));
    }
}
