<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">

<title>{{ config('app.name', 'satflux.io') }} - Accept Bitcoin Without Limits</title>
<meta name="description" content="Accept Bitcoin & Lightning payments without limits. Non-custodial BTCPay Server control panel with multi-store management, PoS terminals, and advanced analytics.">

<link rel="icon" type="image/png" sizes="32x32" href="/favicon.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon.png">
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="192x192" href="/favicon.png">
<link rel="icon" type="image/png" sizes="512x512" href="/favicon.png">

<link rel="canonical" href="{{ url()->current() }}">

<meta property="og:type" content="website">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:title" content="{{ config('app.name', 'satflux.io') }} - Bitcoin Payment Control Panel">
<meta property="og:description" content="Accept Bitcoin & Lightning payments without limits. Non-custodial BTCPay Server control panel with multi-store management, PoS terminals, and advanced analytics.">
<meta property="og:image" content="{{ config('app.url') }}/og-image.webp">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:url" content="{{ url()->current() }}">
<meta name="twitter:title" content="{{ config('app.name', 'satflux.io') }} - Bitcoin Payment Control Panel">
<meta name="twitter:description" content="Accept Bitcoin & Lightning payments without limits. Non-custodial BTCPay Server control panel with multi-store management, PoS terminals, and advanced analytics.">
<meta name="twitter:image" content="{{ config('app.url') }}/og-image.webp">

@if(config('services.matomo.url') && config('services.matomo.site_id'))
<meta name="satflux-matomo-url" content="{{ rtrim((string) config('services.matomo.url'), '/') }}">
<meta name="satflux-matomo-site-id" content="{{ config('services.matomo.site_id') }}">
@endif
