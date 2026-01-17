<?php

namespace App\Notifications;

use App\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WalletConnectionReadyNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Store $store
    ) {
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        // Use APP_URL or fallback to default
        $appUrl = config('app.url', 'http://localhost:8080');
        $storeUrl = rtrim($appUrl, '/') . '/stores/' . $this->store->id;
        
        return (new MailMessage)
            ->subject('Wallet Connection Ready - ' . $this->store->name)
            ->line('Your wallet connection has been successfully configured!')
            ->line("Store: {$this->store->name}")
            ->line('Your store is now ready to accept Lightning payments.')
            ->action('View Store Dashboard', $storeUrl)
            ->line('Thank you for using our service!');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'store_id' => $this->store->id,
            'store_name' => $this->store->name,
        ];
    }
}

