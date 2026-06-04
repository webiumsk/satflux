<?php

namespace App\Services\Invoicing;

use App\Support\Invoicing\CompanyRegistryCoverage;

class CompanyRegistryService
{
    public function __construct(
        protected SubjektRegistryService $subjekt,
        protected OpenRegistryService $openRegistry,
    ) {}

    public function search(string $query, string $country = 'sk', int $limit = 8): array
    {
        $country = CompanyRegistryCoverage::normalize($country);

        return match (CompanyRegistryCoverage::provider($country)) {
            'subjekt' => $this->subjekt->search($query, $country, $limit),
            'openregistry' => $this->openRegistry->search($query, $country, $limit),
            default => ['results' => [], 'count' => 0],
        };
    }

    public function findByIdentifier(string $identifier, string $country = 'sk'): ?array
    {
        $country = CompanyRegistryCoverage::normalize($country);
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        return match (CompanyRegistryCoverage::provider($country)) {
            'subjekt' => $this->subjekt->findByIco(
                preg_replace('/\D/', '', $identifier) ?? '',
                $country
            ),
            'openregistry' => $this->openRegistry->findByCompanyId($identifier, $country)
                ?? $this->summaryFallback($identifier, $country),
            default => null,
        };
    }

    /**
     * When profile API needs auth, search may still return a usable summary row.
     *
     * @return array<string, mixed>|null
     */
    protected function summaryFallback(string $identifier, string $country): ?array
    {
        $search = $this->openRegistry->search($identifier, $country, 5);
        foreach ($search['results'] ?? [] as $row) {
            if (($row['ico'] ?? '') === $identifier) {
                return $this->summaryToDetail($row, $country);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    protected function summaryToDetail(array $summary, string $country): array
    {
        $jurisdiction = strtoupper((string) ($summary['registry_jurisdiction'] ?? $country));
        $line = trim((string) ($summary['address_line'] ?? ''));

        return [
            'ico' => (string) ($summary['ico'] ?? ''),
            'name' => (string) ($summary['name'] ?? ''),
            'dic' => '',
            'ic_dph' => '',
            'street' => '',
            'city' => $line,
            'postal_code' => '',
            'country_code' => $jurisdiction,
            'country' => $jurisdiction,
            'registry_note' => '',
            'source' => 'openregistry',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function coverageMeta(): array
    {
        return CompanyRegistryCoverage::metaForApi();
    }

    /**
     * @return list<array{value: string, group: string, provider: string, label: string, autocomplete: bool}>
     */
    public function coverageOptions(): array
    {
        return CompanyRegistryCoverage::optionsForFrontend();
    }
}
