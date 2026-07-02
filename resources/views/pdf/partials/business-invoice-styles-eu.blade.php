<style>
    @page { margin: 28px 32px 32px; }
    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 10px;
        color: #1a1a1a;
        line-height: 1.45;
        margin: 0;
    }
    .section-label {
        font-size: 8px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #6b7280;
        margin-bottom: 4px;
    }
    .divider {
        border: none;
        border-top: 1px dotted #b8c0cc;
        margin: 12px 0;
        height: 0;
    }
    .header-table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
    .header-table td { vertical-align: top; padding: 0; }
    .supplier-col { width: 38%; font-size: 9px; color: #374151; }
    .logo-col { width: 24%; text-align: center; vertical-align: middle; }
    .logo-col img { max-height: 52px; max-width: 160px; }
    .title-col { width: 38%; text-align: right; }
    .doc-title {
        font-size: 22px;
        font-weight: bold;
        color: #111827;
        margin: 0;
        line-height: 1.15;
    }
    .doc-subtitle {
        font-size: 10px;
        color: #4b5563;
        margin-top: 6px;
    }
    .meta-table { width: 100%; border-collapse: collapse; }
    .meta-table td { vertical-align: top; padding: 0; font-size: 10px; }
    .bank-col { width: 48%; padding-right: 16px; color: #374151; }
    .bank-col .label { color: #6b7280; }
    .customer-col { width: 52%; }
    .customer-name { font-size: 12px; font-weight: bold; color: #111827; margin-bottom: 2px; }
    .dates-table { width: 100%; margin-top: 10px; }
    .dates-table td { padding: 2px 0; font-size: 10px; }
    .dates-table .date-label { text-align: right; padding-right: 8px; color: #6b7280; white-space: nowrap; }
    .dates-table .date-value { text-align: right; font-weight: 600; color: #111827; }
    .lines-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 4px;
        font-size: 10px;
    }
    .lines-table thead th {
        border-bottom: 2px solid #1f2937;
        padding: 8px 6px;
        font-size: 8px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: #374151;
        background: transparent;
    }
    .lines-table tbody td {
        border-bottom: 1px solid #e5e7eb;
        padding: 8px 6px;
        vertical-align: top;
    }
    .lines-table .col-qty,
    .lines-table .col-unit,
    .lines-table .col-price,
    .lines-table .col-total { text-align: right; white-space: nowrap; }
    .lines-table .col-unit { text-align: center; }
    .line-desc { font-size: 9px; color: #6b7280; margin-top: 2px; }
    .footer-table { width: 100%; border-collapse: collapse; margin-top: 16px; }
    .footer-table td { vertical-align: top; padding: 0; }
    .note-col { width: 55%; padding-right: 20px; font-size: 9px; color: #4b5563; }
    .totals-col { width: 45%; }
    .totals-box { width: 100%; border-collapse: collapse; font-size: 10px; }
    .totals-box td { padding: 4px 0; }
    .totals-box .label { text-align: left; color: #374151; }
    .totals-box .value { text-align: right; white-space: nowrap; }
    .totals-box .grand td { font-size: 12px; font-weight: bold; padding-top: 8px; border-top: 1px solid #d1d5db; }
    .totals-box .due td { font-size: 11px; font-weight: bold; color: #111827; }
    .totals-box .paid-row .value { color: #047857; }
    .signature-block { margin-top: 20px; text-align: right; }
    .signature-block img { max-height: 72px; max-width: 220px; }
    .signature-placeholder {
        display: inline-block;
        text-align: left;
        font-size: 9px;
        color: #9ca3af;
    }
    .signature-line {
        height: 40px;
        border-bottom: 1px solid #d1d5db;
        width: 200px;
        margin-top: 4px;
    }
    .pay-bar {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        background: #d9edf7;
        border: 1px solid #b8d4e8;
    }
    .pay-bar td {
        padding: 10px 12px;
        font-size: 9px;
        color: #1e3a5f;
        vertical-align: top;
        width: 25%;
    }
    .pay-bar strong {
        display: block;
        font-size: 8px;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: #4b6a8a;
        margin-bottom: 3px;
        font-weight: bold;
    }
    .pay-bar .amount { font-size: 12px; font-weight: bold; color: #111827; }
    .issuer-line { margin-top: 14px; font-size: 9px; color: #6b7280; }
    .invoice-doc-footer {
        margin-top: 16px;
        border-collapse: collapse;
    }
    .qr-block { margin-top: 12px; }
    .qr-col { vertical-align: top; width: 132px; }
    .qr-gap { font-size: 1px; line-height: 1px; }
    .qr-frame {
        width: 132px;
        height: 132px;
        border: 1.5px solid #00a0e3;
        background: #ffffff;
        padding: 0;
    }
    .qr-frame-pbs { border-color: #00a0e3; }
    .qr-frame-btc { border-color: #f7931a; }
    .qr-frame img {
        width: 110px;
        height: 110px;
        display: block;
        margin: 0 auto;
    }
    .qr-frame a { text-decoration: none; }
    .qr-caption {
        width: 132px;
        margin-top: 5px;
        text-align: center;
        font-size: 9px;
        line-height: 1.3;
        color: #374151;
    }
    .qr-caption strong { font-size: 10px; }
    .qr-caption-pbs strong { color: #00a0e3; }
    .qr-caption-btc strong { color: #f7931a; }
    a.qr-item-link { text-decoration: none; color: inherit; }
    .muted { color: #6b7280; }
    .note-above { margin: 10px 0 4px; font-size: 10px; color: #374151; white-space: pre-wrap; }
    .invoice-doc-body { padding-bottom: 58px; }
</style>
