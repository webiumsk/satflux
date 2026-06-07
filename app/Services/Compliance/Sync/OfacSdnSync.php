<?php

namespace App\Services\Compliance\Sync;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OfacSdnSync
{
    /**
     * @return list<SanctionsEntryDto>
     */
    public function fetchEntries(): array
    {
        $url = config('compliance.ofac_sdn_url');
        $response = Http::timeout(120)->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException('OFAC SDN download failed: HTTP '.$response->status());
        }

        $xml = @simplexml_load_string($response->body());

        if ($xml === false) {
            throw new \RuntimeException('OFAC SDN XML parse failed.');
        }

        $entries = [];

        foreach ($xml->sdnEntry as $sdnEntry) {
            $uid = trim((string) ($sdnEntry->uid ?? ''));

            if ($uid === '') {
                continue;
            }

            $names = $this->collectOfacNames($sdnEntry);

            if ($names === []) {
                continue;
            }

            $primary = $names[0];
            $aliases = array_values(array_unique(array_slice($names, 1)));

            $entries[] = new SanctionsEntryDto(
                source: 'ofac_sdn',
                externalId: $uid,
                primaryName: $primary,
                aliases: $aliases,
            );
        }

        return $entries;
    }

    /**
     * @return list<string>
     */
    protected function collectOfacNames(\SimpleXMLElement $sdnEntry): array
    {
        $names = [];

        $primary = $this->formatOfacName(
            (string) ($sdnEntry->firstName ?? ''),
            (string) ($sdnEntry->lastName ?? ''),
        );

        if ($primary !== null) {
            $names[] = $primary;
        }

        if (isset($sdnEntry->akaList->aka)) {
            foreach ($sdnEntry->akaList->aka as $aka) {
                $akaName = $this->formatOfacName(
                    (string) ($aka->firstName ?? ''),
                    (string) ($aka->lastName ?? ''),
                );

                if ($akaName !== null) {
                    $names[] = $akaName;
                }
            }
        }

        return array_values(array_unique(array_filter($names)));
    }

    protected function formatOfacName(string $firstName, string $lastName): ?string
    {
        $name = trim($firstName.' '.$lastName);

        if ($name === '') {
            return null;
        }

        return Str::squish($name);
    }
}
