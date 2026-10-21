<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TicketEventTimeZoneTest extends TestCase
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

    private function route(Store $store, bool $catalog = false): string
    {
        return "/api/stores/{$store->id}/tickets/".($catalog ? 'time-zones' : 'events/event-one');
    }

    private function provider(array $event = []): void
    {
        Http::fake(fn (Request $request) => str_ends_with($request->url(), '/time-zones')
            ? Http::response(['timeZones' => ['UTC', 'Europe/Bratislava', 'Pacific/Chatham']])
            : Http::response($request->method() === 'GET' ? [] : $event));
    }

    public function test_catalog_is_whitelisted_and_uses_owner_credentials(): void
    {
        $store = $this->fixture();
        Http::fake(['*' => Http::response(['timeZones' => ['UTC', 'Europe/Bratislava'], 'privateMetadata' => 'hidden'])]);
        $this->getJson($this->route($store, true))->assertOk()->assertExactJson(['data' => ['timeZones' => ['UTC', 'Europe/Bratislava']]]);
        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === 'https://btcpay.test/api/v1/stores/upstream-store/satflux-tickets/time-zones'
            && $request->hasHeader('Authorization', 'Bearer merchant-key'));
    }

    public static function blockedActors(): array
    {
        return [['other-merchant'], ['guest'], ['unverified'], ['unauthenticated']];
    }

    #[DataProvider('blockedActors')]
    public function test_catalog_cannot_bypass_existing_authorization(string $actor): void
    {
        $store = $this->fixture();
        if ($actor === 'guest') {
            $user = User::factory()->guest()->create();
            $store->update(['user_id' => $user->id]);
        } elseif ($actor === 'unverified') {
            $user = User::factory()->unverified()->create();
            $store->update(['user_id' => $user->id]);
        } else {
            $user = User::factory()->create();
        }
        if ($actor === 'unauthenticated') {
            $this->app['auth']->forgetGuards();
        } else {
            Sanctum::actingAs($user);
        }
        Http::fake();
        $this->getJson($this->route($store, true))->assertStatus($actor === 'unauthenticated' ? 401 : 403);
        Http::assertNothingSent();
    }

    public static function privilegedRoles(): array
    {
        return [['admin'], ['support']];
    }

    #[DataProvider('privilegedRoles')]
    public function test_privileged_access_remains_audited_and_uses_owner_key(string $role): void
    {
        $store = $this->fixture();
        $accessor = User::factory()->create(['role' => $role, 'btcpay_api_key' => 'accessor-key']);
        Sanctum::actingAs($accessor);
        $this->provider();
        $this->getJson($this->route($store, true))->assertOk();
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer merchant-key'));
        $this->assertDatabaseHas('audit_logs', ['user_id' => $accessor->id, 'action' => 'store.privileged_access']);
    }

    public static function invalidCatalogs(): array
    {
        return [
            'missing' => [[]], 'empty' => [['timeZones' => []]],
            'object' => [['timeZones' => ['one' => 'UTC']]],
            'nested' => [['timeZones' => [['private' => 'secret']]]],
            'duplicates' => [['timeZones' => ['UTC', 'UTC']]],
            'windows' => [['timeZones' => ['Central Europe Standard Time']]],
            'abbreviation' => [['timeZones' => ['CET']]],
            'path' => [['timeZones' => ['../../private']]],
            'oversized' => [['timeZones' => array_fill(0, 2001, 'UTC')]],
        ];
    }

    #[DataProvider('invalidCatalogs')]
    public function test_malformed_provider_catalogs_fail_closed(array $catalog): void
    {
        $store = $this->fixture();
        Http::fake(['*' => Http::response($catalog)]);
        $this->getJson($this->route($store, true))->assertStatus(502)->assertJsonPath('code', 'ticket_event_timezone_unavailable');
    }

    public function test_old_plugin_does_not_silently_ignore_new_event_fields(): void
    {
        $store = $this->fixture();
        Http::fake(['*' => Http::response(['message' => 'private-provider-detail'], 404)]);
        $response = $this->putJson($this->route($store), ['timeZone' => 'Europe/Bratislava']);
        $response->assertNotFound()->assertJsonPath('code', 'ticket_event_timezone_unsupported');
        $this->assertStringNotContainsString('private-provider-detail', $response->getContent());
        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $request) => $request->method() !== 'GET');
    }

    public function test_physical_civil_time_is_forwarded_without_consumer_timezone_conversion(): void
    {
        $store = $this->fixture();
        $this->provider(['id' => 'event-one', 'startDate' => '2026-11-28T08:00:00Z', 'timeZone' => 'Europe/Bratislava']);
        $this->postJson("/api/stores/{$store->id}/tickets/events", [
            'title' => 'Venue event', 'eventType' => 'Physical', 'timeZone' => 'Europe/Bratislava',
            'startLocal' => '2026-11-28T09:00', 'endLocal' => '2026-11-29T12:00',
        ])->assertCreated()->assertJsonPath('data.startDate', '2026-11-28T08:00:00Z');
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request['startLocal'] === '2026-11-28T09:00' && $request['endLocal'] === '2026-11-29T12:00'
            && $request['timeZone'] === 'Europe/Bratislava' && ! array_key_exists('startDate', $request->data())
            && $request->hasHeader('Authorization', 'Bearer merchant-key'));
    }

    public function test_zone_only_update_preserves_exact_instant_and_does_not_fabricate_dates(): void
    {
        $store = $this->fixture();
        $instant = '2026-11-28T08:00:42.123456Z';
        $this->provider(['id' => 'event-one', 'startDate' => $instant]);
        $this->putJson($this->route($store), ['timeZone' => 'Pacific/Chatham'])->assertOk()->assertJsonPath('data.startDate', $instant);
        Http::assertSent(fn (Request $request) => $request->method() === 'PUT' && $request->data() === ['timeZone' => 'Pacific/Chatham']);
    }

    public function test_explicit_null_zone_preserves_metadata_without_new_capability_requirement(): void
    {
        $store = $this->fixture();
        $this->provider();
        $this->putJson($this->route($store), ['title' => 'Renamed', 'timeZone' => null])->assertOk();
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->method() === 'PUT' && $request->data() === ['title' => 'Renamed', 'timeZone' => null]);
    }

    public function test_explicit_null_civil_end_is_forwarded_as_clear_and_omitted_dates_stay_omitted(): void
    {
        $store = $this->fixture();
        $this->provider();
        $this->putJson($this->route($store), ['endLocal' => null])->assertOk();
        Http::assertSent(fn (Request $request) => $request->method() === 'PUT' && $request->data() === ['endLocal' => null]);
    }

    public function test_virtual_explicit_null_instant_end_is_forwarded_without_catalog_or_date_changes(): void
    {
        $store = $this->fixture();
        $this->provider();
        $this->putJson($this->route($store), ['endDate' => null])->assertOk();
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->method() === 'PUT' && $request->data() === ['endDate' => null]);
    }

    public static function invalidPayloads(): array
    {
        return [
            'empty zone' => [['timeZone' => '']],
            'whitespace zone' => [['timeZone' => '  ']],
            'array zone' => [['timeZone' => ['UTC']]],
            'long zone' => [['timeZone' => str_repeat('a', 101)]],
            'null start' => [['startLocal' => null]],
            'date only' => [['startLocal' => '2026-11-28']],
            'offset civil' => [['startLocal' => '2026-11-28T09:00Z']],
            'array civil' => [['startLocal' => ['2026-11-28T09:00']]],
            'conflicting start' => [['startDate' => '2026-11-28T08:00:00Z', 'startLocal' => '2026-11-28T09:00']],
            'conflicting end' => [['endDate' => null, 'endLocal' => null]],
        ];
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_new_fields_are_rejected_before_provider_calls(array $payload): void
    {
        $store = $this->fixture();
        Http::fake();
        $this->putJson($this->route($store), $payload)->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_zone_membership_comes_from_provider_and_civil_time_requires_zone(): void
    {
        $store = $this->fixture();
        $this->provider();
        $this->putJson($this->route($store), ['timeZone' => 'Europe/Prague'])->assertUnprocessable()->assertJsonValidationErrors('timeZone');
        $this->putJson($this->route($store), ['startLocal' => '2026-11-28T09:00'])->assertUnprocessable()->assertJsonValidationErrors('timeZone');
        Http::assertSentCount(2);
        Http::assertNotSent(fn (Request $request) => $request->method() !== 'GET');
    }

    public static function civilErrors(): array
    {
        return [['event-start-local-nonexistent', 'startLocal'], ['event-start-local-ambiguous', 'startLocal'],
            ['event-end-local-invalid', 'endLocal'], ['event-end-local-ambiguous', 'endLocal'],
            ['event-date-range-invalid', 'endLocal']];
    }

    #[DataProvider('civilErrors')]
    public function test_provider_validation_codes_are_safe_and_field_specific(string $code, string $field): void
    {
        $store = $this->fixture();
        Http::fake([
            '*/time-zones' => Http::response(['timeZones' => ['Europe/Bratislava']]),
            '*' => Http::response(['code' => $code, 'message' => 'private-provider-detail'], 422),
        ]);
        $response = $this->putJson($this->route($store), ['timeZone' => 'Europe/Bratislava', 'startLocal' => '2026-10-25T02:30']);
        $response->assertUnprocessable()->assertJsonPath('code', $code)->assertJsonPath("errors.{$field}.0", $code);
        $this->assertStringNotContainsString('private-provider-detail', $response->getContent());
    }
}
