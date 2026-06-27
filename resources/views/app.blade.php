<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.spa-head')

    @vite(['resources/css/app.css', 'resources/js/app.ts'])

    @php
        $lnurlAuthEnabled = config('services.lnurl_auth.enabled', false);
    @endphp
</head>

<body class="font-sans antialiased">
    @if(isset($page))
        <div id="app" class="min-h-dvh" data-page="{{ is_array($page) ? json_encode($page) : $page }}" data-lnurl-auth-enabled="{{ $lnurlAuthEnabled ? 'true' : 'false' }}"></div>
    @else
        <div id="app" class="min-h-dvh" data-lnurl-auth-enabled="{{ $lnurlAuthEnabled ? 'true' : 'false' }}"></div>
    @endif
</body>

</html>
