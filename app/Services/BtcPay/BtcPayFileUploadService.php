<?php

namespace App\Services\BtcPay;

use App\Models\Store;
use App\Services\BtcPay\Exceptions\BtcPayException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Uploads files via Greenfield POST /api/v1/files so blobs live on BTCPay Server
 * (same durability as store logos / ticket images — survives Satflux redeploys).
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
