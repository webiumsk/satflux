<?php

namespace Tests\Feature;

use App\Mail\SystemHealthAlertMail;
use App\Models\SystemHealthSnapshot;
use App\Models\User;
use App\Support\ErrorRateCounter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SystemMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function fakeHealthy(): void
    {
        Http::fake([
            '*/api/v1/health' => Http::response('', 200),
            '*/usage/*' => Http::response('{}', 404),
        ]);
    }

    protected function fakeBtcpayDown(): void
    {
        Http::fake([
            '*/api/v1/health' => Http::response('', 503),
            '*/usage/*' => Http::response('{}', 404),
        ]);
    }

    #[Test]
    public function the_command_persists_a_snapshot_and_prunes_old_ones(): void
    {
        $this->fakeHealthy();
        SystemHealthSnapshot::create([
            'healthy' => true,
            'checks' => [],
            'created_at' => now()->subDays(30),
        ]);

        $this->artisan('system:health-check')->assertSuccessful();

        $this->assertSame(1, SystemHealthSnapshot::query()->count());
        $snapshot = SystemHealthSnapshot::query()->sole();
        $this->assertTrue($snapshot->healthy);
        $this->assertArrayHasKey('database', $snapshot->checks);
    }

    #[Test]
    public function a_failing_check_alerts_once_per_throttle_window_and_recovers(): void
    {
        config(['monitoring.alert_email' => 'admin@example.com']);
        Mail::fake();

        // Two failing runs, then a healthy one (Http::fake stubs cannot be
        // replaced mid-test - a sequence models the transition).
        Http::fake([
            '*/api/v1/health' => Http::sequence()
                ->push('', 503)
                ->push('', 503)
                ->push('', 200),
            '*/usage/*' => Http::response('{}', 404),
        ]);

        $this->artisan('system:health-check')->assertFailed();
        $this->artisan('system:health-check')->assertFailed();

        // One alert despite two failing runs (60 min throttle).
        Mail::assertSent(SystemHealthAlertMail::class, 1);
        Mail::assertSent(SystemHealthAlertMail::class, function (SystemHealthAlertMail $mail) {
            return in_array('btcpay', $mail->failed, true) && $mail->recovered === [];
        });

        // Recovery produces exactly one recovery notification.
        $this->artisan('system:health-check')->assertSuccessful();

        Mail::assertSent(SystemHealthAlertMail::class, 2);
        Mail::assertSent(SystemHealthAlertMail::class, function (SystemHealthAlertMail $mail) {
            return in_array('btcpay', $mail->recovered, true) && $mail->failed === [];
        });
    }

    #[Test]
    public function without_an_alert_address_transitions_are_logged_only(): void
    {
        config(['monitoring.alert_email' => null]);
        Mail::fake();
        $this->fakeBtcpayDown();

        $this->artisan('system:health-check')->assertFailed();

        Mail::assertNothingSent();
    }

    #[Test]
    public function error_rate_counter_counts_only_error_levels_and_flips_the_check(): void
    {
        config(['monitoring.error_rate_threshold' => 2]);
        $this->fakeHealthy();

        Log::info('harmless');
        $this->assertSame(0, ErrorRateCounter::currentHourCount());

        Log::error('boom one');
        Log::critical('boom two');
        $this->assertSame(2, ErrorRateCounter::currentHourCount());

        $admin = User::factory()->admin()->create();
        $response = $this->actingAs($admin)->getJson('/api/admin/system-health')->assertOk();
        $response->assertJsonPath('data.checks.errors.ok', false);
    }

    #[Test]
    public function history_lists_recent_snapshots_for_admins_only(): void
    {
        SystemHealthSnapshot::create([
            'healthy' => false,
            'checks' => ['database' => ['ok' => false, 'detail' => 'x', 'duration_ms' => 1]],
            'created_at' => now(),
        ]);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->getJson('/api/admin/system-health/history')
            ->assertOk()
            ->assertJsonPath('data.0.healthy', false);

        $user = User::factory()->create();
        $this->actingAs($user)
            ->getJson('/api/admin/system-health/history')
            ->assertForbidden();
    }
}
