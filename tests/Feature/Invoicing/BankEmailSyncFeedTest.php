<?php

namespace Tests\Feature\Invoicing;

use App\Enums\CompanyJurisdiction;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Invoicing\BankEmailSyncFeed;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class BankEmailSyncFeedTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    private SubscriptionPlan $plan;

    /** Provision only synthetic authenticated company fixtures in the verified disposable test database. */
    protected function setUp(): void
    {
        parent::setUp();
        $this->plan = SubscriptionPlan::create([
            'code' => 'pro', 'name' => 'pro', 'display_name' => 'Pro',
            'price_eur' => 99, 'billing_period' => 'year', 'max_stores' => 3,
            'max_api_keys' => 3, 'features' => ['business_invoicing'], 'is_active' => true,
        ]);
        $this->user = $this->subscribedUser();
        $this->company = $this->companyFor($this->user);
    }

    /** Give each test actor the existing invoicing feature without changing application plan policy. */
    private function subscribedUser(): User
    {
        $user = User::factory()->create();
        Subscription::create([
            'user_id' => $user->id, 'plan_id' => $this->plan->id, 'status' => 'active',
            'starts_at' => now(), 'expires_at' => now()->addYear(),
        ]);

        return $user;
    }

    /** Create an isolated route-owned company for authorization and cursor-binding checks. */
    private function companyFor(User $user): Company
    {
        return Company::create([
            'user_id' => $user->id, 'legal_name' => 'Synthetic Sync Company',
            'jurisdiction' => CompanyJurisdiction::EuSk, 'default_currency' => 'EUR',
        ]);
    }

    /** Produce deterministic valid UUIDs so timestamp ties can be asserted independently of insertion order. */
    private function uuid(int $number): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $number);
    }

    /** Insert exact nullable legacy timestamps without Eloquent's automatic timestamp replacement. */
    private function movement(int $number, ?string $createdAt, array $overrides = []): string
    {
        $id = $this->uuid($number);
        DB::table('bank_transactions')->insert(array_merge([
            'id' => $id, 'company_id' => $this->company->id,
            'booked_at' => '2026-11-03 12:00:00', 'amount' => '19.00', 'currency' => 'EUR',
            'direction' => 'debit', 'match_status' => 'unmatched', 'source' => 'email',
            'dedupe_hash' => hash('sha256', $id), 'created_at' => $createdAt, 'updated_at' => $createdAt,
        ], $overrides));

        return $id;
    }

    /** Query the optional protocol while retaining the real authentication and company middleware. */
    private function feed(?string $cursor = null, array $overrides = [], ?Company $company = null, ?User $user = null): TestResponse
    {
        $query = array_merge([
            'sync' => 1, 'source' => 'email', 'sync_context' => str_repeat('a', 64),
            'per_page' => 2, 'sync_cursor' => $cursor,
        ], $overrides);

        return $this->actingAs($user ?? $this->user)->getJson(
            '/api/invoicing/companies/'.($company ?? $this->company)->id.'/bank-transactions?'.http_build_query($query),
        );
    }

    /** Inspect only synthetic frame contents to prove complete bindings and unchanged retry positions. */
    private function frame(string $cursor): array
    {
        return json_decode(Crypt::decryptString($cursor), true, 16, JSON_THROW_ON_ERROR);
    }

    /** Require one generic cursor field, without leaking cryptographic or scope-validation details. */
    private function invalid(TestResponse $response): void
    {
        $response->assertUnprocessable();
        $this->assertSame(['sync_cursor' => ['bank_sync_cursor_invalid']], $response->json('errors'));
    }

    /** Bound payload rows and database work without invoking the ordinary aggregate summary. */
    #[Test]
    public function opt_in_reads_are_bounded_without_summary_or_count_and_keep_the_existing_payload(): void
    {
        $this->movement(1, '2026-11-03 12:00:00');
        $second = $this->movement(2, '2026-11-03 12:00:00');
        $newest = $this->movement(3, '2026-11-03 12:01:00');
        $this->movement(4, '2026-11-03 12:02:00', ['source' => 'wise']);
        $this->movement(5, '2026-11-03 12:03:00', ['reference' => 'Stav na ucte']);
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            if (str_contains(strtolower($query->sql), 'bank_transactions')) {
                $queries[] = strtolower($query->sql);
            }
        });

        $response = $this->feed();
        $response->assertOk()->assertJsonPath('meta.sync.protocol', 2)->assertJsonPath('meta.sync.lane', 'head');
        $response->assertJsonPath('meta.sync.unresolved', false)->assertJsonPath('meta.sync.unresolved_after_success', false);
        $this->assertSame([$newest, $second], array_column($response->json('data'), 'id'));
        $this->assertSame(['sync'], array_keys($response->json('meta')));
        $response->assertJsonPath('data.0.recorded_direction', 'debit')->assertJsonPath('data.0.amount', '19.00');
        $this->assertNotEmpty($queries);
        $this->assertStringContainsString('limit 3', implode(' ', $queries));
        $this->assertStringNotContainsString('count(', implode(' ', $queries));
        $this->assertStringNotContainsString('sum(', implode(' ', $queries));
    }

    /** Prove total ordering and strict seeks across timestamp ties and legacy NULL timestamp rows. */
    #[Test]
    public function deterministic_keysets_cross_non_null_ties_and_the_null_bucket_without_omissions(): void
    {
        $ids = [];
        foreach ([1 => null, 2 => null, 3 => '2026-11-02 12:00:00', 4 => '2026-11-03 12:00:00', 5 => '2026-11-03 12:00:00'] as $id => $date) {
            $ids[$id] = $this->movement($id, $date);
        }
        $head = $this->feed();
        $head->assertOk();
        $this->assertSame([$ids[5], $ids[4]], array_column($head->json('data'), 'id'));
        $backfill = $this->feed($head->json('meta.sync.next_cursor'));
        $backfill->assertOk()->assertJsonPath('meta.sync.lane', 'backfill');
        $this->assertSame([$ids[3], $ids[2]], array_column($backfill->json('data'), 'id'));
        $overlap = $this->feed($backfill->json('meta.sync.next_cursor'));
        $overlap->assertOk()->assertJsonPath('meta.sync.lane', 'head');
        $this->assertSame([$ids[5], $ids[4]], array_column($overlap->json('data'), 'id'));
        $this->assertSame(
            $this->frame($backfill->json('meta.sync.next_cursor'))['backfill_before'],
            $this->frame($overlap->json('meta.sync.next_cursor'))['backfill_before'],
        );
        $last = $this->feed($overlap->json('meta.sync.next_cursor'));
        $last->assertOk()->assertJsonPath('meta.sync.cycle_complete', true);
        $this->assertSame([$ids[1]], array_column($last->json('data'), 'id'));
        $this->assertNull($this->frame($last->json('meta.sync.next_cursor'))['backfill_before']);
    }

    /** Admitted incomplete pages advance only the scan bookmark and retain unresolved evidence. */
    #[Test]
    public function incomplete_frames_flip_lanes_and_advance_scan_without_claiming_coverage(): void
    {
        foreach (range(1, 5) as $id) {
            $this->movement($id, '2026-11-03 12:00:00');
        }
        $head = $this->feed();
        $head->assertOk();
        $headRetry = $this->frame($head->json('meta.sync.retry_cursor'));
        $this->assertSame('backfill', $headRetry['next_lane']);
        $this->assertSame($this->frame($head->json('meta.sync.next_cursor'))['backfill_before'], $headRetry['backfill_before']);
        $this->assertTrue($headRetry['cycle_incomplete']);
        $this->assertTrue($headRetry['unresolved']);

        $backfill = $this->feed($head->json('meta.sync.next_cursor'));
        $backfill->assertOk();
        $expected = $this->frame($backfill->json('meta.sync.next_cursor'));
        $expected['cycle_incomplete'] = true;
        $expected['unresolved'] = true;
        $this->assertSame($expected, $this->frame($backfill->json('meta.sync.retry_cursor')));
        $afterRetry = $this->feed($backfill->json('meta.sync.retry_cursor'));
        $afterRetry->assertOk()->assertJsonPath('meta.sync.lane', 'head');
        $retriedPage = $this->feed($afterRetry->json('meta.sync.next_cursor'));
        $retriedPage->assertOk()->assertJsonPath('meta.sync.lane', 'backfill');
        $this->assertSame([$this->uuid(1)], array_column($retriedPage->json('data'), 'id'));
        $retriedPage->assertJsonPath('meta.sync.cycle_complete', false)->assertJsonPath('meta.sync.unresolved_after_success', true);
    }

    /** Repeated full cycles cover rows that no timestamp/UUID high-water claim could safely discover. */
    #[Test]
    public function later_full_cycles_catch_large_head_gaps_and_late_tied_backdated_or_null_rows(): void
    {
        $expected = [];
        foreach (range(1, 210) as $id) {
            $expected[] = $this->movement($id * 10, '2026-11-03 12:00:00');
        }
        foreach (range(10000, 10119) as $id) {
            $expected[] = $this->movement($id, null);
        }
        $seen = [];
        $record = function (TestResponse $response) use (&$seen): void {
            $response->assertOk();
            foreach (array_column($response->json('data'), 'id') as $id) {
                $seen[$id] = true;
            }
        };
        $response = $this->feed(null, ['per_page' => 100]);
        $record($response);
        foreach (range(20000, 20149) as $id) {
            $expected[] = $this->movement($id, '2026-11-04 12:00:00');
        }
        $response = $this->feed($response->json('meta.sync.next_cursor'), ['per_page' => 100]);
        $record($response);
        $lateTie = $this->movement(2055, '2026-11-03 12:00:00');
        $expected[] = $lateTie;
        $expected[] = $this->movement(30000, '2026-11-02 12:00:00');
        $response = $this->feed($response->json('meta.sync.next_cursor'), ['per_page' => 100]);
        $record($response);
        $response = $this->feed($response->json('meta.sync.next_cursor'), ['per_page' => 100]);
        $record($response);
        $this->assertNull($this->frame($response->json('meta.sync.next_cursor'))['backfill_before']['created_at']);
        $lateNull = $this->movement(40000, null);
        $expected[] = $lateNull;
        $completed = 0;
        for ($request = 0; $request < 30 && $completed < 2; $request++) {
            $response = $this->feed($response->json('meta.sync.next_cursor'), ['per_page' => 100]);
            $record($response);
            if ($response->json('meta.sync.cycle_complete')) {
                $completed++;
                if ($completed === 1) {
                    $this->assertArrayNotHasKey($lateTie, $seen);
                    $this->assertArrayNotHasKey($lateNull, $seen);
                    $this->assertArrayNotHasKey($this->uuid(20000), $seen);
                }
            }
        }
        $this->assertSame(2, $completed);
        $this->assertEqualsCanonicalizing($expected, array_keys($seen));
    }

    /** Long-lived cursors remain bookmarks while ordinary authentication and feature checks still apply. */
    #[Test]
    public function empty_and_short_histories_finish_without_expiry_and_restart_at_the_latest_head(): void
    {
        $empty = $this->feed();
        $empty->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.sync.cycle_complete', true);
        $this->assertNull($this->frame($empty->json('meta.sync.next_cursor'))['backfill_before']);
        $first = $this->movement(1, null);
        Subscription::where('user_id', $this->user->id)->update(['expires_at' => now()->addYears(3)]);
        $this->travel(2)->years();
        $next = $this->feed($empty->json('meta.sync.next_cursor'));
        $next->assertOk()->assertJsonPath('meta.sync.lane', 'head')->assertJsonPath('meta.sync.cycle_complete', true);
        $this->assertSame([$first], array_column($next->json('data'), 'id'));
        $this->assertLessThanOrEqual(4096, strlen($next->json('meta.sync.next_cursor')));
        $this->travelBack();
    }

    /** Current route ownership remains authoritative even when a caller possesses a previously valid cursor. */
    #[Test]
    public function route_ownership_is_checked_before_cursor_bindings_and_transfer_invalidates_the_old_user_frame(): void
    {
        $this->movement(1, '2026-11-03 12:00:00');
        $cursor = $this->feed()->assertOk()->json('meta.sync.next_cursor');
        $otherUser = $this->subscribedUser();
        $otherCompany = $this->companyFor($otherUser);
        $this->feed($cursor, [], $otherCompany)->assertForbidden();
        $this->company->update(['user_id' => $otherUser->id]);
        $this->feed($cursor)->assertForbidden();
        $this->invalid($this->feed($cursor, [], $this->company, $otherUser));
    }

    /** Cursors cannot be moved between authorized companies or semantic sync contexts. */
    #[Test]
    public function cross_company_context_source_and_page_size_reuse_are_rejected_without_any_reset_hint(): void
    {
        $this->movement(1, '2026-11-03 12:00:00');
        $cursor = $this->feed()->assertOk()->json('meta.sync.next_cursor');
        $this->invalid($this->feed($cursor, [], $this->companyFor($this->user)));
        $this->invalid($this->feed($cursor, ['sync_context' => str_repeat('b', 64)]));
        $this->invalid($this->feed($cursor, ['source' => 'wise']));
        $this->invalid($this->feed($cursor, ['per_page' => 1]));
    }

    /** Even authenticated ciphertext must contain exactly the expected version, tuple schema and request bindings. */
    #[Test]
    #[TestWith(['protocol'])]
    #[TestWith(['user_id'])]
    #[TestWith(['company_id'])]
    #[TestWith(['source'])]
    #[TestWith(['context'])]
    #[TestWith(['page_size'])]
    #[TestWith(['order'])]
    #[TestWith(['next_lane'])]
    #[TestWith(['backfill_before'])]
    #[TestWith(['extra'])]
    #[TestWith(['missing'])]
    #[TestWith(['bad_id'])]
    #[TestWith(['bad_date'])]
    #[TestWith(['bad_time'])]
    #[TestWith(['tuple_extra'])]
    #[TestWith(['cycle_incomplete'])]
    #[TestWith(['unresolved'])]
    #[TestWith(['missing_flag'])]
    #[TestWith(['inconsistent_flags'])]
    #[TestWith(['legacy_v1'])]
    public function authenticated_but_invalid_frame_schemas_and_bindings_have_one_generic_error(string $field): void
    {
        $this->movement(1, '2026-11-03 12:00:00');
        $frame = $this->frame($this->feed()->assertOk()->json('meta.sync.next_cursor'));
        $position = ['created_at' => '2026-11-03 12:00:00', 'id' => $this->uuid(1)];
        if ($field === 'legacy_v1') {
            $frame['protocol'] = 1;
            unset($frame['cycle_incomplete'], $frame['unresolved']);
        } elseif ($field === 'missing_flag') {
            unset($frame['cycle_incomplete']);
        } elseif ($field === 'inconsistent_flags') {
            $frame['cycle_incomplete'] = true;
            $frame['unresolved'] = false;
        } elseif ($field === 'missing') {
            unset($frame['backfill_before']);
        } elseif ($field === 'extra') {
            $frame['extra'] = true;
        } elseif (in_array($field, ['bad_id', 'bad_date', 'bad_time', 'tuple_extra'], true)) {
            $frame['backfill_before'] = match ($field) {
                'bad_id' => [...$position, 'id' => 'not-a-uuid'],
                'bad_date' => [...$position, 'created_at' => '2026-02-30 12:00:00'],
                'bad_time' => [...$position, 'created_at' => '2026-11-03 24:00:00'],
                default => [...$position, 'extra' => true],
            };
        } else {
            $frame[$field] = match ($field) {
                'protocol' => 1, 'page_size' => 3, 'source' => 'wise',
                'context' => str_repeat('b', 64), 'order' => 'id:desc', 'next_lane' => 'other',
                'backfill_before' => [], 'cycle_incomplete' => 'false', 'unresolved' => 1,
                default => 'different-actor-or-company',
            };
        }
        $this->invalid($this->feed(Crypt::encryptString(json_encode($frame, JSON_THROW_ON_ERROR))));
    }

    /** Malformed opt-in inputs return one cursor error instead of silently restarting a full history read. */
    #[Test]
    #[TestWith(['cursor'])]
    #[TestWith(['large_cursor'])]
    #[TestWith(['context'])]
    #[TestWith(['source'])]
    #[TestWith(['limit_small'])]
    #[TestWith(['limit_large'])]
    #[TestWith(['filter'])]
    public function malformed_protocol_inputs_never_fall_back_to_an_unfiltered_history_response(string $case): void
    {
        $overrides = match ($case) {
            'cursor' => ['sync_cursor' => 'forged'], 'large_cursor' => ['sync_cursor' => str_repeat('x', 4097)],
            'context' => ['sync_context' => 'invalid'], 'source' => ['source' => 'manual'],
            'limit_small' => ['per_page' => 0], 'limit_large' => ['per_page' => 101],
            default => ['match_status' => 'matched'],
        };
        $this->invalid($this->feed(null, $overrides));
    }

    /** The opt-in branch cannot change existing listing order, filters, aggregates or pagination validation. */
    #[Test]
    public function ordinary_listing_retains_booked_order_filters_summary_and_its_original_page_limit(): void
    {
        $earlier = $this->movement(1, '2026-11-04 12:00:00', ['booked_at' => '2026-11-01 12:00:00']);
        $later = $this->movement(2, '2026-11-01 12:00:00', ['booked_at' => '2026-11-03 12:00:00']);
        $base = '/api/invoicing/companies/'.$this->company->id.'/bank-transactions';
        $ordinary = $this->actingAs($this->user)->getJson($base.'?source=email&per_page=5');
        $ordinary->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('meta.per_page', 5);
        $this->assertSame([$later, $earlier], array_column($ordinary->json('data'), 'id'));
        $this->assertArrayHasKey('summary', $ordinary->json('meta'));
        $this->assertArrayNotHasKey('sync', $ordinary->json('meta'));
        $filtered = $this->getJson($base.'?from=2026-11-02&per_page=5');
        $filtered->assertOk()->assertJsonPath('meta.total', 1);
        $this->assertSame([$later], array_column($filtered->json('data'), 'id'));
        $this->getJson($base.'?per_page=1')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->getJson($base.'?sync[]=1&per_page=5')->assertOk()->assertJsonPath('meta.total', 2);
    }

    /** An encrypted bookmark provides no route access without a current authenticated user. */
    #[Test]
    public function possessing_a_valid_cursor_does_not_authorize_an_unauthenticated_request(): void
    {
        $page = app(BankEmailSyncFeed::class)->page($this->company, (string) $this->user->id, [
            'source' => 'email', 'sync_context' => str_repeat('a', 64), 'per_page' => 2,
        ]);
        $this->getJson('/api/invoicing/companies/'.$this->company->id.'/bank-transactions?'.http_build_query([
            'sync' => 1, 'source' => 'email', 'sync_context' => str_repeat('a', 64),
            'per_page' => 2, 'sync_cursor' => $page['sync']['next_cursor'],
        ]))->assertUnauthorized();
    }

    /** A repeatedly incomplete head must not prevent older scan pages, and only a later clean cycle clears its evidence. */
    #[Test]
    public function incomplete_head_pages_allow_backfill_and_remain_unresolved_until_a_clean_cycle(): void
    {
        foreach (range(1, 7) as $id) {
            $this->movement($id, '2026-11-03 12:00:00');
        }
        $head = $this->feed()->assertOk();
        $cursor = $head->json('meta.sync.retry_cursor');
        $older = [];
        for ($step = 0; $step < 10; $step++) {
            $input = $this->frame($cursor);
            $page = $this->feed($cursor)->assertOk()->assertJsonPath('meta.sync.unresolved', true);
            if ($page->json('meta.sync.lane') === 'head') {
                $cursor = $page->json('meta.sync.retry_cursor');
                $this->assertSame($input['backfill_before'], $this->frame($cursor)['backfill_before']);
            } else {
                array_push($older, ...array_column($page->json('data'), 'id'));
                $cursor = $page->json('meta.sync.next_cursor');
                if ($this->frame($cursor)['backfill_before'] === null) {
                    $page->assertJsonPath('meta.sync.cycle_complete', false)->assertJsonPath('meta.sync.unresolved_after_success', true);
                    break;
                }
            }
        }
        $this->assertSame(array_map(fn (int $id): string => $this->uuid($id), [5, 4, 3, 2, 1]), $older);
        $this->assertFalse($this->frame($cursor)['cycle_incomplete']);
        $this->assertTrue($this->frame($cursor)['unresolved']);

        // The caller now ACKs the corrected page and every other page of a new cycle.
        $clean = false;
        for ($step = 0; $step < 10; $step++) {
            $page = $this->feed($cursor)->assertOk()->assertJsonPath('meta.sync.unresolved', true);
            $cursor = $page->json('meta.sync.next_cursor');
            if ($page->json('meta.sync.cycle_complete')) {
                $page->assertJsonPath('meta.sync.unresolved_after_success', false);
                $this->assertFalse($this->frame($cursor)['unresolved']);
                $clean = true;
                break;
            }
            $page->assertJsonPath('meta.sync.unresolved_after_success', true);
        }
        $this->assertTrue($clean);
        $this->feed($cursor)->assertOk()->assertJsonPath('meta.sync.unresolved', false);
    }

    /** Incompleteness on a physical wrap belongs to the closed cycle; the next scan starts clean but still unresolved. */
    #[Test]
    public function incomplete_wrap_resets_the_cycle_accumulator_but_retains_sticky_unresolved_state(): void
    {
        foreach (range(1, 3) as $id) {
            $this->movement($id, '2026-11-03 12:00:00');
        }
        $head = $this->feed()->assertOk();
        $wrap = $this->feed($head->json('meta.sync.next_cursor'))->assertOk();
        $wrap->assertJsonPath('meta.sync.cycle_complete', true)->assertJsonPath('meta.sync.unresolved_after_success', false);
        $retry = $this->frame($wrap->json('meta.sync.retry_cursor'));
        $this->assertNull($retry['backfill_before']);
        $this->assertFalse($retry['cycle_incomplete']);
        $this->assertTrue($retry['unresolved']);
        $nextHead = $this->feed($wrap->json('meta.sync.retry_cursor'))->assertOk();
        $nextHead->assertJsonPath('meta.sync.unresolved', true)->assertJsonPath('meta.sync.unresolved_after_success', true);
        $cleanWrap = $this->feed($nextHead->json('meta.sync.next_cursor'))->assertOk();
        $cleanWrap->assertJsonPath('meta.sync.cycle_complete', true)
            ->assertJsonPath('meta.sync.unresolved', true)->assertJsonPath('meta.sync.unresolved_after_success', false);
        $this->assertFalse($this->frame($cleanWrap->json('meta.sync.next_cursor'))['unresolved']);
    }

    /** A short head's incomplete outcome also retains evidence instead of claiming that its physical wrap covered the rows. */
    #[Test]
    public function short_head_incompleteness_yields_to_backfill_without_a_clean_coverage_claim(): void
    {
        $this->movement(1, null);
        $head = $this->feed()->assertOk()->assertJsonPath('meta.sync.cycle_complete', true);
        $retry = $this->frame($head->json('meta.sync.retry_cursor'));
        $this->assertSame('backfill', $retry['next_lane']);
        $this->assertNull($retry['backfill_before']);
        $this->assertFalse($retry['cycle_incomplete']);
        $this->assertTrue($retry['unresolved']);
        $this->feed($head->json('meta.sync.retry_cursor'))->assertOk()
            ->assertJsonPath('meta.sync.lane', 'backfill')->assertJsonPath('meta.sync.unresolved', true);
    }

    /** The new access path is reversible without changing transaction data or the existing indexes. */
    #[Test]
    public function sync_order_index_has_the_exact_columns_and_its_rollback_preserves_rows(): void
    {
        $id = $this->movement(1, null);
        $index = collect(Schema::getIndexes('bank_transactions'))->firstWhere('name', 'bank_transactions_sync_order_idx');
        $this->assertNotNull($index);
        $this->assertSame(['company_id', 'source', 'created_at', 'id'], $index['columns']);
        $this->assertFalse($index['unique']);
        $migration = require database_path('migrations/2026_11_04_080000_add_bank_email_sync_order_index.php');
        $migration->down();
        $this->assertNull(collect(Schema::getIndexes('bank_transactions'))->firstWhere('name', 'bank_transactions_sync_order_idx'));
        $this->assertTrue(DB::table('bank_transactions')->where('id', $id)->exists());
        $migration->up();
        $this->assertNotNull(collect(Schema::getIndexes('bank_transactions'))->firstWhere('name', 'bank_transactions_sync_order_idx'));
        $this->assertTrue(DB::table('bank_transactions')->where('id', $id)->exists());
    }

    /** Actual SQLite plans must use the composite access path for head, non-NULL seek and NULL seek without a temporary sort. */
    #[Test]
    public function sqlite_partition_plans_use_the_sync_index_without_case_or_temporary_sorting(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('SQLite EXPLAIN syntax; PostgreSQL plans are verified separately in a disposable target.');
        }
        foreach (range(1, 25) as $id) {
            $this->movement($id, '2026-11-03 12:00:00');
        }
        foreach (range(101, 105) as $id) {
            $this->movement($id, null);
        }
        $queries = [];
        $record = true;
        DB::listen(function (QueryExecuted $query) use (&$queries, &$record): void {
            if ($record && str_starts_with(strtolower($query->sql), 'select ') && str_contains($query->sql, 'from "bank_transactions"')) {
                $queries[] = [$query->sql, $query->bindings];
            }
        });
        $head = $this->feed()->assertOk();
        $this->feed($head->json('meta.sync.next_cursor'))->assertOk();
        $frame = $this->frame($head->json('meta.sync.next_cursor'));
        $frame['next_lane'] = 'backfill';
        $frame['backfill_before'] = ['created_at' => null, 'id' => $this->uuid(105)];
        $this->feed(Crypt::encryptString(json_encode($frame, JSON_THROW_ON_ERROR)))->assertOk();
        $this->feed(null, ['per_page' => 100])->assertOk();
        $record = false;
        $this->assertCount(5, $queries);
        foreach ($queries as [$sql, $bindings]) {
            $details = implode(' | ', array_map(fn (object $row): string => $row->detail, DB::select('EXPLAIN QUERY PLAN '.$sql, $bindings)));
            $this->assertStringContainsString('bank_transactions_sync_order_idx', $details);
            $this->assertStringNotContainsString('TEMP B-TREE', $details);
            $this->assertStringNotContainsString('CASE WHEN', strtoupper($sql));
        }
    }
}
