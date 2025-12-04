<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts - Using system fonts for local hosting -->
        <style>
            .font-sans {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            }
        </style>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gradient-to-br from-blue-500 via-cyan-500 to-teal-500 relative overflow-hidden">
            <!-- Decorative elements -->
            <div class="absolute inset-0 overflow-hidden">
                <div class="absolute -top-40 -right-40 w-80 h-80 bg-white/10 rounded-full blur-3xl"></div>
                <div class="absolute top-1/2 -left-40 w-80 h-80 bg-cyan-300/20 rounded-full blur-3xl"></div>
                <div class="absolute -bottom-40 right-1/3 w-80 h-80 bg-blue-300/20 rounded-full blur-3xl"></div>
            </div>
            
            <div class="relative z-10">
                <div class="text-center mb-8">
                    <img src="{{ asset('logo_phkl.webp') }}" alt="PHKL Logo" class="h-24 w-auto mx-auto mb-4">
                    <h1 class="text-3xl font-bold text-white mb-2">PHKL Hospital</h1>
                    <p class="text-blue-100">Management System</p>
                </div>

                <div class="w-full sm:max-w-md px-8 py-8 bg-white/95 backdrop-blur-lg shadow-2xl overflow-hidden rounded-2xl border border-white/20">
                    {{ $slot }}
                </div>
                
                <!-- Footer -->
                <div class="mt-8 text-center">
                    <div class="flex items-center justify-center space-x-2 text-white/90">
                        <span class="text-sm">Developed by</span>
                        <img src="{{ asset('logo qmed.png') }}" alt="Qmed" class="h-6 w-auto">
                    </div>
                    <p class="text-xs text-white/70 mt-2">© 2025 Qmed.asia. All Rights Reserved</p>
                </div>
            </div>
        </div>
    </body>
</html>
