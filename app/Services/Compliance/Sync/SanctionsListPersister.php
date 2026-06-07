<?php

namespace App\Services\Compliance\Sync;

use App\Models\SanctionsEntry;
use App\Services\Compliance\NameNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SanctionsListPersister
{
    public function __construct(
        protected NameNormalizer $normalizer,
    ) {}

    /**
     * @param  list<SanctionsEntryDto>  $entries
     */
    public function replaceSource(string $source, array $entries): int
    {
        $syncedAt = now();
        $rows = [];

        foreach ($entries as $entry) {
            if ($entry->source !== $source) {
                continue;
            }

            $primaryNormalized = $this->normalizer->normalize($entry->primaryName);

            if ($primaryNormalized === null) {
                continue;
            }

            $aliasesNormalized = [];

            foreach ($entry->aliases as $alias) {
                $normalized = $this->normalizer->normalize($alias);

                if ($normalized !== null && $normalized !== $primaryNormalized) {
                    $aliasesNormalized[] = $normalized;
                }
            }

            $rows[] = [
                'id' => (string) Str::uuid(),
                'source' => $entry->source,
                'external_id' => $entry->externalId,
                'primary_name' => $entry->primaryName,
                'primary_name_normalized' => $primaryNormalized,
                'aliases_normalized' => json_encode(array_values(array_unique($aliasesNormalized))),
                'countries' => json_encode($entry->countries),
                'synced_at' => $syncedAt,
            ];
        }

        DB::transaction(function () use ($source, $rows, $syncedAt) {
            SanctionsEntry::query()->where('source', $source)->delete();

            foreach (array_chunk($rows, 500) as $chunk) {
                SanctionsEntry::query()->insert($chunk);
            }

            Cache::put('compliance.sanctions_list_version.'.$source, sha1(json_encode([
                'count' => count($rows),
                'synced_at' => $syncedAt->toIso8601String(),
            ])), now()->addDays(30));
        });

        return count($rows);
    }
}
