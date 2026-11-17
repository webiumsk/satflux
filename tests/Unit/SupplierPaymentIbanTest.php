<?php

namespace Tests\Unit;

use App\Support\Invoicing\SupplierPaymentIban;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SupplierPaymentIbanTest extends TestCase
{
    #[DataProvider('supplierAccounts')]
    public function test_requires_a_known_country_length_and_checksum(string $input, ?string $expected): void
    {
        $this->assertSame($expected, SupplierPaymentIban::normalize($input));
    }

    /** @return array<string, array{string, string|null}> */
    public static function supplierAccounts(): array
    {
        return [
            'published Slovak example' => ['SK6807200002891987426353', 'SK6807200002891987426353'],
            'printed lowercase Slovak example' => ['sk68 0720 0002 8919 8742 6353', 'SK6807200002891987426353'],
            'German example' => ['DE89370400440532013000', 'DE89370400440532013000'],
            'Czech example' => ['CZ6508000000192000145399', 'CZ6508000000192000145399'],
            'bad checksum' => ['SK6807200002891987426354', null],
            // These malformed account strings have valid mod-97 checksums.
            'too short for Slovakia despite valid checksum' => ['SK7700000000000', null],
            'too long for Slovakia despite valid checksum' => ['SK77000000000000000000000', null],
            'unknown country despite valid checksum' => ['ZZ6600000000000', null],
            'unassigned country despite valid checksum' => ['AA7500000000000', null],
            'tab is not printed spacing' => ["SK68\t07200002891987426353", null],
            'line break is not printed spacing' => ["SK6807200002891987426353\n", null],
            'empty account' => ['', null],
        ];
    }
}
