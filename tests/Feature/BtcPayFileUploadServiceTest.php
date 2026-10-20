<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use App\Services\BtcPay\BtcPayFileUploadService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BtcPayFileUploadServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.btcpay.base_url' => 'https://btcpay.test',
            'services.btcpay.api_key' => 'synthetic-server-key',
        ]);
    }

    public function test_unauthenticated_guest_and_foreign_owners_are_rejected_before_any_api_request(): void
    {
        Http::fake();
        $owner = User::factory()->create(['btcpay_api_key' => 'synthetic-merchant-key']);
        $store = Store::factory()->create(['user_id' => $owner->id]);
        $upload = $this->pngFile('product.png');
        $service = app(BtcPayFileUploadService::class);

        try {
            $service->uploadForStore($upload, $store);
            $this->fail('Unauthenticated caller must be rejected.');
        } catch (AuthorizationException) {
            $this->assertGuest();
        }

        $guest = User::factory()->guest()->create();
        $this->actingAs($guest);
        try {
            $service->uploadForStore($upload, $store);
            $this->fail('Guest caller must be rejected.');
        } catch (AuthorizationException) {
            $this->assertAuthenticatedAs($guest);
        }

        $other = User::factory()->create();
        $this->actingAs($other);
        try {
            $service->uploadForStore($upload, $store);
            $this->fail('Non-owner caller must be rejected.');
        } catch (AuthorizationException) {
            $this->assertAuthenticatedAs($other);
        }

        Http::assertNothingSent();
    }

    public function test_store_owner_can_upload_with_narrow_server_key_fallback(): void
    {
        Http::fake(function ($request) {
            if ($request->hasHeader('Authorization', 'Bearer synthetic-merchant-key')) {
                return Http::response(['message' => 'Insufficient API Permissions'], 403);
            }

            return Http::response([
                'id' => 'synthetic-file-id',
                'storageName' => 'synthetic-file-name.png',
                'url' => 'https://btcpay.test/LocalStorage/synthetic-file-name.png',
            ], 200);
        });
        $owner = User::factory()->create(['btcpay_api_key' => 'synthetic-merchant-key']);
        $store = Store::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner);
        $result = app(BtcPayFileUploadService::class)->uploadForStore(
            $this->pngFile('product.png'),
            $store,
        );

        $this->assertSame('synthetic-file-id', $result['id']);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->url() === 'https://btcpay.test/api/v1/files');
    }

    public function test_non_image_and_oversized_files_are_rejected_before_any_api_request(): void
    {
        Http::fake();
        $owner = User::factory()->create(['btcpay_api_key' => 'synthetic-merchant-key']);
        $store = Store::factory()->create(['user_id' => $owner->id]);
        $service = app(BtcPayFileUploadService::class);
        $this->actingAs($owner);

        try {
            $service->uploadForStore(UploadedFile::fake()->createWithContent('document.txt', 'plain text'), $store);
            $this->fail('Non-image upload must be rejected.');
        } catch (ValidationException) {
            $this->assertAuthenticatedAs($owner);
        }

        try {
            $service->uploadForStore($this->pngFile('large.png', 5 * 1024 * 1024 + 1), $store);
            $this->fail('Oversized upload must be rejected.');
        } catch (ValidationException) {
            $this->assertAuthenticatedAs($owner);
        }

        Http::assertNothingSent();
    }

    private function pngFile(string $name, int $size = 128): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX9sAAAAASUVORK5CYII=');

        return UploadedFile::fake()->createWithContent($name, $png.str_repeat('a', max(0, $size - strlen($png))));
    }
}
