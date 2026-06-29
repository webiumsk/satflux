@php
    $footerFixed = $footerFixed ?? true;
    $hasIssuer = $company->issuer_name
        || $company->issuer_phone
        || $company->issuer_email;
    $hasContact = $hasIssuer || $company->website;
@endphp

@if($footerFixed)
    @if($hasContact)
        <table cellpadding="0" cellspacing="0" style="position: fixed; left: 32px; right: 32px; bottom: 44px; width: 100%; font-size: 9px; color: #374151;">
            <tr>
                <td colspan="4" style="border-top: 1px dotted #b8c0cc; padding-top: 8px; padding-bottom: 6px;"></td>
            </tr>
            <tr>
                @if($company->issuer_name)
                    <td style="padding-right: 12px; vertical-align: middle;">
                        <strong>{{ __('Issued by') }}:</strong> {{ $company->issuer_name }}
                    </td>
                @endif
                @if($company->issuer_phone)
                    <td style="text-align: center; vertical-align: middle; padding-right: 8px;">
                        {{ $company->issuer_phone }}
                    </td>
                @endif
                @if($company->website)
                    <td style="text-align: center; vertical-align: middle; padding-right: 8px;">
                        {{ $company->website }}
                    </td>
                @endif
                @if($company->issuer_email)
                    <td style="text-align: center; vertical-align: middle;">
                        {{ $company->issuer_email }}
                    </td>
                @endif
            </tr>
        </table>
    @endif

    <table cellpadding="0" cellspacing="0" style="position: fixed; left: 32px; right: 32px; bottom: 24px; width: 100%;">
        <tr>
            <td align="center" style="font-size: 8px; color: #888888; font-family: DejaVu Sans, sans-serif;">
                {{ __('Created with SATFLUX.io') }}
            </td>
        </tr>
    </table>
@else
    @if($hasContact)
        <div style="margin-top: 10px; font-size: 9px; color: #374151;">
            <hr style="border: none; border-top: 1px dotted #b8c0cc; margin: 0 0 8px; height: 0;">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    @if($company->issuer_name)
                        <td style="padding-right: 12px;"><strong>{{ __('Issued by') }}:</strong> {{ $company->issuer_name }}</td>
                    @endif
                    @if($company->issuer_phone)
                        <td style="text-align: center;">{{ $company->issuer_phone }}</td>
                    @endif
                    @if($company->website)
                        <td style="text-align: center;">{{ $company->website }}</td>
                    @endif
                    @if($company->issuer_email)
                        <td style="text-align: center;">{{ $company->issuer_email }}</td>
                    @endif
                </tr>
            </table>
        </div>
    @endif

    <table cellpadding="0" cellspacing="0" style="width: 100%; margin-top: 8px;">
        <tr>
            <td align="center" style="font-size: 8px; color: #888888; font-family: DejaVu Sans, sans-serif;">
                {{ __('Created with SATFLUX.io') }}
            </td>
        </tr>
    </table>
@endif
