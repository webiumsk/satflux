<?php

namespace Tests\Feature;

use App\Models\BoltzServiceNotification;
use App\Models\Store;
use App\Models\User;
use App\Notifications\BoltzServiceUnavailableNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BoltzServiceNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.boltz.receiving_enabled' => false, 'app.url' => 'https://satflux.test']);
        Notification::fake();
        Http::preventStrayRequests();
    }

    public function test_default_preview_counts_owners_without_writing_or_disclosing_recipients(): void
    {
        $owner = User::factory()->create(['email' => 'private-merchant@example.test']);
        Store::factory()->withAquaBoltz()->count(2)->create(['user_id' => $owner->id, 'name' => 'Private merchant store']);

        $this->assertSame(0, Artisan::call('wallet:notify-boltz-unavailable'));
        $output = Artisan::output();
        $this->assertStringContainsString('1 eligible owner(s), 2 affected store(s)', $output);
        $this->assertStringNotContainsString($owner->email, $output);
        $this->assertStringNotContainsString('Private merchant store', $output);
        $this->assertDatabaseCount('boltz_service_notifications', 0);
        Notification::assertNothingSent();
    }

    public function test_explicit_dry_run_is_read_only(): void
    {
        Store::factory()->withAquaBoltz()->create();
        $this->assertSame(0, Artisan::call('wallet:notify-boltz-unavailable', ['--dry-run' => true]));
        $this->assertDatabaseCount('boltz_service_notifications', 0);
        Notification::assertNothingSent();
    }

    public function test_send_groups_owned_stores_and_persists_one_claim_per_owner(): void
    {
        $transport = $this->useRealNotificationsWithArrayMail();
        $owner = User::factory()->create();
        $owned = Store::factory()->withAquaBoltz()->count(2)->create(['user_id' => $owner->id]);
        Store::factory()->withBlink()->create(['user_id' => $owner->id]);
        $other = User::factory()->create();
        $otherStore = Store::factory()->withAquaBoltz()->create(['user_id' => $other->id]);

        $this->assertSame(0, Artisan::call('wallet:notify-boltz-unavailable', ['--send' => true, '--locale' => 'sk']));

        $this->assertCount(2, $transport->messages());
        $ownerMail = $transport->messages()->map(fn ($message) => $message->getOriginalMessage())
            ->first(fn ($message) => $message->getTo()[0]->getAddress() === $owner->email);
        $this->assertNotNull($ownerMail);
        $this->assertSame(__('boltz.subject', [], 'sk'), $ownerMail->getSubject());
        foreach ($owned as $store) {
            $this->assertStringContainsString($store->id, $ownerMail->getHtmlBody());
        }
        $this->assertStringNotContainsString($otherStore->id, $ownerMail->getHtmlBody());
        $this->assertDatabaseCount('boltz_service_notifications', 2);
        $this->assertDatabaseHas('boltz_service_notifications', ['user_id' => $owner->id, 'status' => 'sent', 'locale' => 'sk']);
        $this->assertNotNull(BoltzServiceNotification::query()->where('user_id', $owner->id)->firstOrFail()->sent_at);
        $this->assertStringNotContainsString($owner->email, Artisan::output());

        $this->assertSame(0, Artisan::call('wallet:notify-boltz-unavailable', ['--send' => true]));
        $this->assertCount(2, $transport->messages());
        $this->assertDatabaseCount('boltz_service_notifications', 2);
    }

    public function test_guest_unverified_invalid_email_and_unaffected_owners_are_skipped(): void
    {
        $guest = User::factory()->guest()->create();
        $unverified = User::factory()->unverified()->create();
        $invalid = User::factory()->create(['email' => 'not-an-email']);
        foreach ([$guest, $unverified, $invalid] as $owner) {
            Store::factory()->withAquaBoltz()->create(['user_id' => $owner->id]);
        }
        Store::factory()->withBlink()->create();

        $this->assertSame(0, Artisan::call('wallet:notify-boltz-unavailable', ['--send' => true]));
        Notification::assertNothingSent();
        $this->assertDatabaseCount('boltz_service_notifications', 0);
    }

    public function test_all_existing_claim_states_suppress_another_attempt(): void
    {
        foreach (['claimed', 'sent', 'delivery_unknown', 'skipped'] as $status) {
            $owner = User::factory()->create();
            Store::factory()->withAquaBoltz()->create(['user_id' => $owner->id]);
            BoltzServiceNotification::query()->create([
                'user_id' => $owner->id,
                'campaign' => BoltzServiceNotification::CAMPAIGN,
                'locale' => 'en',
                'status' => $status,
                'claimed_at' => now(),
            ]);
        }

        $this->assertSame(0, Artisan::call('wallet:notify-boltz-unavailable', ['--send' => true]));
        Notification::assertNothingSent();
        $this->assertDatabaseCount('boltz_service_notifications', 4);
    }

    public function test_transport_error_is_held_without_logging_details_or_retrying(): void
    {
        $owner = User::factory()->create();
        Store::factory()->withAquaBoltz()->create(['user_id' => $owner->id]);
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('private transport details'));

        $this->assertSame(1, Artisan::call('wallet:notify-boltz-unavailable', ['--send' => true]));
        $this->assertStringContainsString('1 uncertain delivery attempt(s)', Artisan::output());
        $this->assertStringNotContainsString('private transport details', Artisan::output());
        $this->assertStringNotContainsString($owner->email, Artisan::output());
        $this->assertDatabaseHas('boltz_service_notifications', ['user_id' => $owner->id, 'status' => 'delivery_unknown', 'sent_at' => null]);

        $this->assertSame(0, Artisan::call('wallet:notify-boltz-unavailable', ['--send' => true]));
        $this->assertDatabaseCount('boltz_service_notifications', 1);
    }

    public function test_unique_claim_arbitrates_an_insert_racing_after_the_initial_check(): void
    {
        $owner = User::factory()->create();
        Store::factory()->withAquaBoltz()->create(['user_id' => $owner->id]);
        $event = 'eloquent.creating: '.BoltzServiceNotification::class;
        Event::listen($event, function () use ($owner) {
            DB::table('boltz_service_notifications')->insert([
                'user_id' => $owner->id,
                'campaign' => BoltzServiceNotification::CAMPAIGN,
                'locale' => 'en',
                'status' => 'claimed',
                'claimed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        try {
            $this->assertSame(0, Artisan::call('wallet:notify-boltz-unavailable', ['--send' => true]));
        } finally {
            Event::forget($event);
        }

        Notification::assertNothingSent();
        $this->assertDatabaseCount('boltz_service_notifications', 1);
        $this->assertDatabaseHas('boltz_service_notifications', ['user_id' => $owner->id, 'status' => 'claimed']);
    }

    public function test_changed_wallet_after_claim_is_rechecked_before_notification(): void
    {
        $owner = User::factory()->create();
        $store = Store::factory()->withAquaBoltz()->create(['user_id' => $owner->id]);
        $event = 'eloquent.created: '.BoltzServiceNotification::class;
        Event::listen($event, fn () => $store->update(['wallet_type' => 'blink']));

        try {
            $this->assertSame(0, Artisan::call('wallet:notify-boltz-unavailable', ['--send' => true]));
        } finally {
            Event::forget($event);
        }

        Notification::assertNothingSent();
        $this->assertDatabaseHas('boltz_service_notifications', ['user_id' => $owner->id, 'status' => 'skipped', 'sent_at' => null]);
    }

    public function test_revoked_verification_after_claim_is_rechecked(): void
    {
        $owner = User::factory()->create();
        Store::factory()->withAquaBoltz()->create(['user_id' => $owner->id]);
        $event = 'eloquent.created: '.BoltzServiceNotification::class;
        Event::listen($event, fn () => $owner->forceFill(['email_verified_at' => null])->save());

        try {
            $this->assertSame(0, Artisan::call('wallet:notify-boltz-unavailable', ['--send' => true]));
        } finally {
            Event::forget($event);
        }

        Notification::assertNothingSent();
        $this->assertDatabaseHas('boltz_service_notifications', ['user_id' => $owner->id, 'status' => 'skipped']);
    }

    public function test_delivery_rechecks_scope_and_does_not_use_foreign_or_changed_store_ids(): void
    {
        $owner = User::factory()->create();
        $own = Store::factory()->withAquaBoltz()->create(['user_id' => $owner->id, 'name' => '[Untrusted link](https://unsafe.example)']);
        $changed = Store::factory()->withBlink()->create(['user_id' => $owner->id]);
        $foreign = Store::factory()->withAquaBoltz()->create();
        $notice = new BoltzServiceUnavailableNotification((int) $owner->id, [$own->id, $changed->id, $foreign->id]);

        $this->assertFalse($notice->shouldSend($foreign->user, 'mail'));
        $this->assertTrue($notice->shouldSend($owner, 'mail'));
        $body = json_encode($notice->toMail($owner)->toArray(), JSON_THROW_ON_ERROR);
        $this->assertStringContainsString($own->id, $body);
        $this->assertStringNotContainsString($changed->id, $body);
        $this->assertStringNotContainsString($foreign->id, $body);
        $this->assertStringNotContainsString($own->btcpay_store_id, $body);
        $this->assertStringNotContainsString('unsafe.example', $body);

        $own->update(['wallet_type' => 'nwc']);
        $this->assertFalse($notice->shouldSend($owner, 'mail'));
    }

    public function test_reopened_provider_has_no_campaign_recipients(): void
    {
        Store::factory()->withAquaBoltz()->create();
        config(['services.boltz.receiving_enabled' => true]);
        $this->assertSame(0, Artisan::call('wallet:notify-boltz-unavailable', ['--send' => true]));
        Notification::assertNothingSent();
        $this->assertDatabaseCount('boltz_service_notifications', 0);
    }

    public function test_email_changes_after_recipient_loading_do_not_deliver_to_the_stale_address(): void
    {
        $owner = User::factory()->create(['email' => 'old-address@example.test']);
        $store = Store::factory()->withAquaBoltz()->create(['user_id' => $owner->id]);
        $notice = new BoltzServiceUnavailableNotification((int) $owner->id, [$store->id]);

        User::query()->whereKey($owner->id)->update(['email' => 'new-address@example.test']);
        $this->assertFalse($notice->shouldSend($owner, 'mail'));
        $this->assertTrue($notice->shouldSend($owner->fresh(), 'mail'));
    }

    public function test_real_notification_sender_skips_a_wallet_changed_immediately_before_the_mail_channel(): void
    {
        $transport = $this->useRealNotificationsWithArrayMail();
        $owner = User::factory()->create();
        $store = Store::factory()->withAquaBoltz()->create(['user_id' => $owner->id]);
        Event::listen(NotificationSending::class, function (NotificationSending $event) use ($store) {
            if ($event->notification instanceof BoltzServiceUnavailableNotification) {
                $store->update(['wallet_type' => 'nwc']);
            }
        });

        try {
            $this->assertSame(0, Artisan::call('wallet:notify-boltz-unavailable', ['--send' => true]));
        } finally {
            Event::forget(NotificationSending::class);
        }

        $this->assertCount(0, $transport->messages());
        $this->assertDatabaseHas('boltz_service_notifications', ['user_id' => $owner->id, 'status' => 'skipped', 'sent_at' => null]);
        $this->assertStringContainsString('0 accepted delivery attempt(s)', Artisan::output());
    }

    public function test_real_notification_sender_skips_a_changed_recipient_address_at_the_mail_channel(): void
    {
        $transport = $this->useRealNotificationsWithArrayMail();
        $owner = User::factory()->create();
        Store::factory()->withAquaBoltz()->create(['user_id' => $owner->id]);
        Event::listen(NotificationSending::class, function (NotificationSending $event) use ($owner) {
            if ($event->notification instanceof BoltzServiceUnavailableNotification) {
                User::query()->whereKey($owner->id)->update(['email' => 'changed@example.test']);
            }
        });

        try {
            $this->assertSame(0, Artisan::call('wallet:notify-boltz-unavailable', ['--send' => true]));
        } finally {
            Event::forget(NotificationSending::class);
        }

        $this->assertCount(0, $transport->messages());
        $this->assertDatabaseHas('boltz_service_notifications', ['user_id' => $owner->id, 'status' => 'skipped', 'sent_at' => null]);
    }

    public function test_real_notification_sender_does_not_record_a_cancelled_channel_as_sent(): void
    {
        $transport = $this->useRealNotificationsWithArrayMail();
        $owner = User::factory()->create();
        Store::factory()->withAquaBoltz()->create(['user_id' => $owner->id]);
        Event::listen(NotificationSending::class, fn () => false);

        try {
            $this->assertSame(0, Artisan::call('wallet:notify-boltz-unavailable', ['--send' => true]));
        } finally {
            Event::forget(NotificationSending::class);
        }

        $this->assertCount(0, $transport->messages());
        $this->assertDatabaseHas('boltz_service_notifications', ['user_id' => $owner->id, 'status' => 'skipped', 'sent_at' => null]);
    }

    public function test_all_five_email_locales_have_matching_keys_and_render_translated_copy(): void
    {
        $owner = User::factory()->create();
        $store = Store::factory()->withAquaBoltz()->create(['user_id' => $owner->id]);
        $notice = new BoltzServiceUnavailableNotification((int) $owner->id, [$store->id]);
        $expectedKeys = array_keys(require lang_path('en/boltz.php'));
        $originalLocale = app()->getLocale();

        try {
            foreach (BoltzServiceUnavailableNotification::SUPPORTED_LOCALES as $locale) {
                $copy = require lang_path("{$locale}/boltz.php");
                $this->assertSame($expectedKeys, array_keys($copy));
                app()->setLocale($locale);
                $mail = $notice->toMail($owner);
                $this->assertSame($copy['subject'], $mail->subject);
                $this->assertStringNotContainsString('boltz.', json_encode($mail->toArray(), JSON_THROW_ON_ERROR));
            }
        } finally {
            app()->setLocale($originalLocale);
        }
    }

    public function test_invalid_options_never_claim_or_send(): void
    {
        Store::factory()->withAquaBoltz()->create();
        $this->assertSame(2, Artisan::call('wallet:notify-boltz-unavailable', ['--send' => true, '--dry-run' => true]));
        $this->assertSame(2, Artisan::call('wallet:notify-boltz-unavailable', ['--send' => true, '--locale' => 'unknown']));
        $this->assertDatabaseCount('boltz_service_notifications', 0);
        Notification::assertNothingSent();
    }

    private function useRealNotificationsWithArrayMail(): ArrayTransport
    {
        Notification::swap(new ChannelManager($this->app));
        $transport = $this->app->make('mailer')->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);

        return $transport;
    }
}
