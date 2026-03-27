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
    protected string $apiKey;
    protected int $maxRetries = 3;
    protected int $initialBackoff = 1; // seconds

    public function __construct(?string $apiKey = null)
    {
        $this->baseUrl = rtrim(config('services.btcpay.base_url', env('BTCPAY_BASE_URL')), '/');
        $this->apiKey = $apiKey ?? config('services.btcpay.api_key', env('BTCPAY_API_KEY'));

        $this->initializeClient();
    }

    /**
     * Initialize the HTTP client with current API key.
     */
    protected function initializeClient(): void
    {
        $this->client = Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->timeout(30);
    }

    /**
     * Get the current API key.
     */
    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    /**
     * Set a different API key for this client instance.
     * Useful for using user-level API keys instead of server-level.
     */
    public function setApiKey(string $apiKey): void
    {
        $this->apiKey = $apiKey;
        $this->initializeClient();
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
     *
     * @param  array<string, string|int|bool>  $query  Optional query parameters
     */
    public function put(string $endpoint, array $data = [], array $query = []): array
    {
        $options = ['json' => $data];
        if ($query !== []) {
            $options['query'] = $query;
        }

        return $this->request('PUT', $endpoint, $options);
    }

    /**
     * Make a PATCH request to BTCPay API.
     */
    public function patch(string $endpoint, array $data = []): array
    {
        return $this->request('PATCH', $endpoint, ['json' => $data]);
    }

    /**
     * Make a DELETE request to BTCPay API.
     *
     * @param  array<string, string|int|bool>  $query  Optional query parameters
     */
    public function delete(string $endpoint, array $query = []): array
    {
        $options = $query !== [] ? ['query' => $query] : [];

        return $this->request('DELETE', $endpoint, $options);
    }

    /**
     * Make a POST request with multipart form data (for file uploads).
     * 
     * @param string $endpoint API endpoint
     * @param \Illuminate\Http\UploadedFile $file The uploaded file
     * @return array Response data
     */
    public function postMultipart(string $endpoint, $file): array
    {
        $attempt = 0;
        $backoff = $this->initialBackoff;

        while ($attempt <= $this->maxRetries) {
            try {
                // Create a new client without Content-Type header for multipart
                $client = Http::baseUrl($this->baseUrl)
                    ->withHeaders([
                        'Authorization' => "Bearer {$this->apiKey}",
                        'Accept' => 'application/json',
                        // Don't set Content-Type - Laravel will set it automatically for multipart
                    ])
                    ->timeout(30);

                // Use safe filename derived from MIME type + uniqid to avoid malicious client names (.php, path traversal, etc.)
                $safeFilename = $this->getSafeFilenameFromUploadedFile($file);
                $fileContents = $file->get();
                $response = $client->attach('file', $fileContents, $safeFilename)
                    ->post($endpoint);

                if ($response->successful()) {
                    $responseData = $response->json();
                    $this->logRequest('POST', $endpoint, ['multipart' => true], $response, $responseData);
                    return $responseData ?? [];
                }

                // Handle rate limiting
                if ($response->status() === 429) {
                    $retryAfter = (int) ($response->header('Retry-After') ?? $backoff);
                    $this->logRequest('POST', $endpoint, ['multipart' => true], $response, null, "Rate limit exceeded, retry after {$retryAfter}s");

                    if ($attempt < $this->maxRetries) {
                        sleep($backoff);
                        $backoff = $this->exponentialBackoff($backoff);
                        $attempt++;
                        continue;
                    }

                    throw new BtcPayRateLimitException("Rate limit exceeded", $retryAfter);
                }

                // Handle other errors
                $this->handleErrorResponse($response, 'POST', $endpoint);

            } catch (BtcPayRateLimitException $e) {
                throw $e;
            } catch (BtcPayException $e) {
                throw $e;
            } catch (\Exception $e) {
                $this->logRequest('POST', $endpoint, ['multipart' => true], null, null, "Exception: {$e->getMessage()}");
                
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
                    // For DELETE requests, BTCPay may return 204 No Content (empty body)
                    // or 200 OK with empty/null body
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

        // Include more details for 422 validation errors
        if ($statusCode === 422) {
            if (isset($json['errors'])) {
                $message .= ' - Validation errors: ' . json_encode($json['errors']);
            }
            // Also include the full response body for debugging
            Log::error('BTCPay API 422 Validation Error', [
                'endpoint' => $endpoint,
                'method' => $method,
                'status_code' => $statusCode,
                'response_body' => $json,
                'errors' => $json['errors'] ?? null,
            ]);
        }

        $this->logRequest($method, $endpoint, [], $response, $json, "Error: {$message}");

        throw new BtcPayException($message, $statusCode);
    }

    /**
     * Derive a safe filename from the uploaded file (MIME type + uniqid).
     * Never use getClientOriginalName() for the sent filename to avoid malicious names.
     */
    protected function getSafeFilenameFromUploadedFile($file): string
    {
        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
        ];
        $mime = $file->getMimeType();
        $ext = $mimeToExt[$mime] ?? 'bin';
        return 'upload_' . uniqid('', true) . '.' . $ext;
    }

    /**
     * GET non-JSON body (e.g. SamRock QR image). Uses current API key Bearer auth.
     */
    public function getBinary(string $endpoint, string $accept = 'image/png'): string
    {
        $attempt = 0;
        $backoff = $this->initialBackoff;

        while ($attempt <= $this->maxRetries) {
            try {
                $response = Http::baseUrl($this->baseUrl)
                    ->withHeaders([
                        'Authorization' => "Bearer {$this->apiKey}",
                        'Accept' => $accept,
                    ])
                    ->timeout(30)
                    ->get($endpoint);

                if ($response->successful()) {
                    $this->logRequest('GET', $endpoint, ['accept' => $accept], $response, null, 'binary response');

                    return $response->body();
                }

                if ($response->status() === 429) {
                    $retryAfter = (int) ($response->header('Retry-After') ?? $backoff);
                    $this->logRequest('GET', $endpoint, ['accept' => $accept], $response, null, "Rate limit exceeded, retry after {$retryAfter}s");

                    if ($attempt < $this->maxRetries) {
                        sleep($backoff);
                        $backoff = $this->exponentialBackoff($backoff);
                        $attempt++;
                        continue;
                    }

                    throw new BtcPayRateLimitException('Rate limit exceeded', $retryAfter);
                }

                $this->handleErrorResponse($response, 'GET', $endpoint);
            } catch (BtcPayRateLimitException $e) {
                throw $e;
            } catch (BtcPayException $e) {
                throw $e;
            } catch (\Exception $e) {
                $this->logRequest('GET', $endpoint, ['accept' => $accept], null, null, "Exception: {$e->getMessage()}");

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

        try {
            Log::channel('btcpay')->info('BTCPay API Request', $logData);
        } catch (\Exception $e) {
            // Silently fail logging in tests to avoid permission errors
            if (!app()->environment('testing')) {
                throw $e;
            }
        }
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






