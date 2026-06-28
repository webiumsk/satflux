<?php

namespace App\Services\Invoicing;

use App\Support\Invoicing\CompanyRegistryCoverage;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Proxy for OpenRegistry authenticated search + company profile.
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

        $token = $this->bearerToken();
        if ($token === '') {
            Log::warning('OpenRegistry search skipped: OPENREGISTRY_BEARER_TOKEN is not configured');

            return ['results' => [], 'count' => 0, 'error' => 'auth_required'];
        }

        try {
            $response = $this->authenticatedClient($token)
                ->get($this->baseUrl().'/companies', [
                    'q' => $query,
                    'jurisdiction' => $jurisdiction,
                    'limit' => min($limit, 20),
                ]);

            return $this->parseSearchResponse($response, $query, $jurisdiction);
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

        $token = $this->bearerToken();
        if ($token === '') {
            return null;
        }

        try {
            $response = $this->authenticatedClient($token)
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
     * @return array{results: list<array<string, mixed>>, count: int, error?: string|null}
     */
    protected function parseSearchResponse(Response $response, string $query, string $jurisdiction): array
    {
        if ($response->redirect()) {
            Log::warning('OpenRegistry search redirected unexpectedly', [
                'query' => $query,
                'jurisdiction' => $jurisdiction,
                'status' => $response->status(),
                'location' => $response->header('Location'),
            ]);

            return ['results' => [], 'count' => 0, 'error' => 'search_unavailable'];
        }

        if ($response->status() === 429) {
            return ['results' => [], 'count' => 0, 'error' => 'rate_limited'];
        }

        if ($response->status() === 403 || $response->json('error') === 'access_denied') {
            return ['results' => [], 'count' => 0, 'error' => 'auth_required'];
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
    }

    protected function authenticatedClient(string $token): PendingRequest
    {
        return Http::timeout(12)
            ->acceptJson()
            ->withToken($token)
            ->withoutRedirecting();
    }

    protected function bearerToken(): string
    {
        return trim((string) config('services.openregistry.bearer_token', ''));
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
