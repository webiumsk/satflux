<?php

namespace App\Services\Invoicing;

use App\Support\Invoicing\CompanyRegistryCoverage;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Proxy for OpenRegistry public search + optional authenticated company profile.
 *
 * @see https://openregistry.sophymarine.com/api
 */
class OpenRegistryService
{
    public function isEnabled(): bool
    {
        return (bool) config('services.openregistry.enabled', true);
    }

    public function search(string $query, string $jurisdiction, int $limit = 8): array
    {
        $query = trim($query);
        $jurisdiction = strtoupper(CompanyRegistryCoverage::normalize($jurisdiction));
        if (strlen($query) < 2 || ! $this->isEnabled()) {
            return ['results' => [], 'count' => 0, 'error' => $this->isEnabled() ? null : 'search_unavailable'];
        }

        try {
            $response = Http::timeout(12)
                ->acceptJson()
                ->get($this->baseUrl().'/search', [
                    'q' => $query,
                    'jurisdiction' => $jurisdiction,
                    'limit' => min($limit, 20),
                ]);

            if ($response->status() === 429) {
                return ['results' => [], 'count' => 0, 'error' => 'rate_limited'];
            }

            $response->throw();

            if ($response->json('error') === 'search_unavailable') {
                return ['results' => [], 'count' => 0, 'error' => 'search_unavailable'];
            }

            $results = [];
            foreach ($response->json('results', []) as $row) {
                $mapped = $this->mapSummary(is_array($row) ? $row : []);
                if ($mapped !== null) {
                    $results[] = $mapped;
                }
            }

            return [
                'results' => $results,
                'count' => (int) ($response->json('count') ?? count($results)),
            ];
        } catch (RequestException $e) {
            Log::warning('OpenRegistry search failed', [
                'query' => $query,
                'jurisdiction' => $jurisdiction,
                'status' => $e->response?->status(),
                'message' => $e->getMessage(),
            ]);

            return ['results' => [], 'count' => 0, 'error' => 'search_unavailable'];
        }
    }

    public function findByCompanyId(string $companyId, string $jurisdiction): ?array
    {
        $companyId = trim($companyId);
        $jurisdiction = strtoupper(CompanyRegistryCoverage::normalize($jurisdiction));
        if ($companyId === '' || ! $this->isEnabled()) {
            return null;
        }

        $token = (string) config('services.openregistry.bearer_token', '');
        if ($token === '') {
            return null;
        }

        try {
            $response = Http::timeout(12)
                ->acceptJson()
                ->withToken($token)
                ->get($this->baseUrl().'/companies/'.$jurisdiction.'/'.$this->encodeCompanyId($companyId));

            if ($response->status() === 404) {
                return null;
            }

            if ($response->status() === 403 || $response->json('error') === 'access_denied') {
                return null;
            }

            $response->throw();

            return $this->mapProfile($response->json(), $jurisdiction);
        } catch (RequestException $e) {
            Log::warning('OpenRegistry profile lookup failed', [
                'company_id' => $companyId,
                'jurisdiction' => $jurisdiction,
                'status' => $e->response?->status(),
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    public function mapSummary(array $row): ?array
    {
        $id = trim((string) ($row['company_id'] ?? ''));
        $name = trim((string) ($row['company_name'] ?? ''));
        if ($id === '' || $name === '') {
            return null;
        }

        $jurisdiction = strtoupper((string) ($row['jurisdiction'] ?? ''));

        return [
            'ico' => $id,
            'name' => $name,
            'address_line' => trim((string) ($row['registered_address'] ?? '')),
            'dic' => '',
            'ic_dph' => '',
            'registry_jurisdiction' => $jurisdiction,
            'source' => 'openregistry',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    protected function mapProfile(?array $payload, string $jurisdiction): ?array
    {
        if (! is_array($payload)) {
            return null;
        }

        $company = $payload['company'] ?? $payload['results']['company'] ?? $payload;
        if (! is_array($company)) {
            return null;
        }

        $id = trim((string) ($company['company_id'] ?? $company['company_number'] ?? ''));
        $name = trim((string) ($company['company_name'] ?? $company['name'] ?? ''));
        if ($id === '' || $name === '') {
            return null;
        }

        $address = trim((string) ($company['registered_address'] ?? ''));
        $parts = $this->parseAddressLine($address);

        return [
            'ico' => $id,
            'name' => $name,
            'dic' => '',
            'ic_dph' => '',
            'street' => $parts['street'],
            'city' => $parts['city'],
            'postal_code' => $parts['postal_code'],
            'country_code' => $jurisdiction === 'GB' ? 'GB' : $jurisdiction,
            'country' => $jurisdiction,
            'registry_note' => (string) ($company['registry_name'] ?? ''),
            'source' => 'openregistry',
        ];
    }

    /**
     * @return array{street: string, city: string, postal_code: string}
     */
    protected function parseAddressLine(string $address): array
    {
        $address = trim($address);
        if ($address === '') {
            return ['street' => '', 'city' => '', 'postal_code' => ''];
        }

        if (preg_match('/^(.+?),\s*([A-Z0-9][A-Z0-9\s-]{2,12})\s+(.+)$/iu', $address, $m)) {
            return [
                'street' => trim($m[1]),
                'postal_code' => trim($m[2]),
                'city' => trim($m[3]),
            ];
        }

        return ['street' => '', 'city' => $address, 'postal_code' => ''];
    }

    protected function encodeCompanyId(string $companyId): string
    {
        return rawurlencode($companyId);
    }

    protected function baseUrl(): string
    {
        return rtrim(config('services.openregistry.base_url', 'https://openregistry.sophymarine.com/api/v1'), '/');
    }
}
