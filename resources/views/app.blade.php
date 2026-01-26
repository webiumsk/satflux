<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'satflux.io') }}</title>

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ config('app.url') }}">
    <meta property="og:title" content="{{ config('app.name', 'satflux.io') }} - Bitcoin Payment Control Panel">
    <meta property="og:description" content="Accept Bitcoin & Lightning payments without limits. Non-custodial BTCPay Server control panel with multi-store management, PoS terminals, and advanced analytics.">
    <meta property="og:image" content="{{ config('app.url') }}/og-image.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="{{ config('app.url') }}">
    <meta name="twitter:title" content="{{ config('app.name', 'satflux.io') }} - Bitcoin Payment Control Panel">
    <meta name="twitter:description" content="Accept Bitcoin & Lightning payments without limits. Non-custodial BTCPay Server control panel with multi-store management, PoS terminals, and advanced analytics.">
    <meta name="twitter:image" content="{{ config('app.url') }}/og-image.png">

    @vite(['resources/css/app.css', 'resources/js/app.ts'])

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body class="font-sans antialiased">
    <div id="app"></div>
</body>

</html>