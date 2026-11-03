<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ExpenseIsdocCreditBalance;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Invoicing\BusinessExpenseIsdocImportService;
use App\Services\Invoicing\BusinessExpenseIsdocQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EphemeralBusinessExpenseTest extends TestCase
{
    use RefreshDatabase;

    private const ROOT = '/api/invoicing/ephemeral/expenses/';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config(['access.free_pro_enabled' => false]);
        Storage::fake('local');
        Storage::fake('public');
        $plan = SubscriptionPlan::create([
            'code' => 'pro', 'name' => 'pro', 'display_name' => 'Pro',
            'price_eur' => 99, 'billing_period' => 'year', 'max_stores' => 3,
            'max_api_keys' => 3, 'features' => ['business_invoicing'], 'is_active' => true,
        ]);
        $this->user = User::factory()->create();
        Subscription::create([
            'user_id' => $this->user->id, 'plan_id' => $plan->id, 'status' => 'active',
            'starts_at' => now(), 'expires_at' => now()->addYear(),
        ]);
    }

    /** @return array<string, array{string, string}> */
    public static function endpoints(): array
    {
        return [
            'detect' => ['POST', 'detect-isdoc'],
            'extract' => ['POST', 'extract'],
            'quota' => ['GET', 'isdoc-extract-quota'],
        ];
    }

    #[Test]
    #[DataProvider('endpoints')]
    public function endpoints_require_authentication(string $method, string $endpoint): void
    {
        $this->json($method, self::ROOT.$endpoint)->assertUnauthorized();
        $this->assertNothingStored();
    }

    #[Test]
    #[DataProvider('endpoints')]
    public function endpoints_require_verified_email(string $method, string $endpoint): void
    {
        $this->user->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($this->user)->json($method, self::ROOT.$endpoint)
            ->assertForbidden()->assertJsonPath('code', 'email_not_verified');
        $this->assertNothingStored();
    }

    #[Test]
    #[DataProvider('endpoints')]
    public function endpoints_preserve_the_business_invoicing_entitlement_gate(string $method, string $endpoint): void
    {
        $this->actingAs(User::factory()->create())->json($method, self::ROOT.$endpoint)->assertForbidden();
        $this->assertNothingStored();
    }

    #[Test]
    public function detection_without_a_company_uses_the_existing_parser_and_does_not_consume_quota(): void
    {
        $this->mock(BusinessExpenseIsdocImportService::class, function (MockInterface $mock) {
            $mock->shouldReceive('hasIsdocInUpload')->once()->withArgs(fn (UploadedFile $file) => $file->getClientOriginalName() === 'synthetic.pdf')->andReturn(true);
        });
        $this->actingAs($this->user)->postJson(self::ROOT.'detect-isdoc', ['file' => $this->pdf()])
            ->assertOk()->assertJsonPath('data.has_isdoc', true)
            ->assertJsonPath('data.filename', 'synthetic.pdf')->assertJsonPath('data.quota.used', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertNothingStored();
    }

    #[Test]
    public function successful_extraction_records_only_the_authenticated_users_usage_and_no_document_identity(): void
    {
        $other = User::factory()->create();
        AuditLog::log(BusinessExpenseIsdocQuotaService::EXTRACT_ACTION, 'business_expense', null, ['billing' => 'free'], $other->id);
        $this->fakeExtraction();
        $this->actingAs($this->user)->postJson(self::ROOT.'extract', ['file' => $this->pdf()])
            ->assertOk()->assertJsonPath('data.source', 'isdoc')->assertJsonPath('data.total', 12.5)
            ->assertJsonPath('quota.used', 1);
        $audit = AuditLog::where('user_id', $this->user->id)->where('action', BusinessExpenseIsdocQuotaService::EXTRACT_ACTION)->sole();
        $this->assertNull($audit->target_id);
        $this->assertSame(['billing' => 'free'], $audit->metadata);
        $this->actingAs($this->user)->getJson(self::ROOT.'isdoc-extract-quota')
            ->assertOk()->assertJsonPath('data.used', 1);
        $this->assertSame(1, AuditLog::where('user_id', $other->id)->count());
        $this->assertNothingStored();
    }

    #[Test]
    public function extraction_failure_does_not_record_or_consume_quota(): void
    {
        $this->mock(BusinessExpenseIsdocImportService::class, function (MockInterface $mock) {
            $mock->shouldReceive('extractFromUpload')->once()->andThrow(ValidationException::withMessages(['file' => ['Synthetic unreadable ISDOC.']]));
        });
        $this->actingAs($this->user)->postJson(self::ROOT.'extract', ['file' => $this->pdf()])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertNothingStored();
    }

    #[Test]
    public function a_free_unit_used_during_parsing_is_rechecked_before_the_draft_is_returned(): void
    {
        config(['invoicing.expense_isdoc_extract_free_limit' => 1]);
        $this->mock(BusinessExpenseIsdocImportService::class, function (MockInterface $mock) {
            $mock->shouldReceive('extractFromUpload')->once()->andReturnUsing(function () {
                AuditLog::log(BusinessExpenseIsdocQuotaService::EXTRACT_ACTION, 'business_expense', null, ['billing' => 'free'], $this->user->id);

                return ['source' => 'isdoc', 'issue_date' => '2026-06-01', 'total' => 12.5, 'currency' => 'EUR'];
            });
        });
        $this->actingAs($this->user)->postJson(self::ROOT.'extract', ['file' => $this->pdf()])
            ->assertUnprocessable()->assertJsonValidationErrors('quota');
        $this->assertSame(1, AuditLog::where('user_id', $this->user->id)->where('action', BusinessExpenseIsdocQuotaService::EXTRACT_ACTION)->count());
        $this->assertNothingStored();
    }

    #[Test]
    public function exhausted_quota_blocks_extraction_before_parsing_but_detection_remains_available(): void
    {
        config(['invoicing.expense_isdoc_extract_free_limit' => 0]);
        $this->mock(BusinessExpenseIsdocImportService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('extractFromUpload');
            $mock->shouldReceive('hasIsdocInUpload')->once()->andReturn(false);
        });
        $this->actingAs($this->user)->postJson(self::ROOT.'extract', ['file' => $this->pdf()])
            ->assertUnprocessable()->assertJsonValidationErrors('quota');
        $this->postJson(self::ROOT.'detect-isdoc', ['file' => $this->pdf()])
            ->assertOk()->assertJsonPath('data.has_isdoc', false)->assertJsonPath('data.quota.can_extract', false);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertNothingStored();
    }

    #[Test]
    public function purchased_credit_consumption_remains_scoped_to_the_authenticated_user(): void
    {
        config(['invoicing.expense_isdoc_extract_free_limit' => 0]);
        $other = User::factory()->create();
        ExpenseIsdocCreditBalance::create(['user_id' => $this->user->id, 'balance' => 2]);
        ExpenseIsdocCreditBalance::create(['user_id' => $other->id, 'balance' => 7]);
        $this->fakeExtraction();
        $this->actingAs($this->user)->postJson(self::ROOT.'extract', ['file' => $this->pdf()])
            ->assertOk()->assertJsonPath('quota.purchased_credits', 1);
        $this->assertSame(1, (int) ExpenseIsdocCreditBalance::where('user_id', $this->user->id)->value('balance'));
        $this->assertSame(7, (int) ExpenseIsdocCreditBalance::where('user_id', $other->id)->value('balance'));
        $this->assertSame(['billing' => 'purchased'], AuditLog::where('user_id', $this->user->id)->sole()->metadata);
        $this->assertNothingStored();
    }

    #[Test]
    #[DataProvider('endpoints')]
    public function callers_cannot_supply_company_expense_or_user_identity(string $method, string $endpoint): void
    {
        $this->mock(BusinessExpenseIsdocImportService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('extractFromUpload');
            $mock->shouldNotReceive('hasIsdocInUpload');
        });
        $this->actingAs($this->user)->json($method, self::ROOT.$endpoint, [
            'file' => $this->pdf(), 'company_id' => 'abcdefghijklmnopqrstuv', 'expense_id' => 'synthetic-expense', 'user_id' => 999,
        ])->assertUnprocessable()->assertJsonValidationErrors(['company_id', 'expense_id', 'user_id']);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertNothingStored();
    }

    #[Test]
    public function missing_wrong_extension_and_oversized_files_fail_before_parsing(): void
    {
        $this->mock(BusinessExpenseIsdocImportService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('extractFromUpload');
            $mock->shouldNotReceive('hasIsdocInUpload');
        });
        $this->actingAs($this->user);
        foreach (['detect-isdoc', 'extract'] as $endpoint) {
            foreach ([null, UploadedFile::fake()->create('synthetic.exe', 1), UploadedFile::fake()->create('synthetic.pdf', 10241, 'application/pdf')] as $file) {
                $this->postJson(self::ROOT.$endpoint, $file === null ? [] : ['file' => $file])
                    ->assertUnprocessable()->assertJsonValidationErrors('file');
            }
        }
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertNothingStored();
    }

    #[Test]
    public function plaintext_detection_has_a_bounded_user_rate_limit(): void
    {
        $this->mock(BusinessExpenseIsdocImportService::class, function (MockInterface $mock) {
            $mock->shouldReceive('hasIsdocInUpload')->times(10)->andReturn(false);
        });
        $this->actingAs($this->user);
        for ($i = 0; $i < 10; $i++) {
            $this->postJson(self::ROOT.'detect-isdoc', ['file' => $this->pdf()])->assertOk();
        }
        $this->postJson(self::ROOT.'detect-isdoc', ['file' => $this->pdf()])->assertStatus(429);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertNothingStored();
    }

    private function fakeExtraction(): void
    {
        $this->mock(BusinessExpenseIsdocImportService::class, function (MockInterface $mock) {
            $mock->shouldReceive('extractFromUpload')->once()->andReturn([
                'source' => 'isdoc', 'title' => 'Synthetic supplier', 'external_number' => 'SYNTHETIC-1',
                'issue_date' => '2026-06-01', 'total' => 12.5, 'currency' => 'EUR',
            ]);
        });
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('synthetic.pdf', "%PDF-1.4\n%%EOF\n");
    }

    private function assertNothingStored(): void
    {
        foreach (['companies', 'business_expenses', 'business_expense_attachments', 'business_documents'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
