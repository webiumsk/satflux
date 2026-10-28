<?php

namespace App\Notifications;

use App\Models\Store;
use App\Models\User;
use App\Services\Boltz\BoltzAvailabilityService;
use Illuminate\Mail\SentMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * A synchronous operational notice, scoped again to its recipient at delivery.
 */
class BoltzServiceUnavailableNotification extends Notification
{
    public const SUPPORTED_LOCALES = ['en', 'sk', 'es', 'de', 'cs'];

    /** Laravel clones notifications; keep the attempt receipt shared with the CLI caller. */
    public readonly \stdClass $deliveryReceipt;

    /** @param list<string> $storeIds Local store UUIDs, never upstream identifiers. */
    public function __construct(
        public int $recipientUserId,
        public array $storeIds,
    ) {
        $this->deliveryReceipt = (object) ['skipped' => false, 'accepted' => false];
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /** Recheck immediately before the mail channel, including changes after the CLI claimed delivery. */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        $recipient = $this->eligibleRecipient();
        $allowed = $channel === 'mail'
            && $notifiable instanceof User
            && (int) $notifiable->id === $this->recipientUserId
            && $recipient !== null
            && $notifiable->email === $recipient->email
            && $this->affectedStores()->isNotEmpty();

        $this->deliveryReceipt->skipped = ! $allowed;

        return $allowed;
    }

    public function toMail(object $notifiable): MailMessage
    {
        // A NotificationSending listener may have changed eligibility after shouldSend.
        if (! $this->shouldSend($notifiable, 'mail')) {
            throw new \RuntimeException('The operational notice is no longer eligible for delivery.');
        }

        $appUrl = rtrim((string) config('app.url'), '/');
        $mail = (new MailMessage)
            ->subject((string) __('boltz.subject'))
            ->line((string) __('boltz.intro'))
            ->line((string) __('boltz.action_required'))
            ->line((string) __('boltz.alternatives'))
            ->line((string) __('boltz.history'));

        // Generic labels avoid interpreting merchant-controlled names as email Markdown.
        foreach ($this->affectedStores()->values() as $index => $store) {
            $mail->line((string) __('boltz.store_link', [
                'number' => $index + 1,
                'url' => "{$appUrl}/stores/{$store->id}/wallet-connection",
            ]));
        }

        return $mail
            ->action((string) __('boltz.open_stores'), "{$appUrl}/stores")
            ->line((string) __('boltz.help'));
    }

    /** Record channel acceptance only after the actual mail channel has returned. */
    public function afterSending(object $notifiable, string $channel, mixed $response): void
    {
        $this->deliveryReceipt->accepted = $channel === 'mail' && $response instanceof SentMessage;
    }

    private function eligibleRecipient(): ?User
    {
        $user = User::query()->find($this->recipientUserId);

        return $user instanceof User
            && ! $user->is_guest
            && $user->hasVerifiedEmail()
            && filter_var($user->email, FILTER_VALIDATE_EMAIL)
            ? $user : null;
    }

    /** @return Collection<int, Store> */
    private function affectedStores(): Collection
    {
        return Store::query()
            ->where('user_id', $this->recipientUserId)
            ->whereIn('id', $this->storeIds)
            ->where('wallet_type', 'aqua_boltz')
            ->orderBy('id')
            ->get()
            ->filter(fn (Store $store) => app(BoltzAvailabilityService::class)->isAffected($store))
            ->values();
    }
}
