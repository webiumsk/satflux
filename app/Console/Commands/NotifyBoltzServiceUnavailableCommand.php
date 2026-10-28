<?php

namespace App\Console\Commands;

use App\Models\BoltzServiceNotification;
use App\Models\Store;
use App\Models\User;
use App\Notifications\BoltzServiceUnavailableNotification;
use App\Services\Boltz\BoltzAvailabilityService;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;

/**
 * Explicit operator campaign. Preview is read-only; no migration or schedule sends mail.
 */
class NotifyBoltzServiceUnavailableCommand extends Command
{
    protected $signature = 'wallet:notify-boltz-unavailable
                            {--send : Claim and send one operational email per eligible owner}
                            {--dry-run : Preview counts only, without writing or sending}
                            {--locale=en : Message language: en, sk, es, de or cs}';

    protected $description = 'Preview or send deduplicated Boltz receiving notices; uncertain deliveries are held for operator review';

    public function __construct(protected BoltzAvailabilityService $availability)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $send = (bool) $this->option('send');
        $locale = (string) $this->option('locale');

        if (($send && $this->option('dry-run')) || ! in_array($locale, BoltzServiceUnavailableNotification::SUPPORTED_LOCALES, true)) {
            $this->error('Choose either --send or --dry-run and a supported --locale.');

            return self::INVALID;
        }

        $owners = 0;
        $stores = 0;
        $held = 0;
        $sent = 0;
        $uncertain = 0;

        User::query()
            ->where('is_guest', false)
            ->whereNotNull('email_verified_at')
            ->whereHas('stores', fn ($query) => $query->where('wallet_type', 'aqua_boltz'))
            ->chunkById(100, function ($users) use ($send, $locale, &$owners, &$stores, &$held, &$sent, &$uncertain) {
                foreach ($users as $user) {
                    if (! $this->eligibleRecipient($user)) {
                        continue;
                    }

                    $affected = $this->affectedStores($user);
                    if ($affected->isEmpty()) {
                        continue;
                    }

                    if (BoltzServiceNotification::query()->where('user_id', $user->id)->where('campaign', BoltzServiceNotification::CAMPAIGN)->exists()) {
                        $held++;

                        continue;
                    }

                    if (! $send) {
                        $owners++;
                        $stores += $affected->count();

                        continue;
                    }

                    try {
                        // The unique user/campaign key arbitrates concurrent commands before transport.
                        $claim = BoltzServiceNotification::query()->create([
                            'user_id' => $user->id,
                            'campaign' => BoltzServiceNotification::CAMPAIGN,
                            'locale' => $locale,
                            'status' => 'claimed',
                            'claimed_at' => now(),
                        ]);
                    } catch (UniqueConstraintViolationException) {
                        $held++;

                        continue;
                    }

                    $freshUser = User::query()->find($user->id);
                    $affected = $freshUser instanceof User && $this->eligibleRecipient($freshUser)
                        ? $this->affectedStores($freshUser) : collect();
                    if (! $freshUser instanceof User || $affected->isEmpty()) {
                        $claim->update(['status' => 'skipped']);

                        continue;
                    }

                    $notification = (new BoltzServiceUnavailableNotification((int) $freshUser->id, $affected->pluck('id')->all()))->locale($locale);

                    try {
                        $freshUser->notify($notification);
                    } catch (\Throwable) {
                        if ($notification->deliveryReceipt->skipped) {
                            $claim->update(['status' => 'skipped']);

                            continue;
                        }

                        // SMTP may have accepted mail before an error. Never retry this claim automatically.
                        $claim->update(['status' => 'delivery_unknown']);
                        $uncertain++;

                        continue;
                    }

                    $claim->update([
                        'status' => $notification->deliveryReceipt->accepted ? 'sent' : 'skipped',
                        'sent_at' => $notification->deliveryReceipt->accepted ? now() : null,
                    ]);
                    if ($notification->deliveryReceipt->accepted) {
                        $sent++;
                    }
                }
            });

        if (! $send) {
            $this->info("Preview: {$owners} eligible owner(s), {$stores} affected store(s), {$held} existing campaign claim(s). No email sent.");
        } else {
            $this->info("Result: {$sent} accepted delivery attempt(s), {$held} existing campaign claim(s), {$uncertain} uncertain delivery attempt(s).");
            if ($uncertain > 0) {
                $this->warn('Uncertain deliveries are held for operator review and will not be retried automatically.');
            }
        }

        return $uncertain > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function eligibleRecipient(User $user): bool
    {
        return ! $user->is_guest
            && $user->hasVerifiedEmail()
            && filter_var($user->email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /** @return Collection<int, Store> */
    private function affectedStores(User $user): Collection
    {
        return Store::query()
            ->where('user_id', $user->id)
            ->where('wallet_type', 'aqua_boltz')
            ->orderBy('id')
            ->get()
            ->filter(fn (Store $store) => $this->availability->isAffected($store))
            ->values();
    }
}
