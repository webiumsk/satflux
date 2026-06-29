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

<div @class(['invoice-doc-footer', 'invoice-doc-footer--fixed' => $footerFixed])>
    @if($hasContact)
        <hr class="footer-divider">
        <table class="footer-contact-table" width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td class="footer-contact-line">
                    @if($company->issuer_name)
                        <span class="footer-contact-chunk footer-contact-issuer">
                            <strong>{{ __('Issued by') }}:</strong> {{ $company->issuer_name }}
                        </span>
                    @endif
                    @if($company->issuer_phone)
                        <span class="footer-contact-chunk">
                            <span class="footer-icon" aria-hidden="true">&#9742;</span>{{ $company->issuer_phone }}
                        </span>
                    @endif
                    @if($displayWebsite)
                        <span class="footer-contact-chunk">
                            <span class="footer-icon" aria-hidden="true">&#8982;</span>{{ $displayWebsite }}
                        </span>
                    @endif
                    @if($company->issuer_email)
                        <span class="footer-contact-chunk">
                            <span class="footer-icon" aria-hidden="true">&#9993;</span>{{ $company->issuer_email }}
                        </span>
                    @endif
                </td>
            </tr>
        </table>
    @endif

    <table class="footer-brand-table" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td class="footer-brand-side" width="33%">&nbsp;</td>
            <td class="footer-brand-center" width="34%">{{ __('Created with SATFLUX.io') }}</td>
            <td class="footer-page-slot" width="33%" align="right">&nbsp;</td>
        </tr>
    </table>
</div>
