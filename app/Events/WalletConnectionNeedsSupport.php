<?php

namespace App\Events;

use App\Models\Store;
use App\Models\WalletConnection;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a wallet connection needs support (new or merchant re-submitted).
 * In-app broadcast only (instant toast for support). Mail and Discord are sent only when the bot fails (bot-failed).
 */
class WalletConnectionNeedsSupport implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public WalletConnection $walletConnection,
        public Store $store
    ) {
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('support.wallet-connections'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'wallet-connection.needs-support';
    }

    /**
     * Data to broadcast for in-app toast / list update.
     */
    public function broadcastWith(): array
    {
        $appUrl = config('app.url');
        $storeName = $this->store->name;
        $type = $this->walletConnection->type === 'blink' ? 'Blink' : 'Aqua';

        return [
            'message' => "New wallet connection needs support: {$storeName} ({$type})",
            'store_name' => $storeName,
            'store_id' => $this->store->id,
            'wallet_connection_id' => $this->walletConnection->id,
            'type' => $type,
            'url' => "{$appUrl}/support/wallet-connections",
        ];
    }
}
