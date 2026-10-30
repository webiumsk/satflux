<?php

namespace Tests\Feature;

use App\Models\App;
use App\Models\Store;
use App\Models\User;
use App\Services\BtcPay\BtcPayClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class Btcpay247ValidationTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): Store
    {
        config(['services.btcpay.base_url' => 'https://btcpay.test']);
        $this->app->forgetInstance(BtcPayClient::class);
        Cache::flush();
        $owner = User::factory()->create(['btcpay_api_key' => 'synthetic-merchant-key', 'role' => 'admin']);
        $store = Store::factory()->create(['user_id' => $owner->id, 'btcpay_store_id' => 'synthetic-store']);
        $this->actingAs($owner);

        return $store;
    }

    #[DataProvider('storeValues')]
    public function test_store_settings_validate_seconds_and_asset_urls_before_forwarding(string $field, mixed $value, bool $accepted): void
    {
        $store = $this->fixture();
        Http::fake(['*' => Http::response(['id' => 'synthetic-store'], 200)]);
        $response = $this->putJson("/api/stores/{$store->id}/settings", [
            'name' => 'Synthetic store', 'default_currency' => 'EUR', 'timezone' => 'UTC', $field => $value,
        ]);

        if (! $accepted) {
            $response->assertUnprocessable()->assertJsonValidationErrors($field);
            Http::assertNothingSent();

            return;
        }

        $response->assertOk();
        $wireFields = [
            'invoice_expiration' => 'invoiceExpiration', 'display_expiration_timer' => 'displayExpirationTimer',
            'monitoring_expiration' => 'monitoringExpiration', 'css_url' => 'cssUrl', 'payment_sound_url' => 'paymentSoundUrl',
        ];
        Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
            && $request->hasHeader('Authorization', 'Bearer synthetic-merchant-key')
            && $request[$wireFields[$field]] === ($value === '' ? null : $value));
    }

    public static function storeValues(): array
    {
        $cases = [];
        foreach (['invoice_expiration' => 60, 'display_expiration_timer' => 60, 'monitoring_expiration' => 600] as $field => $minimum) {
            foreach ([$minimum - 1 => false, $minimum => true, 2073600 => true, 2073601 => false] as $value => $accepted) {
                $cases[$field.' '.$value] = [$field, $value, $accepted];
            }
            $cases[$field.' null'] = [$field, null, true];
            $cases[$field.' empty'] = [$field, '', true];
        }
        foreach ([
            'https://example.test/theme.css' => true, 'http://example.test/theme.css?v=2' => true,
            'ftp://example.test/theme.css' => false, 'javascript:alert(1)' => false,
            'data:text/css,body{}' => false, '/relative.css' => false, '' => true,
        ] as $url => $accepted) {
            $cases['css '.$url] = ['css_url', $url, $accepted];
        }
        foreach ([
            'https://example.test/paid.mp3' => true, 'http://example.test/paid.mp3?v=2' => true,
            'ftp://example.test/paid.mp3' => false, 'javascript:alert(1)' => false,
            'data:audio/mpeg;base64,AAAA' => false, '/paid.mp3' => false, '' => true,
            'https://example.test/'.str_repeat('a', 480).'.mp3' => false,
        ] as $url => $accepted) {
            $cases['payment sound '.$url] = ['payment_sound_url', $url, $accepted];
        }
        $cases['payment sound null'] = ['payment_sound_url', null, true];

        return $cases;
    }

    public function test_crowdfund_creation_maps_weeks_without_mutating_other_fields(): void
    {
        $store = $this->fixture();
        Http::fake(['*' => Http::response(['id' => 'new-crowdfund'], 201)]);
        $this->postJson("/api/stores/{$store->id}/apps", [
            'app_type' => 'Crowdfund', 'name' => 'Synthetic campaign',
            'config' => ['resetEvery' => 'Week', 'resetEveryAmount' => 2, 'startDate' => 1793491200, 'title' => 'Unchanged title'],
        ])->assertCreated();

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request['resetEvery'] === 'Day' && $request['resetEveryAmount'] === 14
            && $request['title'] === 'Unchanged title');
    }

    public function test_invalid_recurrence_never_writes_a_new_remote_app(): void
    {
        $store = $this->fixture();
        Http::fake(['*' => Http::response([], 200)]);
        $this->postJson("/api/stores/{$store->id}/apps", [
            'app_type' => 'Crowdfund', 'name' => 'Synthetic campaign',
            'config' => ['resetEvery' => 'Year', 'resetEveryAmount' => 8000, 'startDate' => 1793491200],
        ])->assertUnprocessable()->assertJsonValidationErrors('config.resetEveryAmount');
        Http::assertNothingSent();
        $this->assertDatabaseCount('apps', 0);
    }

    #[DataProvider('partialCrowdfundUpdates')]
    public function test_partial_crowdfund_update_keeps_existing_numeric_interval(array $delta, string $title, int $amount): void
    {
        $store = $this->fixture();
        $config = ['title' => 'Old title', 'resetEvery' => 'Day', 'resetEveryAmount' => 5, 'startDate' => 1793491200];
        $app = App::factory()->crowdfund()->create([
            'store_id' => $store->id, 'btcpay_app_id' => 'existing-crowdfund', 'config' => $config,
        ]);
        Http::fake(fn (Request $request) => Http::response(array_merge($config, ['id' => 'existing-crowdfund']), 200));

        $this->putJson("/api/stores/{$store->id}/apps/{$app->id}", ['config' => $delta])->assertOk();
        Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
            && $request['title'] === $title && $request['resetEveryAmount'] === $amount && $request['resetEvery'] === 'Day');
    }

    public static function partialCrowdfundUpdates(): array
    {
        return [
            'only title' => [['title' => 'New title'], 'New title', 5],
            'only amount' => [['resetEveryAmount' => 3], 'Old title', 3],
        ];
    }

    #[DataProvider('redirectValues')]
    public function test_ticket_update_redirect_is_absolute_http_or_https(string $url, bool $accepted): void
    {
        $store = $this->fixture();
        Http::fake(['*' => Http::response(['id' => 'event', 'redirectUrl' => $url], 200)]);
        $response = $this->putJson("/api/stores/{$store->id}/tickets/events/event", ['redirectUrl' => $url]);
        if (! $accepted) {
            $response->assertUnprocessable()->assertJsonValidationErrors('redirectUrl');
            Http::assertNothingSent();

            return;
        }
        $response->assertOk();
        Http::assertSent(fn (Request $request) => $request->method() === 'PUT' && $request['redirectUrl'] === $url);
    }

    public static function redirectValues(): array
    {
        return [
            ['https://example.test/thanks?order=2&token=synthetic', true],
            ['http://example.test/thanks', true],
            ['ftp://example.test/thanks', false],
            ['/thanks', false],
            ['javascript:alert(1)', false],
        ];
    }
}
