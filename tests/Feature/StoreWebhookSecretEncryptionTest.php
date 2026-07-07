<?php

namespace Tests\Feature;

use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StoreWebhookSecretEncryptionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function webhook_secret_is_encrypted_at_rest_and_transparent_via_model(): void
    {
        $store = Store::factory()->create(['webhook_secret' => 'plain-secret']);

        $raw = DB::table('stores')->where('id', $store->id)->value('webhook_secret');

        $this->assertNotSame('plain-secret', $raw);
        $this->assertSame('plain-secret', Crypt::decryptString($raw));
        $this->assertSame('plain-secret', $store->fresh()->webhook_secret);
    }

    #[Test]
    public function migration_encrypts_legacy_plaintext_secrets_and_is_idempotent(): void
    {
        $store = Store::factory()->create(['webhook_secret' => 'model-written']);

        // Simulate a legacy row written before the encrypted cast existed
        $legacy = Store::factory()->create();
        DB::table('stores')->where('id', $legacy->id)->update(['webhook_secret' => 'legacy-plaintext']);

        $migration = require database_path('migrations/2026_07_07_100000_encrypt_existing_store_webhook_secrets.php');
        $migration->up();

        $this->assertSame('legacy-plaintext', $legacy->fresh()->webhook_secret);
        $this->assertSame('model-written', $store->fresh()->webhook_secret);

        // Second run must not double-encrypt
        $migration->up();
        $this->assertSame('legacy-plaintext', $legacy->fresh()->webhook_secret);

        // down() restores plaintext in the raw column
        $migration->down();
        $this->assertSame(
            'legacy-plaintext',
            DB::table('stores')->where('id', $legacy->id)->value('webhook_secret'),
        );
    }
}
