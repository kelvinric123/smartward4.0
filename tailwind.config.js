import defaultTheme from 'tailwindcss/defaultTheme';
import colors from 'tailwindcss/colors';
import forms from '@tailwindcss/forms';
import plugin from 'tailwindcss/plugin';

const shades = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];

// brand-* and accent-* are the hospital's theme colours (App\Support\HospitalTheme):
// each shade reads a --brand-N / --accent-N variable holding "r g b" channels,
// so opacity modifiers such as bg-brand-600/20 keep working.
const themeColour = (name) => Object.fromEntries(
    shades.map((shade) => [shade, `rgb(var(--${name}-${shade}) / <alpha-value>)`]),
);

const channels = (hex) => [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16)).join(' ');

// The variables default to Tailwind's blue (brand) and cyan (accent), the
// original SmartWard look. A hospital's own theme overrides them from the
// <x-theme-style> tag in the layouts.
const themeDefaults = plugin(({ addBase }) => {
    addBase({
        ':root': Object.fromEntries(shades.flatMap((shade) => [
            [`--brand-${shade}`, channels(colors.blue[shade])],
            [`--accent-${shade}`, channels(colors.cyan[shade])],
        ])),
    });
});

/** @type {import('tailwindcss').Config} */
export default {
    // The app has a single light theme (see the fixed gradient on <body> in
    // layouts/app.blade.php). Tailwind's default 'media' strategy would switch
    // every dark: utility on whenever the OS is in dark mode, which turned
    // cards and form fields black on otherwise light pages. Gating them behind
    // a .dark class that the app never sets keeps rendering consistent.
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
            },
        },
    },

    plugins: [forms, themeDefaults],
};
