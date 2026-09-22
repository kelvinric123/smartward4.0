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

    @include('components.autofill-guard')
</head>

<body class="font-sans antialiased bg-gradient-to-br from-blue-50 via-cyan-50 to-teal-50 overflow-hidden">
    <div class="flex h-screen min-h-0" x-data="{ hideNav: false, hideFooter: false }"
        @fullscreenchange.window="hideNav = !!document.fullscreenElement"
        @toggle-custom-fullscreen.window="hideNav = $event.detail.enabled; hideFooter = $event.detail.enabled">
        <div x-show="!hideNav" x-transition:enter="transition ease-out duration-200"
            x-transition:leave="transition ease-in duration-200">
            @include('layouts.navigation')
        </div>

        <div class="flex-1 flex flex-col overflow-hidden min-h-0">
            <!-- Page Heading -->
            @isset($header)
                <header class="{{ request()->routeIs('ward.dashboard') ? 'bg-gradient-to-br from-blue-600 to-cyan-500 shadow-sm border-b border-blue-400' : 'bg-white/80 backdrop-blur-sm shadow-sm border-b border-blue-100' }}">
                    <div class="w-full px-4 sm:px-6 lg:px-8 py-4">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto overscroll-contain min-h-0">
                {{ $slot }}
            </main>

            <!-- Footer -->
            <footer x-show="!hideFooter" x-transition class="bg-white/60 backdrop-blur-sm border-t border-blue-100 py-4"
                :class="hideNav ? 'fixed bottom-0 left-0 right-0 z-50' : ''">
                <div class="w-full px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-600">
                            <span class="font-semibold text-blue-600">PHKL</span> Smart Ward 4.0
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="text-xs text-gray-500">Developed by</span>
                            <a href="https://qmed.asia" target="_blank" class="hover:opacity-75 transition-opacity">
                                <img src="{{ asset('logo_qmed.png') }}" alt="Qmed" class="h-5 w-auto">
                            </a>
                            <span class="text-xs text-gray-400">© 2025</span>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>
    @include('components.delete-passphrase-modal')
</body>

</html>