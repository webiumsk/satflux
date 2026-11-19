<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="satflux-app-url" content="{{ config('app.url') }}">

@php
    $siteName = config('app.name', 'satflux.io');
    $pageTitle = isset($seoTitle) ? $seoTitle.' - '.$siteName : $siteName.' - Accept Bitcoin Without Limits';
    $socialTitle = isset($seoTitle) ? $pageTitle : $siteName.' - Bitcoin Payment Control Panel';
    $pageDescription = $seoDescription ?? 'Accept Bitcoin & Lightning payments without limits. Non-custodial BTCPay Server control panel with multi-store management, PoS terminals, and advanced analytics.';
    $pageUrl = $canonicalUrl ?? url()->current();
@endphp
<title>{{ $pageTitle }}</title>
<meta name="description" content="{{ $pageDescription }}">

<link rel="icon" type="image/png" sizes="32x32" href="/favicon.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon.png">
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="192x192" href="/favicon.png">
<link rel="icon" type="image/png" sizes="512x512" href="/favicon.png">

@if(!empty($noIndex))
<meta name="robots" content="noindex" data-documentation-noindex>
@else
<link rel="canonical" href="{{ $pageUrl }}">
@endif
@isset($markdownUrl)
<link rel="alternate" type="text/markdown" href="{{ $markdownUrl }}" data-documentation-markdown>
@endisset

<meta property="og:type" content="website">
<meta property="og:url" content="{{ $pageUrl }}">
<meta property="og:title" content="{{ $socialTitle }}">
<meta property="og:description" content="{{ $pageDescription }}">
<meta property="og:image" content="{{ config('app.url') }}/og-image.webp">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:url" content="{{ $pageUrl }}">
<meta name="twitter:title" content="{{ $socialTitle }}">
<meta name="twitter:description" content="{{ $pageDescription }}">
<meta name="twitter:image" content="{{ config('app.url') }}/og-image.webp">

@if(config('services.matomo.url') && config('services.matomo.site_id'))
<meta name="satflux-matomo-url" content="{{ rtrim((string) config('services.matomo.url'), '/') }}">
<meta name="satflux-matomo-site-id" content="{{ config('services.matomo.site_id') }}">
@endif

@if(config('services.chorala.project_key'))
<meta name="satflux-chorala-key" content="{{ config('services.chorala.project_key') }}">
<meta name="satflux-chorala-widget-url" content="{{ config('services.chorala.widget_url') }}">
<meta name="satflux-chorala-use-proxy" content="{{ config('services.chorala.use_proxy', false) ? 'true' : 'false' }}">
@endif
