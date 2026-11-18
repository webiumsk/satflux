<?php

namespace Tests\Unit;

use App\Services\Invoicing\BusinessExpensePdfTextImportService;
use App\Support\Invoicing\ExpensePaymentReview;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BusinessExpensePdfTextImportTest extends TestCase
{
    private function invoice(string $extra = ''): string
    {
        return "Supplier: Synthetic supplier s.r.o.\nIBAN: SK68 0720 0002 8919 8742 6353\n".$extra."\nCustomer: Synthetic buyer\nInvoice no.: INV-001\nInvoice date: 2026-11-01\nDelivery date: 2026-11-02\nDue date: 2026-11-15\nVariable symbol: 0000123\nTotal due: 1 234,50 EUR\n";
    }

    #[Test]
    public function maps_labelled_fields_without_trusting_text_as_payment_authority(): void
    {
        $text = str_replace('Variable symbol:', 'Variabilný symbol:', $this->invoice());
        $draft = (new BusinessExpensePdfTextImportService)->extractFromText($text);
        $this->assertSame('pdf_text', $draft['source']);
        $this->assertTrue($draft['payment_review_required']);
        $this->assertSame('Synthetic supplier s.r.o.', $draft['supplier_name']);
        $this->assertSame('INV-001', $draft['external_number']);
        $this->assertSame('0000123', $draft['variable_symbol']);
        $this->assertSame('SK6807200002891987426353', $draft['supplier_iban']);
        $this->assertSame('2026-11-01', $draft['issue_date']);
        $this->assertSame('2026-11-02', $draft['delivery_date']);
        $this->assertSame('2026-11-15', $draft['due_date']);
        $this->assertSame(1234.50, $draft['total']);
        $this->assertSame('EUR', $draft['currency']);
        $this->assertArrayNotHasKey('mark_paid', $draft);
        $this->assertArrayNotHasKey('paid_at', $draft);
    }

    #[Test]
    public function supports_slovak_and_czech_invoice_labels(): void
    {
        $draft = (new BusinessExpensePdfTextImportService)->extractFromText("Synthetic carrier s.r.o.\nIBAN\tSK6807200002891987426353\nOdberateľ:\nSynthetic customer s.r.o.\nFaktúra č. : 00000042\nDátum vystavenia: 1.11.2026\nDátum dodania služby: 2.11.2026\nDatum splatnosti: 15.11.2026\nVariabilní symbol: 00000042\nCelkom k úhrade\t42,50\nMena: EUR");
        $this->assertArrayNotHasKey('supplier_name', $draft);
        $this->assertSame('00000042', $draft['external_number']);
        $this->assertSame('00000042', $draft['variable_symbol']);
        $this->assertSame('2026-11-01', $draft['issue_date']);
        $this->assertSame('2026-11-02', $draft['delivery_date']);
        $this->assertSame('2026-11-15', $draft['due_date']);
        $this->assertSame(42.5, $draft['total']);
    }

    #[Test]
    public function a_repeated_slovak_invoice_heading_can_end_with_a_page_counter(): void
    {
        $draft = (new BusinessExpensePdfTextImportService)->extractFromText("Faktúra č.: 000123\nFAKTÚRA č.:  000123        strana 1/1");
        $this->assertSame('000123', $draft['external_number']);
    }

    /** @return array<string, array{string, string}> */
    public static function ambiguousFields(): array
    {
        return [
            'number' => ['Invoice no.: OTHER-002', 'external_number'],
            'different number in footer' => ['FAKTÚRA č.: OTHER-002    strana 1/1', 'external_number'],
            'prose in footer' => ['FAKTÚRA č.: INV-001    strana 1/1 continued', 'external_number'],
            'malformed number in footer' => ['FAKTÚRA č.: INV 001    strana 1/1', 'external_number'],
            'incomplete counter' => ['FAKTÚRA č.: INV-001    strana 1/', 'external_number'],
            'invalid counter' => ['FAKTÚRA č.: INV-001    strana 2/1', 'external_number'],
            'symbol' => ['Variabilný symbol: 0000777', 'variable_symbol'],
            'account' => ['IBAN: DE89370400440532013000', 'supplier_iban'],
            'invalid account' => ['IBAN: SK6807200002891987426354', 'supplier_iban'],
            'date' => ['Due date: 2026-12-01', 'due_date'],
            'invalid date' => ['Due date: 2026-02-30', 'due_date'],
            'short local year' => ['Due date: 1.1.26', 'due_date'],
            'short ISO year' => ['Due date: 26-1-1', 'due_date'],
            'total' => ['Total due: 42.00 EUR', 'total'],
            'invalid precision' => ['Total due: 12.345 EUR', 'total'],
            'invalid spaced grouping' => ['Total due: 12 34,56 EUR', 'total'],
            'ambiguous spaced digits' => ['Total due: 1 2 EUR', 'total'],
            'wide ambiguous spaced digits' => ['Total due: 1  2 EUR', 'total'],
            'currency' => ['Currency: USD', 'currency'],
        ];
    }

    #[Test]
    #[DataProvider('ambiguousFields')]
    public function conflicting_or_invalid_candidates_remain_absent(string $extra, string $field): void
    {
        $text = str_replace('Variable symbol:', 'Variabilný symbol:', $this->invoice($extra));
        $draft = (new BusinessExpensePdfTextImportService)->extractFromText($text);
        $this->assertArrayNotHasKey($field, $draft);
        $this->assertTrue($draft['payment_review_required']);
    }

    #[Test]
    public function absent_payment_fields_are_never_derived_from_other_invoice_fields(): void
    {
        $draft = (new BusinessExpensePdfTextImportService)->extractFromText("Invoice no.: 0000042\nInvoice date: 2026-11-01\nTax subtotal: 100.00 EUR\nTax total: 23.00 EUR");
        foreach (['total', 'due_date', 'variable_symbol', 'supplier_iban', 'supplier_name', 'delivery_date'] as $field) {
            $this->assertArrayNotHasKey($field, $draft);
        }
        $this->assertSame('0000042', $draft['external_number']);
    }

    /** @return array<string, array{string, ?string}> */
    public static function partyLayouts(): array
    {
        return [
            'buyer postal window' => ["Synthetic Shop s.r.o.\nOdberateľ:\nSynthetic Shop s.r.o.", null],
            'delivery and billing addresses differ' => ["Synthetic Warehouse s.r.o.\nCustomer: Synthetic Shop Ltd.", null],
            'sole unlabelled company' => ['Synthetic Shop s.r.o.', null],
            'role word inside a postal name' => ["Synthetic Supplier Ltd.\nCustomer: Synthetic Shop Ltd.", null],
            'signer is not the seller label' => ["Digitally signed by\nSynthetic Signatory Ltd.\nBuyer: Synthetic Shop Ltd.", null],
            'explicit supplier after buyer' => ["Odběratel: Synthetic Shop s.r.o.\nDodavatel:\nSynthetic Carrier s.r.o.", 'Synthetic Carrier s.r.o.'],
            'same seller and buyer candidate' => ["Seller: Synthetic Shop s.r.o.\nBill to: SYNTHETIC SHOP s. r. o.", null],
            'competing explicit sellers' => ["Supplier: Synthetic Carrier Ltd.\nVendor: Synthetic Other Ltd.", null],
            'adjacent party headings' => ["Seller:\tBuyer:\nSynthetic Carrier Ltd.\tSynthetic Shop Ltd.", null],
        ];
    }

    #[Test]
    #[DataProvider('partyLayouts')]
    public function supplier_and_title_require_a_distinct_explicit_seller(string $parties, ?string $expected): void
    {
        $draft = (new BusinessExpensePdfTextImportService)->extractFromText("IBAN: SK6807200002891987426353\n{$parties}\nInvoice no.: INV-001\nTotal due: 19.50 EUR");
        if ($expected === null) {
            $this->assertArrayNotHasKey('supplier_name', $draft);
            $this->assertArrayNotHasKey('title', $draft);
        } else {
            $this->assertSame($expected, $draft['supplier_name']);
            $this->assertSame($expected, $draft['title']);
        }
        if ($expected !== null) {
            $this->assertArrayNotHasKey('supplier_iban', $draft);
        } else {
            $this->assertSame('SK6807200002891987426353', $draft['supplier_iban']);
        }
        $this->assertTrue($draft['payment_review_required']);
    }

    #[Test]
    public function a_supplier_named_only_in_an_annex_does_not_receive_the_invoice_payment(): void
    {
        $invoice = "IBAN: SK6807200002891987426353\nInvoice no.: INV-001\nInvoice date: 2026-11-01\nDue date: 2026-11-15\nTotal due: 19.50 EUR";
        $annex = "Service detail\nSupplier: Synthetic Subcontractor Ltd.\nIBAN: DE89370400440532013000";
        foreach ([$invoice."\f".$annex, $annex."\f".$invoice] as $text) {
            $draft = (new BusinessExpensePdfTextImportService)->extractFromText($text);
            $this->assertArrayNotHasKey('supplier_name', $draft);
            $this->assertArrayNotHasKey('title', $draft);
            $this->assertSame('SK6807200002891987426353', $draft['supplier_iban']);
            $this->assertSame(19.5, $draft['total']);
        }
    }

    #[Test]
    public function a_numbered_annex_cannot_displace_the_invoice_seller_when_dates_are_missing(): void
    {
        $invoice = "Supplier: Synthetic Carrier Ltd.\nIBAN: SK6807200002891987426353\nInvoice no.: INV-001\nInvoice date: 2026-11-01\nTotal due: 19.50 EUR";
        $other = "Supplier: Synthetic Subcontractor Ltd.\nIBAN: DE89370400440532013000\nInvoice no.: INV-001\nInvoice date: 2026-11-01\nDue date: 2026-11-15";
        $service = new BusinessExpensePdfTextImportService;
        foreach (['Service detail', ''] as $heading) {
            $detail = $heading."\n".$other;
            foreach ([$invoice."\f".$detail, $detail."\f".$invoice] as $text) {
                $draft = $service->extractFromText($text);
                if ($heading === '') {
                    $this->assertArrayNotHasKey('supplier_name', $draft);
                    $this->assertArrayNotHasKey('title', $draft);
                    $this->assertArrayNotHasKey('supplier_iban', $draft);
                } else {
                    $this->assertSame('Synthetic Carrier Ltd.', $draft['supplier_name']);
                    $this->assertSame('Synthetic Carrier Ltd.', $draft['title']);
                    $this->assertSame('SK6807200002891987426353', $draft['supplier_iban']);
                }
                $this->assertSame(19.5, $draft['total']);

                $withAnnexTotal = $service->extractFromText(str_replace($other, $other."\nTotal due: 3.00 EUR", $text));
                if ($heading === '') {
                    $this->assertArrayNotHasKey('total', $withAnnexTotal);
                } else {
                    $this->assertSame(19.5, $withAnnexTotal['total']);
                    $onlyAnnexTotal = str_replace('Total due: 19.50 EUR', '', $text);
                    $draft = $service->extractFromText(str_replace($other, $other."\nTotal due: 3.00 EUR", $onlyAnnexTotal));
                    $this->assertSame('Synthetic Carrier Ltd.', $draft['supplier_name']);
                    $this->assertArrayNotHasKey('total', $draft);
                }
            }
        }
    }

    #[Test]
    public function an_unmarked_page_cannot_supply_the_payee_for_another_numbered_pages_total(): void
    {
        $other = "Invoice no.: INV-001\nSupplier: Synthetic Contractor Ltd.\nIBAN: DE89370400440532013000\nInvoice date: 2026-11-01\nDue date: 2026-11-15";
        foreach (['', 'IBAN: SK6807200002891987426353'] as $account) {
            $invoice = "Invoice no.: INV-001\nTotal due: 19.50 EUR\n".$account;
            foreach ([$invoice."\f".$other, $other."\f".$invoice] as $text) {
                $draft = (new BusinessExpensePdfTextImportService)->extractFromText($text);
                $this->assertArrayNotHasKey('supplier_name', $draft);
                $this->assertArrayNotHasKey('title', $draft);
                $this->assertArrayNotHasKey('supplier_iban', $draft);
                $this->assertSame(19.5, $draft['total']);
            }
        }
    }

    #[Test]
    public function a_buyer_account_never_becomes_the_supplier_account(): void
    {
        foreach (['Customer:', 'Customer', 'Odberateľ:', 'Odberateľ'] as $heading) {
            $draft = (new BusinessExpensePdfTextImportService)->extractFromText("Supplier: Synthetic seller s.r.o.\n{$heading}\nSynthetic buyer\nIBAN: SK6807200002891987426353\nInvoice no.: INV-001\nTotal due: 12.50 EUR");
            $this->assertSame('Synthetic seller s.r.o.', $draft['supplier_name']);
            $this->assertArrayNotHasKey('supplier_iban', $draft);
        }
    }

    #[Test]
    public function aligns_a_complete_vertical_issuer_bank_table_without_reading_buyer_accounts(): void
    {
        $text = "BANKOVÉ SPOJENIE\nIBAN\nBIC/SWIFT\nSynthetic bank\nSK6807200002891987426353\nTATRSKBX\nSynthetic carrier s.r.o.\nOdberateľ:\nSynthetic buyer\nIBAN: DE89370400440532013000\nFaktúra č.: INV-001\nCelkom k úhrade: 19,50 EUR";
        $service = new BusinessExpensePdfTextImportService;
        $draft = $service->extractFromText($text);
        $this->assertSame('SK6807200002891987426353', $draft['supplier_iban']);
        $this->assertTrue($draft['payment_review_required']);

        $invalid = $service->extractFromText(str_replace('SK6807200002891987426353', 'SK6807200002891987426354', $text));
        $this->assertArrayNotHasKey('supplier_iban', $invalid);
        $conflicting = $service->extractFromText(str_replace('Odberateľ:', "IBAN: DE89370400440532013000\nOdberateľ:", $text));
        $this->assertArrayNotHasKey('supplier_iban', $conflicting);
    }

    #[Test]
    public function the_legal_bank_name_in_a_vertical_table_cannot_become_a_missing_supplier(): void
    {
        $draft = (new BusinessExpensePdfTextImportService)->extractFromText("BANKOVÉ SPOJENIE\nIBAN\nBIC/SWIFT\nSynthetic Savings s.r.o.\nSK6807200002891987426353\nBREXSKBX\nOdberateľ:\nSynthetic buyer\nFaktúra č.: INV-001\nCelkom k úhrade: 19,50 EUR");
        $this->assertArrayNotHasKey('supplier_name', $draft);
        $this->assertArrayNotHasKey('title', $draft);
        $this->assertTrue($draft['payment_review_required']);
    }

    #[Test]
    public function resolves_annex_totals_only_from_one_coherent_invoice_summary_regardless_of_page_order(): void
    {
        $main = $this->invoice();
        $annex = "Synthetic annex\nTotal due: 3.00 EUR";
        $service = new BusinessExpensePdfTextImportService;
        foreach ([$main."\f".$annex, $annex."\f".$main] as $text) {
            $draft = $service->extractFromText($text);
            $this->assertSame(1234.5, $draft['total']);
            $this->assertTrue($draft['payment_review_required']);
        }
        $draft = $service->extractFromText($main."\f".$annex."\nPaid: 3.00 EUR");
        $this->assertSame(1234.5, $draft['total']);
        $this->assertTrue(ExpensePaymentReview::hasPaymentEvidence($draft['internal_note']));
    }

    #[Test]
    public function incomplete_conflicting_or_multiple_header_pages_cannot_select_a_total(): void
    {
        $main = $this->invoice();
        $annex = "Synthetic annex\nTotal due: 3.00 EUR";
        foreach ([
            $main."\f".str_replace('1 234,50', '3.00', $main),
            $main."\f".$annex."\nInvoice no.: OTHER-002",
            $main."\nTotal due: 7.00 EUR\f".$annex,
            str_replace('Due date: 2026-11-15', '', $main)."\f".$annex,
            $main."\f".$annex."\nTotal due: malformed",
        ] as $text) {
            $draft = (new BusinessExpensePdfTextImportService)->extractFromText($text);
            $this->assertArrayNotHasKey('total', $draft);
        }
    }

    /** @return array<string, array{string, ?string}> */
    public static function slashDates(): array
    {
        return [
            'ambiguous day/month' => ['01/02/2026', null],
            'only day/month valid' => ['13/02/2026', '2026-02-13'],
            'only month/day valid' => ['02/13/2026', '2026-02-13'],
            'same interpretation' => ['02/02/2026', '2026-02-02'],
        ];
    }

    #[Test]
    #[DataProvider('slashDates')]
    public function slash_dates_require_one_unambiguous_calendar_interpretation(string $date, ?string $expected): void
    {
        $draft = (new BusinessExpensePdfTextImportService)->extractFromText("Invoice no.: INV-001\nDue date: {$date}");
        if ($expected === null) {
            $this->assertArrayNotHasKey('due_date', $draft);
        } else {
            $this->assertSame($expected, $draft['due_date']);
        }
    }

    /** @return array<string, array{string, float}> */
    public static function amounts(): array
    {
        return [
            'comma' => ['1234,56', 1234.56],
            'dot' => ['1234.56', 1234.56],
            'space grouped' => ['1 234,56', 1234.56],
            'dot grouped' => ['1.234,56', 1234.56],
            'comma grouped' => ['1,234.56', 1234.56],
            'negative' => ['-12,50', -12.5],
            'credit' => ['12,50', 12.5],
        ];
    }

    #[Test]
    #[DataProvider('amounts')]
    public function parses_exact_supported_amount_formats(string $amount, float $expected): void
    {
        $draft = (new BusinessExpensePdfTextImportService)->extractFromText("Invoice no.: INV-001\nTotal due: {$amount} EUR");
        $this->assertSame($expected, $draft['total']);
    }

    #[Test]
    public function credit_notes_keep_a_negative_total_and_payment_review(): void
    {
        foreach (['12.50', '-12.50'] as $amount) {
            $draft = (new BusinessExpensePdfTextImportService)->extractFromText("Credit note no.: CR-001\nTotal due: {$amount} EUR");
            $this->assertSame(-12.5, $draft['total']);
            $this->assertTrue($draft['payment_review_required']);
        }
    }

    #[Test]
    public function a_body_reference_to_a_credit_note_does_not_change_the_invoice_sign(): void
    {
        $draft = (new BusinessExpensePdfTextImportService)->extractFromText($this->invoice('This invoice is not a credit note.'));
        $this->assertSame(1234.5, $draft['total']);
    }

    /** @return array<string, array{string, ?float}> */
    public static function documentHeaders(): array
    {
        return [
            'invoice with a separate credit reference' => ["Invoice no.: INV-001\nCredit note: CR-001", 12.5],
            'credit reference before the invoice header' => ["Credit note: CR-001\nInvoice no.: INV-001", 12.5],
            'conflicting numbered headers' => ["Invoice no.: INV-001\nCredit note no.: CR-001", null],
            'conflicting bare headers' => ["Invoice\nCredit note", null],
            'no document header' => ['Invoice date: 2026-11-01', null],
            'standalone English credit header' => ['Credit note', -12.5],
            'standalone Slovak credit header' => ['Dobropis č.: CR-001', -12.5],
            'standalone Czech invoice header' => ['Číslo faktury: INV-001', 12.5],
        ];
    }

    #[Test]
    #[DataProvider('documentHeaders')]
    public function positive_total_sign_uses_only_unambiguous_document_headers(string $header, ?float $expected): void
    {
        $draft = (new BusinessExpensePdfTextImportService)->extractFromText("{$header}\nDue date: 2026-11-15\nTotal due: 12.50 EUR");
        if ($expected === null) {
            $this->assertArrayNotHasKey('total', $draft);
        } else {
            $this->assertSame($expected, $draft['total']);
        }
        $this->assertTrue($draft['payment_review_required']);
    }

    #[Test]
    public function already_negative_totals_keep_their_sign_despite_ambiguous_document_headers(): void
    {
        $draft = (new BusinessExpensePdfTextImportService)->extractFromText("Invoice no.: INV-001\nCredit note no.: CR-001\nTotal due: -12.50 EUR");
        $this->assertSame(-12.5, $draft['total']);
    }

    #[Test]
    public function explicit_paid_or_deposit_evidence_becomes_existing_review_vetoes(): void
    {
        $service = new BusinessExpensePdfTextImportService;
        foreach (['Uhradené: 10,00 EUR', 'Prijatá záloha: 10,00 EUR', 'Uhradená záloha: 10,00 EUR',
            'Zaplatená záloha: 10,00 EUR', 'Uhradená suma: 10,00 EUR', 'Paid: 10.00 EUR', 'Amount paid: unknown'] as $evidence) {
            $draft = $service->extractFromText($this->invoice($evidence));
            $this->assertStringContainsString('Payment amount: ', $draft['internal_note']);
            $this->assertTrue(ExpensePaymentReview::hasPaymentEvidence($draft['internal_note']));
        }
        $draft = $service->extractFromText($this->invoice('Payment date: 2026-11-01'));
        $this->assertStringContainsString('Payment date: 2026-11-01', $draft['internal_note']);
    }

    #[Test]
    public function zero_paid_amount_is_not_reclassified_as_concrete_payment(): void
    {
        $draft = (new BusinessExpensePdfTextImportService)->extractFromText($this->invoice('Amount paid: 0.00 EUR'));
        $this->assertFalse(ExpensePaymentReview::hasPaymentEvidence($draft['internal_note']));
        $this->assertTrue($draft['payment_review_required']);
        foreach (['Neuhradené: 10.00 EUR', 'This invoice is not paid.'] as $line) {
            $draft = (new BusinessExpensePdfTextImportService)->extractFromText($this->invoice($line));
            $this->assertFalse(ExpensePaymentReview::hasPaymentEvidence($draft['internal_note']));
        }
    }

    #[Test]
    public function unrecognized_text_and_currency_only_documents_offer_no_extraction(): void
    {
        $service = new BusinessExpensePdfTextImportService;
        foreach (['', 'An ordinary text document with 12.50 EUR.', 'Invoice with EUR and IBAN: SK6807200002891987426353'] as $text) {
            $this->assertFalse($service->hasExtractableFields($text));
        }
        $this->expectException(ValidationException::class);
        $service->extractFromText('');
    }

    #[Test]
    public function raw_document_text_is_never_copied_to_the_internal_note(): void
    {
        $draft = (new BusinessExpensePdfTextImportService)->extractFromText($this->invoice('Synthetic private line which is not a recognized field.'));
        $this->assertStringNotContainsString('Synthetic private line', $draft['internal_note']);
        $this->assertStringNotContainsString('Synthetic supplier', $draft['internal_note']);
    }

    #[Test]
    public function oversized_text_is_rejected_with_a_static_error(): void
    {
        $this->expectException(ValidationException::class);
        (new BusinessExpensePdfTextImportService)->extractFromText(str_repeat('x', 200001));
    }
}
