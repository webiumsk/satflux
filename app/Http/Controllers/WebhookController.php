<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessBtcPayWebhook;
use App\Models\Store;
use App\Models\WebhookEvent;
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

        $secret = $store?->webhook_secret ?? config('services.btcpay.webhook_secret');
        $verified = false;

        if ($secret) {
            $signature = $request->header('BTCPay-Sig');
            if (!$signature) {
                Log::warning('BTCPay webhook received without signature header', [
                    'ip' => $request->ip(),
                ]);
                return response()->json(['error' => 'Missing signature'], 401);
            }

            $signature = preg_replace('/^sha256=/i', '', trim($signature));
            $rawPayload = $request->getContent();
            $expectedSignature = hash_hmac('sha256', $rawPayload, $secret);

            if (!hash_equals($expectedSignature, $signature)) {
                Log::warning('BTCPay webhook signature verification failed', [
                    'ip' => $request->ip(),
                ]);
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            $verified = true;
        } else {
            Log::warning('BTCPay webhook received without verification (no store webhook_secret and BTCPAY_WEBHOOK_SECRET not set)', [
                'ip' => $request->ip(),
            ]);
        }

        $eventType = $payload['type'] ?? $payload['eventType'] ?? 'unknown';

        // Store webhook event
        $webhookEvent = WebhookEvent::create([
            'store_id' => $store?->id,
            'event_type' => $eventType,
            'payload' => $payload,
            'verified' => $verified,
        ]);

        // Dispatch job to process webhook (skeleton - no business logic yet)
        ProcessBtcPayWebhook::dispatch($webhookEvent);

        return response()->json(['status' => 'received']);
    }
}







