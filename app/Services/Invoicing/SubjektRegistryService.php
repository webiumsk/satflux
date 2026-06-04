<?php

namespace App\Services\Invoicing;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Proxy for api.subjekt.sk (SK RPO + CZ ARES). Public registry data; no API key required.
 *
 * @see https://subjekt.sk/
 */
class SubjektRegistryService
{
    public function search(string $query, string $country = 'sk', int $limit = 8): array
    {
        $query = trim($query);
        if (strlen($query) < 2) {
            return ['results' => [], 'count' => 0];
        }

        $country = $this->normalizeCountry($country);

        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->get($this->baseUrl().'/search', [
                    'q' => $query,
                    'country' => $country,
                    'limit' => min($limit, 20),
                ]);

            $response->throw();

            $results = [];
            foreach ($response->json('results', []) as $row) {
                $mapped = $this->mapSummary($row);
                if ($mapped !== null) {
                    $results[] = $mapped;
                }
            }

            return [
                'results' => $results,
                'count' => (int) $response->json('count', count($results)),
            ];
        } catch (RequestException $e) {
            Log::warning('Subjekt registry search failed', [
                'query' => $query,
                'status' => $e->response?->status(),
                'message' => $e->getMessage(),
            ]);

            return ['results' => [], 'count' => 0, 'error' => 'search_unavailable'];
        }
    }

    public function findByIco(string $ico, string $country = 'sk'): ?array
    {
        $ico = preg_replace('/\D/', '', $ico) ?? '';
        if (strlen($ico) < 6) {
            return null;
        }

        $country = $this->normalizeCountry($country);

        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->get($this->baseUrl().'/entity/'.$ico, [
                    'country' => $country,
                ]);

            if ($response->status() === 404) {
                return null;
            }

            $response->throw();

            return $this->mapDetail($response->json());
        } catch (RequestException $e) {
            Log::warning('Subjekt registry entity lookup failed', [
                'ico' => $ico,
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
    protected function mapSummary(array $row): ?array
    {
        $ico = preg_replace('/\D/', '', (string) ($row['ico'] ?? '')) ?? '';
        $name = trim((string) ($row['name'] ?? ''));
        if ($ico === '' || $name === '') {
            return null;
        }

        $address = $this->formatAddressLine($row['address'] ?? []);

        return [
            'ico' => $ico,
            'name' => $name,
            'address_line' => $address,
            'dic' => (string) ($row['dic'] ?? ''),
            'ic_dph' => (string) ($row['ic_dph'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    protected function mapDetail(?array $payload): ?array
    {
        if (! is_array($payload) || empty($payload['ico'])) {
            return null;
        }

        $address = is_array($payload['address'] ?? null) ? $payload['address'] : [];
        $registration = is_array($payload['registration'] ?? null) ? $payload['registration'] : [];

        $street = $this->formatStreet($address);
        $postal = $this->formatPostalCode((string) ($address['zip'] ?? ''));
        $countryCode = strtoupper((string) ($address['country'] ?? 'SK'));

        $registryNote = '';
        if (! empty($registration['office']) || ! empty($registration['number'])) {
            $registryNote = trim(
                ($registration['office'] ?? '').', '.($registration['number'] ?? ''),
                " \t\n\r\0\x0B,"
            );
        }

        return [
            'ico' => preg_replace('/\D/', '', (string) $payload['ico']) ?? '',
            'name' => trim((string) ($payload['name'] ?? '')),
            'dic' => (string) ($payload['dic'] ?? ''),
            'ic_dph' => (string) ($payload['ic_dph'] ?? ''),
            'legal_form' => (string) ($payload['legal_form'] ?? ''),
            'street' => $street,
            'city' => trim((string) ($address['city'] ?? '')),
            'postal_code' => $postal,
            'country_code' => $countryCode,
            'country' => $this->countryLabel($countryCode),
            'registry_note' => $registryNote,
            'source' => 'subjekt.sk',
        ];
    }

    /**
     * @param  array<string, mixed>  $address
     */
    protected function formatAddressLine(array $address): string
    {
        $parts = array_filter([
            $this->formatStreet($address),
            $this->formatPostalCode((string) ($address['zip'] ?? '')),
            trim((string) ($address['city'] ?? '')),
        ]);

        return implode(', ', $parts);
    }

    /**
     * @param  array<string, mixed>  $address
     */
    protected function formatStreet(array $address): string
    {
        $street = trim((string) ($address['street'] ?? ''));
        $building = trim((string) ($address['building_no'] ?? ''));

        if ($street !== '' && $building !== '') {
            return $street.' '.$building;
        }

        return $street !== '' ? $street : $building;
    }

    protected function formatPostalCode(string $zip): string
    {
        $digits = preg_replace('/\D/', '', $zip) ?? '';
        if (strlen($digits) === 5) {
            return substr($digits, 0, 3).' '.substr($digits, 3);
        }

        return trim($zip);
    }

    protected function countryLabel(string $code): string
    {
        return match (strtoupper($code)) {
            'SK' => 'Slovensko',
            'CZ' => 'Česko',
            default => $code,
        };
    }

    protected function normalizeCountry(string $country): string
    {
        $c = strtolower(trim($country));
        if (in_array($c, ['cz', 'czech', 'cesko', 'česko'], true)) {
            return 'cz';
        }

        return 'sk';
    }

    protected function baseUrl(): string
    {
        return rtrim(config('services.subjekt_registry.base_url', 'https://api.subjekt.sk/v1'), '/');
    }
}
