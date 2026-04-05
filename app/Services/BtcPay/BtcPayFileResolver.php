<?php

namespace App\Services\BtcPay;

use Illuminate\Support\Facades\Log;

/**
 * Resolves display URLs for files stored in BTCPay (Greenfield Files API).
 */
class BtcPayFileResolver
{
    public function __construct(
        protected BtcPayClient $client
    ) {}

    /**
     * GET /api/v1/files/{fileId} and derive a public HTTP URL from url, storageName/storage_name, or uri.
     * Returns null on failure, empty metadata, or if no usable URL can be built.
     */
    public function resolveEventLogoUrl(string $fileId): ?string
    {
        $fileId = trim($fileId);
        if ($fileId === '') {
            return null;
        }

        $baseUrl = rtrim(config('services.btcpay.base_url', ''), '/');

        try {
            $file = $this->client->get('/api/v1/files/'.$fileId);
            Log::debug('Ticket event logo GET file response', ['file_id' => $fileId, 'keys' => array_keys($file)]);

            $url = $file['url'] ?? null;
            if (! empty($url) && ! str_starts_with((string) $url, 'fileid:')) {
                Log::debug('Ticket event logo URL resolved from file.url', ['eventLogoUrl' => $url]);

                return $url;
            }

            $storageName = $file['storageName'] ?? $file['storage_name'] ?? null;
            if (! empty($storageName)) {
                $resolved = $baseUrl.'/LocalStorage/'.$storageName;
                Log::debug('Ticket event logo URL built from storageName', ['eventLogoUrl' => $resolved]);

                return $resolved;
            }

            $uri = $file['uri'] ?? null;
            if (! empty($uri)) {
                $resolved = str_starts_with((string) $uri, 'http')
                    ? $uri
                    : $baseUrl.(str_starts_with((string) $uri, '/') ? '' : '/').$uri;
                Log::debug('Ticket event logo URL from file.uri', ['eventLogoUrl' => $resolved]);

                return $resolved;
            }
        } catch (\Throwable $e) {
            Log::warning('Could not resolve event logo URL from file id', ['file_id' => $fileId, 'error' => $e->getMessage()]);
        }

        return null;
    }
}
