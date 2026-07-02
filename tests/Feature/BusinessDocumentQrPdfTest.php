<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentStatus;
use App\Enums\CompanyJurisdiction;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BusinessDocumentQrPdfTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function btc_qr_block_renders_clickable_link_and_branding(): void
    {
        $user = User::factory()->create();
        $proPlan = SubscriptionPlan::create([
            'code' => 'pro',
            'name' => 'pro',
            'display_name' => 'Pro',
            'price_eur' => 99,
            'billing_period' => 'year',
            'max_stores' => 3,
            'max_api_keys' => 3,
            'max_ln_addresses' => null,
            'features' => ['business_invoicing'],
            'is_active' => true,
        ]);
        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $proPlan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $company = Company::create([
            'user_id' => $user->id,
            'legal_name' => 'QR Co',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
        ]);

        $store = Store::factory()->create(['user_id' => $user->id]);
        $doc = BusinessDocument::create([
            'company_id' => $company->id,
            'store_id' => $store->id,
            'type' => 'invoice',
            'status' => BusinessDocumentStatus::Issued,
            'number' => '20260099',
            'total' => 50,
            'currency' => 'EUR',
            'issue_date' => now(),
            'payment_btc_enabled' => true,
            'payment_token' => 'test-token-abc',
        ]);

        $payUrl = 'https://satflux.test/pay/i/test-token-abc';
        $qrDataUri = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        $html = View::make('pdf.business-invoice-eu', [
            'document' => $doc,
            'company' => $company,
            'contact' => null,
            'lines' => collect(),
            'taxBreakdown' => [],
            'showVatColumn' => false,
            'showVatBreakdown' => false,
            'reverseChargeNote' => null,
            'bankQr' => null,
            'btcPayQr' => $qrDataUri,
            'btcPayUrl' => $payUrl,
            'logoDataUri' => null,
            'signatureStampDataUri' => null,
            'isUs' => false,
        ])->render();

        $this->assertStringContainsString('href="'.$payUrl.'"', $html);
        $this->assertStringContainsString('SATFLUX.io', $html);
        $this->assertStringContainsString('Bitcoin', $html);
        $this->assertStringNotContainsString('href="'.$payUrl.'"><img', $html);
        $this->assertStringContainsString('Lightning', $html);
    }

    #[Test]
    public function bank_qr_block_renders_pay_by_square_branding(): void
    {
        $qrDataUri = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        $html = View::make('pdf.partials.business-invoice-qr-block', [
            'isQuote' => false,
            'bankQr' => $qrDataUri,
            'btcPayQr' => null,
            'btcPayUrl' => null,
            'isUs' => false,
        ])->render();

        $this->assertStringContainsString('width: 132px; height: 132px; border: 1.5px solid #00a0e3', $html);
        $this->assertStringContainsString('PAY', $html);
        $this->assertStringContainsString('by square', $html);
    }
}
