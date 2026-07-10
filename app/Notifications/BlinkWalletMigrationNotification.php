<?php

namespace App\Notifications;

use App\Models\Store;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * One-shot campaign email listing Blink wallet stores that need migration.
 */
class BlinkWalletMigrationNotification extends Notification
{
    use Queueable;

    /**
     * @param  Collection<int, Store>  $stores
     */
    public function __construct(
        public User $user,
        public Collection $stores,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appUrl = rtrim(config('app.url', 'http://localhost:8080'), '/');
        $storesUrl = "{$appUrl}/stores";

        $mail = (new MailMessage)
            ->subject('Action required: migrate your Blink wallet connection')
            ->line('Blink API custody is no longer available in the EU. If your Blink account is in Europe, Lightning payments may stop working.')
            ->line('We recommend migrating to Aqua or Bull Bitcoin via Wallet connection (SamRock QR or watch-only descriptor).')
            ->line('The following stores still use Blink:');

        foreach ($this->stores as $store) {
            $walletUrl = "{$appUrl}/stores/{$store->id}/wallet-connection";
            $mail->line("- **{$store->name}** - [Open wallet connection]({$walletUrl})");
        }

        return $mail
            ->action('View stores', $storesUrl)
            ->line('Thank you for keeping your payments running smoothly.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'user_id' => $this->user->id,
            'store_ids' => $this->stores->pluck('id')->all(),
        ];
    }
}
