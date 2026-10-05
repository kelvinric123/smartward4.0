import defaultTheme from 'tailwindcss/defaultTheme';
import colors from 'tailwindcss/colors';
import forms from '@tailwindcss/forms';
import plugin from 'tailwindcss/plugin';

const shades = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];

// brand-* and accent-* are the theme's primary and secondary colours, backdrop-*
// the page background's (App\Support\HospitalTheme, App\Support\UserTheme):
// each shade reads a --name-N variable holding "r g b" channels, so opacity
// modifiers such as bg-brand-600/20 keep working.
const themeColour = (name) => Object.fromEntries(
    shades.map((shade) => [shade, `rgb(var(--${name}-${shade}) / <alpha-value>)`]),
);

const channels = (hex) => [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16)).join(' ');

// The variables default to Tailwind's blue (brand) and cyan (accent), the
// original SmartWard look, and the backdrop follows the brand. A hospital's or
// a user's own theme overrides them from the <x-theme-style> tag in the layouts.
const themeDefaults = plugin(({ addBase }) => {
    addBase({
        ':root': Object.fromEntries(shades.flatMap((shade) => [
            [`--brand-${shade}`, channels(colors.blue[shade])],
            [`--accent-${shade}`, channels(colors.cyan[shade])],
            [`--backdrop-${shade}`, `var(--brand-${shade})`],
        ])),
    });
});

// ---- Display modes ---------------------------------------------------------
// A user picks Normal, Dark or Sol on their profile (App\Support\UserTheme),
// which sets data-theme-mode on <html>. Normal is Tailwind's palette as it is.
// Dark and Sol re-colour it by role rather than view by view, so the views need
// no dark: classes: light fills (bg-*, from-*) turn dark, dark text turns light
// and light borders turn dark, while the saturated middle shades (buttons,
// badges, the sidebar) stay as they are. Each re-colourable shade reads
// --{role}-{palette}-{shade} and falls back to Tailwind's own value, so a page
// with no mode set renders exactly as before. The brand, accent and backdrop
// shades follow the chosen colours, so UserTheme works theirs out with the same
// mixes as the hues below and writes them into the page.
const NEUTRALS = ['slate', 'gray', 'zinc', 'neutral', 'stone'];
const HUES = ['red', 'orange', 'amber', 'yellow', 'lime', 'green', 'emerald', 'teal', 'cyan', 'sky', 'blue', 'indigo', 'violet', 'purple', 'fuchsia', 'pink', 'rose'];
const THEMED = ['brand', 'accent', 'backdrop'];

// The shades each role lets a mode re-colour, for the neutrals and for the hues
// (and the theme's colours). A neutral's dark fills (bg-gray-800 buttons and
// tooltips with white text) are lifted off the dark cards too. Light grey text
// (gray-300, gray-400) is left alone: besides faint text on white it is the
// soft text on the coloured dashboard headers, which must stay light.
const ROLE_SHADES = {
    bg: { neutral: [50, 100, 200, 300, 600, 700, 800, 900, 950], hue: [50, 100, 200, 300] },
    line: { neutral: [50, 100, 200, 300], hue: [50, 100, 200, 300] },
    text: { neutral: [500, 600, 700, 800, 900, 950], hue: [500, 600, 700, 800, 900, 950] },
};

const roleColour = (variable, fallback) => `rgb(var(${variable}, ${fallback}) / <alpha-value>)`;

// One utility family's palette: Tailwind's colours, with the shades its role
// re-colours reading their variable
const rolePalette = (role) => {
    const palette = {};
    for (const name of [...NEUTRALS, ...HUES]) {
        const reColoured = ROLE_SHADES[role][NEUTRALS.includes(name) ? 'neutral' : 'hue'];
        palette[name] = Object.fromEntries(shades.map((shade) => [shade, reColoured.includes(shade)
            ? roleColour(`--${role}-${name}-${shade}`, channels(colors[name][shade]))
            : colors[name][shade]]));
    }
    for (const name of THEMED) {
        palette[name] = Object.fromEntries(shades.map((shade) => [shade, ROLE_SHADES[role].hue.includes(shade)
            ? roleColour(`--${role}-${name}-${shade}`, `var(--${name}-${shade})`)
            : `rgb(var(--${name}-${shade}) / <alpha-value>)`]));
    }
    // bg-white is the surface cards and fields sit on; text-white stays white,
    // since it is the text on the sidebar and on coloured buttons
    if (role === 'bg') {
        palette.white = roleColour('--bg-white', '255 255 255');
    }

    return palette;
};

// "r g b" of $hex mixed into $base, $weight of the way to $hex
const mix = (hex, base, weight) => {
    const [a, b] = [channels(hex), channels(base)].map((value) => value.split(' ').map(Number));
    return a.map((channel, i) => Math.round(channel * weight + b[i] * (1 - weight))).join(' ');
};

// Dark: slate-blue surfaces, the page darker than the cards on it.
// Keep DARK_PAGE, DARK_CARD and the hue mixes in step with UserTheme.
const DARK_PAGE = '#0f1724';
const DARK_CARD = '#1b2433';
const DARK = {
    white: DARK_CARD,
    text: '#e2e8f0',
    neutral: {
        bg: {
            50: '#161e2b', 100: '#232d3d', 200: '#2c3748', 300: '#3a4659',
            // Just off the card, so coloured text on dark panels (from-gray-700 stat bars) stays readable
            600: '#4b5a70', 700: '#3a4659', 800: '#2e3a4d', 900: '#263042', 950: '#212a3a',
        },
        line: { 50: '#222c3b', 100: '#273142', 200: '#303c4f', 300: '#3d4a5e' },
        text: { 500: '#94a3b8', 600: '#b4bfcd', 700: '#cbd5e1', 800: '#e2e8f0', 900: '#f1f5f9', 950: '#f8fafc' },
    },
    // A hue's light fills and borders become its dark shades, its dark text its light ones
    hue: (p) => ({
        bg: { 50: mix(p[900], DARK_PAGE, 0.3), 100: mix(p[900], DARK_CARD, 0.45), 200: mix(p[800], DARK_CARD, 0.6), 300: mix(p[700], DARK_CARD, 0.7) },
        line: { 50: mix(p[900], DARK_CARD, 0.45), 100: mix(p[800], DARK_CARD, 0.5), 200: mix(p[700], DARK_CARD, 0.55), 300: mix(p[600], DARK_CARD, 0.6) },
        text: { 500: channels(p[400]), 600: channels(p[400]), 700: channels(p[300]), 800: channels(p[200]), 900: channels(p[200]), 950: channels(p[100]) },
    }),
};

// Sol: Solarized light, cream paper with blue-grey ink. Keep SOL_BASE3 and the
// hue mixes in step with UserTheme.
const SOL_BASE3 = '#fdf6e3';
const SOL = {
    white: SOL_BASE3,
    text: '#073642',
    neutral: {
        // Dark fills take Solarized's deep teal
        bg: {
            50: '#f8f0dc', 100: '#eee8d5', 200: '#e5dec8', 300: '#d8d0b6',
            600: '#586e75', 700: '#2c4f5a', 800: '#073642', 900: '#002b36', 950: '#00212b',
        },
        line: { 50: '#f1ead6', 100: '#ebe4cf', 200: '#e0d8bf', 300: '#d2c9ac' },
        text: { 500: '#657b83', 600: '#586e75', 700: '#46626b', 800: '#26505b', 900: '#073642', 950: '#002b36' },
    },
    // Pale fills warmed towards the paper
    hue: (p) => ({
        bg: { 50: mix(p[50], SOL_BASE3, 0.55), 100: mix(p[100], SOL_BASE3, 0.75) },
    }),
};

const modeVariables = (mode) => {
    const variables = { '--bg-white': channels(mode.white), '--text-default': channels(mode.text) };
    for (const name of NEUTRALS) {
        for (const [role, values] of Object.entries(mode.neutral)) {
            for (const [shade, hex] of Object.entries(values)) {
                variables[`--${role}-${name}-${shade}`] = channels(hex);
            }
        }
    }
    for (const name of HUES) {
        for (const [role, values] of Object.entries(mode.hue(colors[name]))) {
            for (const [shade, rgb] of Object.entries(values)) {
                variables[`--${role}-${name}-${shade}`] = rgb;
            }
        }
    }

    return variables;
};

// Screen only, so printing a page always comes out on white paper
const displayModes = plugin(({ addBase }) => {
    addBase({
        '@media screen': {
            '[data-theme-mode="dark"]': { ...modeVariables(DARK), colorScheme: 'dark' },
            '[data-theme-mode="sol"]': { ...modeVariables(SOL), colorScheme: 'light' },
        },
    });
});

/** @type {import('tailwindcss').Config} */
export default {
    // Dark mode comes from the palette variables above, not from dark: classes.
    // Tailwind's default 'media' strategy would switch every dark: utility on
    // whenever the OS is in dark mode, which turned cards and form fields black
    // on otherwise light pages. Gating them behind a .dark class that the app
    // never sets keeps rendering consistent.
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                // Using system fonts for offline/local hosting compatibility
                sans: ['-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'Helvetica Neue', 'Arial', 'sans-serif'],
            },
            colors: {
                brand: themeColour('brand'),
                accent: themeColour('accent'),
                backdrop: themeColour('backdrop'),
            },
            backgroundColor: rolePalette('bg'),
            gradientColorStops: rolePalette('bg'),
            textColor: rolePalette('text'),
            placeholderColor: rolePalette('text'),
            borderColor: { ...rolePalette('line'), DEFAULT: 'rgb(var(--line-gray-200, 229 231 235))' },
            divideColor: rolePalette('line'),
            ringColor: rolePalette('line'),
            outlineColor: rolePalette('line'),
            textDecorationColor: rolePalette('line'),
        },
    },

    plugins: [forms, themeDefaults, displayModes],
};
