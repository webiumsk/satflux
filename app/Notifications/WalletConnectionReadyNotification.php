<?php

namespace App\Notifications;

use App\Models\Store;
use App\Models\WalletConnection;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WalletConnectionReadyNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Store $store,
        public WalletConnection $walletConnection
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appUrl = rtrim(config('app.url', 'http://localhost:8080'), '/');
        $storeUrl = "{$appUrl}/stores/{$this->store->id}";
        $masked = $this->walletConnection->masked_secret ?: '******';

        return (new MailMessage)
            ->subject('Wallet Connection Ready - '.$this->store->name)
            ->line('Your wallet connection has been successfully configured!')
            ->line("Store: {$this->store->name}")
            ->line('**Masked connection (for your records):** `'.$masked.'`')
            ->line('Your store is now ready to accept Lightning payments.')
            ->action('View Store Dashboard', $storeUrl)
            ->line('Thank you for using our service!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'store_id' => $this->store->id,
            'store_name' => $this->store->name,
        ];
    }
}
