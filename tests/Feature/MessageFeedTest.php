<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use App\Models\UserMessage;
use App\Services\BtcPay\Exceptions\BtcPayException;
use App\Services\BtcPay\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class MessageFeedTest extends TestCase
{
    use RefreshDatabase;

    private function userWithFeed(array $rows): User
    {
        $user = User::factory()->create(['btcpay_api_key' => 'synthetic-notification-key']);
        $this->mock(NotificationService::class, function (MockInterface $mock) use ($rows) {
            $mock->shouldReceive('listNotifications')->andReturnUsing(function ($key, $options) use ($rows) {
                $this->assertArrayNotHasKey('seen', $options, 'Read-state changes must not shift upstream offsets.');

                return ['data' => array_slice($rows, $options['skip'], $options['take'])];
            });
        });

        return $user;
    }

    private function row(string $id, int $time, string $type = 'invoicestate', bool $seen = false, array $extra = []): array
    {
        return array_merge(['id' => $id, 'createdTime' => $time, 'type' => $type, 'identifier' => 'confirmed',
            'body' => 'Invoice synthetic is settled', 'seen' => $seen, 'link' => '/invoices/syntheticInvoice'], $extra);
    }

    private function message(User $user, string $title, int $time, string $type = 'security', bool $read = false): UserMessage
    {
        $message = UserMessage::createForUser($user->id, $title, 'Synthetic body', $type);
        $message->forceFill(['created_at' => date('Y-m-d H:i:s', $time), 'read_at' => $read ? now() : null])->save();

        return $message;
    }

    public function test_feed_merges_by_date_and_paginates_without_pinning_old_security_messages(): void
    {
        $time = now()->timestamp;
        $user = $this->userWithFeed([$this->row('newInvoice', $time), $this->row('oldInvoice', $time - 100)]);
        $security = $this->message($user, 'Security between invoices', $time - 50);
        $other = User::factory()->create();
        $this->message($other, 'Other tenant', $time + 100);
        $first = $this->actingAs($user)->getJson('/api/messages?per_page=2')->assertOk()
            ->assertJsonPath('data.0.id', 'newInvoice')
            ->assertJsonPath('data.1.id', 'sf_'.$security->id)
            ->assertJsonPath('meta.has_more', true);
        $cursor = $first->json('meta.next_cursor');
        $second = $this->getJson('/api/messages?per_page=2&cursor='.urlencode($cursor))->assertOk()
            ->assertJsonPath('data.0.id', 'oldInvoice')
            ->assertJsonPath('meta.has_more', false);
        $this->assertCount(1, $second->json('data'));
    }

    public function test_invoice_and_unread_filters_apply_before_pagination_using_real_btcpay_type(): void
    {
        $time = now()->timestamp;
        $user = $this->userWithFeed([$this->row('payout', $time, 'payout'), $this->row('readInvoice', $time - 1, 'invoicestate', true),
            $this->row('firstInvoice', $time - 2), $this->row('secondInvoice', $time - 3)]);
        $this->message($user, 'Critical', $time + 10);
        $first = $this->actingAs($user)->getJson('/api/messages?category=invoices&unread=true&per_page=1')
            ->assertOk()->assertJsonPath('data.0.id', 'firstInvoice')->assertJsonPath('data.0.category', 'invoices');
        $this->getJson('/api/messages?category=invoices&unread=1&per_page=1&cursor='.urlencode($first->json('meta.next_cursor')))
            ->assertOk()->assertJsonPath('data.0.id', 'secondInvoice');
    }

    public function test_sparse_filter_has_bounded_empty_continuation_and_eventually_reaches_older_match(): void
    {
        $time = now()->timestamp;
        $rows = [];
        for ($i = 0; $i < 501; $i++) {
            $rows[] = $this->row('payout'.$i, $time - $i, 'payout');
        }
        $rows[] = $this->row('olderInvoice', $time - 502);
        $user = $this->userWithFeed($rows);
        $first = $this->actingAs($user)->getJson('/api/messages?category=invoices')->assertOk()
            ->assertJsonCount(0, 'data')->assertJsonPath('meta.scan_limited', true)->assertJsonPath('meta.has_more', true);
        $this->getJson('/api/messages?category=invoices&cursor='.urlencode($first->json('meta.next_cursor')))
            ->assertOk()->assertJsonPath('data.0.id', 'olderInvoice')->assertJsonPath('meta.has_more', false);
    }

    public function test_scan_frontier_defers_older_local_messages_and_keeps_tied_local_messages_reachable(): void
    {
        $time = now()->timestamp;
        $rows = [];
        for ($i = 0; $i < 500; $i++) {
            $rows[] = $this->row('read'.$i, $time - $i, 'payout', true);
        }
        $rows[] = $this->row('unreadRemote', $time - 500);
        $user = $this->userWithFeed($rows);
        $tie = $this->message($user, 'Tied local', $time - 499);
        $older = $this->message($user, 'Older local', $time - 501);
        $first = $this->actingAs($user)->getJson('/api/messages?unread=1')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', 'sf_'.$tie->id)->assertJsonPath('meta.scan_limited', true);
        $this->getJson('/api/messages?unread=1&cursor='.urlencode($first->json('meta.next_cursor')))->assertOk()
            ->assertJsonPath('data.0.id', 'unreadRemote')->assertJsonPath('data.1.id', 'sf_'.$older->id);
    }

    public function test_critical_filter_reads_local_history_with_keyset_after_marking_read(): void
    {
        $user = User::factory()->create(['btcpay_api_key' => 'synthetic-key']);
        $this->mock(NotificationService::class, fn (MockInterface $mock) => $mock->shouldNotReceive('listNotifications'));
        $firstMessage = $this->message($user, 'First', now()->subDays(60)->timestamp);
        $secondMessage = $this->message($user, 'Second', now()->subDays(61)->timestamp, 'critical');
        $first = $this->actingAs($user)->getJson('/api/messages?category=critical&unread=1&per_page=1')->assertOk()
            ->assertJsonPath('data.0.id', 'sf_'.$firstMessage->id);
        $this->patchJson('/api/messages/sf_'.$firstMessage->id.'/read')->assertOk();
        $this->getJson('/api/messages?category=critical&unread=1&per_page=1&cursor='.urlencode($first->json('meta.next_cursor')))
            ->assertOk()->assertJsonPath('data.0.id', 'sf_'.$secondMessage->id)->assertJsonPath('meta.has_more', false);
    }

    public function test_cursor_is_user_filter_and_page_size_bound_and_expires(): void
    {
        $user = $this->userWithFeed([$this->row('one', now()->timestamp), $this->row('two', now()->subMinute()->timestamp)]);
        $cursor = $this->actingAs($user)->getJson('/api/messages?per_page=1')->assertOk()->json('meta.next_cursor');
        $this->getJson('/api/messages?per_page=2&cursor='.urlencode($cursor))->assertUnprocessable();
        $this->getJson('/api/messages?per_page=1&category=other&cursor='.urlencode($cursor))->assertUnprocessable();
        $this->getJson('/api/messages?per_page=1&unread=1&cursor='.urlencode($cursor))->assertUnprocessable();
        $this->getJson('/api/messages?cursor=broken')->assertUnprocessable();
        $other = User::factory()->create(['btcpay_api_key' => null]);
        $this->actingAs($other)->getJson('/api/messages?per_page=1&cursor='.urlencode($cursor))->assertUnprocessable();
        $this->actingAs($user);
        $this->travel(61)->minutes();
        $this->getJson('/api/messages?per_page=1&cursor='.urlencode($cursor))->assertUnprocessable();
    }

    public function test_no_key_and_forbidden_remote_preserve_local_filtering_and_availability(): void
    {
        $user = User::factory()->create(['btcpay_api_key' => null]);
        $local = $this->message($user, 'Read local', now()->timestamp, 'info', true);
        $this->actingAs($user)->getJson('/api/messages')->assertOk()->assertJsonPath('available', true)->assertJsonPath('data.0.id', 'sf_'.$local->id);
        $this->getJson('/api/messages/count')->assertOk()->assertJsonPath('available', true)->assertJsonPath('data.unread', 0);
    }

    public function test_forbidden_remote_keeps_recent_read_local_messages_available(): void
    {
        $user = User::factory()->create(['btcpay_api_key' => 'synthetic-key']);
        $local = $this->message($user, 'Read local', now()->timestamp, 'info', true);
        $this->actingAs($user);
        $this->mock(NotificationService::class, fn (MockInterface $mock) => $mock->shouldReceive('listNotifications')->andThrow(new BtcPayException('Forbidden', 403)));
        $this->getJson('/api/messages?category=other')->assertOk()->assertJsonPath('available', true)->assertJsonPath('data.0.id', 'sf_'.$local->id);
        $this->getJson('/api/messages?category=invoices')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('available', true);
    }

    public function test_invoice_links_use_owned_local_uuid_for_real_legacy_and_absolute_paths(): void
    {
        $time = now()->timestamp;
        $user = $this->userWithFeed([]);
        $owned = Store::factory()->create(['user_id' => $user->id]);
        $other = Store::factory()->create();
        config(['app.url' => 'https://panel.test', 'services.btcpay.base_url' => 'https://btcpay.test/root']);
        $links = ['/invoices/InvoiceA', '/i/InvoiceB', '/stores/'.$owned->btcpay_store_id.'/invoices/InvoiceC', 'https://btcpay.test/root/invoices/InvoiceD', ''];
        $rows = [];
        foreach ($links as $index => $link) {
            $rows[] = $this->row('owned'.$index, $time - $index, 'invoicestate', false, ['link' => $link, 'storeId' => $owned->btcpay_store_id]);
        }
        $rows[] = $this->row('other', $time - 10, 'invoicestate', false, ['storeId' => $other->btcpay_store_id]);
        $rows[] = $this->row('mismatch', $time - 11, 'invoicestate', false, ['storeId' => $owned->btcpay_store_id, 'link' => '/stores/'.$other->btcpay_store_id.'/invoices/InvoiceX']);
        $this->mock(NotificationService::class, fn (MockInterface $mock) => $mock->shouldReceive('listNotifications')->andReturn(['data' => $rows]));
        $data = $this->actingAs($user)->getJson('/api/messages')->assertOk()->json('data');
        foreach (['InvoiceA', 'InvoiceB', 'InvoiceC', 'InvoiceD'] as $index => $invoice) {
            $this->assertSame('https://panel.test/stores/'.$owned->id.'/invoices/'.$invoice, $data[$index]['link']);
        }
        $this->assertSame('https://panel.test/stores/'.$owned->id.'?section=invoices', $data[4]['link']);
        $this->assertNull($data[5]['link']);
        $this->assertNull($data[6]['link']);
        $serialized = json_encode($data);
        $this->assertStringNotContainsString($owned->btcpay_store_id, $serialized);
        $this->assertStringNotContainsString($other->btcpay_store_id, $serialized);
    }

    public function test_unread_counts_include_local_info_and_invoice_but_security_count_is_a_subset(): void
    {
        $user = User::factory()->create(['btcpay_api_key' => null]);
        foreach (['info', 'invoice', 'security', 'critical'] as $type) {
            $this->message($user, 'Synthetic '.$type, now()->timestamp, $type);
        }
        $other = User::factory()->create();
        $this->message($other, 'Other tenant', now()->timestamp);
        $this->actingAs($user)->getJson('/api/messages/count')->assertOk()
            ->assertJsonPath('data.unread', 4)->assertJsonPath('data.security_unread', 2);
        $this->postJson('/api/messages/mark-all-read')->assertOk();
        $this->getJson('/api/messages/count')->assertOk()->assertJsonPath('data.unread', 0)->assertJsonPath('data.security_unread', 0);
        $this->assertSame(1, UserMessage::query()->where('user_id', $other->id)->whereNull('read_at')->count());
    }

    public function test_unsafe_links_are_dropped_and_non_invoice_https_link_is_preserved(): void
    {
        $time = now()->timestamp;
        $rows = [];
        foreach (['javascript:alert(1)', '//evil.test/path', 'data:text/html,test', '/invoices/../bad', 'https://user:password@external.test/path', "https://external.test/\npath"] as $index => $link) {
            $rows[] = $this->row('unsafe'.$index, $time - $index, 'payout', false, ['link' => $link]);
        }
        $rows[] = $this->row('safe', $time - 10, 'payout', false, ['link' => 'https://explorer.test/tx/synthetic']);
        $user = $this->userWithFeed($rows);
        $data = $this->actingAs($user)->getJson('/api/messages')->assertOk()->json('data');
        foreach (array_slice($data, 0, 6) as $row) {
            $this->assertNull($row['link']);
        }
        $this->assertSame('https://explorer.test/tx/synthetic', $data[6]['link']);
    }

    public function test_invalid_filters_and_unscoped_mark_read_are_rejected(): void
    {
        $user = User::factory()->create(['btcpay_api_key' => null]);
        $other = User::factory()->create();
        $message = $this->message($other, 'Other tenant', now()->timestamp);
        $this->actingAs($user)->getJson('/api/messages?category=unknown')->assertUnprocessable();
        $this->getJson('/api/messages?unread=not-a-boolean')->assertUnprocessable();
        $this->getJson('/api/messages?page=2')->assertUnprocessable();
        $this->patchJson('/api/messages/sf_'.$message->id.'/read')->assertNotFound();
        $this->assertNull($message->fresh()->read_at);
    }
}
