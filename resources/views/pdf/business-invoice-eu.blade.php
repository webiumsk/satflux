<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $document->number }}</title>
    @include('pdf.partials.business-invoice-styles-eu')
</head>
<body>
    @include('pdf.partials.business-invoice-body-eu', compact(
        'document',
        'company',
        'contact',
        'lines',
        'taxBreakdown',
        'showVatColumn',
        'showVatBreakdown',
        'reverseChargeNote',
        'bankQr',
        'btcPayQr',
        'logoDataUri',
        'signatureStampDataUri',
        'isUs',
    ))
</body>
</html>
