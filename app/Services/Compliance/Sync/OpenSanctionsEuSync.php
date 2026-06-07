<?php

namespace App\Services\Compliance\Sync;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * EU consolidated coverage via OpenSanctions bulk export (EU FSF XML returns 403 from many server IPs).
 */
class OpenSanctionsEuSync
{
    /**
     * @return list<SanctionsEntryDto>
     */
    public function fetchEntries(): array
    {
        $url = config('compliance.opensanctions_csv_url');
        $response = Http::timeout(300)
            ->withHeaders(['User-Agent' => 'satflux-compliance-sync/1.0'])
            ->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException('OpenSanctions CSV download failed: HTTP '.$response->status());
        }

        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new \RuntimeException('Unable to open temp stream for OpenSanctions CSV.');
        }

        fwrite($handle, $response->body());
        rewind($handle);

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            throw new \RuntimeException('OpenSanctions CSV is empty.');
        }

        $columns = array_flip($header);
        $entries = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < count($header)) {
                continue;
            }

            $dataset = $row[$columns['dataset']] ?? '';

            if (! $this->isEuDataset($dataset)) {
                continue;
            }

            $id = trim($row[$columns['id']] ?? '');

            if ($id === '') {
                continue;
            }

            $name = Str::squish(trim($row[$columns['name']] ?? ''));

            if ($name === '') {
                continue;
            }

            $aliases = $this->parseAliases($row[$columns['aliases']] ?? '');
            $countries = $this->parseCountries($row[$columns['countries']] ?? '');

            $entries[] = new SanctionsEntryDto(
                source: 'eu_consolidated',
                externalId: $id,
                primaryName: $name,
                aliases: $aliases,
                countries: $countries,
            );
        }

        fclose($handle);

        return $entries;
    }

    protected function isEuDataset(string $dataset): bool
    {
        return (bool) preg_match('/eu financial|european union|eu consolidated|eu sanctions/i', $dataset);
    }

    /**
     * @return list<string>
     */
    protected function parseAliases(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/\s*;\s*/', $raw) ?: [];

        return array_values(array_unique(array_filter(array_map(
            fn (string $alias) => Str::squish(trim($alias, " \t\n\r\0\x0B\"")),
            $parts,
        ))));
    }

    /**
     * @return list<string>
     */
    protected function parseCountries(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/\s*;\s*/', strtolower($raw)) ?: [];

        return array_values(array_unique(array_filter(array_map(
            fn (string $code) => strtoupper(trim($code)),
            $parts,
        ))));
    }
}
