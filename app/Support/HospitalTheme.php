<?php

namespace App\Support;

use App\Models\Hospital;

/**
 * A hospital's theme: the two colours behind SmartWard's brand gradient, which
 * paints the sidebar, the dashboard headers, primary buttons and the login page.
 *
 * Views colour those parts with Tailwind's brand-* and accent-* classes, which
 * read the --brand-* and --accent-* CSS variables. tailwind.config.js defaults
 * them to Tailwind's blue and cyan, the original look. A hospital with its own
 * colours gets a full shade scale for each, generated here and written into the
 * page by <x-theme-style>. The primary colour is the brand-600 shade and the
 * secondary the accent-500 shade: the two ends of the sidebar gradient.
 */
final class HospitalTheme
{
    /** Tailwind blue-600 */
    public const DEFAULT_PRIMARY = '#2563eb';

    /** Tailwind cyan-500 */
    public const DEFAULT_SECONDARY = '#06b6d4';

    /** Offered as one-click choices on the Theme & Menu tab; the first is the default. */
    public const PRESETS = [
        ['name' => 'Ocean', 'primary' => self::DEFAULT_PRIMARY, 'secondary' => self::DEFAULT_SECONDARY],
        ['name' => 'Royal', 'primary' => '#1e40af', 'secondary' => '#3b82f6'],
        ['name' => 'Teal', 'primary' => '#0f766e', 'secondary' => '#14b8a6'],
        ['name' => 'Emerald', 'primary' => '#047857', 'secondary' => '#10b981'],
        ['name' => 'Indigo', 'primary' => '#4338ca', 'secondary' => '#8b5cf6'],
        ['name' => 'Violet', 'primary' => '#6d28d9', 'secondary' => '#d946ef'],
        ['name' => 'Maroon', 'primary' => '#9f1239', 'secondary' => '#e11d48'],
        ['name' => 'Graphite', 'primary' => '#334155', 'secondary' => '#64748b'],
    ];

    /** Tailwind shade => roughly the HSL lightness (%) Tailwind's own palettes use for it. */
    private const LIGHTNESS = [
        50 => 97, 100 => 94, 200 => 86, 300 => 77, 400 => 66, 500 => 56,
        600 => 47, 700 => 39, 800 => 32, 900 => 26, 950 => 17,
    ];

    /**
     * The hospital's primary and secondary colours as lower-case #rrggbb, falling
     * back to the defaults.
     *
     * @return array{0: string, 1: string}
     */
    public static function colours(?Hospital $hospital): array
    {
        return [
            self::normalise($hospital?->theme_primary_color) ?? self::DEFAULT_PRIMARY,
            self::normalise($hospital?->theme_secondary_color) ?? self::DEFAULT_SECONDARY,
        ];
    }

    /**
     * The rule that points the brand-* and accent-* classes at the hospital's
     * colours, or null when it uses the default theme. The html:root selector
     * outranks the defaults' :root however the stylesheets end up ordered.
     */
    public static function css(?Hospital $hospital): ?string
    {
        [$primary, $secondary] = self::colours($hospital);

        if ($primary === self::DEFAULT_PRIMARY && $secondary === self::DEFAULT_SECONDARY) {
            return null;
        }

        $variables = '';
        foreach (self::scale($primary, 600) as $shade => $rgb) {
            $variables .= "--brand-{$shade}: {$rgb};";
        }
        foreach (self::scale($secondary, 500) as $shade => $rgb) {
            $variables .= "--accent-{$shade}: {$rgb};";
        }

        return "html:root{{$variables}}";
    }

    /**
     * A Tailwind-style shade scale (50-950) around a colour, which becomes the
     * $anchor shade exactly. The other shades keep its hue and saturation and
     * spread its lightness towards near-white and near-black in the proportions
     * Tailwind's palettes use.
     *
     * @return array<int, string> shade => "r g b", the channel format the
     *                            Tailwind colours in tailwind.config.js expect
     */
    public static function scale(string $hex, int $anchor): array
    {
        [$hue, $saturation, $lightness] = self::toHsl($hex);
        $anchorTarget = self::LIGHTNESS[$anchor];
        $lightest = max(98, $lightness);
        $darkest = min(10, $lightness);

        $scale = [];
        foreach (self::LIGHTNESS as $shade => $target) {
            if ($shade === $anchor) {
                $rgb = self::toRgb($hex);
            } else {
                $shadeLightness = $target > $anchorTarget
                    ? $lightness + ($target - $anchorTarget) / (98 - $anchorTarget) * ($lightest - $lightness)
                    : $lightness - ($anchorTarget - $target) / ($anchorTarget - 10) * ($lightness - $darkest);
                $rgb = self::hslToRgb($hue, $saturation, $shadeLightness);
            }

            $scale[$shade] = implode(' ', $rgb);
        }

        return $scale;
    }

    /** A #rrggbb colour in lower case, or null when the value isn't one. */
    public static function normalise(?string $hex): ?string
    {
        return $hex !== null && preg_match('/^#[0-9a-f]{6}$/i', $hex) ? strtolower($hex) : null;
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function toRgb(string $hex): array
    {
        return array_map('hexdec', str_split(substr($hex, 1), 2));
    }

    /** @return array{0: float, 1: float, 2: float} hue in degrees, saturation and lightness in % */
    private static function toHsl(string $hex): array
    {
        [$r, $g, $b] = array_map(fn ($channel) => $channel / 255, self::toRgb($hex));
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $lightness = ($max + $min) / 2;
        $delta = $max - $min;

        if ($delta == 0) {
            return [0.0, 0.0, $lightness * 100];
        }

        $saturation = $delta / (1 - abs(2 * $lightness - 1));
        $hue = match ($max) {
            $r => fmod(($g - $b) / $delta + 6, 6),
            $g => ($b - $r) / $delta + 2,
            default => ($r - $g) / $delta + 4,
        };

        return [$hue * 60, $saturation * 100, $lightness * 100];
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function hslToRgb(float $hue, float $saturation, float $lightness): array
    {
        $s = $saturation / 100;
        $l = max(0, min(100, $lightness)) / 100;
        $chroma = (1 - abs(2 * $l - 1)) * $s;
        $x = $chroma * (1 - abs(fmod($hue / 60, 2) - 1));
        $m = $l - $chroma / 2;

        [$r, $g, $b] = match (true) {
            $hue < 60 => [$chroma, $x, 0],
            $hue < 120 => [$x, $chroma, 0],
            $hue < 180 => [0, $chroma, $x],
            $hue < 240 => [0, $x, $chroma],
            $hue < 300 => [$x, 0, $chroma],
            default => [$chroma, 0, $x],
        };

        return array_map(fn ($channel) => (int) round(($channel + $m) * 255), [$r, $g, $b]);
    }
}
