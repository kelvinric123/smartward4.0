<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IsolationType extends Model
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
     * Get isolation type by code
     */
    public static function findByCode(string $code): ?self
    {
        return static::where('code', strtoupper(trim($code)))
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get display name for an isolation code
     */
    public static function getDisplayName(string $code): string
    {
        $isolationType = static::findByCode($code);
        return $isolationType ? $isolationType->name : ucfirst(str_replace('_', ' ', $code));
    }

    /**
     * Get short label for dashboard display (max 4 chars)
     */
    public function getShortLabel(): string
    {
        return strtoupper(substr($this->code, 0, 4));
    }
}



