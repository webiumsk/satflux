<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use App\Services\BtcPay\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TicketEventImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.btcpay.base_url' => 'https://btcpay.test', 'services.btcpay.api_key' => 'server-key']);
    }

    public function test_image_upload_uses_only_the_owned_event_endpoint_with_merchant_key(): void
    {
        $store = $this->ownedStore();
        $url = 'https://btcpay.test/api/v1/stores/upstream-store/satflux-tickets/events/event-one/logo';
        Http::fake([$url => Http::response([
            'id' => 'event-one', 'storeId' => 'upstream-store',
            'eventLogoFileId' => 'file-id', 'eventLogoUrl' => 'https://btcpay.test/LocalStorage/safe-image.png',
        ])]);

        $this->postJson("/api/stores/{$store->id}/tickets/events/event-one/logo", ['file' => UploadedFile::fake()->image('picture.png')])
            ->assertOk()->assertJsonPath('data.eventLogoFileId', 'file-id')
            ->assertJsonPath('data.eventLogoUrl', 'https://btcpay.test/LocalStorage/safe-image.png')
            ->assertJsonMissingPath('data.storeId');

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->url() === $url
            && $request->method() === 'POST' && $request->isMultipart()
            && $request->hasHeader('Authorization', 'Bearer merchant-key'));
    }

    public function test_old_provider_upload_response_requires_refresh_instead_of_a_fabricated_image_url(): void
    {
        $store = $this->ownedStore();
        Http::fake(['*' => Http::response(['id' => 'event-one'])]);
        $this->postJson("/api/stores/{$store->id}/tickets/events/event-one/logo", ['file' => UploadedFile::fake()->image('picture.png')])
            ->assertStatus(502)->assertJsonPath('code', 'ticket_event_logo_response_incomplete');
        Http::assertSentCount(1);
    }

    public function test_image_delete_is_event_scoped(): void
    {
        $store = $this->ownedStore();
        Http::fake(['*' => Http::response(['id' => 'event-one', 'eventLogoFileId' => null, 'eventLogoUrl' => null])]);

        $this->deleteJson("/api/stores/{$store->id}/tickets/events/event-one/logo")
            ->assertOk()->assertJsonPath('data.eventLogoFileId', '')->assertJsonPath('data.eventLogoUrl', '');
        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
            && $request->url() === 'https://btcpay.test/api/v1/stores/upstream-store/satflux-tickets/events/event-one/logo'
            && $request->hasHeader('Authorization', 'Bearer merchant-key'));
    }

    public static function upstreamFailures(): array
    {
        return [[403, 403], [404, 404], [422, 422], [500, 502]];
    }

    #[DataProvider('upstreamFailures')]
    public function test_upload_failures_never_fallback_or_expose_provider_error(int $status, int $expectedStatus): void
    {
        $store = $this->ownedStore();
        Http::fake(['*' => Http::response(['message' => 'sensitive upstream payload'], $status)]);

        $this->postJson("/api/stores/{$store->id}/tickets/events/other-event/logo", ['file' => UploadedFile::fake()->image('picture.png')])
            ->assertStatus($expectedStatus)->assertJsonPath('code', 'ticket_event_logo_upload_failed')
            ->assertDontSee('sensitive upstream payload');
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/events/other-event/logo')
            && $request->hasHeader('Authorization', 'Bearer merchant-key'));
    }

    public function test_foreign_store_and_guest_uploads_are_rejected_before_btcpay(): void
    {
        $store = Store::factory()->create();
        Http::fake();
        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/stores/{$store->id}/tickets/events/event-one/logo", ['file' => UploadedFile::fake()->image('picture.png')])->assertForbidden();

        $guest = User::factory()->guest()->create();
        $guestStore = Store::factory()->create(['user_id' => $guest->id]);
        Sanctum::actingAs($guest);
        $this->postJson("/api/stores/{$guestStore->id}/tickets/events/event-one/logo", ['file' => UploadedFile::fake()->image('picture.png')])->assertForbidden();
        Http::assertNothingSent();
    }

    public static function privilegedRoles(): array
    {
        return [['admin'], ['support']];
    }

    #[DataProvider('privilegedRoles')]
    public function test_audited_privileged_access_still_uses_store_owner_credentials(string $role): void
    {
        $store = $this->ownedStore();
        Sanctum::actingAs(User::factory()->create(['role' => $role, 'btcpay_api_key' => 'accessor-key']));
        Http::fake(['*' => Http::response(['id' => 'event-one', 'eventLogoUrl' => 'https://btcpay.test/image.png'])]);

        $this->postJson("/api/stores/{$store->id}/tickets/events/event-one/logo", ['file' => UploadedFile::fake()->image('picture.png')])->assertOk();
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer merchant-key'));
    }

    public function test_non_images_and_oversized_files_are_rejected_before_btcpay(): void
    {
        $store = $this->ownedStore();
        Http::fake();
        foreach ([UploadedFile::fake()->create('payload.php', 1, 'image/png'), UploadedFile::fake()->createWithContent('vector.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'), UploadedFile::fake()->image('large.png')->size(2049)] as $file) {
            $this->postJson("/api/stores/{$store->id}/tickets/events/event-one/logo", ['file' => $file])
                ->assertUnprocessable()->assertJsonValidationErrors('file');
        }
        Http::assertNothingSent();
    }

    public function test_legacy_pre_event_upload_is_retired_without_global_file_api(): void
    {
        $store = $this->ownedStore();
        Http::fake();
        $this->postJson("/api/stores/{$store->id}/tickets/events/image", ['image' => UploadedFile::fake()->image('picture.png')])
            ->assertGone()->assertJsonPath('code', 'ticket_event_required');
        Http::assertNothingSent();
    }

    public function test_event_json_cannot_bind_an_arbitrary_file_or_trigger_file_resolution(): void
    {
        $store = $this->ownedStore();
        Http::fake(['*' => Http::response(['id' => 'event-one', 'storeId' => 'upstream-store'])]);

        $this->putJson("/api/stores/{$store->id}/tickets/events/event-one", [
            'title' => 'Updated', 'eventLogoFileId' => 'other-merchant-file', 'eventLogoUrl' => 'https://untrusted.test/image.png',
        ])->assertOk()->assertJsonMissingPath('data.storeId');

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
            && ! array_key_exists('eventLogoFileId', $request->data()) && ! array_key_exists('eventLogoUrl', $request->data()));
    }

    public function test_missing_logo_url_is_not_fabricated_from_a_file_id(): void
    {
        $store = $this->ownedStore();
        Http::fake(['*' => Http::response(['id' => 'event-one', 'eventLogoFileId' => 'opaque-id'])]);
        $this->getJson("/api/stores/{$store->id}/tickets/events/event-one")
            ->assertOk()->assertJsonPath('data.eventLogoUrl', '');
    }

    public function test_empty_merchant_key_cannot_use_server_key_for_upload(): void
    {
        Http::fake();
        $this->expectException(\InvalidArgumentException::class);
        try {
            app(TicketService::class)->uploadEventLogo('upstream-store', 'event-one', UploadedFile::fake()->image('picture.png'), '');
        } finally {
            Http::assertNothingSent();
        }
    }

    protected function ownedStore(): Store
    {
        $owner = User::factory()->create(['btcpay_api_key' => 'merchant-key']);
        $store = Store::factory()->create(['user_id' => $owner->id, 'btcpay_store_id' => 'upstream-store']);
        Sanctum::actingAs($owner);

        return $store;
    }
}
