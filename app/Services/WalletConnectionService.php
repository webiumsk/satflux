<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Store;
use App\Models\User;
use App\Models\WalletConnection;
use Illuminate\Support\Facades\Crypt;
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
        // Validate the secret
        $validation = $this->validator->validate($type, $secret);
        if (!$validation['valid']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'secret' => $validation['errors'],
            ]);
        }

        // Create or update wallet connection
        $connection = WalletConnection::updateOrCreate(
            ['store_id' => $store->id],
            [
                'type' => $type,
                'encrypted_secret' => Crypt::encryptString($secret),
                'status' => 'needs_support',
                'submitted_by_user_id' => $user->id,
            ]
        );

        Log::info('Wallet connection created/updated', [
            'store_id' => $store->id,
            'type' => $type,
            'status' => $connection->status,
            'user_id' => $user->id,
        ]);

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


