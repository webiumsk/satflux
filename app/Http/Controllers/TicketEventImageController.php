<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\BtcPay\BtcPayClient;
use App\Services\BtcPay\Exceptions\BtcPayException;
use App\Services\BtcPay\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TicketEventImageController extends Controller
{
    public function __construct(
        protected BtcPayClient $btcPayClient,
        protected TicketService $ticketService
    ) {}

    /**
     * Upload an image for a ticket event (logo/banner).
     * Uses BTCPay Server core Files API: POST /api/v1/files.
     * Tries store owner's API key first (so file is in user context and plugin can resolve URL).
     * On 403 Insufficient API Permissions (endpoint requires btcpay.server.canmodifyserversettings),
     * falls back to server API key so upload still works.
     */
    public function upload(Request $request, Store $store)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,gif,webp|max:2048', // 2MB
        ]);

        $user = $request->user();
        if ($store->user_id !== $user->id) {
            return response()->json(['message' => __('messages.unauthorized')], 403);
        }

        try {
            $result = $this->uploadToBtcPayFiles($request->file('image'), $store);

            $id = $result['id'] ?? null;
            $url = $result['url'] ?? null;
            $storageName = $result['storageName'] ?? $result['storage_name'] ?? null;

            if (empty($id)) {
                return response()->json(['message' => 'Upload succeeded but no file id returned'], 500);
            }

            // Plugin uses /LocalStorage/{uuid}-{name}.{ext} – build storageName if API didn't return it
            if (empty($storageName)) {
                $originalName = $request->file('image')->getClientOriginalName();
                $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION) ?: 'jpg');
                $baseName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'image';
                $storageName = $id . '-' . $baseName . '.' . $ext;
            }

            $baseUrl = rtrim(config('services.btcpay.base_url', ''), '/');
            // Plugin expects eventLogoFileId = file id (UUID) from Greenfield API
            $displayUrl = $url;
            if (empty($displayUrl) || (is_string($displayUrl) && str_starts_with($displayUrl, 'fileid:'))) {
                $displayUrl = $baseUrl . '/LocalStorage/' . $storageName;
            }

            Log::info('Ticket event image uploaded (BTCPay Files API)', [
                'store_id' => $store->id,
                'user_id' => $user->id,
                'file_id' => $id,
                'storage_name' => $storageName,
                'btcpay_response_keys' => array_keys($result),
            ]);

            return response()->json([
                'message' => 'Image uploaded successfully',
                'data' => [
                    'id' => $id,
                    'eventLogoFileId' => $id,
                    'url' => $displayUrl,
                    'image_url' => $displayUrl,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to upload ticket event image', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Failed to upload image: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Upload event logo to an existing event (plugin endpoint, store-level permission).
     * POST /api/v1/stores/{storeId}/satoshi-tickets/events/{eventId}/logo
     */
    public function uploadLogo(Request $request, Store $store, string $eventId)
    {
        $request->validate([
            'file' => ['required', 'image', 'mimes:jpeg,png,gif,webp', 'max:2048'],
        ]);

        if ($store->user_id !== $request->user()?->id) {
            return response()->json(['message' => __('messages.unauthorized')], 403);
        }

        try {
            $userApiKey = $store->user->getBtcPayApiKeyOrFail();
            $event = $this->ticketService->uploadEventLogo(
                $store->btcpay_store_id,
                $eventId,
                $request->file('file'),
                $userApiKey
            );
            Log::info('Ticket event logo uploaded (plugin logo endpoint)', [
                'store_id' => $store->id,
                'event_id' => $eventId,
            ]);
            return response()->json(['data' => $this->normalizeEventLogo($event)]);
        } catch (\Exception $e) {
            Log::error('Failed to upload ticket event logo', [
                'store_id' => $store->id,
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Failed to upload logo: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete event logo (plugin endpoint).
     * DELETE /api/v1/stores/{storeId}/satoshi-tickets/events/{eventId}/logo
     */
    public function deleteLogo(Request $request, Store $store, string $eventId)
    {
        if ($store->user_id !== $request->user()?->id) {
            return response()->json(['message' => __('messages.unauthorized')], 403);
        }

        try {
            $userApiKey = $store->user->getBtcPayApiKeyOrFail();
            $event = $this->ticketService->deleteEventLogo(
                $store->btcpay_store_id,
                $eventId,
                $userApiKey
            );
            return response()->json(['data' => $this->normalizeEventLogo($event)]);
        } catch (\Exception $e) {
            Log::error('Failed to delete ticket event logo', [
                'store_id' => $store->id,
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Failed to delete logo: ' . $e->getMessage()], 500);
        }
    }

    protected function normalizeEventLogo(array $event): array
    {
        if (! isset($event['eventLogoUrl']) && ! empty($event['logoUrl'])) {
            $event['eventLogoUrl'] = $event['logoUrl'];
        }
        $event['eventLogoFileId'] = $event['eventLogoFileId'] ?? '';
        $fileId = trim((string) ($event['eventLogoFileId'] ?? ''));
        if (($event['eventLogoUrl'] ?? '') === '' && $fileId !== '') {
            $baseUrl = rtrim(config('services.btcpay.base_url', ''), '/');
            $event['eventLogoUrl'] = $baseUrl . '/LocalStorage/' . $fileId;
        }
        return $event;
    }

    /**
     * Call POST /api/v1/files. Try user API key first; on 403 (insufficient permissions) use server key.
     */
    protected function uploadToBtcPayFiles($file, Store $store): array
    {
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();
        $clientWithUserKey = new BtcPayClient($userApiKey);

        try {
            return $clientWithUserKey->postMultipart('/api/v1/files', $file);
        } catch (BtcPayException $e) {
            $isPermissionDenied = $e->getStatusCode() === 403
                || stripos($e->getMessage(), 'Insufficient API Permissions') !== false
                || stripos($e->getMessage(), 'canmodifyserversettings') !== false;
            if (!$isPermissionDenied) {
                throw $e;
            }
            Log::info('Ticket event image: user API key lacks Files permission, using server key', [
                'store_id' => $store->id,
            ]);
            return $this->btcPayClient->postMultipart('/api/v1/files', $file);
        }
    }
}
