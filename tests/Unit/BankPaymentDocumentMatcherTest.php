<?php

namespace Tests\Unit;

use App\Models\BusinessDocument;
use App\Support\Invoicing\BankPaymentDocumentMatcher;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BankPaymentDocumentMatcherTest extends TestCase
{
    #[Test]
    public function matches_invoice_by_bank_variable_symbol(): void
    {
        $document = new BusinessDocument([
            'variable_symbol' => '20260042',
            'number' => '20260042',
        ]);

        $this->assertTrue(BankPaymentDocumentMatcher::matches(
            $document,
            '20260042',
            'Unrelated payment note',
        ));
    }

    #[Test]
    public function matches_invoice_by_payment_reference_when_bank_vs_differs(): void
    {
        $document = new BusinessDocument([
            'variable_symbol' => '20260042',
            'number' => '20260042',
        ]);

        $this->assertTrue(BankPaymentDocumentMatcher::matches(
            $document,
            '99999999',
            'Uhrada faktury 20260042',
        ));
    }

    #[Test]
    public function matches_invoice_by_reference_to_document_number(): void
    {
        $document = new BusinessDocument([
            'variable_symbol' => '0042',
            'number' => 'INV-0042',
        ]);

        $this->assertTrue(BankPaymentDocumentMatcher::matches(
            $document,
            null,
            'Invoice INV-0042',
        ));
    }

    #[Test]
    public function does_not_match_when_neither_hint_fits(): void
    {
        $document = new BusinessDocument([
            'variable_symbol' => '20260042',
            'number' => '20260042',
        ]);

        $this->assertFalse(BankPaymentDocumentMatcher::matches(
            $document,
            '11111111',
            'Completely different note',
        ));
    }
}
