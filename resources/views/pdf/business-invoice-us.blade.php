<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $document->number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 20px; margin: 0 0 8px; }
        .row { width: 100%; margin-bottom: 16px; }
        .col { display: inline-block; vertical-align: top; width: 48%; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; }
        .totals { margin-top: 12px; text-align: right; }
        .qr-block { margin-top: 20px; }
        .qr-block img { width: 160px; height: 160px; }
        .qr-hint { font-size: 9px; color: #6b7280; margin-top: 4px; max-width: 160px; line-height: 1.25; }
        .muted { color: #555; font-size: 11px; }
    </style>
</head>
<body>
    <h1>Invoice {{ $document->number }}</h1>
    @if($document->title)
        <p class="muted">{{ $document->title }}</p>
    @endif

    @include('pdf.partials.business-invoice-body-us')
</body>
</html>
