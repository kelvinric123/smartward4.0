@php
    $hospital = \App\Models\Hospital::first();
@endphp
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
    <x-theme-style :hospital="$hospital" />
</head>

<body class="font-sans text-gray-900 antialiased bg-slate-50">
    @php
        $logoUrl = $hospital && $hospital->login_logo_path ? \Illuminate\Support\Facades\Storage::url($hospital->login_logo_path) : ($hospital && $hospital->logo_path ? \Illuminate\Support\Facades\Storage::url($hospital->logo_path) : asset('phkl_new.png'));
        $hospitalName = $hospital ? $hospital->name : 'PHKL Hospital';
        $hospitalDescription = $hospital && $hospital->description ? $hospital->description : 'Management System';
    @endphp

    <div class="min-h-screen flex flex-col lg:flex-row">

        <!-- Brand panel: a compact header on phones/tablets, the left half on desktops -->
        <aside
            class="relative overflow-hidden bg-gradient-to-br from-brand-700 via-accent-600 to-accent-500 text-white lg:w-[44%] xl:w-1/2 lg:h-screen lg:sticky lg:top-0">
            <!-- Decorative background -->
            <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                <div class="absolute -top-32 -right-24 h-80 w-80 rounded-full bg-white/10 blur-3xl"></div>
                <div class="absolute top-1/2 -left-32 h-96 w-96 rounded-full bg-cyan-300/20 blur-3xl"></div>
                <div class="absolute -bottom-40 right-1/4 h-96 w-96 rounded-full bg-blue-400/20 blur-3xl"></div>
                <svg class="absolute inset-0 h-full w-full opacity-[0.07]" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <pattern id="guest-grid" width="32" height="32" patternUnits="userSpaceOnUse">
                            <path d="M32 0H0v32" fill="none" stroke="white" stroke-width="1" />
                        </pattern>
                    </defs>
                    <rect width="100%" height="100%" fill="url(#guest-grid)" />
                </svg>
            </div>

            <div
                class="relative z-10 flex h-full flex-col px-5 py-5 sm:px-8 sm:py-7 lg:overflow-y-auto lg:px-12 lg:py-10 xl:px-16 xl:py-12">
                <!-- Logo (+ name on small screens) -->
                <div class="flex items-center gap-4 lg:block">
                    <div
                        class="inline-flex shrink-0 items-center justify-center rounded-2xl bg-white/10 p-2.5 ring-1 ring-white/25 backdrop-blur-sm sm:p-3 lg:p-4">
                        <img src="{{ $logoUrl }}" alt="{{ $hospitalName }} logo"
                            class="h-10 w-auto max-w-[9rem] object-contain sm:h-14 sm:max-w-[12rem] lg:h-20 lg:max-w-[16rem] xl:h-24">
                    </div>
                    <div class="min-w-0 lg:hidden">
                        <p class="line-clamp-2 text-base font-bold leading-tight sm:text-xl">{{ $hospitalName }}</p>
                        <p class="mt-0.5 line-clamp-1 text-xs text-white/80 sm:text-sm">{{ $hospitalDescription }}</p>
                    </div>
                </div>

                <!-- Welcome copy (desktop only) -->
                <div class="hidden flex-1 flex-col justify-center py-10 lg:flex">
                    <span
                        class="inline-flex w-fit items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-wider ring-1 ring-white/25">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-300"></span>
                        SmartWard 4.0
                    </span>
                    <h1 class="mt-5 text-3xl font-bold leading-tight xl:text-4xl 2xl:text-5xl">{{ $hospitalName }}</h1>
                    <p class="mt-4 max-w-xl text-base leading-relaxed text-white/85 xl:text-lg">{{ $hospitalDescription }}</p>

                    <ul class="mt-8 hidden max-w-md space-y-4 text-sm text-white/90 xl:text-base [@media(min-height:720px)]:block">
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-white/15 ring-1 ring-white/20">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12h4l3-9 4 18 3-9h4" />
                                </svg>
                            </span>
                            <span>Live bedside and device data for every patient</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-white/15 ring-1 ring-white/20">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                </svg>
                            </span>
                            <span>Charting, medications and orders in one place</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-white/15 ring-1 ring-white/20">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </span>
                            <span>Secure, role-based access for every staff member</span>
                        </li>
                    </ul>
                </div>

                <p class="hidden text-xs text-white/70 lg:block">&copy; {{ date('Y') }} {{ $hospitalName }}</p>
            </div>
        </aside>

        <!-- Form panel -->
        <main class="flex flex-1 flex-col">
            <div class="flex flex-1 flex-col px-4 py-6 sm:px-6 sm:py-10 lg:px-10">
                <!-- my-auto centres the card when there is room and lets the page scroll when there isn't -->
                <div class="mx-auto my-auto w-full max-w-md">
                    <div
                        class="rounded-2xl bg-white px-5 py-6 shadow-xl shadow-slate-900/5 ring-1 ring-slate-900/5 sm:px-8 sm:py-8">
                        {{ $slot }}
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <footer class="px-4 pb-6 text-center">
                <div class="flex items-center justify-center gap-2 text-slate-500">
                    <span class="text-xs">Developed by</span>
                    <img src="{{ asset('logo_qmed.png') }}" alt="Qmed" class="h-5 w-auto">
                </div>
                <p class="mt-1.5 text-xs text-slate-400">&copy; {{ date('Y') }} Qmed.asia. All Rights Reserved</p>
            </footer>
        </main>
    </div>
</body>

</html>
