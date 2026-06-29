@php
    $footerFixed = $footerFixed ?? true;
    $hasContact = $company->issuer_name
        || $company->issuer_phone
        || $company->issuer_email
        || $company->website;
@endphp

<div @class(['invoice-doc-footer', 'invoice-doc-footer--fixed' => $footerFixed])>
    @if($hasContact)
        <hr class="footer-divider">
        <table class="footer-contact-table" width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td class="footer-contact-col" width="25%" align="left" valign="middle">
                    @if($company->issuer_name)
                        <strong>{{ __('Issued by') }}:</strong> {{ $company->issuer_name }}
                    @endif
                </td>
                <td class="footer-contact-col" width="25%" align="left" valign="middle">
                    @if($company->issuer_phone)
                        <span class="footer-icon" aria-hidden="true">&#9742;</span>{{ $company->issuer_phone }}
                    @endif
                </td>
                <td class="footer-contact-col" width="25%" align="left" valign="middle">
                    @if($company->website)
                        <span class="footer-icon" aria-hidden="true">&#8982;</span>{{ $company->website }}
                    @endif
                </td>
                <td class="footer-contact-col" width="25%" align="left" valign="middle">
                    @if($company->issuer_email)
                        <span class="footer-icon" aria-hidden="true">&#9993;</span>{{ $company->issuer_email }}
                    @endif
                </td>
            </tr>
        </table>
    @endif

    <table class="footer-brand-table" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td class="footer-brand-center" colspan="3">{{ __('Created with SATFLUX.io') }}</td>
        </tr>
    </table>
</div>
