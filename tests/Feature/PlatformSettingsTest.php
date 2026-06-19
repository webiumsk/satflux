<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\PlatformSettingsRepository;
use App\Support\PlatformSettingsSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlatformSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $support;

    protected User $merchant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->support = User::factory()->create(['role' => 'support']);
        $this->merchant = User::factory()->create(['role' => 'free']);
    }

    #[Test]
    public function admin_can_view_platform_settings(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/admin/platform-settings')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'groups',
                    'fields',
                    'values',
                ],
            ])
            ->assertJsonPath('data.groups.0', PlatformSettingsSchema::GROUP_AUTH);
    }

    #[Test]
    public function non_admin_cannot_view_platform_settings(): void
    {
        $this->actingAs($this->merchant)
            ->getJson('/api/admin/platform-settings')
            ->assertForbidden();

        $this->actingAs($this->support)
            ->getJson('/api/admin/platform-settings')
            ->assertForbidden();
    }

    #[Test]
    public function admin_can_update_platform_settings(): void
    {
        $response = $this->actingAs($this->admin)
            ->patchJson('/api/admin/platform-settings', [
                'efaktura.enabled' => true,
                'guest.purge_enabled' => true,
                'guest.idle_days' => 120,
            ]);

        $response->assertOk();
        $values = $response->json('data.values');
        $this->assertTrue($values['efaktura.enabled']);
        $this->assertTrue($values['guest.purge_enabled']);
        $this->assertSame(120, $values['guest.idle_days']);

        $this->assertDatabaseHas('platform_settings', [
            'key' => 'efaktura.enabled',
        ]);
    }

    #[Test]
    public function secrets_are_redacted_and_can_be_updated_write_only(): void
    {
        app(PlatformSettingsRepository::class)->updateMany([
            'services.openregistry.bearer_token' => 'existing-token-value',
        ], $this->admin);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/platform-settings');

        $response->assertOk()
            ->assertJsonMissingPath('data.values.services.openregistry.bearer_token')
            ->assertJsonPath('data.values.services_openregistry_bearer_token_set', true);

        $this->actingAs($this->admin)
            ->patchJson('/api/admin/platform-settings', [
                'services.openregistry.bearer_token' => 'rotated-token-value',
            ])
            ->assertOk()
            ->assertJsonPath('data.values.services_openregistry_bearer_token_set', true);

        $stored = app(PlatformSettingsRepository::class)->all();
        $this->assertSame('rotated-token-value', $stored['services.openregistry.bearer_token']);
    }

    #[Test]
    public function empty_secret_patch_keeps_existing_value(): void
    {
        app(PlatformSettingsRepository::class)->updateMany([
            'bank_inbound.webhook_secret' => 'keep-me',
        ], $this->admin);

        $this->actingAs($this->admin)
            ->patchJson('/api/admin/platform-settings', [
                'efaktura.enabled' => false,
                'bank_inbound.webhook_secret' => '',
            ])
            ->assertOk();

        $stored = app(PlatformSettingsRepository::class)->all();
        $this->assertSame('keep-me', $stored['bank_inbound.webhook_secret']);
    }

    #[Test]
    public function config_overlay_reflects_database_values(): void
    {
        PlatformSetting::query()->create([
            'key' => 'efaktura.enabled',
            'value' => json_encode(true, JSON_THROW_ON_ERROR),
        ]);

        app(PlatformSettingsRepository::class)->flushCache();
        app(PlatformSettingsRepository::class)->applyToConfig();

        $this->assertTrue((bool) config('efaktura.enabled'));
    }

    #[Test]
    public function import_env_command_imports_registered_keys(): void
    {
        $envPath = 'storage/framework/testing/platform-settings-import.env';
        $fullPath = base_path($envPath);
        if (! is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }
        file_put_contents($fullPath, implode("\n", [
            'EFAKTURA_ENABLED=true',
            'GUEST_PURGE_IDLE_DAYS=45',
            'APP_KEY=should-not-import',
            'DB_PASSWORD=should-not-import',
        ]));

        $this->artisan('platform-settings:import-env', ['--file' => $envPath])
            ->assertSuccessful();

        $stored = app(PlatformSettingsRepository::class)->all();
        $this->assertTrue($stored['efaktura.enabled']);
        $this->assertSame(45, $stored['guest.idle_days']);
        $this->assertArrayNotHasKey('app.key', $stored);

        @unlink($fullPath);
    }

    #[Test]
    public function export_signed_url_ttl_uses_config_key(): void
    {
        Config::set('exports.signed_url_ttl', 7200);
        $this->assertSame(7200, (int) config('exports.signed_url_ttl'));
    }
}
