<?php

namespace Tests\Feature;

use App\Jobs\ProcessBankNotificationEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_and_non_staff_cannot_read_catalog_or_specs(): void
    {
        foreach (['catalog', 'specs/satflux', 'specs/woocommerce', 'specs/webhooks'] as $path) {
            $this->getJson('/api/admin/api-docs/'.$path)->assertUnauthorized();
        }
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);
        foreach (['catalog', 'specs/satflux', 'specs/woocommerce', 'specs/webhooks'] as $path) {
            $this->getJson('/api/admin/api-docs/'.$path)->assertForbidden();
        }
    }

    public function test_staff_can_read_only_fixed_static_documents_without_external_requests(): void
    {
        Http::preventStrayRequests();
        foreach (['admin', 'support'] as $role) {
            $user = User::factory()->create(['email_verified_at' => now()]);
            $user->forceFill(['role' => $role])->save();
            $this->actingAs($user);
            $catalog = $this->getJson('/api/admin/api-docs/catalog')->assertOk()
                ->assertHeader('Cache-Control', 'no-store, private')
                ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
            $this->assertCount(3, $catalog->json('documents'));
            foreach (['satflux', 'woocommerce', 'webhooks'] as $document) {
                $this->getJson('/api/admin/api-docs/specs/'.$document)->assertOk()
                    ->assertHeader('Cache-Control', 'no-store, private')
                    ->assertJsonPath('openapi', '3.1.0');
            }
            auth()->shouldUse('web');
            $this->getJson('/api/admin/api-docs/specs/route-coverage')->assertNotFound();
            $this->getJson('/api/admin/api-docs/specs/composer')->assertNotFound();
        }
        Http::assertNothingSent();
    }

    public function test_unverified_staff_cannot_read_the_reference(): void
    {
        $user = User::factory()->unverified()->create();
        $user->forceFill(['role' => 'admin'])->save();
        $this->actingAs($user)->getJson('/api/admin/api-docs/catalog')->assertForbidden();
    }

    public function test_scramble_does_not_register_public_runtime_documentation(): void
    {
        $routes = collect(app('router')->getRoutes())->map(fn ($route) => $route->uri());
        $this->assertFalse($routes->contains('docs/api'));
        $this->assertFalse($routes->contains('docs/api.json'));
    }

    public function test_export_fails_closed_on_unsafe_generation_configuration(): void
    {
        config(['mail.default' => 'smtp']);
        $this->artisan('api-docs:export', ['--check' => true])->assertFailed();
    }

    public function test_mailgun_schema_and_receiver_accept_each_supported_sender_and_body_alias(): void
    {
        $hooks = json_decode(file_get_contents(base_path('docs/api/webhooks.json')), true);
        $schema = $hooks['paths']['/webhooks/bank-inbound']['post']['requestBody']['content']['application/x-www-form-urlencoded']['schema'];
        $this->assertSame(['recipient', 'timestamp', 'token', 'signature'], $schema['required']);
        $senderAlternatives = array_column($schema['allOf'][0]['anyOf'], 'required');
        $bodyAlternatives = array_column($schema['allOf'][1]['anyOf'], 'required');
        config(['bank_inbound.mailgun_webhook_signing_key' => 'synthetic-mailgun-key']);
        Queue::fake();

        foreach (['sender', 'from', 'From'] as $senderField) {
            $this->assertArrayHasKey($senderField, $schema['properties']);
            $this->assertContains([$senderField], $senderAlternatives);
            foreach (['stripped-text', 'body-plain', 'stripped-html', 'body-html'] as $bodyField) {
                $this->assertArrayHasKey($bodyField, $schema['properties']);
                $this->assertContains([$bodyField], $bodyAlternatives);
                $timestamp = (string) time();
                $token = 'synthetic-'.$senderField.'-'.$bodyField;
                $payload = [
                    'recipient' => 'inbound@example.invalid',
                    $senderField => 'bank@example.invalid',
                    $bodyField => 'Synthetic bank notification',
                    'timestamp' => $timestamp,
                    'token' => $token,
                    'signature' => hash_hmac('sha256', $timestamp.$token, 'synthetic-mailgun-key'),
                ];
                foreach ($schema['required'] as $required) {
                    $this->assertArrayHasKey($required, $payload);
                }
                $this->post('/api/webhooks/bank-inbound', $payload, ['Accept' => 'application/json'])
                    ->assertOk()->assertJsonPath('accepted', true);
            }
        }
        Queue::assertPushed(ProcessBankNotificationEmail::class, 12);
    }

    public function test_checked_export_detects_route_policy_drift(): void
    {
        $this->artisan('api-docs:export', ['--check' => true])->assertSuccessful();
        Route::get('/api/synthetic-doc-drift', fn () => []);
        $this->artisan('api-docs:export', ['--check' => true])->assertFailed();
    }

    public function test_contracts_preserve_auth_units_and_inbox_variants(): void
    {
        $satflux = json_decode(file_get_contents(base_path('docs/api/satflux.json')), true);
        $woo = json_decode(file_get_contents(base_path('docs/api/woocommerce.json')), true);
        $hooks = json_decode(file_get_contents(base_path('docs/api/webhooks.json')), true);
        $this->assertSame([['sessionCookie' => []]], $satflux['paths']['/stores/{store}/raffles']['post']['security']);
        $this->assertSame('uuid', $satflux['paths']['/stores/{store}/raffles']['post']['parameters'][0]['schema']['format']);
        $this->assertArrayHasKey('419', $satflux['paths']['/stores/{store}/raffles']['post']['responses']);
        $this->assertSame([], $woo['paths']['/integrations/woocommerce/oauth-exchange']['get']['security']);
        $this->assertSame([['integrationToken' => []]], $woo['paths']['/integrations/woocommerce/documents']['post']['security']);
        $this->assertSame('object', $woo['paths']['/integrations/woocommerce/documents']['post']['requestBody']['content']['application/json']['schema']['properties']['buyer']['type']);
        $this->assertArrayHasKey('PendingInboxDocument', $woo['components']['schemas']);
        $this->assertArrayHasKey('application/pdf', $woo['paths']['/integrations/woocommerce/documents/{documentId}/pdf']['get']['responses']['200']['content']);
        $this->assertSame([['btcpaySignature' => []]], $hooks['paths']['/webhooks/btcpay']['post']['security']);
        $this->assertStringContainsString('NOT raw-body HMAC', $hooks['paths']['/webhooks/bank-inbound']['post']['description']);
        $this->assertSame(['timestamp', 'token', 'signature'], $hooks['paths']['/webhooks/bank-inbound']['post']['x-body-authentication']['requiredFields']);
    }
}
