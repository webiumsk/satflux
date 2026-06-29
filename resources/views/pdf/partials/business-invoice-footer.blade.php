@php
    $footerFixed = $footerFixed ?? true;
    $hasContact = $company->issuer_name
        || $company->issuer_phone
        || $company->issuer_email
        || $company->website;
    $displayWebsite = $company->website
        ? preg_replace('#^https?://#i', '', rtrim((string) $company->website, '/'))
        : null;
    $phoneHref = $company->issuer_phone
        ? 'tel:'.preg_replace('/[^\d+]/', '', (string) $company->issuer_phone)
        : null;
    $emailHref = $company->issuer_email
        ? 'mailto:'.(string) $company->issuer_email
        : null;
    $websiteHref = null;
    if ($company->website) {
        $websiteHref = preg_match('#^https?://#i', (string) $company->website)
            ? (string) $company->website
            : 'https://'.ltrim((string) $company->website, '/');
    }
@endphp

<table
    cellpadding="0"
    cellspacing="0"
    @if($footerFixed)
        style="position: fixed; left: 32px; right: 32px; bottom: 28px; border-collapse: collapse;"
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
                <table cellpadding="0" cellspacing="0">
                    <tr>
                        @if($company->issuer_name)
                            <td style="padding: 0 16px 0 0; font-size: 9px; color: #374151; vertical-align: middle; white-space: nowrap; font-family: DejaVu Sans, sans-serif;">
                                <strong style="color: #111827;">{{ __('Issued by') }}:</strong> {{ $company->issuer_name }}
                            </td>
                        @endif
                        @if($company->issuer_phone && $phoneHref)
                            <td style="padding: 0 16px 0 0; vertical-align: middle; white-space: nowrap;">
                                @include('pdf.partials.business-invoice-footer-contact-item', [
                                    'icon' => 'phone',
                                    'href' => $phoneHref,
                                    'label' => $company->issuer_phone,
                                ])
                            </td>
                        @endif
                        @if($displayWebsite && $websiteHref)
                            <td style="padding: 0 16px 0 0; vertical-align: middle; white-space: nowrap;">
                                @include('pdf.partials.business-invoice-footer-contact-item', [
                                    'icon' => 'web',
                                    'href' => $websiteHref,
                                    'label' => $displayWebsite,
                                ])
                            </td>
                        @endif
                        @if($company->issuer_email && $emailHref)
                            <td style="padding: 0; vertical-align: middle; white-space: nowrap;">
                                @include('pdf.partials.business-invoice-footer-contact-item', [
                                    'icon' => 'email',
                                    'href' => $emailHref,
                                    'label' => $company->issuer_email,
                                ])
                            </td>
                        @endif
                    </tr>
                </table>
            @else
                &nbsp;
            @endif
        </td>
    </tr>
    <tr>
        <td align="center" style="font-size: 8px; color: #6b7280; padding-top: 3px; font-family: DejaVu Sans, sans-serif; vertical-align: middle;">
            {{ __('Created with SATFLUX.io') }}
        </td>
        <td align="right" style="width: 30%; font-size: 8px; color: #6b7280; padding-top: 3px; font-family: DejaVu Sans, sans-serif; vertical-align: middle;">
            &nbsp;
        </td>
    </tr>
</table>
