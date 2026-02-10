<?php

namespace App\Notifications;

use App\Models\Store;
use App\Models\WalletConnection;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the store owner whenever their wallet connection string is changed (create/update).
 * Includes masked secret and security warning.
 */
class WalletConnectionChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Store $store,
        public WalletConnection $walletConnection
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appUrl = rtrim(config('app.url', 'http://localhost:8080'), '/');
        $storeUrl = "{$appUrl}/stores/{$this->store->id}/wallet-connection";
        $masked = $this->walletConnection->masked_secret ?: '******';
        $type = $this->walletConnection->type === 'blink' ? 'Blink' : 'Aqua (Boltz)';

        return (new MailMessage)
            ->subject('Wallet connection updated - ' . $this->store->name)
            ->line('Your wallet connection for store **' . $this->store->name . '** has been updated.')
            ->line('**Type:** ' . $type)
            ->line('**Masked connection (for your records):** `' . $masked . '`')
            ->line('**If this was not you,** please contact our support immediately to secure your account.')
            ->action('Wallet connection settings', $storeUrl)
            ->line('Thank you for using our service!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'store_id' => $this->store->id,
            'wallet_connection_id' => $this->walletConnection->id,
        ];
    }
}
