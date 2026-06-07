<?php

namespace App\Services\Compliance;

use App\Models\SanctionsEntry;

class LocalSanctionsIndex
{
    public function __construct(
        protected NameNormalizer $normalizer,
    ) {}

    public function hasEntries(): bool
    {
        return SanctionsEntry::query()->exists();
    }

    /**
     * @return array{matched: bool, reference: ?string, payload_hash: ?string, reason: ?string}
     */
    public function match(string $email, ?string $name = null): array
    {
        $candidates = array_values(array_filter(array_unique([
            $this->normalizer->normalizeEmailLocalPart($email),
            $this->normalizer->normalize($name),
        ])));

        if ($candidates === []) {
            return [
                'matched' => false,
                'reference' => null,
                'payload_hash' => null,
                'reason' => null,
            ];
        }

        foreach ($candidates as $candidate) {
            $entry = SanctionsEntry::query()
                ->where('primary_name_normalized', $candidate)
                ->orWhereJsonContains('aliases_normalized', $candidate)
                ->first();

            if ($entry !== null) {
                return [
                    'matched' => true,
                    'reference' => $entry->source.':'.$entry->external_id,
                    'payload_hash' => hash('sha256', json_encode([
                        'source' => $entry->source,
                        'external_id' => $entry->external_id,
                        'primary_name' => $entry->primary_name,
                        'matched_on' => $candidate,
                    ])),
                    'reason' => 'sanctions_list_match',
                ];
            }
        }

        return [
            'matched' => false,
            'reference' => null,
            'payload_hash' => null,
            'reason' => null,
        ];
    }
}
