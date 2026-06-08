<?php

namespace Tests\Unit\Services;

use App\Models\SanctionsEntry;
use App\Services\Compliance\Sync\SanctionsEntryDto;
use App\Services\Compliance\Sync\SanctionsListPersister;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SanctionsListPersisterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function replace_source_rejects_empty_replacement_without_deleting_existing_entries(): void
    {
        SanctionsEntry::create([
            'source' => 'ofac_sdn',
            'external_id' => 'existing-1',
            'primary_name' => 'Existing Blocked Person',
            'primary_name_normalized' => 'existing blocked person',
            'aliases_normalized' => [],
            'countries' => [],
            'synced_at' => now()->subDay(),
        ]);

        try {
            app(SanctionsListPersister::class)->replaceSource('ofac_sdn', []);
        } catch (\UnexpectedValueException) {
            $this->assertDatabaseHas('sanctions_entries', [
                'source' => 'ofac_sdn',
                'external_id' => 'existing-1',
            ]);

            return;
        }

        $this->fail('Expected empty sanctions replacement to be rejected.');
    }

    #[Test]
    public function replace_source_rejects_entries_that_normalize_to_zero_rows(): void
    {
        SanctionsEntry::create([
            'source' => 'ofac_sdn',
            'external_id' => 'existing-1',
            'primary_name' => 'Existing Blocked Person',
            'primary_name_normalized' => 'existing blocked person',
            'aliases_normalized' => [],
            'countries' => [],
            'synced_at' => now()->subDay(),
        ]);

        try {
            app(SanctionsListPersister::class)->replaceSource('ofac_sdn', [
                new SanctionsEntryDto('ofac_sdn', 'blank-1', ' '),
            ]);
        } catch (\UnexpectedValueException) {
            $this->assertDatabaseHas('sanctions_entries', [
                'source' => 'ofac_sdn',
                'external_id' => 'existing-1',
            ]);

            return;
        }

        $this->fail('Expected empty normalized sanctions replacement to be rejected.');
    }
}
