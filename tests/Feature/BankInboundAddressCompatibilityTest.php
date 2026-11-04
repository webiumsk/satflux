<?php

namespace Tests\Feature;

use App\Enums\CompanyJurisdiction;
use App\Models\Company;
use App\Models\Store;
use App\Models\User;
use App\Services\Invoicing\BankInboundAddressService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class BankInboundAddressCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    private BankInboundAddressService $addresses;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'bank_inbound.domain' => 'payments.satflux.io',
            'bank_inbound.address_prefix' => 'pay',
            'bank_inbound.store_address_prefix' => 'ps',
            'bank_inbound.max_address_length' => 50,
        ]);
        $this->addresses = app(BankInboundAddressService::class);
    }

    private function company(?string $token): Company
    {
        return Company::create([
            'user_id' => User::factory()->create()->id,
            'legal_name' => 'Synthetic Company',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
            'bank_inbound_token' => $token,
        ]);
    }

    #[Test]
    #[TestWith([8])]
    #[TestWith([9])]
    #[TestWith([10])]
    #[TestWith([11])]
    #[TestWith([12])]
    #[TestWith([13])]
    #[TestWith([14])]
    #[TestWith([15])]
    #[TestWith([16])]
    public function persisted_company_tokens_resolve_without_rotation(int $length): void
    {
        $token = str_repeat('a', $length);
        $company = $this->company($token);
        $address = $this->addresses->buildAddress($company);

        $this->assertSame('pay'.$token.'@payments.satflux.io', $address);
        $this->assertSame($company->id, $this->addresses->resolveCompany($address)->id);
        $this->assertInstanceOf(Company::class, $this->addresses->resolveOwner($address));
        $this->assertSame($company->id, $this->addresses->resolveOwner($address)->id);
        $this->assertSame($token, $company->fresh()->bank_inbound_token);
    }

    #[Test]
    #[TestWith([31, 50, 8, 12])]
    #[TestWith([50, 31, 12, 8])]
    public function configuration_changes_only_affect_new_token_lengths(
        int $oldLimit,
        int $newLimit,
        int $oldLength,
        int $newLength,
    ): void {
        config(['bank_inbound.max_address_length' => $oldLimit]);
        $company = $this->company(null);
        $address = $this->addresses->buildAddress($company);
        $token = $company->bank_inbound_token;
        $this->assertSame($oldLength, strlen($token));

        config(['bank_inbound.max_address_length' => $newLimit]);

        $this->assertSame($newLength, $this->addresses->tokenLength());
        $this->assertSame($company->id, $this->addresses->resolveCompany($address)->id);
        $this->assertSame($token, $this->addresses->ensureToken($company));
        $this->assertSame($token, $company->fresh()->bank_inbound_token);
        $this->assertSame($newLength, strlen($this->addresses->generateUniqueToken()));
    }

    #[Test]
    #[TestWith(['payshort12@payments.satflux.io'])]
    #[TestWith(['payabcdefghijklmnopq@payments.satflux.io'])]
    #[TestWith(['payabc_1234@payments.satflux.io'])]
    #[TestWith(['payabc-1234@payments.satflux.io'])]
    #[TestWith(['payabc.1234@payments.satflux.io'])]
    #[TestWith(['payabc12345+tag@payments.satflux.io'])]
    #[TestWith(['psabc12345@payments.satflux.io'])]
    #[TestWith(['xpayabc12345@payments.satflux.io'])]
    #[TestWith(['payabc12345@other.example'])]
    #[TestWith(['payabc12345@payments.satflux.io.example'])]
    public function malformed_addresses_never_resolve_a_company(string $address): void
    {
        $this->company('abc12345');

        $this->expectException(ValidationException::class);
        $this->addresses->resolveCompany($address);
    }

    #[Test]
    public function a_valid_looking_unknown_token_does_not_match_a_known_prefix(): void
    {
        $this->company('abc12345');

        $this->expectException(ModelNotFoundException::class);
        $this->addresses->resolveCompany('payabc12345zzzz@payments.satflux.io');
    }

    #[Test]
    public function company_and_store_tokens_are_resolved_only_under_their_own_prefix(): void
    {
        $token = 'shared123456';
        $company = $this->company($token);
        $store = Store::factory()->create();
        $store->forceFill(['bank_inbound_token' => $token])->save();

        $companyOwner = $this->addresses->resolveOwner('pay'.$token.'@payments.satflux.io');
        $storeOwner = $this->addresses->resolveOwner('ps'.$token.'@payments.satflux.io');

        $this->assertInstanceOf(Company::class, $companyOwner);
        $this->assertSame($company->id, $companyOwner->id);
        $this->assertInstanceOf(Store::class, $storeOwner);
        $this->assertSame($store->id, $storeOwner->id);
    }

    #[Test]
    public function a_store_token_cannot_be_used_under_the_company_prefix(): void
    {
        $store = Store::factory()->create();
        $store->forceFill(['bank_inbound_token' => 'store1234567'])->save();

        $this->expectException(ModelNotFoundException::class);
        $this->addresses->resolveOwner('paystore1234567@payments.satflux.io');
    }
}
