<?php

namespace Tests\Feature;

use App\Models\App;
use App\Models\Store;
use App\Models\User;
use App\Services\AppResponseFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PosAppRoundtripTest extends TestCase
{
    use RefreshDatabase;

    public function test_metadata_only_list_response_preserves_cached_pos_settings(): void
    {
        [$user, $store, $app] = $this->createPos([
            'defaultView' => 'Light',
            'showItems' => true,
            'enableTips' => true,
            'template' => $this->items(),
        ]);

        $baseUrl = config('services.btcpay.base_url');
        Http::fake([
            $baseUrl."/api/v1/stores/{$store->btcpay_store_id}/apps" => Http::response([$this->metadata()]),
        ]);

        $this->actingAs($user)->getJson("/api/stores/{$store->id}/apps")
            ->assertOk()
            ->assertJsonPath('data.0.id', $app->id)
            ->assertJsonPath('data.0.config.showItems', true)
            ->assertJsonPath('data.0.config.enableTips', true)
            ->assertJsonPath('data.0.config.template', $this->items());
    }

    public function test_submitted_pos_settings_survive_partial_update_and_detail_responses(): void
    {
        [$user, $store, $app] = $this->createPos([
            'showItems' => false,
            'enableTips' => false,
            'items' => [['id' => 'old-product', 'title' => 'Old product']],
            'unsupportedSetting' => ['keep' => true],
        ]);
        $baseUrl = config('services.btcpay.base_url');
        Http::fake([
            $baseUrl.'/api/v1/apps/pos/test-pos-app' => Http::response($this->metadata()),
        ]);

        $this->actingAs($user)->putJson("/api/stores/{$store->id}/apps/{$app->id}", [
            'config' => [
                'defaultView' => 'Light',
                'showItems' => true,
                'enableTips' => true,
                'currency' => 'EUR',
                'tipTaxRate' => null,
                'template' => $this->items(),
            ],
        ])->assertOk()
            ->assertJsonPath('data.config.showItems', true)
            ->assertJsonPath('data.config.enableTips', true)
            ->assertJsonPath('data.config.template', $this->items());

        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && $request['showItems'] === true
            && $request['enableTips'] === true
            && json_decode($request['template'], true) === $this->items()
            && $request->hasHeader('Authorization', 'Bearer '.$user->getBtcPayApiKeyOrFail()));

        $this->actingAs($user)->getJson("/api/stores/{$store->id}/apps/{$app->id}")
            ->assertOk()
            ->assertJsonPath('data.config.defaultView', 'Light')
            ->assertJsonPath('data.config.showItems', true)
            ->assertJsonPath('data.config.enableTips', true)
            ->assertJsonPath('data.config.tipTaxRate', null)
            ->assertJsonPath('data.config.unsupportedSetting.keep', true)
            ->assertJsonPath('data.config.template', $this->items());

        $config = $app->fresh()->config;
        $this->assertSame($this->items(), $config['template']);
        $this->assertArrayNotHasKey('items', $config);
    }

    public function test_fresh_false_and_empty_items_override_cached_values_without_resurrecting_products(): void
    {
        [, , $app] = $this->createPos([
            'showItems' => true,
            'enableTips' => true,
            'items' => $this->items(),
            'template' => $this->items(),
            'tipTaxRate' => '10',
        ]);
        $fresh = $this->metadata() + [
            'showItems' => false,
            'enableTips' => false,
            'tipTaxRate' => null,
            'items' => [],
        ];

        $data = app(AppResponseFormatter::class)->format($app, $fresh);

        $this->assertFalse($data['config']['showItems']);
        $this->assertFalse($data['config']['enableTips']);
        $this->assertNull($data['config']['tipTaxRate']);
        $this->assertSame([], $data['config']['items']);
        $this->assertSame([], $data['config']['template']);
        $stored = $app->fresh()->config;
        $this->assertSame([], $stored['items']);
        $this->assertArrayNotHasKey('template', $stored);
    }

    public function test_pinned_btcpay_detail_shape_preserves_item_fields_and_replaces_the_whole_list(): void
    {
        [, , $app] = $this->createPos([
            'showItems' => false,
            'enableTips' => false,
            'requestCustomerData' => 'email',
            'template' => $this->items(),
        ]);
        // BTCPay 2.4.7 PointOfSaleAppData: flat settings, items array and numeric strings.
        $items = [$this->items()[1] + ['customField' => ['retained' => true]]];
        $fresh = $this->metadata() + [
            'defaultView' => 'Light',
            'showItems' => true,
            'enableTips' => true,
            'currency' => 'EUR',
            'items' => $items,
        ];

        $data = app(AppResponseFormatter::class)->format($app, $fresh);

        $this->assertTrue($data['config']['showItems']);
        $this->assertTrue($data['config']['enableTips']);
        $this->assertSame('email', $data['config']['requestCustomerData']);
        $this->assertSame($items, $data['config']['items']);
        $this->assertSame($items, $data['config']['template']);
        $this->assertSame($items, $app->fresh()->config['items']);
    }

    #[DataProvider('invalidProductSources')]
    public function test_invalid_product_reads_retain_cached_products_and_report_incomplete_config(mixed $source): void
    {
        [, , $app] = $this->createPos(['items' => $this->items(), 'showItems' => true]);

        $data = app(AppResponseFormatter::class)->format($app, $this->metadata() + ['items' => $source]);

        $this->assertSame($this->items(), $data['config']['items']);
        $this->assertSame($this->items(), $data['config']['template']);
        $this->assertSame(['products'], $data['config_read_errors']);
        $this->assertSame($this->items(), $app->fresh()->config['items']);
    }

    public function test_partial_update_serializes_cached_detail_items_back_to_template(): void
    {
        [$user, $store, $app] = $this->createPos([
            'defaultView' => 'Light',
            'currency' => 'EUR',
            'showItems' => true,
            'enableTips' => true,
            'items' => $this->items(),
        ]);
        $baseUrl = config('services.btcpay.base_url');
        Http::fake([
            $baseUrl.'/api/v1/apps/pos/test-pos-app' => Http::response($this->metadata()),
        ]);

        $this->actingAs($user)->putJson("/api/stores/{$store->id}/apps/{$app->id}", ['name' => 'Renamed PoS'])
            ->assertOk()
            ->assertJsonPath('data.config.template', $this->items());

        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && $request['showItems'] === true
            && $request['enableTips'] === true
            && isset($request['template'])
            && json_decode($request['template'], true) === $this->items());
    }

    public function test_serialized_empty_template_intentionally_clears_cached_items(): void
    {
        [, , $app] = $this->createPos(['items' => $this->items()]);

        $data = app(AppResponseFormatter::class)->format($app, $this->metadata() + ['template' => '[]']);

        $this->assertSame([], $data['config']['template']);
        $this->assertArrayNotHasKey('items', $data['config']);
        $this->assertArrayNotHasKey('config_read_errors', $data);
        $this->assertSame('[]', $app->fresh()->config['template']);
    }

    public function test_failed_detail_read_keeps_cached_display_but_reports_unconfirmed_settings(): void
    {
        [$user, $store, $app] = $this->createPos([
            'defaultView' => 'Light',
            'showItems' => true,
            'enableTips' => true,
            'items' => $this->items(),
        ]);
        $baseUrl = config('services.btcpay.base_url');
        Http::fake([
            $baseUrl.'/api/v1/apps/pos/test-pos-app' => Http::response(['message' => 'Forbidden'], 403),
        ]);

        $this->actingAs($user)->getJson("/api/stores/{$store->id}/apps/{$app->id}")
            ->assertOk()
            ->assertJsonPath('data.config.showItems', true)
            ->assertJsonPath('data.config.enableTips', true)
            ->assertJsonPath('data.config.template', $this->items())
            ->assertJsonPath('data.config_read_errors', ['settings']);

        $this->assertArrayNotHasKey('config_read_errors', $app->fresh()->config);
        Http::assertSentCount(1);
    }

    public function test_missing_remote_id_reports_unconfirmed_settings_without_a_remote_request(): void
    {
        [$user, $store, $app] = $this->createPos(['showItems' => true, 'items' => $this->items()]);
        $app->update(['btcpay_app_id' => '']);

        $this->actingAs($user)->getJson("/api/stores/{$store->id}/apps/{$app->id}")
            ->assertOk()
            ->assertJsonPath('data.config.template', $this->items())
            ->assertJsonPath('data.config_read_errors', ['settings']);

        Http::assertNothingSent();
    }

    #[DataProvider('tipTexts')]
    public function test_fresh_canonical_tip_text_replaces_stale_local_alias(?string $text): void
    {
        [, , $app] = $this->createPos(['tipsMessage' => 'Stale local message']);

        $data = app(AppResponseFormatter::class)->format($app, $this->metadata() + ['tipText' => $text]);

        $this->assertSame($text, $data['config']['tipText']);
        $this->assertArrayNotHasKey('tipsMessage', $data['config']);
        $this->assertSame($text, $app->fresh()->config['tipText']);
    }

    #[DataProvider('tipTexts')]
    public function test_partial_update_keeps_canonical_tip_text_and_legacy_edit_takes_priority(?string $text): void
    {
        [$user, $store, $app] = $this->createPos([
            'tipText' => $text,
            'tipsMessage' => 'Stale local message',
            'items' => [],
        ]);
        $baseUrl = config('services.btcpay.base_url');
        Http::fake([
            $baseUrl.'/api/v1/apps/pos/test-pos-app' => Http::response($this->metadata()),
        ]);

        $this->actingAs($user)->putJson("/api/stores/{$store->id}/apps/{$app->id}", ['name' => 'Renamed PoS'])
            ->assertOk();
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && array_key_exists('tipText', $request->data())
            && $request['tipText'] === $text);

        $this->actingAs($user)->putJson("/api/stores/{$store->id}/apps/{$app->id}", [
            'config' => ['tipsMessage' => 'Newly edited message'],
        ])->assertOk()->assertJsonPath('data.config.tipsMessage', 'Newly edited message');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && $request['tipText'] === 'Newly edited message');
    }

    /**
     * Preserve explicit nullable and empty tip text independently of omission.
     *
     * @return array<string, array{?string}>
     */
    public static function tipTexts(): array
    {
        return ['null' => [null], 'empty' => [''], 'custom text' => ['Current remote message']];
    }

    /**
     * Keep malformed sources distinct from an intentional empty product list.
     *
     * @return array<string, array{mixed}>
     */
    public static function invalidProductSources(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'invalid JSON' => ['invalid-json'],
            'JSON object' => ['{}'],
            'scalar' => [false],
            'object-shaped array' => [['id' => 'coffee']],
        ];
    }

    /**
     * Create a synthetic merchant-owned PoS without contacting BTCPay.
     *
     * @param  array<string, mixed>  $config
     * @return array{User, Store, App}
     */
    private function createPos(array $config): array
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);
        $app = App::factory()->create([
            'store_id' => $store->id,
            'btcpay_app_id' => 'test-pos-app',
            'app_type' => 'PointOfSale',
            'config' => $config,
        ]);

        return [$user, $store, $app];
    }

    /**
     * Supply the metadata-only shape returned by Greenfield app lists.
     *
     * @return array<string, mixed>
     */
    private function metadata(): array
    {
        return ['id' => 'test-pos-app', 'appName' => 'Test PoS', 'appType' => 'PointOfSale'];
    }

    /**
     * Supply safe examples of nullable and numeric-string Greenfield item fields.
     *
     * @return array<int, array<string, mixed>>
     */
    private function items(): array
    {
        return [
            ['id' => 'coffee', 'title' => 'Coffee', 'priceType' => 'Fixed', 'price' => '2.50', 'disabled' => false, 'taxRate' => null, 'inventory' => null, 'categories' => null],
            ['id' => 'tea', 'title' => 'Tea', 'priceType' => 'Fixed', 'price' => '1.75', 'disabled' => false, 'taxRate' => '20', 'inventory' => 0, 'categories' => []],
        ];
    }
}
