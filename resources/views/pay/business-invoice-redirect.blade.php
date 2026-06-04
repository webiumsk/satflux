<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="2;url={{ $checkoutLink }}">
    <title>{{ __('messages.business_invoice_pay_title') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 28rem; margin: 3rem auto; padding: 0 1rem; color: #111; text-align: center; }
        h1 { font-size: 1.25rem; margin-bottom: 0.5rem; }
        .amount { font-size: 1.5rem; font-weight: 700; margin: 1rem 0; }
        .muted { color: #555; font-size: 0.875rem; }
        a.btn { display: inline-block; margin-top: 1.5rem; padding: 0.75rem 1.25rem; background: #059669; color: #fff; text-decoration: none; border-radius: 0.5rem; font-weight: 600; }
    </style>
</head>
<body>
    <h1>{{ __('messages.business_invoice_pay_title') }}</h1>
    @if($document->number)
        <p class="muted">{{ __('Invoice') }} {{ $document->number }}</p>
    @endif
    <p class="amount">{{ number_format((float) $document->total, 2, ',', ' ') }} {{ $document->currency }}</p>
    <p class="muted">{{ __('messages.business_invoice_pay_redirecting') }}</p>
    <a class="btn" href="{{ $checkoutLink }}">{{ __('messages.business_invoice_pay_continue') }}</a>
    <script>
        setTimeout(function () { window.location.href = @json($checkoutLink); }, 1500);
    </script>
</body>
</html>
