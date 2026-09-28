@props(['hospital' => null])

{{-- The hospital's theme colours for the brand-* and accent-* classes (App\Support\HospitalTheme); nothing for the default theme --}}
@php($themeCss = \App\Support\HospitalTheme::css($hospital))
@if ($themeCss)
    <style>{!! $themeCss !!}</style>
@endif
