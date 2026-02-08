<?php

namespace App\Services;

use App\Events\WalletConnectionNeedsSupport;
use App\Models\AuditLog;
use App\Models\Store;
use App\Models\User;
use App\Models\WalletConnection;
use App\Notifications\SupportNeededNotification;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WalletConnectionService
{
    protected WalletConnectionValidator $validator;

    public function __construct(WalletConnectionValidator $validator)
    {
        $this->validator = $validator;
    }

    /**
     * Create or update a wallet connection for a store.
     *
     * @param Store $store
     * @param string $type Connection type ('blink' or 'aqua_descriptor')
     * @param string $secret Secret value (will be encrypted)
     * @param User $user User submitting the connection
     * @return WalletConnection
     * @throws \Illuminate\Validation\ValidationException
     */
    public function createOrUpdate(Store $store, string $type, string $secret, User $user): WalletConnection
    {
        Log::info('WalletConnectionService::createOrUpdate called', [
            'store_id' => $store->id,
            'store_btcpay_store_id' => $store->btcpay_store_id ?? 'NULL',
            'type' => $type,
            'secret_length' => strlen($secret),
            'secret_preview' => substr($secret, 0, 50) . '...',
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
        
        if (!$validation['valid']) {
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
                        'This descriptor is already in use by another store. ' .
                        'BTCPay allows each descriptor to be used only once. ' .
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
        
        Log::info('Checking for existing wallet connection', [
            'store_id' => $store->id,
            'is_new' => $isNew,
            'existing_connection_id' => $existingConnection->id ?? 'NULL',
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
                    'encrypted_secret' => Crypt::encryptString($secret),
                    'status' => 'needs_support',
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

        // Notify support users when a connection needs support (new or re-submitted after merchant change)
        if ($connection->status === 'needs_support') {
            // BTCPay config bot (disabled by default; use poller on host instead: node scripts/btcpay-config-bot/poll.js)
            if (config('services.btcpay_config_bot.enabled') && config('services.btcpay_config_bot.use_job', false)) {
                try {
                    \App\Jobs\ConfigureWalletViaBtcpPayUI::dispatch($connection);
                    Log::info('ConfigureWalletViaBtcpPayUI job dispatched', [
                        'connection_id' => $connection->id,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to dispatch ConfigureWalletViaBtcpPayUI job', [
                        'connection_id' => $connection->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Instant in-app notification via Reverb (no queue) so support can act immediately
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

            // Discord webhook (instant, works even when no one has browser open)
            $webhookUrl = config('services.discord.support_webhook_url');
            if ($webhookUrl) {
                try {
                    $storeName = $store->name;
                    $type = $connection->type === 'blink' ? 'Blink' : 'Aqua';
                    $panelUrl = rtrim(config('app.url'), '/') . '/support/wallet-connections';

                    Http::post($webhookUrl, [
                        'content' => "🔔 **Wallet connection needs support**: {$storeName} ({$type})",
                        'embeds' => [
                            [
                                'title' => 'Wallet Connection Needs Support',
                                'description' => "**Store:** {$storeName}\n**Type:** {$type}\n**Status:** Needs Support",
                                'url' => $panelUrl,
                                'color' => 5814783, // indigo
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

            // Email notification (can be queued)
            $supportUsers = User::whereIn('role', ['support', 'admin'])
                ->whereNotNull('email')
                ->whereNotNull('email_verified_at')
                ->get();

            foreach ($supportUsers as $supportUser) {
                try {
                    $supportUser->notify(new SupportNeededNotification($connection, $store));
                    Log::info('Support needed notification sent', [
                        'connection_id' => $connection->id,
                        'store_id' => $store->id,
                        'support_user_id' => $supportUser->id,
                        'support_user_email' => $supportUser->email,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to send support needed notification', [
                        'connection_id' => $connection->id,
                        'store_id' => $store->id,
                        'support_user_id' => $supportUser->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $connection;
    }

    /**
     * Reveal the plaintext secret (for support/admin use).
     *
     * @param WalletConnection $connection
     * @param User $revealedBy User revealing the secret
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
     * @param string $descriptor The descriptor to check
     * @param string|null $currentStoreId Current store ID (to exclude from check), or null/'new' for new stores
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
     * @param WalletConnection $connection
     * @param User $markedBy User marking as connected
     * @return void
     */
    public function markConnected(WalletConnection $connection, User $markedBy): void
    {
        $wasNeedsSupport = $connection->status === 'needs_support';
        
        $connection->update([
            'status' => 'connected',
        ]);

        // Auto-complete store checklist "connect_wallet" item
        $store = $connection->store;
        $checklistItem = \App\Models\StoreChecklist::where('store_id', $store->id)
            ->where('item_key', 'connect_wallet')
            ->first();
        if ($checklistItem && ! $checklistItem->isCompleted()) {
            $checklistItem->markAsCompleted();
            Log::info('Store checklist connect_wallet auto-completed', [
                'store_id' => $store->id,
                'connection_id' => $connection->id,
            ]);
        }

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
                    $merchant->notify(new \App\Notifications\WalletConnectionReadyNotification($store));
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


