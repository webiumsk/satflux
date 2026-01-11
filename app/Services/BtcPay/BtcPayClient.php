<?php

namespace App\Services\BtcPay;

use App\Services\BtcPay\Exceptions\BtcPayException;
use App\Services\BtcPay\Exceptions\BtcPayRateLimitException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BtcPayClient
{
    protected PendingRequest $client;
    protected string $baseUrl;
    protected int $maxRetries = 3;
    protected int $initialBackoff = 1; // seconds

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.btcpay.base_url', env('BTCPAY_BASE_URL')), '/');
        $apiKey = config('services.btcpay.api_key', env('BTCPAY_API_KEY'));

        $this->client = Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->timeout(30);
    }

    /**
     * Make a GET request to BTCPay API.
     */
    public function get(string $endpoint, array $query = []): array
    {
        return $this->request('GET', $endpoint, ['query' => $query]);
    }

    /**
     * Make a POST request to BTCPay API.
     */
    public function post(string $endpoint, array $data = []): array
    {
        return $this->request('POST', $endpoint, ['json' => $data]);
    }

    /**
     * Make a PUT request to BTCPay API.
     */
    public function put(string $endpoint, array $data = []): array
    {
        return $this->request('PUT', $endpoint, ['json' => $data]);
    }

    /**
     * Make a DELETE request to BTCPay API.
     */
    public function delete(string $endpoint): array
    {
        return $this->request('DELETE', $endpoint);
    }

    /**
     * Make a request with retry logic and error handling.
     */
    protected function request(string $method, string $endpoint, array $options = []): array
    {
        $attempt = 0;
        $backoff = $this->initialBackoff;

        while ($attempt <= $this->maxRetries) {
            try {
                $response = $this->performRequest($method, $endpoint, $options);

                if ($response->successful()) {
                    $data = $response->json();
                    $this->logRequest($method, $endpoint, $options, $response, $data);
                    return $data ?? [];
                }

                // Handle rate limiting
                if ($response->status() === 429) {
                    $retryAfter = (int) ($response->header('Retry-After') ?? $backoff);
                    $this->logRequest($method, $endpoint, $options, $response, null, "Rate limit exceeded, retry after {$retryAfter}s");

                    if ($attempt < $this->maxRetries) {
                        sleep($backoff);
                        $backoff = $this->exponentialBackoff($backoff);
                        $attempt++;
                        continue;
                    }

                    throw new BtcPayRateLimitException("Rate limit exceeded", $retryAfter);
                }

                // Handle other errors
                $this->handleErrorResponse($response, $method, $endpoint);

            } catch (BtcPayRateLimitException $e) {
                throw $e;
            } catch (BtcPayException $e) {
                throw $e;
            } catch (\Exception $e) {
                $this->logRequest($method, $endpoint, $options, null, null, "Exception: {$e->getMessage()}");
                
                if ($attempt < $this->maxRetries) {
                    sleep($backoff);
                    $backoff = $this->exponentialBackoff($backoff);
                    $attempt++;
                    continue;
                }

                throw new BtcPayException("Request failed after {$this->maxRetries} retries: {$e->getMessage()}", 0, $e);
            }
        }

        throw new BtcPayException("Request failed after {$this->maxRetries} retries");
    }

    /**
     * Perform the actual HTTP request.
     */
    protected function performRequest(string $method, string $endpoint, array $options): Response
    {
        $client = $this->client;

        if (isset($options['query'])) {
            $client = $client->withQueryParameters($options['query']);
        }

        if (isset($options['json'])) {
            $client = $client->withBody(json_encode($options['json']), 'application/json');
        }

        return $client->send($method, $endpoint);
    }

    /**
     * Handle error response and throw appropriate exception.
     */
    protected function handleErrorResponse(Response $response, string $method, string $endpoint): void
    {
        $statusCode = $response->status();
        $body = $response->body();
        $json = $response->json();

        $message = $json['message'] ?? $json['error'] ?? "HTTP {$statusCode}";
        if (is_array($message)) {
            $message = json_encode($message);
        }

        $this->logRequest($method, $endpoint, [], $response, $json, "Error: {$message}");

        throw new BtcPayException($message, $statusCode);
    }

    /**
     * Calculate exponential backoff delay.
     */
    protected function exponentialBackoff(int $currentBackoff): int
    {
        return min($currentBackoff * 2, 60); // Max 60 seconds
    }

    /**
     * Log request/response (sanitized, no secrets).
     */
    protected function logRequest(string $method, string $endpoint, array $options, ?Response $response, ?array $data, ?string $additional = null): void
    {
        $logData = [
            'method' => $method,
            'endpoint' => $endpoint,
        ];

        if (isset($options['query'])) {
            $logData['query'] = $options['query'];
        }

        if (isset($options['json'])) {
            $logData['body'] = $this->sanitizeData($options['json']);
        }

        if ($response) {
            $logData['status'] = $response->status();
        }

        if ($data !== null) {
            $logData['response'] = $this->sanitizeData($data);
        }

        if ($additional) {
            $logData['note'] = $additional;
        }

        Log::channel('btcpay')->info('BTCPay API Request', $logData);
    }

    /**
     * Sanitize data to remove secrets before logging.
     */
    protected function sanitizeData(array $data): array
    {
        $sanitized = $data;
        $secretKeys = ['apiKey', 'api_key', 'secret', 'password', 'token', 'webhookSecret'];

        foreach ($sanitized as $key => $value) {
            if (in_array(strtolower($key), array_map('strtolower', $secretKeys))) {
                $sanitized[$key] = '***REDACTED***';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeData($value);
            }
        }

        return $sanitized;
    }
}

