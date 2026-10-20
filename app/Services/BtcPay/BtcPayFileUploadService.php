<?php

namespace App\Services\BtcPay;

use App\Models\Store;
use App\Services\BtcPay\Exceptions\BtcPayException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Uploads files via Greenfield POST /api/v1/files so blobs live on BTCPay Server
 * (same durability as store logos / ticket images - survives Satflux redeploys).
 */
class BtcPayFileUploadService
{
    public function __construct(
        protected BtcPayClient $btcPayClient
    ) {}

    /**
     * @return array{id: string, storage_name: string, url: string, image_url: string}
     */
    public function uploadForStore(UploadedFile $file, Store $store): array
    {
        // The server-key fallback crosses the merchant API permission boundary.
        // Keep authorization inside this service as well as at its HTTP routes,
        // so a future caller cannot upload against another tenant's Store.
        $actor = auth()->user();
        if (! $actor || (bool) ($actor->is_guest ?? false) || (string) $store->user_id !== (string) $actor->id) {
            throw new AuthorizationException('You are not allowed to upload files for this store.');
        }
        if (! $file->isValid()
            || ! in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)
            || $file->getSize() > 5 * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => 'Only JPEG, PNG, GIF, or WebP images up to 5 MB can be uploaded.']);
        }

        $result = $this->postMultipartWithPermissionFallback($file, $store);

        $id = $result['id'] ?? null;
        $url = $result['url'] ?? null;
        $storageName = $result['storageName'] ?? $result['storage_name'] ?? null;

        if (empty($id)) {
            throw new \RuntimeException('Upload succeeded but no file id returned from BTCPay');
        }

        if (empty($storageName)) {
            $originalName = $file->getClientOriginalName();
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION) ?: 'jpg');
            $baseName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'image';
            $storageName = $id.'-'.$baseName.'.'.$ext;
        }

        $baseUrl = rtrim((string) config('services.btcpay.base_url', ''), '/');
        $displayUrl = $url;
        if (empty($displayUrl) || (is_string($displayUrl) && str_starts_with($displayUrl, 'fileid:'))) {
            $displayUrl = $baseUrl.'/LocalStorage/'.$storageName;
        }

        return [
            'id' => $id,
            'storage_name' => $storageName,
            'url' => $displayUrl,
            'image_url' => $displayUrl,
        ];
    }

    /**
     * Call POST /api/v1/files. Try user API key first; on 403 fall back to server key.
     */
    protected function postMultipartWithPermissionFallback(UploadedFile $file, Store $store): array
    {
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();
        $clientWithUserKey = new BtcPayClient($userApiKey);

        try {
            return $clientWithUserKey->postMultipart('/api/v1/files', $file);
        } catch (BtcPayException $e) {
            $isPermissionDenied = $e->getStatusCode() === 403
                || stripos($e->getMessage(), 'Insufficient API Permissions') !== false
                || stripos($e->getMessage(), 'canmodifyserversettings') !== false;
            if (! $isPermissionDenied) {
                throw $e;
            }
            Log::info('BTCPay Files: user API key lacks permission, using server key', [
                'store_id' => $store->id,
            ]);

            return $this->btcPayClient->postMultipart('/api/v1/files', $file);
        }
    }
}
