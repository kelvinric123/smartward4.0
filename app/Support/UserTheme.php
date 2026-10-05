<?php

namespace App\Support;

use App\Models\Hospital;
use App\Models\User;

/**
 * How SmartWard looks for one user, chosen on their profile page: a display
 * mode, and three colours that are either a preset, their own, or the
 * hospital's theme (HospitalTheme), which is everyone's default.
 *
 * Primary and secondary are the brand-* and accent-* colours: the sidebar
 * gradient, page headers and main buttons. Background is the backdrop-* tint
 * behind the pages, which otherwise follows the primary.
 *
 * Dark and Sol re-colour Tailwind's palette by role in the compiled CSS (see
 * tailwind.config.js). The brand, accent and backdrop shades follow the chosen
 * colours, so their Dark and Sol variants are worked out here, with the same
 * mixes the config uses for Tailwind's own hues, and written into the page by
 * <x-theme-style>.
 */
final class UserTheme
{
    public const MODE_LIGHT = 'light';
    public const MODE_DARK = 'dark';
    public const MODE_SOL = 'sol';
    public const MODE_SYSTEM = 'system';

    public const MODES = [
        self::MODE_LIGHT => ['label' => 'Normal', 'hint' => 'Light pages, as SmartWard has always looked'],
        self::MODE_DARK => ['label' => 'Dark', 'hint' => 'Dark pages, easier on the eyes on night shifts'],
        self::MODE_SOL => ['label' => 'Sol', 'hint' => 'Solarized: warm paper tones, softer than white'],
        self::MODE_SYSTEM => ['label' => 'System', 'hint' => 'Follows the device, dark when it is set to dark'],
    ];

    /** Colour choices besides the presets */
    public const COLOURS_HOSPITAL = 'hospital';
    public const COLOURS_CUSTOM = 'custom';

    /** One-click colour themes: primary, secondary and background */
    public const PRESETS = [
        'ocean' => ['name' => 'Ocean', 'primary' => '#2563eb', 'secondary' => '#06b6d4', 'background' => '#2563eb'],
        'royal' => ['name' => 'Royal', 'primary' => '#1e40af', 'secondary' => '#3b82f6', 'background' => '#6366f1'],
        'teal' => ['name' => 'Teal', 'primary' => '#0f766e', 'secondary' => '#14b8a6', 'background' => '#0d9488'],
        'emerald' => ['name' => 'Emerald', 'primary' => '#047857', 'secondary' => '#10b981', 'background' => '#84cc16'],
        'forest' => ['name' => 'Forest', 'primary' => '#166534', 'secondary' => '#65a30d', 'background' => '#ca8a04'],
        'sunset' => ['name' => 'Sunset', 'primary' => '#c2410c', 'secondary' => '#f59e0b', 'background' => '#f97316'],
        'rose' => ['name' => 'Rose', 'primary' => '#be123c', 'secondary' => '#f43f5e', 'background' => '#fb7185'],
        'sakura' => ['name' => 'Sakura', 'primary' => '#be185d', 'secondary' => '#ec4899', 'background' => '#f9a8d4'],
        'lavender' => ['name' => 'Lavender', 'primary' => '#6d28d9', 'secondary' => '#a855f7', 'background' => '#c084fc'],
        'midnight' => ['name' => 'Midnight', 'primary' => '#1e3a8a', 'secondary' => '#7c3aed', 'background' => '#4f46e5'],
        'graphite' => ['name' => 'Graphite', 'primary' => '#334155', 'secondary' => '#64748b', 'background' => '#94a3b8'],
    ];

    /** Shade each colour is anchored at, as in HospitalTheme */
    private const ANCHORS = ['brand' => 600, 'accent' => 500, 'backdrop' => 600];

    // Surfaces, as in tailwind.config.js
    private const DARK_PAGE = '#0f1724';
    private const DARK_CARD = '#1b2433';
    private const SOL_BASE3 = '#fdf6e3';

    /**
     * Each Dark and Sol variable as [shade, base, weight]: that shade of the
     * colour mixed into the base, $weight of the way to it, or [shade] alone.
     * The same mixes tailwind.config.js applies to Tailwind's own hues.
     */
    private const DARK_MIXES = [
        'bg' => [50 => [900, self::DARK_PAGE, 0.3], 100 => [900, self::DARK_CARD, 0.45], 200 => [800, self::DARK_CARD, 0.6], 300 => [700, self::DARK_CARD, 0.7]],
        'line' => [50 => [900, self::DARK_CARD, 0.45], 100 => [800, self::DARK_CARD, 0.5], 200 => [700, self::DARK_CARD, 0.55], 300 => [600, self::DARK_CARD, 0.6]],
        'text' => [500 => [400], 600 => [400], 700 => [300], 800 => [200], 900 => [200], 950 => [100]],
    ];

    private const SOL_MIXES = [
        'bg' => [50 => [50, self::SOL_BASE3, 0.55], 100 => [100, self::SOL_BASE3, 0.75]],
    ];

    /**
     * The user's saved choice, cleaned up: an unknown mode falls back to
     * Normal, and an unknown preset or incomplete custom colours to the
     * hospital's theme.
     *
     * @return array{mode: string, colours: string, primary: ?string, secondary: ?string, background: ?string}
     */
    public static function preferences(?User $user): array
    {
        $saved = is_array($user?->theme) ? $user->theme : [];

        $mode = $saved['mode'] ?? null;
        $mode = is_string($mode) && isset(self::MODES[$mode]) ? $mode : self::MODE_LIGHT;

        $custom = [
            'primary' => HospitalTheme::normalise($saved['primary'] ?? null),
            'secondary' => HospitalTheme::normalise($saved['secondary'] ?? null),
            'background' => HospitalTheme::normalise($saved['background'] ?? null),
        ];

        $colours = $saved['colours'] ?? null;
        $colours = match (true) {
            $colours === self::COLOURS_CUSTOM && !in_array(null, $custom, true) => self::COLOURS_CUSTOM,
            is_string($colours) && isset(self::PRESETS[$colours]) => $colours,
            default => self::COLOURS_HOSPITAL,
        };

        return ['mode' => $mode, 'colours' => $colours] + ($colours === self::COLOURS_CUSTOM ? $custom : array_fill_keys(array_keys($custom), null));
    }

    /** What the profile form posts, as stored on the user */
    public static function fromInput(array $input): array
    {
        $theme = ['mode' => $input['mode'], 'colours' => $input['colours']];

        if ($input['colours'] === self::COLOURS_CUSTOM) {
            foreach (['primary', 'secondary', 'background'] as $which) {
                $theme[$which] = HospitalTheme::normalise($input[$which] ?? null);
            }
        }

        return $theme;
    }

    /** Every value the profile form's colour choice can take */
    public static function colourChoices(): array
    {
        return [self::COLOURS_HOSPITAL, ...array_keys(self::PRESETS), self::COLOURS_CUSTOM];
    }

    public static function mode(?User $user): string
    {
        return self::preferences($user)['mode'];
    }

    /**
     * The colours in effect for the user: their own or a preset's, else the
     * hospital's theme with the background following its primary.
     *
     * @return array{0: string, 1: string, 2: string} primary, secondary, background as #rrggbb
     */
    public static function colours(?User $user, ?Hospital $hospital): array
    {
        $preferences = self::preferences($user);

        if ($preferences['colours'] === self::COLOURS_CUSTOM) {
            return [$preferences['primary'], $preferences['secondary'], $preferences['background']];
        }

        if (isset(self::PRESETS[$preferences['colours']])) {
            $preset = self::PRESETS[$preferences['colours']];

            return [$preset['primary'], $preset['secondary'], $preset['background']];
        }

        [$primary, $secondary] = HospitalTheme::colours($hospital);

        return [$primary, $secondary, $primary];
    }

    /**
     * The CSS <x-theme-style> writes into the page, or null when there is
     * nothing to change: the colours' shade scales when they are not the
     * defaults, and their Dark and Sol variants for the modes the page can
     * show. $preview adds every mode's, for the profile page to try them on.
     */
    public static function css(?User $user, ?Hospital $hospital, bool $preview = false): ?string
    {
        [$primary, $secondary, $background] = self::colours($user, $hospital);
        $mode = self::mode($user);

        $scales = [
            'brand' => HospitalTheme::scale($primary, self::ANCHORS['brand']),
            'accent' => HospitalTheme::scale($secondary, self::ANCHORS['accent']),
            'backdrop' => HospitalTheme::scale($background, self::ANCHORS['backdrop']),
        ];

        // Light: the default colours are already in the compiled CSS, and the
        // backdrop follows the brand there unless it has a colour of its own
        $light = [];
        if ($primary !== HospitalTheme::DEFAULT_PRIMARY || $secondary !== HospitalTheme::DEFAULT_SECONDARY) {
            $light += self::variables('brand', $scales['brand']) + self::variables('accent', $scales['accent']);
        }
        if ($background !== $primary) {
            $light += self::variables('backdrop', $scales['backdrop']);
        }

        // The html:root selectors outrank the compiled defaults however the stylesheets end up ordered
        $css = $light ? 'html:root{' . self::declarations($light) . '}' : '';

        if ($preview || in_array($mode, [self::MODE_DARK, self::MODE_SYSTEM], true)) {
            $css .= '@media screen{html:root[data-theme-mode="dark"]{' . self::declarations(self::modeVariables($scales, self::DARK_MIXES)) . '}}';
        }
        if ($preview || $mode === self::MODE_SOL) {
            $css .= '@media screen{html:root[data-theme-mode="sol"]{' . self::declarations(self::modeVariables($scales, self::SOL_MIXES)) . '}}';
        }

        return $css === '' ? null : $css;
    }

    /** @return array<string, string> --name-shade => "r g b" */
    private static function variables(string $name, array $scale): array
    {
        $variables = [];
        foreach ($scale as $shade => $rgb) {
            $variables["--{$name}-{$shade}"] = $rgb;
        }

        return $variables;
    }

    /** @return array<string, string> --role-name-shade => "r g b" */
    private static function modeVariables(array $scales, array $mixes): array
    {
        $variables = [];
        foreach ($scales as $name => $scale) {
            foreach ($mixes as $role => $shades) {
                foreach ($shades as $shade => $recipe) {
                    $colour = $scale[$recipe[0]];
                    $variables["--{$role}-{$name}-{$shade}"] = isset($recipe[1])
                        ? self::mix($colour, $recipe[1], $recipe[2])
                        : $colour;
                }
            }
        }

        return $variables;
    }

    private static function declarations(array $variables): string
    {
        $css = '';
        foreach ($variables as $name => $value) {
            $css .= "{$name}: {$value};";
        }

        return $css;
    }

    /** "r g b" of a colour mixed into a #rrggbb base, $weight of the way to the colour */
    private static function mix(string $rgb, string $base, float $weight): string
    {
        $colour = array_map('intval', explode(' ', $rgb));
        $base = array_map('hexdec', str_split(substr($base, 1), 2));

        return implode(' ', array_map(
            fn (int $channel, int $baseChannel) => (int) round($channel * $weight + $baseChannel * (1 - $weight)),
            $colour,
            $base
        ));
    }
}
