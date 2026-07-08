<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChoralaWidgetSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_widget_settings_returns_404_when_widget_not_configured(): void
    {
        config([
            'services.chorala.project_key' => null,
        ]);

        $this->getJson('/api/chorala/widget-settings')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Chorala widget is not configured.');
    }

    public function test_widget_settings_uses_env_when_api_key_missing(): void
    {
        config([
            'services.chorala.project_key' => 'pk_test_123',
            'services.chorala.api_key' => null,
            'services.chorala.widget_theme' => 'dark',
            'services.chorala.widget_primary_color' => '#1e3a5f',
        ]);

        $this->getJson('/api/chorala/widget-settings')
            ->assertOk()
            ->assertJsonPath('settings.theme', 'dark')
            ->assertJsonPath('settings.primaryColor', '#1e3a5f')
            ->assertJsonPath('settings.position', 'bottom-left')
            ->assertJsonPath('settings.mode', 'manual');

        Http::assertNothingSent();
    }

    public function test_widget_settings_returns_satflux_overrides_without_appearance_source(): void
    {
        config([
            'services.chorala.project_key' => 'pk_test_123',
            'services.chorala.api_key' => null,
            'services.chorala.widget_theme' => null,
            'services.chorala.widget_primary_color' => null,
        ]);

        $this->getJson('/api/chorala/widget-settings')
            ->assertOk()
            ->assertJsonPath('settings', [
                'position' => 'bottom-left',
                'mode' => 'manual',
            ]);
    }

    public function test_widget_settings_prefers_api_sync_over_env(): void
    {
        config([
            'services.chorala.project_key' => 'pk_test_123',
            'services.chorala.project_id' => 'proj_test_abc',
            'services.chorala.api_key' => 'hk_test_secret',
            'services.chorala.widget_url' => 'https://chorala.example.com',
            'services.chorala.widget_theme' => 'light',
            'services.chorala.widget_primary_color' => '#ffffff',
        ]);

        Http::fake([
            'https://chorala.example.com/api/v1/projects/proj_test_abc' => Http::response([
                'id' => 'proj_test_abc',
                'publicKey' => 'pk_test_123',
                'widgetSettings' => [
                    'theme' => 'dark',
                    'primaryColor' => '#1e3a5f',
                ],
            ], 200),
        ]);

        $this->getJson('/api/chorala/widget-settings')
            ->assertOk()
            ->assertJsonPath('settings.theme', 'dark')
            ->assertJsonPath('settings.primaryColor', '#1e3a5f');
    }

    public function test_widget_settings_falls_back_to_env_when_api_sync_fails(): void
    {
        config([
            'services.chorala.project_key' => 'pk_test_123',
            'services.chorala.project_id' => 'proj_test_abc',
            'services.chorala.api_key' => 'hk_test_secret',
            'services.chorala.widget_url' => 'https://chorala.example.com',
            'services.chorala.widget_theme' => 'dark',
            'services.chorala.widget_primary_color' => '#334155',
        ]);

        Http::fake([
            'https://chorala.example.com/api/v1/projects/proj_test_abc' => Http::response([], 500),
        ]);

        $this->getJson('/api/chorala/widget-settings')
            ->assertOk()
            ->assertJsonPath('settings.theme', 'dark')
            ->assertJsonPath('settings.primaryColor', '#334155')
            ->assertJsonPath('settings.mode', 'manual');
    }

    public function test_widget_settings_fetches_project_by_id_and_merges_satflux_overrides(): void
    {
        config([
            'services.chorala.project_key' => 'pk_test_123',
            'services.chorala.project_id' => 'proj_test_abc',
            'services.chorala.api_key' => 'hk_test_secret',
            'services.chorala.widget_url' => 'https://chorala.example.com',
        ]);

        Http::fake([
            'https://chorala.example.com/api/v1/projects/proj_test_abc' => Http::response([
                'id' => 'proj_test_abc',
                'publicKey' => 'pk_test_123',
                'widgetSettings' => [
                    'theme' => 'dark',
                    'primaryColor' => '#1e3a5f',
                    'mode' => 'floating',
                ],
            ], 200),
        ]);

        $this->getJson('/api/chorala/widget-settings')
            ->assertOk()
            ->assertJsonPath('settings.theme', 'dark')
            ->assertJsonPath('settings.primaryColor', '#1e3a5f')
            ->assertJsonPath('settings.position', 'bottom-left')
            ->assertJsonPath('settings.mode', 'manual');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://chorala.example.com/api/v1/projects/proj_test_abc'
                && $request->header('Authorization')[0] === 'Bearer hk_test_secret';
        });
    }

    public function test_widget_settings_resolves_project_by_public_key_when_id_omitted(): void
    {
        config([
            'services.chorala.project_key' => 'pk_test_123',
            'services.chorala.project_id' => null,
            'services.chorala.api_key' => 'hk_test_secret',
            'services.chorala.widget_url' => 'https://chorala.example.com',
        ]);

        Http::fake([
            'https://chorala.example.com/api/v1/projects' => Http::response([
                [
                    'id' => 'proj_other',
                    'publicKey' => 'pk_other',
                    'widgetSettings' => ['theme' => 'light'],
                ],
                [
                    'id' => 'proj_match',
                    'publicKey' => 'pk_test_123',
                    'widgetSettings' => [
                        'theme' => 'dark',
                        'primaryColor' => '#334155',
                    ],
                ],
            ], 200),
        ]);

        $this->getJson('/api/chorala/widget-settings')
            ->assertOk()
            ->assertJsonPath('settings.theme', 'dark')
            ->assertJsonPath('settings.primaryColor', '#334155')
            ->assertJsonPath('settings.mode', 'manual');
    }

    public function test_widget_settings_uses_cache_on_second_request(): void
    {
        config([
            'services.chorala.project_key' => 'pk_test_123',
            'services.chorala.project_id' => 'proj_test_abc',
            'services.chorala.api_key' => 'hk_test_secret',
            'services.chorala.widget_url' => 'https://chorala.example.com',
        ]);

        Http::fake([
            'https://chorala.example.com/api/v1/projects/proj_test_abc' => Http::response([
                'id' => 'proj_test_abc',
                'publicKey' => 'pk_test_123',
                'widgetSettings' => ['theme' => 'dark'],
            ], 200),
        ]);

        $this->getJson('/api/chorala/widget-settings')->assertOk();
        $this->getJson('/api/chorala/widget-settings')->assertOk();

        Http::assertSentCount(1);
    }
}
