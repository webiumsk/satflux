<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TicketTypeLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public static function actions(): array
    {
        return [['DELETE', ''], ['PUT', '/toggle']];
    }

    #[DataProvider('actions')]
    public function test_lifecycle_uses_the_event_scoped_plugin_route_and_merchant_key(string $method, string $suffix): void
    {
        config(['services.btcpay.base_url' => 'https://btcpay.test', 'services.btcpay.api_key' => 'server-key']);
        $owner = User::factory()->create(['btcpay_api_key' => 'merchant-key']);
        $store = Store::factory()->create(['user_id' => $owner->id, 'btcpay_store_id' => 'upstream-store']);
        $url = 'https://btcpay.test/api/v1/stores/upstream-store/satflux-tickets/events/event-one/ticket-types/type-one'.$suffix;
        Http::fake([$url => Http::response($method === 'PUT' ? ['id' => 'type-one', 'ticketTypeState' => 'Disabled'] : null, 200)]);
        Sanctum::actingAs($owner);

        $this->json($method, "/api/stores/{$store->id}/tickets/events/event-one/ticket-types/type-one".$suffix)->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->url() === $url
            && $request->method() === $method
            && $request->hasHeader('Authorization', 'Bearer merchant-key'));
    }

    public function test_disabled_ticket_type_can_be_activated(): void
    {
        config(['services.btcpay.base_url' => 'https://btcpay.test', 'services.btcpay.api_key' => 'server-key']);
        $store = Store::factory()->create();
        Http::fake(['*' => Http::response(['id' => 'type-one', 'ticketTypeState' => 'Active'])]);
        Sanctum::actingAs($store->user);

        $this->putJson("/api/stores/{$store->id}/tickets/events/event-one/ticket-types/type-one/toggle")
            ->assertOk()->assertJsonPath('data.ticketTypeState', 'Active');
    }

    #[DataProvider('actions')]
    public function test_another_merchant_cannot_mutate_a_ticket_type(string $method, string $suffix): void
    {
        $store = Store::factory()->create();
        Sanctum::actingAs(User::factory()->create());
        Http::fake();

        $this->json($method, "/api/stores/{$store->id}/tickets/events/event-one/ticket-types/type-one".$suffix)->assertForbidden();
        Http::assertNothingSent();
    }

    #[DataProvider('actions')]
    public function test_provider_event_mismatch_remains_not_found_without_fallback(string $method, string $suffix): void
    {
        config(['services.btcpay.base_url' => 'https://btcpay.test', 'services.btcpay.api_key' => 'server-key']);
        $store = Store::factory()->create();
        Http::fake(['*' => Http::response(['code' => 'event-not-found', 'message' => 'private upstream detail'], 404)]);
        Sanctum::actingAs($store->user);

        $this->json($method, "/api/stores/{$store->id}/tickets/events/other-event/ticket-types/type-one".$suffix)
            ->assertNotFound()->assertDontSee('private upstream detail');
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/events/other-event/ticket-types/type-one'));
    }

    public function test_toggle_is_not_replayed_after_an_uncertain_transport_failure(): void
    {
        config(['services.btcpay.base_url' => 'https://btcpay.test', 'services.btcpay.api_key' => 'server-key']);
        $store = Store::factory()->create();
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;
            throw new ConnectionException('Synthetic transport failure');
        });
        Sanctum::actingAs($store->user);

        $this->putJson("/api/stores/{$store->id}/tickets/events/event-one/ticket-types/type-one/toggle")
            ->assertStatus(502)->assertJsonPath('code', 'ticket_type_change_failed');
        $this->assertSame(1, $calls);
    }

    public function test_guest_cannot_mutate_ticket_types(): void
    {
        $owner = User::factory()->guest()->create();
        $store = Store::factory()->create(['user_id' => $owner->id]);
        Http::fake();
        Sanctum::actingAs($owner);

        $this->putJson("/api/stores/{$store->id}/tickets/events/event-one/ticket-types/type-one/toggle")->assertForbidden();
        Http::assertNothingSent();
    }
}
