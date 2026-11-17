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
    public function supports_slovak_and_czech_labels_and_supplier_header_before_customer(): void
    {
        $draft = (new BusinessExpensePdfTextImportService)->extractFromText("Synthetic carrier s.r.o.\nIBAN\tSK6807200002891987426353\nOdberateľ:\nSynthetic customer s.r.o.\nFaktúra č. : 00000042\nDátum vystavenia: 1.11.2026\nDátum dodania služby: 2.11.2026\nDatum splatnosti: 15.11.2026\nVariabilní symbol: 00000042\nCelkom k úhrade\t42,50\nMena: EUR");
        $this->assertSame('Synthetic carrier s.r.o.', $draft['supplier_name']);
        $this->assertSame('00000042', $draft['external_number']);
        $this->assertSame('00000042', $draft['variable_symbol']);
        $this->assertSame('2026-11-01', $draft['issue_date']);
        $this->assertSame('2026-11-02', $draft['delivery_date']);
        $this->assertSame('2026-11-15', $draft['due_date']);
        $this->assertSame(42.5, $draft['total']);
    }

    /** @return array<string, array{string, string}> */
    public static function ambiguousFields(): array
    {
        return [
            'number' => ['Invoice no.: OTHER-002', 'external_number'],
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
