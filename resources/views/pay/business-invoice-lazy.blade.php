<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>{{ __('messages.business_invoice_pay_title') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 28rem; margin: 3rem auto; padding: 0 1.25rem; color: #111827; line-height: 1.5; text-align: center; }
        h1 { font-size: 1.4rem; }
        .amount { font-size: 1.75rem; font-weight: 700; }
        .muted { color: #4b5563; font-size: .9rem; }
        .notice { padding: 1rem; background: #eff6ff; border-radius: .5rem; text-align: left; }
        button { width: 100%; padding: 1rem; margin-top: 1rem; border: 0; border-radius: .5rem; background: #047857; color: white; font: inherit; font-weight: 600; cursor: pointer; }
        button:hover { background: #065f46; }
        button:focus-visible { outline: 3px solid #2563eb; outline-offset: 3px; }
    </style>
</head>
<body>
    @if($state === 'paid')
        <h1>{{ __('messages.business_invoice_pay_already_paid') }}</h1>
    @elseif($state === 'ready' && $actionUrl)
        <h1>{{ __('messages.business_invoice_pay_title') }}</h1>
        @if($number)
            <p class="muted">{{ $number }}</p>
        @endif
        <p class="amount">{{ number_format((float) $amount, 2, ',', ' ') }} {{ $currency }}</p>
        <p class="notice">{{ __('messages.business_invoice_pay_link_notice') }}</p>
        <p class="muted">{{ __('messages.business_invoice_pay_prepare_notice') }}</p>
        <form method="POST" action="{{ $actionUrl }}">
            @csrf
            <button type="submit">{{ __('messages.business_invoice_pay_continue') }}</button>
        </form>
    @else
        <h1>{{ __('messages.business_invoice_pay_unavailable') }}</h1>
        <p>{{ __('messages.business_invoice_pay_contact_issuer') }}</p>
    @endif
</body>
</html>
