<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $document->number }}</title>
    @include('pdf.partials.business-invoice-styles-eu')
</head>
<body>
    @php
        $btcPayUrl = $btcPayUrl ?? null;
    @endphp
    <div class="invoice-doc-body">
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
        'btcPayUrl',
        'logoDataUri',
        'signatureStampDataUri',
        'isUs',
    ))

    </div>

    @include('pdf.partials.business-invoice-footer', ['company' => $company, 'footerFixed' => true])
    @include('pdf.partials.business-invoice-page-script')
</body>
</html>
