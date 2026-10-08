<?php

namespace App\Services\Integrations;

use App\Models\BusinessDocument;
use App\Models\StoreIntegration;
use App\Support\Http\OutboundUrlGuard;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WooCommerceWebhookNotifier
{
    public function notifyDocumentPaid(BusinessDocument $document): void
    {
        if (! preg_match('/woocommerce_order_id=(\d+)/', (string) $document->internal_note, $matches)) {
            return;
        }

        $wcOrderId = (int) $matches[1];
        if ($wcOrderId <= 0 || ! $document->store_id) {
            return;
        }

        $integration = StoreIntegration::query()
            ->where('store_id', $document->store_id)
            ->where('platform', 'woocommerce')
            ->where('is_active', true)
            ->first();

        if (! $integration || ! $integration->webhook_url || ! $integration->hasCurrentCompanyLink()
            || $integration->company_id !== $document->company_id) {
            return;
        }

        $secret = $integration->integration_secret;
        $body = json_encode([
            'event' => 'document.paid',
            'document_id' => $document->id,
            'document_number' => $document->number,
            'woocommerce_order_id' => $wcOrderId,
            'paid_at' => $document->paid_at?->toIso8601String(),
        ]);

        if ($body === false) {
            return;
        }

        $signature = hash_hmac('sha256', $body, $secret);

        $options = app(OutboundUrlGuard::class)->pinnedOptions($integration->webhook_url);
        if ($options === null) {
            Log::warning('WooCommerce integration webhook refused: URL is not a public https endpoint', [
                'store_id' => $document->store_id,
                'webhook_host' => parse_url($integration->webhook_url, PHP_URL_HOST),
            ]);

            return;
        }

        try {
            $response = Http::timeout(10)
                ->withOptions($options)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Satflux-Signature' => $signature,
                ])
                ->withBody($body, 'application/json')
                ->post($integration->webhook_url);

            if (! $response->successful()) {
                Log::warning('WooCommerce integration webhook failed', [
                    'store_id' => $document->store_id,
                    'status' => $response->status(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('WooCommerce integration webhook error', [
                'store_id' => $document->store_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
