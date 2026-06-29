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
        $name = trim((string) ($row['company_name'] ?? $row['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $jurisdiction = strtoupper((string) ($row['jurisdiction'] ?? ''));

        if ($jurisdiction === 'CH') {
            return $this->mapSwissSummary($row, $name);
        }

        $id = trim((string) ($row['company_id'] ?? ''));
        if ($id === '') {
            return null;
        }

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

        $jurisdiction = strtoupper($jurisdiction);

        if ($jurisdiction === 'CH') {
            return $this->mapSwissProfile($company, $payload);
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
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    protected function mapSwissSummary(array $row, string $name): ?array
    {
        $jd = $this->jurisdictionData($row);
        $identifiers = $this->extractSwissIdentifiers($row, $jd);
        if ($identifiers['registration'] === '' && $identifiers['uid'] === '') {
            return null;
        }

        $address = $this->extractSwissAddress($row, $jd);

        return [
            'ico' => $identifiers['registration'] !== '' ? $identifiers['registration'] : $identifiers['uid'],
            'name' => $name,
            'address_line' => $address['address_line'],
            'dic' => $identifiers['uid'],
            'ic_dph' => '',
            'registry_jurisdiction' => 'CH',
            'source' => 'openregistry',
        ];
    }

    /**
     * @param  array<string, mixed>  $company
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    protected function mapSwissProfile(array $company, array $payload): ?array
    {
        $name = trim((string) ($company['company_name'] ?? $company['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $jd = $this->jurisdictionData($company);
        $identifiers = $this->extractSwissIdentifiers($company, $jd);
        if ($identifiers['registration'] === '' && $identifiers['uid'] === '') {
            return null;
        }

        $address = $this->extractSwissAddress($company, $jd);
        $legalForm = $this->swissLegalFormLabel($jd);

        return [
            'ico' => $identifiers['registration'] !== '' ? $identifiers['registration'] : $identifiers['uid'],
            'name' => $name,
            'dic' => $identifiers['uid'],
            'ic_dph' => '',
            'street' => $address['street'],
            'city' => $address['city'],
            'postal_code' => $address['postal_code'],
            'state_region' => $address['canton'],
            'country_code' => 'CH',
            'country' => 'CH',
            'registry_note' => $legalForm,
            'source' => 'openregistry',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function jurisdictionData(array $row): array
    {
        $jd = $row['jurisdiction_data'] ?? null;

        return is_array($jd) ? $jd : [];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $jd
     * @return array{registration: string, uid: string}
     */
    protected function extractSwissIdentifiers(array $row, array $jd): array
    {
        $uid = $this->formatSwissUid((string) ($jd['uid'] ?? $row['company_id'] ?? ''));
        $registration = $this->formatSwissChid((string) ($jd['chid'] ?? ''));

        return [
            'registration' => $registration,
            'uid' => $uid,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $jd
     * @return array{street: string, city: string, postal_code: string, canton: string, address_line: string}
     */
    protected function extractSwissAddress(array $row, array $jd): array
    {
        $addr = is_array($jd['address'] ?? null) ? $jd['address'] : [];
        $street = trim(implode(' ', array_filter([
            trim((string) ($addr['street'] ?? '')),
            trim((string) ($addr['houseNumber'] ?? '')),
        ])));
        $postal = trim((string) ($addr['swissZipCode'] ?? ''));
        $city = $this->normalizeSwissCity(
            trim((string) ($addr['city'] ?? '')),
            trim((string) ($jd['canton'] ?? ''))
        );
        $canton = trim((string) ($jd['canton'] ?? ''));

        if ($street === '' && $city === '' && $postal === '') {
            $parsed = $this->parseAddressLine(trim((string) ($row['registered_address'] ?? '')));
            $street = $parsed['street'];
            $city = $this->normalizeSwissCity($parsed['city'], $canton);
            $postal = $parsed['postal_code'];
        }

        if ($postal !== '' && ! str_starts_with(strtoupper($postal), 'CH-')) {
            $postal = 'CH-'.$postal;
        }

        $addressLine = implode(', ', array_filter([$street, $postal, $city]));

        return [
            'street' => $street,
            'city' => $city,
            'postal_code' => $postal,
            'canton' => $canton,
            'address_line' => $addressLine,
        ];
    }

    protected function formatSwissUid(string $uid): string
    {
        $uid = strtoupper(trim($uid));
        if ($uid === '') {
            return '';
        }

        if (preg_match('/^CHE-\d{3}\.\d{3}\.\d{3}$/', $uid)) {
            return $uid;
        }

        $digits = preg_replace('/\D/', '', $uid) ?? '';
        if (strlen($digits) === 9) {
            return sprintf(
                'CHE-%s.%s.%s',
                substr($digits, 0, 3),
                substr($digits, 3, 3),
                substr($digits, 6, 3),
            );
        }

        return $uid;
    }

    protected function formatSwissChid(string $chid): string
    {
        $chid = strtoupper(trim($chid));
        if ($chid === '') {
            return '';
        }

        if (preg_match('/^CH-\d{3}\.\d\.\d{3}\.\d{3}-\d$/', $chid)) {
            return $chid;
        }

        if (preg_match('/^CH(\d{3})(\d)(\d{6})(\d)$/', $chid, $m)) {
            return sprintf(
                'CH-%s.%s.%s.%s-%s',
                $m[1],
                $m[2],
                substr($m[3], 0, 3),
                substr($m[3], 3, 3),
                $m[4],
            );
        }

        return $chid;
    }

    protected function normalizeSwissCity(string $city, string $canton): string
    {
        $city = trim($city);
        if ($city === '') {
            return '';
        }

        $city = preg_replace('/\s*\([A-Z]{2}\)\s*$/', '', $city) ?? $city;
        $city = preg_replace('/\s+[A-Z]{2}$/', '', $city) ?? $city;

        if ($canton !== '' && str_ends_with(strtoupper($city), ' '.$canton)) {
            $city = trim(substr($city, 0, -strlen($canton)));
        }

        return trim($city);
    }

    /**
     * @param  array<string, mixed>  $jd
     */
    protected function swissLegalFormLabel(array $jd): string
    {
        $legalForm = $jd['legalForm'] ?? null;
        if (! is_array($legalForm)) {
            return '';
        }

        $short = $legalForm['shortName'] ?? null;
        if (is_array($short)) {
            foreach (['de', 'en', 'fr', 'it'] as $lang) {
                $label = trim((string) ($short[$lang] ?? ''));
                if ($label !== '') {
                    return $label;
                }
            }
        }

        return trim((string) ($legalForm['uid'] ?? ''));
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

        if (preg_match('/^(.+?),\s*(\d{4,5})\s*,\s*(.+)$/iu', $address, $m)) {
            return [
                'street' => trim($m[1]),
                'postal_code' => trim($m[2]),
                'city' => trim($m[3]),
            ];
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
