<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.spa-head')

    @vite(['resources/css/public.css', 'resources/js/public.ts'])

    @php
        $lnurlAuthEnabled = config('services.lnurl_auth.enabled', false);
    @endphp
</head>

<body class="font-sans antialiased">
    @if(!empty($showLandingShell))
        @include('partials.landing-hero-shell')
    @endif

    <div
        id="app"
        class="min-h-dvh{{ !empty($showLandingShell) ? ' sf-app-pending' : '' }}"
        data-spa="public"
        data-lnurl-auth-enabled="{{ $lnurlAuthEnabled ? 'true' : 'false' }}"
    ></div>
</body>

</html>
