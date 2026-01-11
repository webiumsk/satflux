<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessBtcPayWebhook;
use App\Models\WebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle BTCPay Server webhook.
     */
    public function handle(Request $request)
    {
        $secret = config('services.btcpay.webhook_secret');
        $verified = false;

        if ($secret) {
            // Verify webhook signature if secret is configured
            $signature = $request->header('BTCPay-Sig');
            if (!$signature) {
                Log::warning('BTCPay webhook received without signature header', [
                    'ip' => $request->ip(),
                ]);
                return response()->json(['error' => 'Missing signature'], 401);
            }

            // Verify signature (simplified - actual implementation depends on BTCPay signature format)
            $payload = $request->getContent();
            $expectedSignature = hash_hmac('sha256', $payload, $secret);
            
            if (!hash_equals($expectedSignature, $signature)) {
                Log::warning('BTCPay webhook signature verification failed', [
                    'ip' => $request->ip(),
                ]);
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            $verified = true;
        } else {
            // Dev mode: allow but log warning
            Log::warning('BTCPay webhook received without verification (BTCPAY_WEBHOOK_SECRET not set)', [
                'ip' => $request->ip(),
            ]);
        }

        $payload = $request->all();
        $eventType = $payload['type'] ?? $payload['eventType'] ?? 'unknown';
        $storeId = $payload['storeId'] ?? null;

        // Find local store by BTCPay store ID
        $store = null;
        if ($storeId) {
            $store = \App\Models\Store::where('btcpay_store_id', $storeId)->first();
        }

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

