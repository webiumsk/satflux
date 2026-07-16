<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentStatus;
use App\Enums\CompanyJurisdiction;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\User;
use App\Services\Invoicing\BankQrGenerator;
use App\Services\Invoicing\EpcQrGenerator;
use App\Services\Invoicing\SwissQrGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The bank QR is scanned by the PAYER - the standard follows the customer's
 * country (per-document pdf_bank_qr overrides), never the issuer jurisdiction.
 */
class BankQrGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private function company(array $overrides = []): Company
    {
        $user = User::factory()->create();

        return Company::create(array_merge([
            'user_id' => $user->id,
            'legal_name' => 'Webium s.r.o.',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
            'iban' => 'SK3112000000198742637541',
            'street' => 'Bohunice 47',
            'city' => 'Bohunice',
            'postal_code' => '93505',
            'country' => 'SK',
            'vat_payer' => true,
            'app_settings' => ['show_pay_by_square' => true],
        ], $overrides));
    }

    private function document(Company $company, array $overrides = []): BusinessDocument
    {
        return BusinessDocument::create(array_merge([
            'company_id' => $company->id,
            'type' => 'invoice',
            'status' => BusinessDocumentStatus::Issued,
            'number' => 'FV20260001',
            'subtotal' => 100,
            'tax_total' => 0,
            'total' => 100,
            'currency' => 'EUR',
            'issue_date' => now(),
            'payment_bank_enabled' => true,
        ], $overrides))->fresh(['company', 'contact']);
    }

    private function contact(Company $company, string $country): CompanyContact
    {
        return CompanyContact::create([
            'company_id' => $company->id,
            'name' => 'Buyer '.$country,
            'country' => $country,
        ]);
    }

    private function standard(Company $company, BusinessDocument $document): ?string
    {
        return app(BankQrGenerator::class)->selectStandard($company, $document);
    }

    #[Test]
    public function payer_country_picks_the_standard(): void
    {
        $company = $this->company();

        // SK issuer + SK payer: PayBySquare, unchanged.
        $domestic = $this->document($company, [
            'company_contact_id' => $this->contact($company, 'SK')->id,
        ]);
        $this->assertSame('paybysquare', $this->standard($company, $domestic));

        // SK issuer + DE payer: the German app reads EPC, not PayBySquare.
        $german = $this->document($company, [
            'number' => 'FV20260002',
            'company_contact_id' => $this->contact($company, 'DE')->id,
        ]);
        $this->assertSame('epc', $this->standard($company, $german));

        // US payer: no scannable standard.
        $us = $this->document($company, [
            'number' => 'FV20260003',
            'company_contact_id' => $this->contact($company, 'US')->id,
        ]);
        $this->assertNull($this->standard($company, $us));
    }

    #[Test]
    public function swiss_issuer_invoicing_a_slovak_customer_prints_paybysquare(): void
    {
        $company = $this->company([
            'legal_name' => 'Muster AG',
            'jurisdiction' => CompanyJurisdiction::Ch,
            'iban' => 'CH9300762011623852957',
            'country' => 'CH',
        ]);

        $document = $this->document($company, [
            'company_contact_id' => $this->contact($company, 'SK')->id,
        ]);

        // The Slovak payer's app reads PayBySquare; the payload carries the
        // CH IBAN over SEPA.
        $this->assertSame('paybysquare', $this->standard($company, $document));
    }

    #[Test]
    public function swiss_payer_gets_swiss_qr_only_onto_a_ch_account(): void
    {
        $swissCompany = $this->company([
            'jurisdiction' => CompanyJurisdiction::Ch,
            'iban' => 'CH9300762011623852957',
            'country' => 'CH',
        ]);
        $document = $this->document($swissCompany, [
            'currency' => 'CHF',
            'company_contact_id' => $this->contact($swissCompany, 'CH')->id,
        ]);
        $this->assertSame('swiss', $this->standard($swissCompany, $document));

        // Swiss payer, but the creditor account is Slovak: QR-bill cannot pay
        // onto it - EPC (EUR) is the fallback.
        $skCompany = $this->company();
        $eurDocument = $this->document($skCompany, [
            'company_contact_id' => $this->contact($skCompany, 'CH')->id,
        ]);
        $this->assertSame('epc', $this->standard($skCompany, $eurDocument));
    }

    #[Test]
    public function without_a_contact_the_issuer_country_is_the_payer(): void
    {
        $company = $this->company([
            'jurisdiction' => CompanyJurisdiction::EuDe,
            'iban' => 'DE89370400440532013000',
            'country' => 'DE',
        ]);

        $this->assertSame('epc', $this->standard($company, $this->document($company)));
    }

    #[Test]
    public function per_document_choice_overrides_the_matrix_but_stays_feasible(): void
    {
        $company = $this->company();

        // SK payer forced to EPC.
        $forcedEpc = $this->document($company, [
            'company_contact_id' => $this->contact($company, 'SK')->id,
            'pdf_bank_qr' => 'epc',
        ]);
        $this->assertSame('epc', $this->standard($company, $forcedEpc));

        // Forced Swiss without a CH/LI IBAN: no QR, never a broken payload.
        $forcedSwiss = $this->document($company, [
            'number' => 'FV20260002',
            'pdf_bank_qr' => 'swiss',
        ]);
        $this->assertNull($this->standard($company, $forcedSwiss));

        // Explicit none wins over a valid matrix result.
        $none = $this->document($company, [
            'number' => 'FV20260003',
            'company_contact_id' => $this->contact($company, 'SK')->id,
            'pdf_bank_qr' => 'none',
        ]);
        $this->assertNull($this->standard($company, $none));
    }

    #[Test]
    public function epc_payload_follows_the_bcd_format(): void
    {
        $company = $this->company(['bic' => 'GIBASKBX']);
        $document = $this->document($company, ['variable_symbol' => '20260001']);

        $payload = app(EpcQrGenerator::class)->generatePayload($company, $document);

        $this->assertSame([
            'BCD',
            '002',
            '1',
            'SCT',
            'GIBASKBX',
            'Webium s.r.o.',
            'SK3112000000198742637541',
            'EUR100.00',
            '',
            '',
            '20260001',
        ], explode("\n", $payload));
    }

    #[Test]
    public function epc_is_eur_only(): void
    {
        $company = $this->company();
        $document = $this->document($company, ['currency' => 'USD']);

        $this->assertFalse(app(EpcQrGenerator::class)->canGenerate($company, $document));
    }

    #[Test]
    public function swiss_payload_follows_the_spc_format(): void
    {
        $company = $this->company([
            'legal_name' => 'Muster AG',
            'jurisdiction' => CompanyJurisdiction::Ch,
            'iban' => 'CH9300762011623852957',
            'street' => 'Musterstrasse 1',
            'city' => 'Zürich',
            'postal_code' => '8000',
            'country' => 'CH',
        ]);
        $document = $this->document($company, ['currency' => 'CHF']);

        $lines = explode("\n", app(SwissQrGenerator::class)->generatePayload($company, $document));

        $this->assertSame('SPC', $lines[0]);
        $this->assertSame('0200', $lines[1]);
        $this->assertSame('CH9300762011623852957', $lines[3]);
        $this->assertSame('K', $lines[4]);
        $this->assertSame('Muster AG', $lines[5]);
        $this->assertSame('8000 Zürich', $lines[7]);
        $this->assertSame('100.00', $lines[18]);
        $this->assertSame('CHF', $lines[19]);
        $this->assertSame('NON', $lines[27]);
        $this->assertSame('EPD', end($lines));
    }

    #[Test]
    public function swiss_qr_requires_a_ch_or_li_creditor_iban(): void
    {
        $company = $this->company(['jurisdiction' => CompanyJurisdiction::Ch, 'country' => 'CH']);
        $document = $this->document($company, ['currency' => 'CHF']);

        $this->assertFalse(app(SwissQrGenerator::class)->canGenerate($company, $document));
    }
}
