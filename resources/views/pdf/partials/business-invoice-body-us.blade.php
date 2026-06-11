@php
    $isQuote = $document->type->value === 'quote';
    $amountDue = max(0, (float) $document->total - (float) ($document->amount_paid ?? 0));
    $showPaidInfo = ! $isQuote
        && $document->pdf_show_payment_info
        && ($document->status->value === 'paid' || $amountDue < 0.005);
@endphp

<div class="row">
    <div class="col">
        <strong>{{ $company->displayName() }}</strong><br>
        @if($company->street){{ $company->street }}<br>@endif
        @if($company->city){{ $company->city }}, {{ $company->state_region }} {{ $company->postal_code }}<br>@endif
        @if($company->registration_number)EIN: {{ $company->registration_number }}<br>@endif
        @if($company->tax_id)Tax ID: {{ $company->tax_id }}<br>@endif
        @if($company->iban)Account: {{ $company->iban }}<br>@endif
    </div>
    <div class="col">
        @if($contact)
            <strong>Bill to: {{ $contact->name }}</strong><br>
            @if($contact->street){{ $contact->street }}<br>@endif
            @if($contact->city){{ $contact->city }}@if($contact->state_region), {{ $contact->state_region }}@endif {{ $contact->postal_code }}<br>@endif
            @if($contact->email){{ $contact->email }}<br>@endif
        @endif
        <br>
        Issue date: {{ $document->issue_date?->format('m/d/Y') }}<br>
        @if($document->due_date)
            {{ $isQuote ? __('Quote valid until') : __('Due date') }}: {{ $document->due_date->format('m/d/Y') }}<br>
        @endif
    </div>
</div>

@if($document->note_above_lines)
    <p>{{ $document->note_above_lines }}</p>
@endif

<table>
    <thead>
        <tr>
            <th>Description</th>
            <th>Qty</th>
            <th>Unit</th>
            <th>Rate</th>
            @if(!empty($showSalesTaxColumn))
                <th>Tax %</th>
            @endif
            <th>Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach($lines as $line)
            <tr>
                <td>
                    {{ $line->name }}
                    @if($line->description)<br><span class="muted">{{ $line->description }}</span>@endif
                </td>
                <td>{{ $line->quantity }}</td>
                <td>{{ $line->unit }}</td>
                <td>{{ number_format((float) $line->unit_price, 2) }} {{ $document->currency }}</td>
                @if(!empty($showSalesTaxColumn))
                    <td>{{ number_format((float) $line->tax_rate, 2) }}%</td>
                @endif
                <td>{{ number_format((float) $line->line_total, 2) }} {{ $document->currency }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="totals">
    @if((float) $document->discount_percent > 0)
        <div>Discount {{ $document->discount_percent }}%</div>
    @endif
    <div>Subtotal: {{ number_format((float) $document->subtotal, 2) }} {{ $document->currency }}</div>
    @if(!empty($taxBreakdown))
        @foreach($taxBreakdown as $row)
            <div>{{ $row->label ?? ('Sales tax '.$row->ratePercent.'%') }}: {{ number_format((float) $row->taxAmount, 2) }} {{ $document->currency }}</div>
        @endforeach
    @elseif((float) $document->tax_total > 0)
        <div>Sales tax: {{ number_format((float) $document->tax_total, 2) }} {{ $document->currency }}</div>
    @endif
    @if(! $isQuote)
        <div><strong>Total due: {{ number_format($showPaidInfo ? 0 : $amountDue, 2) }} {{ $document->currency }}</strong></div>
    @else
        <div><strong>{{ __('Grand total') }}: {{ number_format((float) $document->total, 2) }} {{ $document->currency }}</strong></div>
    @endif
    @if($showPaidInfo)
        <div class="muted">Paid {{ $document->paid_at?->format('m/d/Y') }}</div>
    @endif
</div>

@if($document->note_footer)
    <p class="muted" style="margin-top:16px;">{{ $document->note_footer }}</p>
@endif

@if(! $isQuote && $btcPayQr)
    <div class="qr-block">
        <div class="muted">{{ __('Bitcoin / Lightning (payment link)') }}</div>
        <img src="{{ $btcPayQr }}" alt="">
        <div class="qr-hint">{{ __('BTC payment QR is a web link. Open in your browser - do not scan with a Lightning wallet.') }}</div>
    </div>
@endif
