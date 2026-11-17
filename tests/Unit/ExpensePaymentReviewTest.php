<?php

namespace Tests\Unit;

use App\Support\Invoicing\ExpensePaymentReview;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ExpensePaymentReviewTest extends TestCase
{
    #[DataProvider('legacyEvidence')]
    public function test_conservatively_preserves_original_legacy_evidence(?bool $stored, ?string $note, bool $required): void
    {
        $this->assertSame($required, ExpensePaymentReview::required($stored, $note));
    }

    /** @return array<string, array{?bool, ?string, bool}> */
    public static function legacyEvidence(): array
    {
        return [
            'manual note' => [null, 'Ordinary manual note.', false],
            'no note' => [null, null, false],
            'zero imported payment' => [null, 'Payment amount: 0.00', false],
            'nonzero payment' => [null, 'Payment amount: 0.01', true],
            'unknown amount' => [null, 'Payment amount: unknown', true],
            'payment date evidence' => [null, 'Payment date: 2026-01-01', true],
            'historical ISDOC omitted financial proof' => [null, 'Imported from ISDOC.', true],
            'historical Peppol omitted financial proof' => [null, 'Importované z Peppol (SAPI-SK).', true],
            'stored false is authoritative' => [false, 'Imported from ISDOC.', false],
            'stored true survives missing note' => [true, null, true],
        ];
    }

    #[DataProvider('concretePaymentEvidence')]
    public function test_manual_confirmation_distinguishes_concrete_payment_evidence(?string $note, bool $blocked): void
    {
        $this->assertSame($blocked, ExpensePaymentReview::hasPaymentEvidence($note));
    }

    /** @return array<string, array{?string, bool}> */
    public static function concretePaymentEvidence(): array
    {
        return [
            'none' => [null, false],
            'legacy XML uncertainty' => ['Imported from ISDOC.', false],
            'legacy Peppol uncertainty' => ['Importované z Peppol (SAPI-SK).', false],
            'explicit zero' => ['Payment amount: 0,00', false],
            'zero with trailing whitespace' => ["Payment amount: 0.00 \t", false],
            'payment date' => ['Payment date: 2026-11-03', true],
            'nonzero amount' => ['Payment amount: 0.01', true],
            'malformed amount' => ['Payment amount: unknown', true],
            'unsupported precision' => ['Payment amount: 0.000', true],
            'case insensitive evidence' => ['payment AMOUNT: 2.00', true],
        ];
    }

    public function test_confirmation_tuple_matches_browser_canonical_json_without_disclosing_additional_fields(): void
    {
        $this->assertSame('["Žluťoučký/čaj","SK6807200002891987426353","19.50","EUR","00019"]', ExpensePaymentReview::confirmationTuple([
            'supplier_name' => "\u{FEFF}  Žluťoučký/čaj\u{00A0}",
            'supplier_iban' => 'sk68 0720 0002 8919 8742 6353',
            'total' => '0019.5', 'currency' => 'EUR', 'variable_symbol' => ' 00019 ',
            'internal_note' => 'This prose is not confirmation evidence.',
        ]));
    }

    #[DataProvider('ineligibleConfirmationFields')]
    public function test_confirmation_never_authorizes_ineligible_payment_details(string $field, mixed $value): void
    {
        $fields = ['supplier_name' => 'Synthetic supplier', 'supplier_iban' => 'SK6807200002891987426353',
            'total' => '19.50', 'currency' => 'EUR', 'variable_symbol' => '00019'];
        $fields[$field] = $value;
        $this->assertNull(ExpensePaymentReview::confirmationTuple($fields));
    }

    /** @return array<string, array{string, mixed}> */
    public static function ineligibleConfirmationFields(): array
    {
        return [
            'missing name' => ['supplier_name', null],
            'empty name' => ['supplier_name', '  '],
            'overlong name' => ['supplier_name', str_repeat('x', 71)],
            'name injection' => ['supplier_name', "Synthetic\nrecipient"],
            'invalid Unicode' => ['supplier_name', "\xED\xA0\x80"],
            'invalid IBAN' => ['supplier_iban', 'SK6807200002891987426354'],
            'missing reference' => ['variable_symbol', null],
            'long reference' => ['variable_symbol', '12345678901'],
            'nonnumeric reference' => ['variable_symbol', 'INV00019'],
            'unsupported currency' => ['currency', 'USD'],
            'unrecorded currency casing' => ['currency', 'eur'],
            'zero' => ['total', '0.00'],
            'credit note' => ['total', '-19.50'],
            'subcent source' => ['total', '19.501'],
            'extra trailing fraction' => ['total', '19.500'],
            'exponent' => ['total', '1.95e1'],
            'unsupported integer precision' => ['total', '1000000000000.00'],
            'nonfinite amount' => ['total', INF],
            'floating fractional cents' => ['total', 19.501],
        ];
    }

    #[DataProvider('exactConfirmationAmounts')]
    public function test_confirmation_preserves_exact_amounts(mixed $total, string $expected): void
    {
        $tuple = ExpensePaymentReview::confirmationTuple(['supplier_name' => 'Synthetic supplier', 'supplier_iban' => 'SK6807200002891987426353',
            'total' => $total, 'currency' => 'EUR', 'variable_symbol' => '00019']);
        $this->assertNotNull($tuple);
        $this->assertSame($expected, json_decode($tuple, true, flags: JSON_THROW_ON_ERROR)[2]);
    }

    /** @return array<string, array{mixed, string}> */
    public static function exactConfirmationAmounts(): array
    {
        return [
            'integer' => [19, '19.00'],
            'float' => [19.5, '19.50'],
            'trimmed exact amount' => ["\u{FEFF} 0019.5\u{00A0}", '19.50'],
            'smallest amount' => ['0.01', '0.01'],
            'largest supported amount' => ['999999999999.99', '999999999999.99'],
        ];
    }
}
