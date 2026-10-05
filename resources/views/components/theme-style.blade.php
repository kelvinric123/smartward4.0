@props(['hospital' => null])

{{--
    The page's look: the signed-in user's display mode and colours, else the
    hospital's theme (App\Support\UserTheme, App\Support\HospitalTheme), for the
    brand-*, accent-* and backdrop-* classes and Tailwind's palette. Nothing is
    written for the default theme in Normal mode. Goes in <head>, after @vite.
--}}
@php
    $themeUser = auth()->user();
    $themeHospital = $hospital ?? once(fn () => \App\Models\Hospital::first());
    $themeMode = \App\Support\UserTheme::mode($themeUser);
    // The profile page tries every mode on before it is saved
    $themeCss = \App\Support\UserTheme::css($themeUser, $themeHospital, request()->routeIs('profile.edit'));
@endphp
@if ($themeMode !== \App\Support\UserTheme::MODE_LIGHT)
    <script>
        // Set before the page is drawn, so it never flashes in the other mode
        (function (mode, root) {
            if (mode !== 'system') {
                root.setAttribute('data-theme-mode', mode);
                return;
            }
            var dark = window.matchMedia('(prefers-color-scheme: dark)');
            var follow = function () { root.setAttribute('data-theme-mode', dark.matches ? 'dark' : 'light'); };
            follow();
            dark.addEventListener ? dark.addEventListener('change', follow) : dark.addListener(follow);
        })(@js($themeMode), document.documentElement);
    </script>
@endif
@if ($themeCss)
    <style>{!! $themeCss !!}</style>
@endif
