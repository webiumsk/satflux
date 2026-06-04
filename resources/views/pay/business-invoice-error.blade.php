<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('messages.business_invoice_pay_error_title') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 28rem; margin: 3rem auto; padding: 0 1rem; color: #111; text-align: center; }
        h1 { font-size: 1.25rem; color: #b91c1c; }
        .muted { color: #555; font-size: 0.875rem; margin-top: 0.75rem; }
    </style>
</head>
<body>
    <h1>{{ __('messages.business_invoice_pay_error_title') }}</h1>
    <p class="muted">{{ $message }}</p>
</body>
</html>
