import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            // chart.js, sortable.js and qr-scanner.js: libraries only some pages need, bundled rather
            // than taken from a CDN, so a hospital network with no internet has them
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/chart.js',
                'resources/js/sortable.js',
                'resources/js/qr-scanner.js',
                // QR codes for the AI Nurse Schedule's view-only links
                'resources/js/qrcode.js',
            ],
            refresh: true,
        }),
    ],
});
