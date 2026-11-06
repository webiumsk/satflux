@php
    $homepageVariant = \App\Support\HomepageVariant::resolve();
    $homepageDemoPosUrl = \App\Support\HomepageDemoUrl::resolve();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" style="background-color:#111827;color:#fff">

<head>
    @include('partials.spa-head')

    @vite(['resources/css/public.css', 'resources/js/public.ts'])

    @php
        $lnurlAuthEnabled = config('services.lnurl_auth.enabled', false);
    @endphp
</head>

<body class="font-sans antialiased bg-gray-900 text-white">
    @if(!empty($showLandingShell))
        @if($homepageVariant === 'showcase')
            @include('partials.landing-showcase-shell')
        @else
            @include('partials.landing-hero-shell')
        @endif
    @endif

    <div
        id="app"
        class="min-h-dvh{{ !empty($showLandingShell) ? ' sf-app-pending' : '' }}"
        data-spa="public"
        data-homepage-variant="{{ $homepageVariant }}"
        data-homepage-demo-pos-url="{{ $homepageDemoPosUrl }}"
        data-lnurl-auth-enabled="{{ $lnurlAuthEnabled ? 'true' : 'false' }}"
    ></div>
</body>

</html>
