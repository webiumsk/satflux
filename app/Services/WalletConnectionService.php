<?php

namespace App\Services;

use App\Events\WalletConnectionNeedsSupport;
use App\Models\AuditLog;
use App\Models\Store;
use App\Models\User;
use App\Models\WalletConnection;
use App\Notifications\SupportNeededNotification;
use App\Notifications\WalletConnectionChangedNotification;
use App\Notifications\WalletConnectionNeedsSupportMerchantNotification;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class WalletConnectionService
{
    public function __construct(
        protected WalletConnectionValidator $validator,
        protected \App\Services\BtcPay\CashuService $cashuService,
    ) {}

    /**
     * Create or update a wallet connection for a store.
     *
     * @param  string  $type  Connection type ('blink' or 'aqua_descriptor')
     * @param  string  $secret  Secret value (will be encrypted)
     * @param  User  $user  User submitting the connection
     * @param  string  $initialStatus  'pending' = bot will run first, no support emails yet; 'needs_support' = notify support immediately
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function createOrUpdate(Store $store, string $type, string $secret, User $user, string $initialStatus = 'needs_support'): WalletConnection
    {
        Log::info('WalletConnectionService::createOrUpdate called', [
            'store_id' => $store->id,
            'store_btcpay_store_id' => $store->btcpay_store_id ?? 'NULL',
            'type' => $type,
            'secret_length' => strlen($secret),
            'secret_preview' => substr($secret, 0, 50).'...',
            'user_id' => $user->id,
        ]);

        // Validate the secret
        Log::info('Validating wallet connection secret', [
            'store_id' => $store->id,
            'type' => $type,
        ]);

        $validation = $this->validator->validate($type, $secret);

        Log::info('Wallet connection validation result', [
            'store_id' => $store->id,
            'type' => $type,
            'valid' => $validation['valid'] ?? 'NOT_SET',
            'errors' => $validation['errors'] ?? [],
        ]);

        if (! $validation['valid']) {
            Log::error('Wallet connection validation failed', [
                'store_id' => $store->id,
                'type' => $type,
                'errors' => $validation['errors'] ?? [],
            ]);
            throw \Illuminate\Validation\ValidationException::withMessages([
                'secret' => $validation['errors'],
            ]);
        }

        // For Aqua/Boltz descriptors, check if this descriptor is already used in another store
        // BTCPay limitation: each descriptor can only be used once
        if ($type === 'aqua_descriptor') {
            $duplicateCheck = $this->checkDescriptorDuplicate($secret, $store->id);
            if ($duplicateCheck['exists']) {
                Log::warning('Aqua descriptor already in use', [
                    'store_id' => $store->id,
                    'existing_store_id' => $duplicateCheck['existing_store_id'],
                    'existing_store_name' => $duplicateCheck['existing_store_name'],
                ]);
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'secret' => [
                        'This descriptor is already in use by another store. '.
                        'BTCPay allows each descriptor to be used only once. '.
                        ($duplicateCheck['existing_store_name']
                            ? "It is currently used by store: {$duplicateCheck['existing_store_name']}"
                            : 'Please use a different wallet/descriptor.'),
                    ],
                ]);
            }
        }

        // Check if this is a new connection or update
        $existingConnection = WalletConnection::where('store_id', $store->id)->first();
        $isNew = $existingConnection === null;
        $wasConnected = $existingConnection && $existingConnection->status === 'connected';

        Log::info('Checking for existing wallet connection', [
            'store_id' => $store->id,
            'is_new' => $isNew,
            'existing_connection_id' => $existingConnection->id ?? 'NULL',
            'was_connected' => $wasConnected,
        ]);

        // Create or update wallet connection
        Log::info('Creating/updating wallet connection in database', [
            'store_id' => $store->id,
            'type' => $type,
            'is_new' => $isNew,
        ]);

        try {
            $connection = WalletConnection::updateOrCreate(
                ['store_id' => $store->id],
                [
                    'type' => $type,
                    'configuration_source' => null,
                    'encrypted_secret' => Crypt::encryptString($secret),
                    'status' => $initialStatus,
                    'reconfig' => $wasConnected,
                    'bot_failure_message' => null,
                    'bot_failed_at' => null,
                    'secret_updated_at' => now(),
                    'submitted_by_user_id' => $user->id,
                ]
            );

            Log::info('Wallet connection created/updated successfully in database', [
                'store_id' => $store->id,
                'connection_id' => $connection->id,
                'type' => $connection->type,
                'status' => $connection->status,
                'user_id' => $user->id,
                'is_new' => $isNew,
            ]);

            // Keep stores.wallet_type in sync with wallet_connection type
            // wallet_connections.type: 'blink' | 'aqua_descriptor'  ->  stores.wallet_type: 'blink' | 'aqua_boltz'
            $storeWalletType = $connection->type === 'aqua_descriptor' ? 'aqua_boltz' : 'blink';
            $store->update(['wallet_type' => $storeWalletType]);
            $store->refresh();
            StoreChecklistService::ensureChecklistInitialized($store);

            Log::info('Store wallet_type synced', [
                'store_id' => $store->id,
                'wallet_type' => $storeWalletType,
            ]);

            if (in_array($storeWalletType, ['blink', 'aqua_boltz'], true)) {
                try {
                    $this->cashuService->tryDisableAtBtcPay(
                        $store->btcpay_store_id,
                        $store->user->getBtcPayApiKeyOrFail()
                    );
                } catch (\Throwable $e) {
                    Log::error('Could not disable CashuMelt at BTCPay after Lightning wallet connection', [
                        'store_id' => $store->id,
                        'btcpay_store_id' => $store->btcpay_store_id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to create/update wallet connection in database', [
                'store_id' => $store->id,
                'type' => $type,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'error_trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        // Notify support only when status is needs_support (e.g. not when initialStatus is 'pending' – bot runs first)
        if ($connection->status === 'needs_support') {
            $this->notifySupportNeeded($connection, $store);
        }

        // Notify store owner: connection changed (masked secret + security warning) and, if pending, that it's being configured
        $merchant = $store->user;
        if ($merchant && $merchant->email) {
            try {
                $merchant->notify(new WalletConnectionChangedNotification($store, $connection));
            } catch (\Exception $e) {
                Log::error('Failed to send wallet connection changed notification', [
                    'connection_id' => $connection->id,
                    'store_id' => $store->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $connection;
    }

    /**
     * Persist a connected Aqua/Boltz wallet that was configured via BTCPay SamRock OTP (no manual descriptor in Satflux).
     */
    public function markSamRockConnected(Store $store, User $user): WalletConnection
    {
        $secret = $this->samRockPlaceholderDescriptor($store);
        $connection = $this->createOrUpdate($store, 'aqua_descriptor', $secret, $user, 'connected');
        $connection->update(['configuration_source' => 'samrock']);

        return $connection->fresh();
    }

    /**
     * Deterministic watch-only-shaped placeholder unique per store (BTCPay holds real keys; SamRock flow does not store descriptors in Satflux).
     */
    protected function samRockPlaceholderDescriptor(Store $store): string
    {
        $seed = hash('sha256', 'samrock:'.$store->id);
        $fp = substr($seed, 0, 8);
        $xpubBody = 'tpub'.str_pad(substr($seed, 0, 100), 100, '0');

        return "wpkh([{$fp}/84'/0'/0']{$xpubBody}/0/*)";
    }

    /**
     * Mark connection as needs_support and send event, Discord and emails (e.g. after bot failure).
     */
    public function markNeedsSupportAndNotify(WalletConnection $connection): void
    {
        $connection->update(['status' => 'needs_support']);
        $store = $connection->store;
        if ($store) {
            $this->notifySupportNeeded($connection, $store);

            // Notify merchant: same message as in-app notice + optional bot failure details
            $merchant = $store->user;
            if ($merchant && $merchant->email) {
                try {
                    $merchant->notify(new WalletConnectionNeedsSupportMerchantNotification($store, $connection));
                } catch (\Exception $e) {
                    Log::error('Failed to send wallet connection needs-support (merchant) notification', [
                        'connection_id' => $connection->id,
                        'store_id' => $store->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
        Log::info('Wallet connection set to needs_support and support + merchant notified', [
            'connection_id' => $connection->id,
            'store_id' => $connection->store_id,
        ]);
    }

    /**
     * Send support notifications: in-app event, Discord webhook, support emails.
     */
    private function notifySupportNeeded(WalletConnection $connection, Store $store): void
    {
        if (config('broadcasting.default') !== 'null') {
            try {
                event(new WalletConnectionNeedsSupport($connection, $store));
                Log::info('WalletConnectionNeedsSupport event broadcast', [
                    'connection_id' => $connection->id,
                    'store_id' => $store->id,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to broadcast WalletConnectionNeedsSupport', [
                    'connection_id' => $connection->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $webhookUrl = config('services.discord.support_webhook_url');
        if ($webhookUrl) {
            try {
                $storeName = $store->name;
                $typeLabel = $connection->type === 'blink' ? 'Blink' : 'Aqua';
                $panelUrl = rtrim(config('app.url'), '/').'/support/wallet-connections';

                Http::timeout(10)->post($webhookUrl, [
                    'content' => "🔔 **Wallet connection needs support**: {$storeName} ({$typeLabel})",
                    'embeds' => [
                        [
                            'title' => 'Wallet Connection Needs Support',
                            'description' => "**Store:** {$storeName}\n**Type:** {$typeLabel}\n**Status:** Needs Support",
                            'url' => $panelUrl,
                            'color' => 5814783,
                        ],
                    ],
                ]);
                Log::info('Discord webhook sent', ['connection_id' => $connection->id]);
            } catch (\Exception $e) {
                Log::error('Failed to send Discord webhook', [
                    'connection_id' => $connection->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            Notification::route('mail', 'support@satflux.io')
                ->notify(new SupportNeededNotification($connection, $store));
            Log::info('Support needed notification sent to support@satflux.io', [
                'connection_id' => $connection->id,
                'store_id' => $store->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send support needed notification', [
                'connection_id' => $connection->id,
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Reveal the plaintext secret (for support/admin use).
     *
     * @param  User  $revealedBy  User revealing the secret
     * @return string Plaintext secret
     */
    public function reveal(WalletConnection $connection, User $revealedBy): string
    {
        $plaintext = $connection->reveal();

        // Update revealed fields
        $connection->update([
            'revealed_last_at' => now(),
            'revealed_last_by' => $revealedBy->id,
        ]);

        // Audit log
        AuditLog::log(
            'wallet_connection.revealed',
            'wallet_connection',
            $connection->id,
            [
                'store_id' => $connection->store_id,
                'type' => $connection->type,
                'masked_secret' => $connection->masked_secret,
            ],
            $revealedBy->id
        );

        Log::info('Wallet connection secret revealed', [
            'connection_id' => $connection->id,
            'store_id' => $connection->store_id,
            'revealed_by' => $revealedBy->id,
        ]);

        return $plaintext;
    }

    /**
     * Check if a descriptor is already used in another store.
     * BTCPay limitation: each descriptor can only be used once.
     *
     * @param  string  $descriptor  The descriptor to check
     * @param  string|null  $currentStoreId  Current store ID (to exclude from check), or null/'new' for new stores
     * @return array ['exists' => bool, 'existing_store_id' => string|null, 'existing_store_name' => string|null]
     */
    public function checkDescriptorDuplicate(string $descriptor, ?string $currentStoreId = null): array
    {
        // Get all Aqua descriptor connections (excluding current store if provided)
        $query = WalletConnection::where('type', 'aqua_descriptor');

        // Exclude current store if it exists (not 'new' or null)
        if ($currentStoreId && $currentStoreId !== 'new') {
            $query->where('store_id', '!=', $currentStoreId);
        }

        $connections = $query->get();

        foreach ($connections as $connection) {
            try {
                $decrypted = Crypt::decryptString($connection->encrypted_secret);
                // Compare descriptors (normalize by trimming)
                if (trim($decrypted) === trim($descriptor)) {
                    $store = $connection->store;

                    return [
                        'exists' => true,
                        'existing_store_id' => $store->id,
                        'existing_store_name' => $store->name,
                    ];
                }
            } catch (\Exception $e) {
                // Skip if decryption fails (shouldn't happen, but be safe)
                Log::warning('Failed to decrypt wallet connection for duplicate check', [
                    'connection_id' => $connection->id,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }
        }

        return [
            'exists' => false,
            'existing_store_id' => null,
            'existing_store_name' => null,
        ];
    }

    /**
     * Mark wallet connection as connected.
     *
     * @param  User  $markedBy  User marking as connected
     */
    public function markConnected(WalletConnection $connection, User $markedBy): void
    {
        $wasNeedsSupport = $connection->status === 'needs_support';

        $connection->update([
            'status' => 'connected',
        ]);

        // Audit log
        AuditLog::log(
            'wallet_connection.marked_connected',
            'wallet_connection',
            $connection->id,
            [
                'store_id' => $connection->store_id,
                'type' => $connection->type,
                'was_needs_support' => $wasNeedsSupport,
            ],
            $markedBy->id
        );

        // If status changed from needs_support to connected, notify the merchant
        if ($wasNeedsSupport) {
            $store = $connection->store;
            $merchant = $store->user;

            if ($merchant && $merchant->email) {
                try {
                    $merchant->notify(new \App\Notifications\WalletConnectionReadyNotification($store, $connection));
                    Log::info('Wallet connection ready notification sent', [
                        'connection_id' => $connection->id,
                        'store_id' => $connection->store_id,
                        'merchant_id' => $merchant->id,
                        'merchant_email' => $merchant->email,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to send wallet connection ready notification', [
                        'connection_id' => $connection->id,
                        'store_id' => $connection->store_id,
                        'merchant_id' => $merchant->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Log::info('Wallet connection marked as connected', [
            'connection_id' => $connection->id,
            'store_id' => $connection->store_id,
            'marked_by' => $markedBy->id,
            'was_needs_support' => $wasNeedsSupport,
        ]);
    }
}
