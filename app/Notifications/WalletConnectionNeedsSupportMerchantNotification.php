<?php

namespace App\Notifications;

use App\Models\Store;
use App\Models\WalletConnection;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the store owner when their wallet connection could not be configured (status = needs_support, e.g. after bot failure).
 */
class WalletConnectionNeedsSupportMerchantNotification extends Notification
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
        $storeUrl = "{$appUrl}/stores/{$this->store->id}";
        $masked = $this->walletConnection->masked_secret ?: '******';

        $mail = (new MailMessage)
            ->subject('Wallet connection needs support - ' . $this->store->name)
            ->line('Your wallet connection is being configured by our support team.')
            ->line("Store: {$this->store->name}")
            ->line('**Masked connection (for your records):** `' . $masked . '`')
            ->line("You'll be notified when it's ready.");

        if ($this->walletConnection->bot_failure_message) {
            $mail->line('**Technical details (for your reference):** ' . $this->walletConnection->bot_failure_message);
        }

        return $mail
            ->action('View store', $storeUrl)
            ->line('Thank you for your patience!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'store_id' => $this->store->id,
            'wallet_connection_id' => $this->walletConnection->id,
        ];
    }
}
