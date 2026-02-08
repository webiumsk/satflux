<?php

namespace App\Notifications;

use App\Models\Store;
use App\Models\WalletConnection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to support when the BTCPay config bot fails to configure a wallet connection.
 */
class WalletConnectionBotFailedNotification extends Notification
{
    public function __construct(
        public WalletConnection $connection,
        public Store $store,
        public string $errorMessage
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appUrl = rtrim(config('app.url'), '/');
        $supportUrl = "{$appUrl}/support/wallet-connections";

        return (new MailMessage)
            ->subject("BTCPay Config Bot Failed - {$this->store->name}")
            ->greeting('Support:')
            ->line('The automated BTCPay config bot failed to configure this wallet connection. Please configure it manually.')
            ->line("**Store:** {$this->store->name}")
            ->line("**Connection ID:** {$this->connection->id}")
            ->line("**Error:** {$this->errorMessage}")
            ->action('Open wallet connections', $supportUrl)
            ->line('Thank you.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'wallet_connection_id' => $this->connection->id,
            'store_id' => $this->store->id,
            'store_name' => $this->store->name,
            'error' => $this->errorMessage,
        ];
    }
}
