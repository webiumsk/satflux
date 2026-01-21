<?php

namespace App\Services\BtcPay;

use App\Services\BtcPay\Exceptions\BtcPayException;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    protected BtcPayClient $client;

    public function __construct(BtcPayClient $client)
    {
        $this->client = $client;
    }

    /**
     * Create a plan checkout in BTCPay.
     * 
     * @param string $storeId BTCPay store ID
     * @param string $offeringId BTCPay offering ID
     * @param string $planId BTCPay plan ID
     * @param array $options Optional checkout options:
     *   - successRedirectUrl (string): URL to redirect after successful checkout
     *   - cancelRedirectUrl (string|null): URL to redirect if checkout cancelled
     *   - newSubscriberEmail (string|null): Email for new subscriber (creates new subscriber)
     *   - customerSelector (string|null): Email to find existing customer (customer must exist)
     * @return array Checkout data with checkoutUrl, checkoutId, and optional expiresAt
     * @throws BtcPayException
     */
    public function createPlanCheckout(
        string $storeId,
        string $offeringId,
        string $planId,
        array $options = []
    ): array {
        // Validate that plan and offering belong to the store
        // This is important for security - we don't want users to subscribe to plans from other stores
        // However, if API key lacks permissions, we'll skip validation and trust the config values
        try {
            // Verify offering belongs to store
            $offering = $this->client->get("/api/v1/stores/{$storeId}/offerings/{$offeringId}");
            if (!isset($offering['id']) || $offering['id'] !== $offeringId) {
                throw new BtcPayException("Offering not found or does not belong to this store", 404);
            }

            // Verify plan belongs to offering
            $plan = $this->client->get("/api/v1/stores/{$storeId}/offerings/{$offeringId}/plans/{$planId}");
            if (!isset($plan['id']) || $plan['id'] !== $planId) {
                throw new BtcPayException("Plan not found or does not belong to this offering", 404);
            }
        } catch (BtcPayException $e) {
            // If error is due to insufficient permissions (403), log warning but continue
            // We trust the config values - they should be set correctly by admin
            if ($e->getStatusCode() === 403) {
                Log::warning('Cannot validate plan/offering ownership - insufficient API permissions', [
                    'store_id' => $storeId,
                    'offering_id' => $offeringId,
                    'plan_id' => $planId,
                    'error' => $e->getMessage(),
                    'note' => 'Proceeding with checkout - trusting config values. Consider adding "btcpay.store.canviewofferings" permission to API key.',
                ]);
                // Continue with checkout - we trust the config values
            } elseif ($e->getStatusCode() === 404) {
                // 404 means plan/offering doesn't exist - this is a real error
                Log::error('Failed to validate plan/offering ownership', [
                    'store_id' => $storeId,
                    'offering_id' => $offeringId,
                    'plan_id' => $planId,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            } else {
                // Other errors - log and throw
                Log::error('Failed to validate plan/offering ownership', [
                    'store_id' => $storeId,
                    'offering_id' => $offeringId,
                    'plan_id' => $planId,
                    'error' => $e->getMessage(),
                    'status_code' => $e->getStatusCode(),
                ]);
                throw $e;
            }
        }

        // Build checkout payload
        $payload = [
            'storeId' => $storeId,
            'offeringId' => $offeringId,
            'planId' => $planId,
        ];

        // Add success redirect URL (required)
        if (isset($options['successRedirectUrl'])) {
            $payload['successRedirectUrl'] = $options['successRedirectUrl'];
        } else {
            // Fallback to default from config
            $baseUrl = config('app.url');
            $payload['successRedirectUrl'] = config('services.btcpay.subscription_success_url', "{$baseUrl}/billing/success");
        }

        // Add cancel redirect URL if provided
        if (isset($options['cancelRedirectUrl'])) {
            $payload['cancelRedirectUrl'] = $options['cancelRedirectUrl'];
        } elseif (config('services.btcpay.subscription_cancel_url')) {
            $payload['cancelRedirectUrl'] = config('services.btcpay.subscription_cancel_url');
        }

        // Handle subscriber email - use newSubscriberEmail if provided, otherwise skip
        // Do NOT use customerSelector unless we're certain the customer exists
        if (isset($options['newSubscriberEmail']) && !empty($options['newSubscriberEmail'])) {
            $payload['newSubscriberEmail'] = $options['newSubscriberEmail'];
        }

        try {
            Log::info('Creating plan checkout', [
                'store_id' => $storeId,
                'offering_id' => $offeringId,
                'plan_id' => $planId,
                'has_email' => isset($payload['newSubscriberEmail']),
            ]);

            $response = $this->client->post('/api/v1/plan-checkout', $payload);

            // Extract checkout URL and ID from response
            // BTCPay returns: { id: "...", url: "...", expiration?: timestamp }
            // Note: BTCPay uses 'id' and 'url' fields, not 'checkoutId' and 'checkoutUrl'
            $checkoutId = $response['id'] ?? $response['checkoutId'] ?? null;
            $checkoutUrl = $response['url'] ?? $response['checkoutUrl'] ?? null;

            if (!$checkoutId || !$checkoutUrl) {
                Log::error('Invalid checkout response from BTCPay', [
                    'response' => $response,
                ]);
                throw new BtcPayException('Invalid response from BTCPay: missing id/checkoutId or url/checkoutUrl', 500);
            }

            $result = [
                'checkoutId' => $checkoutId,
                'checkoutUrl' => $checkoutUrl,
            ];

            // Handle expiration - BTCPay may return 'expiration' as Unix timestamp
            if (isset($response['expiration'])) {
                $result['expiresAt'] = is_numeric($response['expiration'])
                    ? date('c', $response['expiration']) // Convert Unix timestamp to ISO 8601
                    : $response['expiration'];
            } elseif (isset($response['expiresAt'])) {
                $result['expiresAt'] = $response['expiresAt'];
            }

            Log::info('Plan checkout created successfully', [
                'checkout_id' => $checkoutId,
                'store_id' => $storeId,
                'plan_id' => $planId,
            ]);

            return $result;
        } catch (BtcPayException $e) {
            Log::error('Failed to create plan checkout', [
                'store_id' => $storeId,
                'offering_id' => $offeringId,
                'plan_id' => $planId,
                'error' => $e->getMessage(),
                'status_code' => $e->getStatusCode(),
            ]);
            throw $e;
        }
    }

    /**
     * Get plan checkout details by checkout ID.
     * 
     * @param string $checkoutId BTCPay plan checkout ID
     * @return array Checkout details including plan ID, subscription info, etc.
     * @throws BtcPayException
     */
    public function getPlanCheckout(string $checkoutId): array
    {
        try {
            $response = $this->client->get("/api/v1/plan-checkout/{$checkoutId}");
            return $response;
        } catch (BtcPayException $e) {
            Log::error('Failed to get plan checkout details', [
                'checkout_id' => $checkoutId,
                'error' => $e->getMessage(),
                'status_code' => $e->getStatusCode(),
            ]);
            throw $e;
        }
    }

    /**
     * Get subscription details by subscription ID.
     * 
     * @param string $storeId BTCPay store ID
     * @param string $subscriptionId BTCPay subscription ID
     * @return array Subscription details including status, expiration, etc.
     * @throws BtcPayException
     */
    public function getSubscription(string $storeId, string $subscriptionId): array
    {
        try {
            $response = $this->client->get("/api/v1/stores/{$storeId}/subscriptions/{$subscriptionId}");
            return $response;
        } catch (BtcPayException $e) {
            Log::error('Failed to get subscription details', [
                'store_id' => $storeId,
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
                'status_code' => $e->getStatusCode(),
            ]);
            throw $e;
        }
    }

    /**
     * List subscriptions for a store.
     * 
     * @param string $storeId BTCPay store ID
     * @param array $filters Optional filters (status, etc.)
     * @return array List of subscriptions
     * @throws BtcPayException
     */
    public function listSubscriptions(string $storeId, array $filters = []): array
    {
        try {
            $response = $this->client->get("/api/v1/stores/{$storeId}/subscriptions", $filters);
            return $response;
        } catch (BtcPayException $e) {
            Log::error('Failed to list subscriptions', [
                'store_id' => $storeId,
                'error' => $e->getMessage(),
                'status_code' => $e->getStatusCode(),
            ]);
            throw $e;
        }
    }
}

