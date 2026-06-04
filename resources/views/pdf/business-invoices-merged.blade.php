<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Invoice') }}</title>
    @include('pdf.partials.business-invoice-styles-eu')
    <style>
        .invoice-page { page-break-after: always; }
        .invoice-page:last-child { page-break-after: auto; }
    </style>
</head>
<body>
@foreach($pages as $page)
    @php
        $document = $page['document'];
        $company = $page['company'];
        $contact = $page['contact'];
        $lines = $page['lines'];
        $bankQr = $page['bankQr'] ?? null;
        $btcPayQr = $page['btcPayQr'] ?? null;
        $logoDataUri = $page['logoDataUri'] ?? null;
        $signatureStampDataUri = $page['signatureStampDataUri'] ?? null;
    @endphp
    <div class="invoice-page">
        @include('pdf.partials.business-invoice-body-eu', compact('document', 'company', 'contact', 'lines', 'bankQr', 'btcPayQr', 'logoDataUri', 'signatureStampDataUri'))
    </div>
@endforeach
</body>
</html>
