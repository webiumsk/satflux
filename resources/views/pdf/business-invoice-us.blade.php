<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $document->number }}</title>
    @include('pdf.partials.business-invoice-styles-eu')
</head>
<body>
    <div class="invoice-doc-body">
    @include('pdf.partials.business-invoice-body-eu', array_merge(compact(
        'document',
        'company',
        'contact',
        'lines',
        'taxBreakdown',
        'showVatColumn',
        'showVatBreakdown',
        'showSalesTaxColumn',
        'reverseChargeNote',
        'bankQr',
        'btcPayQr',
        'btcPayUrl',
        'logoDataUri',
        'signatureStampDataUri',
    ), ['isUs' => true]))

    </div>

    @include('pdf.partials.business-invoice-footer', ['company' => $company, 'footerFixed' => true])
    @include('pdf.partials.business-invoice-page-script')
</body>
</html>
