@php
    $footerFixed = $footerFixed ?? true;
    $hasContact = $company->issuer_name
        || $company->issuer_phone
        || $company->issuer_email
        || $company->website;
    $displayWebsite = $company->website
        ? preg_replace('#^https?://#i', '', rtrim((string) $company->website, '/'))
        : null;
@endphp

<table
    cellpadding="0"
    cellspacing="0"
    @if($footerFixed)
        style="position: fixed; left: 32px; right: 32px; bottom: 20px; border-collapse: collapse;"
    @else
        style="width: 100%; margin-top: 18px; border-collapse: collapse;"
    @endif
>
    <tr>
        <td
            colspan="2"
            style="border-top: 1px dotted #b8c0cc; padding: 7px 0 5px; font-size: 9px; color: #374151; font-family: DejaVu Sans, sans-serif; vertical-align: middle;"
        >
            @if($hasContact)
                @if($company->issuer_name)
                    <strong style="color: #111827;">{{ __('Issued by') }}:</strong> {{ $company->issuer_name }}
                @endif
                @if($company->issuer_phone)
                    @if($company->issuer_name)&nbsp;&nbsp;@endif{{ $company->issuer_phone }}
                @endif
                @if($displayWebsite)
                    @if($company->issuer_name || $company->issuer_phone)&nbsp;&nbsp;@endif{{ $displayWebsite }}
                @endif
                @if($company->issuer_email)
                    @if($company->issuer_name || $company->issuer_phone || $displayWebsite)&nbsp;&nbsp;@endif{{ $company->issuer_email }}
                @endif
            @else
                &nbsp;
            @endif
        </td>
    </tr>
    <tr>
        <td align="center" style="font-size: 8px; color: #6b7280; padding-top: 2px; font-family: DejaVu Sans, sans-serif; vertical-align: middle;">
            {{ __('Created with SATFLUX.io') }}
        </td>
        <td align="right" style="width: 30%; font-size: 8px; color: #6b7280; padding-top: 2px; font-family: DejaVu Sans, sans-serif; vertical-align: middle;">
            &nbsp;
        </td>
    </tr>
</table>
