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

class TicketTypeOrderTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): Store
    {
        config(['services.btcpay.base_url' => 'https://btcpay.test', 'services.btcpay.api_key' => 'server-key']);
        $owner = User::factory()->create(['btcpay_api_key' => 'merchant-key']);
        $store = Store::factory()->create(['user_id' => $owner->id, 'btcpay_store_id' => 'upstream-store']);
        Sanctum::actingAs($owner);

        return $store;
    }

    private function route(Store $store): string
    {
        return "/api/stores/{$store->id}/tickets/events/event-one/ticket-types/order";
    }

    public function test_snapshot_and_save_use_the_event_scoped_route_and_merchant_key(): void
    {
        $store = $this->fixture();
        $snapshot = ['ticketTypeIds' => ['disabled-type', 'active-type'], 'revision' => 4];
        Http::fake(['*' => Http::response($snapshot + ['privateProviderMetadata' => 'not-public'])]);

        $this->getJson($this->route($store))->assertOk()->assertExactJson(['data' => $snapshot]);
        $this->putJson($this->route($store), ['ticketTypeIds' => $snapshot['ticketTypeIds'], 'expectedRevision' => 3, 'price' => 0])
            ->assertOk()->assertExactJson(['data' => $snapshot]);

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
            && $request->url() === 'https://btcpay.test/api/v1/stores/upstream-store/satflux-tickets/events/event-one/ticket-types/order'
            && $request->hasHeader('Authorization', 'Bearer merchant-key')
            && $request['ticketTypeIds'] === ['disabled-type', 'active-type']
            && $request['expectedRevision'] === 3
            && count($request->data()) === 2);
        foreach (Http::recorded() as [$request]) {
            $this->assertTrue($request->hasHeader('Authorization', 'Bearer merchant-key'));
        }
    }

    public function test_type_listing_defaults_to_saved_order_and_retains_explicit_sort(): void
    {
        $store = $this->fixture();
        Http::fake(['*' => Http::response([])]);
        $url = str_replace('/order', '', $this->route($store));

        $this->getJson($url)->assertOk();
        $this->getJson($url.'?sortBy=Price&sortDir=desc')->assertOk();

        $queries = Http::recorded()->map(function (array $record) {
            parse_str((string) parse_url($record[0]->url(), PHP_URL_QUERY), $query);

            return $query;
        })->all();
        $this->assertSame([
            ['sortBy' => 'DisplayOrder', 'sortDir' => 'asc'],
            ['sortBy' => 'Price', 'sortDir' => 'desc'],
        ], $queries);
    }

    public static function malformedPayloads(): array
    {
        return [
            'missing ids' => [['expectedRevision' => 0]],
            'associative ids' => [['ticketTypeIds' => ['x' => 'one'], 'expectedRevision' => 0]],
            'duplicate ids' => [['ticketTypeIds' => ['one', 'one'], 'expectedRevision' => 0]],
            'non-string id' => [['ticketTypeIds' => [42], 'expectedRevision' => 0]],
            'empty id' => [['ticketTypeIds' => [''], 'expectedRevision' => 0]],
            'long id' => [['ticketTypeIds' => [str_repeat('x', 129)], 'expectedRevision' => 0]],
            'too many ids' => [['ticketTypeIds' => array_map(fn ($n) => 'type-'.$n, range(0, 1000)), 'expectedRevision' => 0]],
            'missing revision' => [['ticketTypeIds' => ['one']]],
            'negative revision' => [['ticketTypeIds' => ['one'], 'expectedRevision' => -1]],
            'fractional revision' => [['ticketTypeIds' => ['one'], 'expectedRevision' => 1.5]],
            'unsafe JS revision' => [['ticketTypeIds' => ['one'], 'expectedRevision' => 9007199254740992]],
        ];
    }

    #[DataProvider('malformedPayloads')]
    public function test_invalid_order_is_rejected_before_any_provider_call(array $payload): void
    {
        $store = $this->fixture();
        Http::fake();

        $this->putJson($this->route($store), $payload)->assertUnprocessable();
        Http::assertNothingSent();
    }

    public static function methods(): array
    {
        return [['GET'], ['PUT']];
    }

    #[DataProvider('methods')]
    public function test_another_merchant_cannot_read_or_write_the_order(string $method): void
    {
        $store = $this->fixture();
        Sanctum::actingAs(User::factory()->create());
        Http::fake();

        $this->json($method, $this->route($store), ['ticketTypeIds' => [], 'expectedRevision' => 0])->assertForbidden();
        Http::assertNothingSent();
    }

    #[DataProvider('methods')]
    public function test_guest_cannot_read_or_write_the_order(string $method): void
    {
        $store = $this->fixture();
        $guest = User::factory()->guest()->create();
        $store->update(['user_id' => $guest->id]);
        Sanctum::actingAs($guest);
        Http::fake();

        $this->json($method, $this->route($store), ['ticketTypeIds' => [], 'expectedRevision' => 0])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_unauthenticated_order_request_is_rejected(): void
    {
        $store = Store::factory()->create();
        Http::fake();

        $this->getJson($this->route($store))->assertUnauthorized();
        $this->putJson($this->route($store), ['ticketTypeIds' => [], 'expectedRevision' => 0])->assertUnauthorized();
        Http::assertNothingSent();
    }

    public static function privilegedRoles(): array
    {
        return [['admin'], ['support']];
    }

    #[DataProvider('privilegedRoles')]
    public function test_existing_privileged_access_is_audited_and_uses_the_store_owners_key(string $role): void
    {
        $store = $this->fixture();
        $accessor = User::factory()->create(['role' => $role, 'btcpay_api_key' => 'accessor-key']);
        Sanctum::actingAs($accessor);
        Http::fake(['*' => Http::response(['ticketTypeIds' => [], 'revision' => 0])]);

        $this->getJson($this->route($store))->assertOk();
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer merchant-key'));
        $this->assertDatabaseHas('audit_logs', ['user_id' => $accessor->id, 'action' => 'store.privileged_access']);
    }

    public static function providerFailures(): array
    {
        return [[403, 403], [404, 404], [409, 409], [422, 422], [500, 502], [502, 502]];
    }

    #[DataProvider('providerFailures')]
    public function test_failure_is_safe_and_never_falls_back_or_replays(int $upstream, int $expected): void
    {
        $store = $this->fixture();
        Http::fake(['*' => Http::response(['message' => 'private upstream detail'], $upstream)]);

        $this->putJson($this->route($store), ['ticketTypeIds' => ['foreign-type'], 'expectedRevision' => 0])
            ->assertStatus($expected)->assertDontSee('private upstream detail')
            ->assertJsonPath('code', $expected === 409 ? 'ticket_type_order_conflict' : 'ticket_type_order_unavailable');
        Http::assertSentCount(1);
    }

    public function test_uncertain_transport_failure_is_not_replayed(): void
    {
        $store = $this->fixture();
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;
            throw new ConnectionException('Synthetic uncertain response');
        });

        $this->putJson($this->route($store), ['ticketTypeIds' => [], 'expectedRevision' => 0])->assertStatus(502);
        $this->assertSame(1, $calls);
    }

    public function test_empty_event_order_remains_a_valid_provider_decision(): void
    {
        $store = $this->fixture();
        Http::fake(['*' => Http::response(['ticketTypeIds' => [], 'revision' => 0])]);

        $this->putJson($this->route($store), ['ticketTypeIds' => [], 'expectedRevision' => 0])
            ->assertOk()->assertJsonPath('data.ticketTypeIds', []);
        Http::assertSentCount(1);
    }

    public static function malformedSnapshots(): array
    {
        return [
            [[]],
            [['ticketTypeIds' => ['one'], 'revision' => -1]],
            [['ticketTypeIds' => ['one', 'one'], 'revision' => 0]],
            [['ticketTypeIds' => [42], 'revision' => 0]],
            [['ticketTypeIds' => [['nested']], 'revision' => 0]],
            [['ticketTypeIds' => ['one'], 'revision' => 0.5]],
        ];
    }

    #[DataProvider('malformedSnapshots')]
    public function test_incompatible_success_response_is_not_reported_as_saved(array $snapshot): void
    {
        $store = $this->fixture();
        Http::fake(['*' => Http::response($snapshot)]);

        $this->putJson($this->route($store), ['ticketTypeIds' => ['one'], 'expectedRevision' => 0])
            ->assertStatus(502)->assertJsonPath('code', 'ticket_type_order_unavailable');
    }
}
