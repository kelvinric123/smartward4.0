import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

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
        },
    },

    plugins: [forms],
};
