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

    /**
     * Carry every writable v2.4.7 PoS setting through an unrelated name edit.
     *
     * @param  array<int, int>|null  $tipPercentages
     */
    #[DataProvider('unrepresentedFieldValues')]
    public function test_unrelated_edit_preserves_all_supported_pos_settings_and_nested_items(?array $tipPercentages, ?string $formId): void
    {
        $settings = [
            'title' => 'Original title',
            'description' => 'Original description',
            'defaultView' => 'Light',
            'showItems' => true,
            'showCustomAmount' => false,
            'showDiscount' => false,
            'showSearch' => true,
            'showCategories' => false,
            'enableTips' => true,
            'currency' => 'EUR',
            'fixedAmountPayButtonText' => '',
            'customAmountPayButtonText' => 'Custom payment',
            'tipText' => '',
            'notificationUrl' => 'https://merchant.test/notify',
            'redirectUrl' => '',
            'redirectAutomatically' => false,
            'customTipPercentages' => $tipPercentages,
            'htmlLang' => 'sk',
            'htmlMetaTags' => '<meta name="description" content="Synthetic PoS">',
            'formId' => $formId,
        ];
        $items = $this->items();
        $items[0]['customField'] = ['enabled' => false, 'values' => [0, 10]];
        $remote = $this->metadata() + $settings + [
            'archived' => true,
            'storeId' => 'synthetic-remote-store',
            'created' => 1700000000,
            'items' => $items,
        ];
        [$user, $store, $app] = $this->createPos($remote);
        $baseUrl = config('services.btcpay.base_url');
        Http::fake([
            $baseUrl.'/api/v1/apps/pos/test-pos-app' => function (Request $request) use (&$remote, $settings) {
                if ($request->method() === 'PUT') {
                    $payload = $request->data();
                    // The host replaces settings; omitted form/tip fields become null.
                    $remote = array_replace($remote, ['customTipPercentages' => null, 'formId' => null], array_intersect_key($payload, $settings));
                    $remote['appName'] = $payload['appName'];
                    $remote['items'] = json_decode($payload['template'], true);
                    if (isset($payload['archived'])) {
                        $remote['archived'] = $payload['archived'];
                    }
                }

                return Http::response($remote);
            },
        ]);

        $this->actingAs($user)->getJson("/api/stores/{$store->id}/apps/{$app->id}")->assertOk();
        $this->actingAs($user)->putJson("/api/stores/{$store->id}/apps/{$app->id}", ['name' => 'Renamed PoS'])->assertOk();

        $requests = Http::recorded(fn (Request $request): bool => $request->method() === 'PUT');
        $this->assertCount(1, $requests);
        $payload = $requests->first()[0]->data();
        foreach ($settings as $key => $value) {
            $this->assertArrayHasKey($key, $payload);
            $this->assertSame($value, $payload[$key]);
        }
        $this->assertSame($items, json_decode($payload['template'], true));
        foreach (['id', 'storeId', 'appType', 'created', 'config_read_errors'] as $key) {
            $this->assertArrayNotHasKey($key, $payload);
        }

        $response = $this->actingAs($user)->getJson("/api/stores/{$store->id}/apps/{$app->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed PoS')
            ->assertJsonPath('data.archived', true)
            ->assertJsonPath('data.config.items', $items);
        foreach ($settings as $key => $value) {
            $response->assertJsonPath('data.config.'.$key, $value);
        }
    }

    /**
     * Exercise configured values and explicit empty/null resets through the API.
     *
     * @return array<string, array{array<int, int>|null, ?string}>
     */
    public static function unrepresentedFieldValues(): array
    {
        return [
            'configured' => [[0, 5, 20], 'synthetic-form'],
            'empty' => [[], ''],
            'null reset' => [null, null],
        ];
    }

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

        $response = $this->actingAs($user)->getJson("/api/stores/{$store->id}/apps")
            ->assertOk()
            ->assertJsonPath('data.0.id', $app->id)
            ->assertJsonPath('data.0.config.showItems', true)
            ->assertJsonPath('data.0.config.enableTips', true)
            ->assertJsonPath('data.0.config.template', $this->items());
        $this->assertArrayNotHasKey('config_read_errors', $response->json('data.0'));
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
            ->assertJsonPath('data.config.template', $this->items())
            ->assertJsonPath('data.config_read_errors', ['settings', 'products']);

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
            ->assertJsonPath('data.config.template', $this->items())
            ->assertJsonPath('data.config_read_errors', ['settings', 'products']);

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
        [, , $app] = $this->createPos($this->completePosConfig());

        $raw = array_replace($this->completePosConfig(), ['items' => $source]);
        $data = app(AppResponseFormatter::class)->format($app, $this->metadata() + $raw);

        $this->assertSame($this->items(), $data['config']['items']);
        $this->assertSame($this->items(), $data['config']['template']);
        $this->assertSame(['products'], $data['config_read_errors']);
        $this->assertSame($this->items(), $app->fresh()->config['items']);
    }

    /**
     * Cached completeness cannot confirm fields absent or malformed in a live read.
     *
     * @param  array<string, mixed>  $overrides
     * @param  list<string>  $missing
     * @param  list<string>  $errors
     */
    #[DataProvider('incompleteLiveConfigs')]
    public function test_incomplete_live_details_remain_unconfirmed_with_a_complete_cache(array $overrides, array $missing, array $errors): void
    {
        [$user, $store, $app] = $this->createPos($this->completePosConfig());
        $raw = array_replace($this->completePosConfig(), $overrides);
        foreach ($missing as $key) {
            unset($raw[$key]);
        }
        $baseUrl = config('services.btcpay.base_url');
        Http::fake([$baseUrl.'/api/v1/apps/pos/test-pos-app' => Http::response($this->metadata() + $raw)]);

        $this->actingAs($user)->getJson("/api/stores/{$store->id}/apps/{$app->id}")
            ->assertOk()
            ->assertJsonPath('data.config_read_errors', $errors)
            ->assertJsonPath('data.config.items', $this->items());
        $this->assertArrayNotHasKey('config_read_errors', $app->fresh()->config);
    }

    /**
     * Validate both submitted aliases before fallback merging can hide bad input.
     */
    #[DataProvider('invalidProductSources')]
    public function test_malformed_submitted_product_aliases_return_422_without_requests_or_cache_writes(mixed $source): void
    {
        $cached = $this->completePosConfig();
        [$user, $store, $app] = $this->createPos($cached);
        $originalName = $app->name;
        Http::fake(['*' => Http::response($this->metadata() + $cached)]);

        foreach (['items' => 'template', 'template' => 'items'] as $alias => $other) {
            $this->actingAs($user)->putJson("/api/stores/{$store->id}/apps/{$app->id}", [
                'name' => 'Must not persist',
                'config' => [$alias => $source, $other => $this->items()],
            ])->assertUnprocessable()->assertJsonValidationErrors('config.'.$alias);
            $this->assertSame($cached, $app->fresh()->config);
            $this->assertSame($originalName, $app->fresh()->name);
        }
        Http::assertNothingSent();
    }

    /**
     * Legacy updates must reject both malformed aliases before creating a remote app.
     */
    #[DataProvider('invalidProductSources')]
    public function test_legacy_malformed_submitted_aliases_return_422_without_creation_or_local_writes(mixed $source): void
    {
        [$user, $store, $app] = $this->createPos($this->completePosConfig());
        $app->update(['btcpay_app_id' => '']);
        $before = $app->fresh()->getRawOriginal();
        Http::fake(['*' => Http::response($this->metadata() + $this->completePosConfig())]);

        foreach (['items' => 'template', 'template' => 'items'] as $alias => $other) {
            $this->actingAs($user)->putJson("/api/stores/{$store->id}/apps/{$app->id}", [
                'name' => 'Must not persist',
                'config' => [$alias => $source, $other => $this->items()],
            ])->assertUnprocessable()->assertJsonValidationErrors('config.'.$alias);
            $this->assertSame($before, $app->fresh()->getRawOriginal());
        }
        Http::assertNothingSent();
    }

    /**
     * Legacy creation must serialize valid product aliases into the supported request.
     *
     * @param  list<array<string, mixed>>  $expected
     */
    #[DataProvider('validSubmittedCatalogs')]
    public function test_legacy_valid_submitted_aliases_preserve_lists_and_explicit_clears(string $alias, mixed $source, array $expected): void
    {
        $cached = $this->completePosConfig();
        $cached[$alias === 'items' ? 'template' : 'items'] = [[]];
        [$user, $store, $app] = $this->createPos($cached);
        $app->update(['btcpay_app_id' => '']);
        $baseUrl = config('services.btcpay.base_url');
        Http::fake([
            $baseUrl."/api/v1/stores/{$store->btcpay_store_id}/apps/pos" => function (Request $request) {
                $template = $request['template'];
                $items = is_string($template) ? json_decode($template, true) : [];

                return Http::response($this->metadata() + array_replace($this->completePosConfig(), ['items' => $items]), 201);
            },
        ]);

        $this->actingAs($user)->putJson("/api/stores/{$store->id}/apps/{$app->id}", ['config' => [$alias => $source]])
            ->assertOk()->assertJsonPath('data.config.items', $expected);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && is_string($request['template'])
            && json_decode($request['template'], true) === $expected
            && ! array_key_exists('items', $request->data()));
        $this->assertSame('test-pos-app', $app->fresh()->btcpay_app_id);
        $this->assertSame($expected, $app->fresh()->config['items']);
    }

    /**
     * Preserve a usable cached catalog when legacy creation only changes the name.
     */
    public function test_legacy_name_only_update_preserves_cached_products(): void
    {
        [$user, $store, $app] = $this->createPos($this->completePosConfig());
        $app->update(['btcpay_app_id' => '']);
        Http::fake(['*' => Http::response($this->metadata() + $this->completePosConfig(), 201)]);

        $this->actingAs($user)->putJson("/api/stores/{$store->id}/apps/{$app->id}", ['name' => 'Renamed legacy PoS'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed legacy PoS')
            ->assertJsonPath('data.config.items', $this->items());
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request['appName'] === 'Renamed legacy PoS'
            && is_string($request['template'])
            && json_decode($request['template'], true) === $this->items()
            && ! array_key_exists('items', $request->data()));
        Http::assertSentCount(1);
    }

    /**
     * Valid serialized lists and deliberate empty lists retain their update meaning.
     *
     * @param  list<array<string, mixed>>  $expected
     */
    #[DataProvider('validSubmittedCatalogs')]
    public function test_valid_submitted_product_aliases_preserve_extras_and_explicit_clears(string $alias, mixed $source, array $expected): void
    {
        [$user, $store, $app] = $this->createPos($this->completePosConfig());
        $remote = $this->metadata() + $this->completePosConfig();
        $baseUrl = config('services.btcpay.base_url');
        Http::fake([
            $baseUrl.'/api/v1/apps/pos/test-pos-app' => function (Request $request) use (&$remote) {
                if ($request->method() === 'PUT') {
                    $remote['items'] = json_decode($request['template'], true);
                }

                return Http::response($remote);
            },
        ]);

        $response = $this->actingAs($user)->putJson("/api/stores/{$store->id}/apps/{$app->id}", ['config' => [$alias => $source]])
            ->assertOk()->assertJsonPath('data.config.items', $expected);
        $this->assertArrayNotHasKey('config_read_errors', $response->json('data'));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && json_decode($request['template'], true) === $expected);
        $this->assertSame($expected, $app->fresh()->config['items']);
    }

    /**
     * Confirm explicit false flags and an empty catalog without consulting cache.
     */
    public function test_complete_live_false_flags_and_empty_catalog_are_confirmed(): void
    {
        [, , $app] = $this->createPos($this->completePosConfig());
        $raw = array_fill_keys(['showItems', 'showCustomAmount', 'showDiscount', 'showSearch', 'showCategories', 'enableTips'], false);
        $raw += ['defaultView' => 'Light', 'items' => []];

        $data = app(AppResponseFormatter::class)->format($app, $this->metadata() + $raw);

        $this->assertArrayNotHasKey('config_read_errors', $data);
        $this->assertFalse($data['config']['showItems']);
        $this->assertSame([], $data['config']['items']);
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
        [, , $app] = $this->createPos($this->completePosConfig());
        $raw = $this->completePosConfig();
        unset($raw['items']);

        $data = app(AppResponseFormatter::class)->format($app, $this->metadata() + ['template' => '[]'] + $raw);

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
        $cases = [
            'null' => [null],
            'empty string' => [''],
            'invalid JSON' => ['invalid-json'],
            'JSON object' => ['{}'],
            'scalar' => [false],
            'object-shaped array' => [['id' => 'coffee']],
        ];
        foreach ([
            'missing title' => [],
            'empty title' => ['title' => ''],
            'ASCII blank title' => ['title' => " \t\r\n"],
            'Unicode blank title' => ['title' => "\u{00a0}\u{feff}"],
            'null title' => ['title' => null],
            'numeric title' => ['title' => 0],
            'array title' => ['title' => []],
        ] as $label => $item) {
            $cases[$label.' array'] = [[$item]];
            $cases[$label.' serialized'] = [json_encode([$item], JSON_THROW_ON_ERROR)];
        }

        return $cases;
    }

    /**
     * Exercise every required raw setting plus missing or unreadable products.
     *
     * @return array<string, array{array<string, mixed>, list<string>, list<string>}>
     */
    public static function incompleteLiveConfigs(): array
    {
        $flags = ['showItems', 'showCustomAmount', 'showDiscount', 'showSearch', 'showCategories', 'enableTips'];
        $cases = [
            'missing view' => [[], ['defaultView'], ['settings']],
            'invalid view' => [['defaultView' => 'Unsupported'], [], ['settings']],
            'numeric flag' => [['showItems' => 0], [], ['settings']],
            'string flag' => [['showItems' => 'false'], [], ['settings']],
            'missing products' => [[], ['items'], ['products']],
            'invalid products' => [['items' => [[]]], [], ['products']],
            'metadata only' => [[], ['defaultView', ...$flags, 'items'], ['settings', 'products']],
        ];
        foreach ($flags as $flag) {
            $cases['missing '.$flag] = [[], [$flag], ['settings']];
            $cases['null '.$flag] = [[$flag => null], [], ['settings']];
        }

        return $cases;
    }

    /**
     * Accept one canonical product list representation without passing through fields.
     *
     * @return array<string, array{string, mixed, list<array<string, mixed>>}>
     */
    public static function validSubmittedCatalogs(): array
    {
        $items = [['id' => 'coffee', 'title' => 'Coffee', 'price' => 0, 'custom' => ['keep' => false]]];
        $cases = [];
        foreach (['items', 'template'] as $alias) {
            $cases[$alias.' list'] = [$alias, $items, $items];
            $cases[$alias.' serialized'] = [$alias, json_encode($items, JSON_THROW_ON_ERROR), $items];
            $cases[$alias.' empty'] = [$alias, [], []];
            $cases[$alias.' serialized empty'] = [$alias, '[]', []];
        }

        return $cases;
    }

    /**
     * Supply a complete canonical raw detail independently of local fallback values.
     *
     * @return array<string, mixed>
     */
    private function completePosConfig(): array
    {
        return [
            'defaultView' => 'Light',
            'showItems' => true,
            'showCustomAmount' => false,
            'showDiscount' => false,
            'showSearch' => true,
            'showCategories' => true,
            'enableTips' => true,
            'items' => $this->items(),
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
