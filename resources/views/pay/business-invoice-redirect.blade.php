<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('messages.business_invoice_pay_title') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 26rem; margin: 2.5rem auto; padding: 0 1.25rem; color: #111; text-align: center; line-height: 1.45; }
        h1 { font-size: 1.25rem; margin-bottom: 0.35rem; }
        .amount { font-size: 1.5rem; font-weight: 700; margin: 1rem 0 1.25rem; }
        .notice { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e3a8a; font-size: 0.875rem; padding: 0.85rem 1rem; border-radius: 0.5rem; text-align: left; margin-bottom: 1.25rem; }
        .muted { color: #555; font-size: 0.875rem; }
        a.btn { display: block; width: 100%; box-sizing: border-box; margin-top: 0.5rem; padding: 0.9rem 1.25rem; background: #059669; color: #fff; text-decoration: none; border-radius: 0.5rem; font-weight: 600; font-size: 1rem; }
        a.btn:hover { background: #047857; }
    </style>
</head>
<body>
    <h1>{{ __('messages.business_invoice_pay_title') }}</h1>
    @if($document->number)
        <p class="muted">{{ __('Invoice') }} {{ $document->number }}</p>
    @endif
    <p class="amount">{{ number_format((float) $document->total, 2, ',', ' ') }} {{ $document->currency }}</p>

    <div class="notice">
        {{ __('messages.business_invoice_pay_link_notice') }}
    </div>

    <a class="btn" href="{{ $checkoutLink }}">{{ __('messages.business_invoice_pay_continue') }}</a>
    <p class="muted" style="margin-top: 1rem;">{{ __('messages.business_invoice_pay_checkout_hint') }}</p>
</body>
</html>
