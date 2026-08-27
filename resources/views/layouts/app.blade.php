<!DOCTYPE html>
@php
    $preferences = auth()->user()?->preference;
    $theme = $preferences?->theme?->value ?? 'SYSTEM';
    $density = $preferences?->density?->value ?? 'COMFORTABLE';
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="theme-{{ strtolower($theme) }} density-{{ strtolower($density) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'PlanOps') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <a class="skip-link" href="#main-content">Skip to main content</a>
        <div class="min-h-screen app-shell" x-data="{ mobileOpen: false, railCollapsed: false }" :class="{ 'rail-collapsed': railCollapsed }">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="page-header">
                    <div class="page-header-inner">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main id="main-content" class="app-content">
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
