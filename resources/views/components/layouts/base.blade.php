{{--
    The HTML shell every page shares: fonts, CSS and JS, cream background.
    Pages don't use this directly; they use <x-layouts.customer> or <x-layouts.dashboard>.
--}}
@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>

    {{-- Installable on phones ("Add to Home screen"): public/manifest.webmanifest and public/sw.js. --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="{{ config('foodloop.theme_colour') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="FoodLoop">

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body {{ $attributes->class('min-h-screen bg-cream font-sans text-forest antialiased') }}>
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-50 focus:rounded-xl focus:bg-forest focus:px-4 focus:py-3 focus:text-cream">
        Skip to content
    </a>

    {{ $slot }}
</body>
</html>
