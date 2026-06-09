<?php

namespace Tests\Unit;

use App\Models\Company;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompanyBankAccountLabelTest extends TestCase
{
    #[Test]
    public function masked_bank_account_label_uses_bank_name_and_last_four_digits(): void
    {
        $company = new Company([
            'bank_name' => 'Tatra banka',
            'iban' => 'SK31 1200 0000 1987 4269 76',
        ]);

        $this->assertSame('Tatra banka ****6976', $company->maskedBankAccountLabel());
    }

    #[Test]
    public function masked_bank_account_label_falls_back_to_account_number(): void
    {
        $company = new Company([
            'bank_account' => '2600123456',
        ]);

        $this->assertSame('****3456', $company->maskedBankAccountLabel());
    }
}
