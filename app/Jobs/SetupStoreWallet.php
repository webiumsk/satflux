<?php

namespace App\Jobs;

use App\Models\Store;
use App\Models\User;
use App\Services\BtcPay\BtcPayClient;
use App\Services\BtcPay\LightningService;
use App\Services\BtcPay\StoreService;
use App\Services\BtcPay\UserService;
use App\Services\WalletConnectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SetupStoreWallet implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected Store $store,
        protected User $user,
        protected ?string $connectionString = null
    ) {
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(
        StoreService $storeService,
        UserService $userService,
        LightningService $lightningService,
        WalletConnectionService $walletConnectionService
    ): void {
        Log::info('Background job SetupStoreWallet started', [
            'store_id' => $this->store->id,
            'btcpay_store_id' => $this->store->btcpay_store_id,
            'user_id' => $this->user->id,
        ]);

        $btcpayStoreId = $this->store->btcpay_store_id;

        // 1. Ensure admin is Owner (for support access)
        try {
            $adminBtcPayUserId = $userService->getAdminBtcPayUserId();
            if ($adminBtcPayUserId) {
                $storeService->addUserToStore($btcpayStoreId, $adminBtcPayUserId, 'Owner');
                Log::info('Assigned admin to store in background', ['store_id' => $btcpayStoreId]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to assign admin to store in background', [
                'store_id' => $btcpayStoreId,
                'error' => $e->getMessage(),
            ]);
        }

        // 2. Ensure merchant is Owner
        if ($this->user->btcpay_user_id) {
            try {
                $storeService->addUserToStore($btcpayStoreId, $this->user->btcpay_user_id, 'Owner');
                Log::info('Assigned merchant to store in background', ['store_id' => $btcpayStoreId]);
            } catch (\Exception $e) {
                Log::error('Failed to assign merchant to store in background', [
                    'store_id' => $btcpayStoreId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 3. Setup Wallet Connection if provided
        if ($this->connectionString) {
            try {
                $connectionType = $this->store->wallet_type === 'blink' ? 'blink' : 'aqua_descriptor';

                $walletConnection = $walletConnectionService->createOrUpdate(
                    $this->store,
                    $connectionType,
                    $this->connectionString,
                    $this->user
                );

                Log::info('Wallet connection created in background', [
                    'store_id' => $this->store->id,
                    'connection_id' => $walletConnection->id,
                ]);

                // 4. Connect to BTCPay
                try {
                    $userApiKey = $this->user->getBtcPayApiKeyOrFail();
                    $result = $lightningService->connectLightningNode(
                        $btcpayStoreId,
                        'BTC',
                        $this->connectionString,
                        $userApiKey
                    );

                    if ($result['success'] ?? false) {
                        $walletConnectionService->markConnected($walletConnection, $this->user);
                        Log::info('Wallet automatically connected to BTCPay in background', ['store_id' => $this->store->id]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to connect wallet to BTCPay in background', [
                        'store_id' => $this->store->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to create wallet connection in background', [
                    'store_id' => $this->store->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Background job SetupStoreWallet completed', ['store_id' => $this->store->id]);
    }
}
