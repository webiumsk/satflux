<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicTicketCheckinTest extends TestCase
{
    use RefreshDatabase;

    public function test_path_shaping_ids_never_reach_btcpay(): void
    {
        Http::fake();
        $owner = User::factory()->create(['btcpay_api_key' => 'owner-key']);
        $store = Store::factory()->create(['user_id' => $owner->id]);

        foreach (['..', 'abc%3Fstatus=all', 'abc%2F..%2Fapps', 'a.b'] as $ticketNumber) {
            // Not routed to the check-in action: 404, or 405 from the GET-only SPA fallback.
            $status = $this->postJson("/api/public/ticket-checkin/{$store->id}/events/evt-1/tickets/{$ticketNumber}/check-in")->status();
            $this->assertContains($status, [404, 405], "ticket number {$ticketNumber}");
        }

        // A GET that misses the API route lands on the SPA shell instead.
        $this->getJson("/api/public/ticket-checkin/{$store->id}/events/evt.1");

        Http::assertNothingSent();
    }
}
