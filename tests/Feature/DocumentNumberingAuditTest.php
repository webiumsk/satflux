<?php

namespace Tests\Feature;

use App\Enums\BusinessDocumentStatus;
use App\Enums\CompanyJurisdiction;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\CompanyDocumentSequence;
use App\Models\DocumentNumberReservation;
use App\Models\User;
use App\Services\Invoicing\DocumentSequenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Numbering audit 2026-10: period rollover, release by reservation key,
 * the client's period date, the reservation floor on every allocation path
 * and the "latest by number" delete rule.
 */
class DocumentNumberingAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-05-10 12:00:00'));

        $this->user = User::factory()->create(['role' => 'enterprise']);
        $this->company = Company::create([
            'user_id' => $this->user->id,
            'legal_name' => 'Webium s.r.o.',
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
        ]);

        CompanyDocumentSequence::create([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'name' => 'INV',
            'format' => 'INVYYYYNNNN',
            'reset_period' => 'yearly',
            'is_default' => true,
            'period_key' => '2026',
            'last_number' => 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function reserve(string $issueRequestId, array $extra = []): TestResponse
    {
        return $this->actingAs($this->user)
            ->postJson('/api/invoicing/companies/'.$this->company->id.'/number-allocator/reserve', [
                'document_type' => 'invoice',
                'issue_request_id' => $issueRequestId,
                ...$extra,
            ]);
    }

    public function test_yearly_series_restarts_in_the_new_year_despite_server_documents(): void
    {
        BusinessDocument::create([
            'company_id' => $this->company->id,
            'type' => 'invoice',
            'status' => BusinessDocumentStatus::Issued,
            'number' => 'INV20260342',
            'issue_date' => '2026-12-20',
            'total' => 10,
            'currency' => 'EUR',
        ]);

        $this->travelTo(Carbon::parse('2027-01-02 09:00:00'));

        // The client's high counter is per period too - 0 in the new year.
        $this->reserve('req-new-year-1')
            ->assertOk()
            ->assertJsonPath('data.number', 'INV20270001')
            ->assertJsonPath('data.period_key', '2027');
    }

    public function test_client_period_date_decides_the_period_around_midnight_utc(): void
    {
        // 23:30 UTC on Dec 31 is already 00:30 on Jan 1 in Bratislava.
        $this->travelTo(Carbon::parse('2026-12-31 23:30:00'));

        $this->reserve('req-new-year-2', ['period_date' => '2027-01-01'])
            ->assertOk()
            ->assertJsonPath('data.number', 'INV20270001')
            ->assertJsonPath('data.period_key', '2027');

        // A date further away than one day is ignored - no back-dating.
        $this->reserve('req-backdated', ['period_date' => '2025-06-01'])
            ->assertOk()
            ->assertJsonPath('data.period_key', '2026');
    }

    public function test_release_by_issue_request_id_works_when_the_client_formats_differently(): void
    {
        $this->reserve('req-release-1')->assertOk();
        $this->reserve('req-release-2')->assertOk()->assertJsonPath('data.counter', 2);

        // The client renders "FV20260002" with its local format - the number
        // lookup misses, the reservation key does not.
        $this->actingAs($this->user)
            ->postJson('/api/invoicing/companies/'.$this->company->id.'/number-allocator/release', [
                'document_type' => 'invoice',
                'issue_request_id' => 'req-release-2',
                'number' => 'FV20260002',
            ])
            ->assertOk()
            ->assertJsonPath('data.released', true);

        $this->reserve('req-release-3')->assertOk()->assertJsonPath('data.counter', 2);
    }

    public function test_release_refuses_a_number_that_is_not_the_top(): void
    {
        $this->reserve('req-top-1')->assertOk();
        $this->reserve('req-top-2')->assertOk();

        $this->actingAs($this->user)
            ->postJson('/api/invoicing/companies/'.$this->company->id.'/number-allocator/release', [
                'document_type' => 'invoice',
                'issue_request_id' => 'req-top-1',
            ])
            ->assertStatus(422);
    }

    public function test_next_number_respects_the_reservation_floor(): void
    {
        $this->reserve('req-floor-1')->assertOk();
        $this->reserve('req-floor-2')->assertOk();

        // Server-side issuing (Woo inbox, server documents) used to restart
        // from the server documents - 0 for a local-first company.
        $number = app(DocumentSequenceService::class)->nextNumber($this->company, 'invoice');

        $this->assertSame('INV20260003', $number);
    }

    public function test_previews_do_not_write_and_include_the_reservation_floor(): void
    {
        $this->reserve('req-preview-1')->assertOk();
        CompanyDocumentSequence::query()->where('company_id', $this->company->id)
            ->update(['last_number' => 0]);

        $service = app(DocumentSequenceService::class);
        $this->assertSame('INV20260002', $service->previewNextNumber($this->company, 'invoice'));
        $this->assertSame('INV20260051', $service->previewNextNumber($this->company, 'invoice', 50));

        $series = CompanyDocumentSequence::query()->where('company_id', $this->company->id)->firstOrFail();
        $this->assertSame(0, (int) $series->last_number);
    }

    public function test_concurrent_first_reserve_of_the_same_request_returns_one_reservation(): void
    {
        $service = app(DocumentSequenceService::class);
        $first = $service->reserveNumberForIssue($this->company, 'invoice', 'req-race-1');
        $again = $service->reserveNumberForIssue($this->company, 'invoice', 'req-race-1');

        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, DocumentNumberReservation::query()->where('issue_request_id', 'req-race-1')->count());
    }

    public function test_only_the_top_numbered_document_is_deletable_including_cancelled_ones(): void
    {
        // Issued later but drafted earlier: created_at no longer decides.
        $top = BusinessDocument::create([
            'company_id' => $this->company->id,
            'type' => 'invoice',
            'status' => BusinessDocumentStatus::Issued,
            'number' => 'INV20260002',
            'issue_date' => '2026-05-01',
            'total' => 10,
            'currency' => 'EUR',
        ]);
        $this->travel(1)->minutes();
        $older = BusinessDocument::create([
            'company_id' => $this->company->id,
            'type' => 'invoice',
            'status' => BusinessDocumentStatus::Cancelled,
            'number' => 'INV20260001',
            'issue_date' => '2026-05-01',
            'total' => 10,
            'currency' => 'EUR',
        ]);
        // A newer draft never blocks the top issued number.
        BusinessDocument::create([
            'company_id' => $this->company->id,
            'type' => 'invoice',
            'status' => BusinessDocumentStatus::Draft,
            'total' => 10,
            'currency' => 'EUR',
        ]);

        $this->assertTrue($top->fresh()->canDelete());
        // A cancelled number in the middle would leave a hole.
        $this->assertFalse($older->fresh()->canDelete());

        $this->actingAs($this->user)
            ->deleteJson("/api/invoicing/companies/{$this->company->id}/documents/{$older->id}")
            ->assertStatus(422);
        $this->actingAs($this->user)
            ->deleteJson("/api/invoicing/companies/{$this->company->id}/documents/{$top->id}")
            ->assertOk();

        // Now the cancelled one is the top and may go.
        $this->assertTrue($older->fresh()->canDelete());
    }

    public function test_profile_local_high_counter_of_an_earlier_period_is_ignored(): void
    {
        $service = app(DocumentSequenceService::class);

        $this->assertSame(342, $service->localHighCounterForCurrentPeriod(
            $this->company, 'invoice', 342, Carbon::parse('2026-03-01'),
        ));
        $this->assertNull($service->localHighCounterForCurrentPeriod(
            $this->company, 'invoice', 342, Carbon::parse('2025-12-30'),
        ));
    }

    public function test_manual_last_used_counter_is_a_floor_and_release_keeps_it(): void
    {
        $series = CompanyDocumentSequence::query()->where('company_id', $this->company->id)->firstOrFail();
        $this->actingAs($this->user)
            ->patchJson("/api/invoicing/companies/{$this->company->id}/number-series/{$series->id}", [
                'name' => 'INV',
                'document_type' => 'invoice',
                'format' => 'INVYYYYNNNN',
                'reset_period' => 'yearly',
                'is_default' => true,
                'last_number' => 120,
            ])
            ->assertOk()
            ->assertJsonPath('data.next_number_preview', 'INV20260121');

        $this->reserve('req-manual-1')->assertOk()->assertJsonPath('data.number', 'INV20260121');

        // Deleting it releases 121 - and keeps the migration start value.
        $this->actingAs($this->user)
            ->postJson('/api/invoicing/companies/'.$this->company->id.'/number-allocator/release', [
                'document_type' => 'invoice',
                'issue_request_id' => 'req-manual-1',
            ])
            ->assertOk()
            ->assertJsonPath('data.released', true);

        $this->reserve('req-manual-2')->assertOk()->assertJsonPath('data.number', 'INV20260121');
    }

    public function test_series_list_shows_the_counter_of_the_current_period_only(): void
    {
        CompanyDocumentSequence::query()->where('company_id', $this->company->id)
            ->update(['period_key' => '2025', 'last_number' => 342]);

        $this->actingAs($this->user)
            ->getJson("/api/invoicing/companies/{$this->company->id}/number-series")
            ->assertOk()
            ->assertJsonPath('data.0.last_number', 0)
            ->assertJsonPath('data.0.next_number_preview', 'INV20260001');
    }

    public function test_server_bulk_delete_of_the_tail_lowers_the_counter_once_per_number(): void
    {
        $service = app(DocumentSequenceService::class);
        $ids = [];
        foreach ([1, 2, 3] as $i) {
            $ids[] = BusinessDocument::create([
                'company_id' => $this->company->id,
                'type' => 'invoice',
                'status' => BusinessDocumentStatus::Issued,
                'number' => $service->nextNumber($this->company, 'invoice'),
                'issue_date' => '2026-05-01',
                'total' => 10,
                'currency' => 'EUR',
            ])->id;
        }

        // Memoized "latest" must follow each delete inside one bulk run.
        $this->actingAs($this->user)
            ->postJson("/api/invoicing/companies/{$this->company->id}/documents/bulk", [
                'action' => 'delete',
                'document_ids' => [$ids[1], $ids[2]],
            ])
            ->assertOk();

        $this->assertSame(1, BusinessDocument::query()->where('company_id', $this->company->id)->count());
        $this->assertSame('INV20260002', $service->nextNumber($this->company, 'invoice'));
    }
}
