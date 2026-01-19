<?php

namespace App\Notifications;

use App\Models\Store;
use App\Models\WalletConnection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupportNeededNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public WalletConnection $walletConnection,
        public Store $store
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
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
        $appUrl = config('app.url');
        $supportUrl = "{$appUrl}/support/wallet-connections";
        $storeName = $this->store->name;
        $type = $this->walletConnection->type === 'blink' ? 'Blink' : 'Aqua';
        
        return (new MailMessage)
            ->subject("New Wallet Connection Needs Support - {$storeName}")
            ->greeting('Hello!')
            ->line("A new wallet connection requires your support:")
            ->line("**Store:** {$storeName}")
            ->line("**Type:** {$type}")
            ->line("**Status:** Needs Support")
            ->action('Review Connection', $supportUrl)
            ->line('Please review and configure the wallet connection when you have a moment.')
            ->line('Thank you for your support!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'wallet_connection_id' => $this->walletConnection->id,
            'store_id' => $this->store->id,
            'store_name' => $this->store->name,
            'type' => $this->walletConnection->type,
        ];
    }
}
