<?php

namespace Tests\Feature;

use App\Enums\CompanyJurisdiction;
use App\Enums\CompanyMemberRole;
use App\Models\Company;
use App\Models\CompanyAutoIssueProfile;
use App\Models\CompanyMember;
use App\Models\User;
use App\Services\Integrations\IntegrationAutoIssueService;
use App\Services\Invoicing\BusinessDocumentPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EphemeralSmtpCredentialIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $member;

    private Company $company;

    /** @var list<array<string, mixed>> */
    private array $transports = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => 'enterprise']);
        $this->member = User::factory()->create();
        $this->company = Company::create([
            'user_id' => $this->owner->id,
            'legal_name' => 'SMTP Isolation Co',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
            'email_settings' => [
                'delivery_method' => 'smtp',
                'smtp' => [
                    'host' => 'owner-mail.example.test',
                    'port' => 587,
                    'encryption' => 'tls',
                    'username' => 'owner@example.test',
                    'password_encrypted' => Crypt::encryptString('synthetic-owner-password'),
                ],
            ],
        ]);
        CompanyMember::create([
            'company_id' => $this->company->id,
            'user_id' => $this->member->id,
            'role' => CompanyMemberRole::Accountant,
            'invited_by' => $this->owner->id,
            'accepted_at' => now(),
        ]);

        // Exercise mailer construction while keeping every delivery in memory.
        Mail::extend('smtp', function (array $config): ArrayTransport {
            $this->transports[] = $config;

            return new ArrayTransport;
        });
        $this->mock(BusinessDocumentPdfService::class)
            ->shouldReceive('renderBinary')->andReturn('%PDF-synthetic-test-attachment');
    }

    /** @return iterable<string, array{string, array<string, mixed>}> */
    public static function unsafeOverrides(): iterable
    {
        foreach (['test-smtp', 'send-email'] as $flow) {
            foreach ([
                'host' => ['host' => 'attacker-mail.example.test'],
                'port' => ['port' => 2525],
                'security' => ['encryption' => 'none'],
                'username' => ['username' => 'attacker@example.test'],
                'empty password' => ['host' => 'attacker-mail.example.test', 'password' => ''],
                'password flag' => ['host' => 'attacker-mail.example.test', 'password_set' => true],
            ] as $name => $smtp) {
                yield "$flow / $name" => [$flow, $smtp];
            }
        }
    }

    #[Test]
    #[DataProvider('unsafeOverrides')]
    public function member_cannot_redirect_stored_smtp_credentials(string $flow, array $smtp): void
    {
        $before = $this->company->email_settings;

        $this->actingAs($this->member)
            ->postJson($this->url($flow), $this->payload($flow, $smtp))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('company.email_settings.smtp.password');

        $this->assertSame([], $this->transports);
        $this->assertSame($before, $this->company->fresh()->email_settings);
    }

    /** @return iterable<string, array{string}> */
    public static function emailFlows(): iterable
    {
        yield 'SMTP test' => ['test-smtp'];
        yield 'ephemeral email' => ['send-email'];
    }

    #[Test]
    #[DataProvider('emailFlows')]
    public function owner_can_use_unchanged_stored_smtp_credentials(string $flow): void
    {
        $this->actingAs($this->owner)
            ->postJson($this->url($flow), $this->payload($flow, ['host' => 'owner-mail.example.test', 'port' => '587']))
            ->assertOk();

        $this->assertCount(1, $this->transports);
        $this->assertSame('owner-mail.example.test', $this->transports[0]['host']);
        $this->assertSame('synthetic-owner-password', $this->transports[0]['password']);
    }

    #[Test]
    #[DataProvider('emailFlows')]
    public function default_port_and_security_do_not_require_reentering_the_password(string $flow): void
    {
        $settings = $this->company->email_settings;
        $settings['smtp']['port'] = null;
        $this->company->update(['email_settings' => $settings]);

        $this->actingAs($this->owner)
            ->postJson($this->url($flow), $this->payload($flow, [
                'port' => '587',
                'encryption' => null,
                'from_name' => 'Invoice sender',
            ]))->assertOk();

        $this->assertSame(587, $this->transports[0]['port']);
        $this->assertSame('tls', $this->transports[0]['encryption']);
        $this->assertSame('synthetic-owner-password', $this->transports[0]['password']);
    }

    #[Test]
    #[DataProvider('emailFlows')]
    public function member_can_use_explicit_ephemeral_credentials_without_using_the_stored_password(string $flow): void
    {
        $this->assertExplicitCredentialsWork($this->member, $flow);
    }

    #[Test]
    #[DataProvider('emailFlows')]
    public function owner_can_use_explicit_ephemeral_credentials(string $flow): void
    {
        $this->assertExplicitCredentialsWork($this->owner, $flow);
    }

    #[Test]
    #[DataProvider('emailFlows')]
    public function company_less_flows_cannot_redirect_stored_credentials(string $flow): void
    {
        $url = $flow === 'test-smtp'
            ? '/api/invoicing/ephemeral/email-settings/test-smtp'
            : '/api/invoicing/ephemeral/send-email';

        $this->actingAs($this->owner)
            ->postJson($url, $this->payload($flow, ['host' => 'attacker-mail.example.test']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('company.email_settings.smtp.password');

        $this->assertSame([], $this->transports);
    }

    #[Test]
    public function persistent_smtp_configuration_and_testing_stay_owner_only(): void
    {
        $before = $this->company->email_settings;
        $base = "/api/invoicing/companies/{$this->company->id}/email-settings";

        $this->actingAs($this->member)->patchJson($base, [
            'smtp' => ['host' => 'attacker-mail.example.test'],
        ])->assertForbidden();
        $this->actingAs($this->member)->postJson($base.'/test-smtp', [
            'to' => 'recipient@example.test',
        ])->assertForbidden();

        $this->assertSame([], $this->transports);
        $this->assertSame($before, $this->company->fresh()->email_settings);

        $this->actingAs($this->owner)->postJson($base.'/test-smtp', [
            'to' => 'recipient@example.test',
        ])->assertOk();
        $this->assertSame('synthetic-owner-password', $this->transports[0]['password']);
    }

    #[Test]
    public function queued_auto_issue_snapshot_cannot_redirect_stored_smtp_credentials(): void
    {
        $payload = $this->payload('test-smtp', ['host' => 'attacker-mail.example.test']);
        $profile = CompanyAutoIssueProfile::create([
            'company_id' => $this->company->id,
            'profile_json' => ['company' => $payload['company']],
        ]);
        // This is the snapshot builder used by the queued WooCommerce mail job.
        try {
            app(IntegrationAutoIssueService::class)->buildCompany($this->company, $profile);
            $this->fail('An unsafe queued SMTP snapshot must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('company.email_settings.smtp.password', $exception->errors());
        }
        $this->assertSame([], $this->transports);
    }

    private function assertExplicitCredentialsWork(User $actor, string $flow): void
    {
        $before = $this->company->email_settings;
        $this->actingAs($actor)
            ->postJson($this->url($flow), $this->payload($flow, [
                'host' => 'caller-mail.example.test',
                'port' => 2525,
                'encryption' => 'tls',
                'username' => 'caller@example.test',
                'password' => 'synthetic-caller-password',
            ]))->assertOk();

        $this->assertCount(1, $this->transports);
        $this->assertSame('caller-mail.example.test', $this->transports[0]['host']);
        $this->assertSame('caller@example.test', $this->transports[0]['username']);
        $this->assertSame('synthetic-caller-password', $this->transports[0]['password']);
        $this->assertSame($before, $this->company->fresh()->email_settings);
    }

    private function url(string $flow): string
    {
        $base = "/api/invoicing/companies/{$this->company->id}";

        return $flow === 'test-smtp'
            ? $base.'/email-settings/ephemeral/test-smtp'
            : $base.'/documents/ephemeral/send-email';
    }

    /** @param array<string, mixed> $smtp
     * @return array<string, mixed>
     */
    private function payload(string $flow, array $smtp): array
    {
        $payload = [
            'company' => [
                'legal_name' => $this->company->legal_name,
                'email_settings' => ['delivery_method' => 'smtp', 'smtp' => $smtp],
            ],
            'to' => $flow === 'test-smtp' ? 'recipient@example.test' : ['recipient@example.test'],
        ];
        if ($flow === 'send-email') {
            $payload['document'] = [
                'type' => 'invoice',
                'status' => 'issued',
                'number' => 'TEST-001',
                'issue_date' => now()->toDateString(),
                'currency' => 'EUR',
            ];
            $payload['lines'] = [['name' => 'Test service', 'quantity' => 1, 'unit_price' => 10]];
        }

        return $payload;
    }
}
