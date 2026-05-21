<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketEventRaffleBundleTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_event_forwards_raffle_bundle_fields_to_btcpay(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);
        $btcpayStoreId = $store->btcpay_store_id;
        $raffleId = '11111111-1111-1111-1111-111111111111';

        $baseUrl = rtrim(config('services.btcpay.base_url', 'http://localhost'), '/');
        $postSeen = false;
        $postedBundleRaffleId = null;
        $postedBundlePerAdmission = null;

        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($baseUrl, $btcpayStoreId, $raffleId, &$postSeen, &$postedBundleRaffleId, &$postedBundlePerAdmission) {
            $url = (string) $request->url();
            if (! str_contains($url, $baseUrl)) {
                return Http::response([], 404);
            }

            if ($request->method() === 'GET'
                && str_contains($url, "/api/v1/stores/{$btcpayStoreId}/satoshi-tickets/events")
                && ! str_contains($url, '/satoshi-tickets/events/')
            ) {
                return Http::response([], 200);
            }

            if ($request->method() === 'POST'
                && str_contains($url, "/api/v1/stores/{$btcpayStoreId}/satoshi-tickets/events")
            ) {
                $postSeen = true;
                $body = $request->data();
                $postedBundleRaffleId = $body['bundledRaffleId'] ?? null;
                $postedBundlePerAdmission = $body['bundledRaffleTicketsPerAdmission'] ?? null;

                return Http::response([
                    'id' => 'evt-bundle',
                    'storeId' => $btcpayStoreId,
                    'title' => 'Bundle show',
                    'eventState' => 'Active',
                    'eventType' => 'Physical',
                    'startDate' => '2030-06-01T12:00:00Z',
                    'hasMaximumCapacity' => false,
                    'bundledRaffleId' => $raffleId,
                    'bundledRaffleTicketsPerAdmission' => 2,
                ], 201);
            }

            return Http::response([], 404);
        });

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/stores/{$store->id}/tickets/events", [
            'title' => 'Bundle show',
            'startDate' => '2030-06-01T12:00:00Z',
            'bundledRaffleId' => $raffleId,
            'bundledRaffleTicketsPerAdmission' => 2,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.bundledRaffleTicketsPerAdmission', 2);
        $this->assertTrue($postSeen, 'Create event should POST bundle fields to BTCPay');
        $this->assertSame($raffleId, $postedBundleRaffleId);
        $this->assertSame(2, $postedBundlePerAdmission);
    }
}
