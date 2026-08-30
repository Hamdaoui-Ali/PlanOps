<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>PlanOps</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="public-body auth-body">
        <a class="skip-link" href="#main-content">Skip to main content</a>
        <div class="auth-shell">
            <header class="auth-nav"><a href="{{ url('/') }}" class="public-brand"><x-application-logo /></a><a href="{{ url('/') }}" class="auth-home-link">Back to home <span aria-hidden="true">↗</span></a></header>
            <main id="main-content" class="auth-main"><section class="auth-intro" aria-label="PlanOps introduction"><p class="public-eyebrow"><span class="public-eyebrow-dot"></span> Your work, organized</p><h1>Make progress<br><span>visible.</span></h1><p>Plan, track, and complete the work that moves your goals forward.</p><div class="auth-rule"></div><span class="auth-caption">Personal work operations<br>without the overhead.</span></section><section class="auth-card" aria-label="Account form">{{ $slot }}</section></main>
            <footer class="auth-footer"><span>PlanOps</span><span>Track the work. See the progress.</span></footer>
        </div>
    </body>
</html>
