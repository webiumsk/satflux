<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('messages.business_invoice_pay_already_paid') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 28rem; margin: 3rem auto; padding: 0 1rem; color: #111; text-align: center; }
        h1 { font-size: 1.25rem; color: #059669; }
        .muted { color: #555; font-size: 0.875rem; margin-top: 0.5rem; }
    </style>
</head>
<body>
    <h1>{{ __('messages.business_invoice_pay_already_paid') }}</h1>
    @if($document->number)
        <p class="muted">{{ __('Invoice') }} {{ $document->number }}</p>
    @endif
</body>
</html>
