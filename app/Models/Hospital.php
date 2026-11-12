<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Hospital extends Model
{
    /** The bottom bar's text until a hospital sets its own: the highlighted part, then the rest. */
    public const DEFAULT_FOOTER_HIGHLIGHT = 'PHKL';
    public const DEFAULT_FOOTER_TEXT = 'Smart Ward 4.0';

    /** How long each ward dashboard slide shows, in seconds. */
    public const DEFAULT_SLIDESHOW_SECONDS = 10;
    public const MIN_SLIDESHOW_SECONDS = 3;
    public const MAX_SLIDESHOW_SECONDS = 600;

    protected $fillable = [
        'name',
        'address',
        'phone',
        'email',
        'description',
        'is_active',
        'logo_path',
        'login_logo_path',
        'navbar_logo_path',
        'theme_primary_color',
        'theme_secondary_color',
        'hidden_nav_items',
        'ed_target_minutes',
        'footer_highlight',
        'footer_text',
        'slideshow_enabled',
        'slideshow_seconds',
        'slideshow_hidden_pictures',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'hidden_nav_items' => 'array',
        'ed_target_minutes' => 'integer',
        'slideshow_enabled' => 'boolean',
        'slideshow_seconds' => 'integer',
        'slideshow_hidden_pictures' => 'array',
    ];

    public function wards()
    {
        return $this->hasMany(Ward::class);
    }

    public function slideshows()
    {
        return $this->hasMany(Slideshow::class);
    }

    /*
     * The readers below fall back to the defaults when a column is empty, or not
     * there yet because a server hasn't run the migration that adds it.
     */

    /**
     * The bottom bar's text as [highlighted part, rest], "PHKL" and "Smart Ward 4.0"
     * until the hospital sets either one on the Theme & Menu tab.
     *
     * @return array{0: string, 1: string}
     */
    public function footerParts(): array
    {
        if (blank($this->footer_highlight) && blank($this->footer_text)) {
            return [self::DEFAULT_FOOTER_HIGHLIGHT, self::DEFAULT_FOOTER_TEXT];
        }

        return [(string) $this->footer_highlight, (string) $this->footer_text];
    }

    /** Whether the ward dashboard logo opens the slideshow (on until switched off). */
    public function slideshowEnabled(): bool
    {
        return $this->slideshow_enabled ?? true;
    }

    public function slideshowSeconds(): int
    {
        $seconds = $this->slideshow_seconds ?: self::DEFAULT_SLIDESHOW_SECONDS;

        return max(self::MIN_SLIDESHOW_SECONDS, min(self::MAX_SLIDESHOW_SECONDS, $seconds));
    }

    /**
     * Whether this server's database has the bottom bar and slideshow columns. A
     * server that hasn't run their migration keeps working: it reads the defaults
     * and skips saving these settings.
     */
    public static function hasDisplaySettings(): bool
    {
        return Schema::hasColumn((new static)->getTable(), 'slideshow_enabled');
    }

    /** @return list<string> file names in base_path('picture') left out of the slideshow */
    public function hiddenSlideshowPictures(): array
    {
        return array_values(array_filter((array) $this->slideshow_hidden_pictures, 'is_string'));
    }
}
