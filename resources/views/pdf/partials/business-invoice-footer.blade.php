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
        <table class="footer-contact-table">
            <tr>
                @if($company->issuer_name)
                    <td class="footer-issued-by">
                        <strong>{{ __('Issued by') }}:</strong> {{ $company->issuer_name }}
                    </td>
                @endif
                @if($company->issuer_phone)
                    <td class="footer-contact-item">
                        <span class="footer-icon" aria-hidden="true">☎</span>{{ $company->issuer_phone }}
                    </td>
                @endif
                @if($company->website)
                    <td class="footer-contact-item">
                        <span class="footer-icon" aria-hidden="true">⌁</span>{{ $company->website }}
                    </td>
                @endif
                @if($company->issuer_email)
                    <td class="footer-contact-item">
                        <span class="footer-icon" aria-hidden="true">✉</span>{{ $company->issuer_email }}
                    </td>
                @endif
            </tr>
        </table>
    @endif

    <table class="footer-brand-table">
        <tr>
            <td class="footer-brand-center">{{ __('Created with SATFLUX.io') }}</td>
        </tr>
    </table>
</div>
