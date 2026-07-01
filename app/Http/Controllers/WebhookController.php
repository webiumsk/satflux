<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessBtcPayWebhook;
use App\Models\Store;
use App\Models\WebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle BTCPay Server webhook.
     * Verifies signature using the store's webhook_secret (when set) or config fallback.
     */
    public function handle(Request $request)
    {
        $payload = $request->all();
        $storeId = $payload['storeId'] ?? null;

        $store = $storeId
            ? Store::where('btcpay_store_id', $storeId)->first()
            : null;

        $secret = $this->resolveWebhookSecret($storeId, $store);
        $verified = false;

        if ($secret) {
            $signature = $request->header('BTCPay-Sig');
            if (! $signature) {
                Log::warning('BTCPay webhook received without signature header', [
                    'ip' => $request->ip(),
                ]);

                return response()->json(['error' => 'Missing signature'], 401);
            }

            $signature = preg_replace('/^sha256=/i', '', trim($signature));
            $rawPayload = $request->getContent();
            $expectedSignature = hash_hmac('sha256', $rawPayload, $secret);

            if (! hash_equals($expectedSignature, $signature)) {
                Log::warning('BTCPay webhook signature verification failed', [
                    'ip' => $request->ip(),
                ]);

                return response()->json(['error' => 'Invalid signature'], 401);
            }

            $verified = true;
        } else {
            Log::error('BTCPay webhook rejected: no webhook secret configured', [
                'ip' => $request->ip(),
                'store_id' => $store?->id,
            ]);

            return response()->json(['error' => 'Webhook secret not configured'], 401);
        }

        $eventType = $payload['type'] ?? $payload['eventType'] ?? 'unknown';

        // Replay protection: BTCPay sends a unique deliveryId per delivery
        // (redeliveries get a new one). A replayed payload reuses it, so
        // reject duplicates. The unique index backstops concurrent replays.
        $deliveryId = is_string($payload['deliveryId'] ?? null) && $payload['deliveryId'] !== ''
            ? $payload['deliveryId']
            : null;

        try {
            $webhookEvent = WebhookEvent::create([
                'store_id' => $store?->id,
                'event_type' => $eventType,
                'payload' => $payload,
                'verified' => $verified,
                'delivery_id' => $deliveryId,
            ]);
        } catch (UniqueConstraintViolationException) {
            Log::warning('BTCPay webhook replay rejected (duplicate deliveryId)', [
                'ip' => $request->ip(),
                'store_id' => $store?->id,
                'delivery_id' => $deliveryId,
            ]);

            return response()->json(['status' => 'duplicate']);
        }

        // Dispatch job to process webhook (skeleton - no business logic yet)
        ProcessBtcPayWebhook::dispatch($webhookEvent);

        return response()->json(['status' => 'received']);
    }

    /**
     * Resolve HMAC secret for BTCPay webhook signature verification.
     */
    protected function resolveWebhookSecret(?string $btcpayStoreId, ?Store $store): ?string
    {
        $subscriptionStoreId = config('services.btcpay.subscription_store_id');

        if (
            $btcpayStoreId
            && $subscriptionStoreId
            && $btcpayStoreId === $subscriptionStoreId
        ) {
            return config('services.btcpay.subscription_webhook_secret')
                ?: config('services.btcpay.webhook_secret');
        }

        return $store?->webhook_secret ?? config('services.btcpay.webhook_secret');
    }
}
