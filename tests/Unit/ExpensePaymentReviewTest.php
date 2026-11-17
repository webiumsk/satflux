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
}
