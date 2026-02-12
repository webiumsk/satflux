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
     *
     * Verification: per-store secret first (from stores created with programmatic webhook),
     * then global BTCPAY_WEBHOOK_SECRET. If neither is set, webhook is accepted but marked unverified.
     *
     * BTCPay sends the signature in the BTCPay-Sig header as: sha256=HMAC_HEX
     */
    public function handle(Request $request)
    {
        $payload = $request->all();
        $eventType = $payload['type'] ?? $payload['eventType'] ?? 'unknown';
        $storeId = $payload['storeId'] ?? null;

        // Find local store by BTCPay store ID (needed for per-store secret and for linking event)
        $store = null;
        if ($storeId) {
            $store = Store::where('btcpay_store_id', $storeId)->first();
        }

        // Determine which secret to use: per-store first, then global
        $secret = null;
        if ($store && $store->webhook_secret) {
            $secret = $store->webhook_secret;
        }
        if (!$secret) {
            $secret = config('services.btcpay.webhook_secret') ?: null;
        }

        $verified = false;
        if ($secret) {
            $signature = $request->header('BTCPay-Sig');
            if (!$signature) {
                Log::warning('BTCPay webhook received without signature header', [
                    'ip' => $request->ip(),
                    'storeId' => $storeId,
                ]);
                return response()->json(['error' => 'Missing signature'], 401);
            }

            $rawPayload = $request->getContent();
            $expectedHmac = hash_hmac('sha256', $rawPayload, $secret);

            $signatureHmac = $signature;
            if (str_starts_with($signature, 'sha256=')) {
                $signatureHmac = substr($signature, 7);
            }

            if (!hash_equals($expectedHmac, $signatureHmac)) {
                Log::warning('BTCPay webhook signature verification failed', [
                    'ip' => $request->ip(),
                    'storeId' => $storeId,
                ]);
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            $verified = true;
        } else {
            Log::warning('BTCPay webhook received without verification (no per-store or global secret)', [
                'ip' => $request->ip(),
                'storeId' => $storeId,
            ]);
        }

        $webhookEvent = WebhookEvent::create([
            'store_id' => $store?->id,
            'event_type' => $eventType,
            'payload' => $payload,
            'verified' => $verified,
        ]);

        ProcessBtcPayWebhook::dispatch($webhookEvent);

        return response()->json(['status' => 'received']);
    }
}







