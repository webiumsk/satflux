<?php

namespace App\Console\Commands;

use App\Models\WalletConnection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Diagnose wallet connection encryption.
 * Run: php artisan wallet:diagnose-encryption
 */
class DiagnoseWalletEncryption extends Command
{
    protected $signature = 'wallet:diagnose-encryption';

    protected $description = 'Diagnose wallet connection encryption (APP_KEY, stored format)';

    public function handle(): int
    {
        $this->info('=== Wallet encryption diagnostic ===');
        $this->newLine();

        $key = config('app.key');
        if (empty($key) || $key === 'base64:') {
            $this->error('APP_KEY is empty or not set.');
            return 1;
        }
        $this->info('APP_KEY: ' . substr($key, 0, 15) . '... (length: ' . strlen($key) . ')');

        $this->newLine();
        $this->info('1. Testing fresh encrypt/decrypt cycle...');
        try {
            $test = Crypt::encryptString('test-secret-123');
            $decrypted = Crypt::decryptString($test);
            if ($decrypted === 'test-secret-123') {
                $this->info('   OK – Encrypt/decrypt works.');
            } else {
                $this->error('   FAIL – Decrypted value does not match.');
            }
        } catch (\Throwable $e) {
            $this->error('   FAIL – ' . $e->getMessage());
            return 1;
        }

        $this->newLine();
        $this->info('2. Checking stored wallet_connections...');
        $connections = WalletConnection::all();
        if ($connections->isEmpty()) {
            $this->warn('   No wallet connections in database.');
            return 0;
        }

        $hasSecretEncrypted = Schema::hasColumn('wallet_connections', 'secret_encrypted');
        if ($hasSecretEncrypted) {
            $this->warn('   Table has BOTH encrypted_secret and secret_encrypted. If encrypted_secret is empty, run: php artisan migrate');
            $this->newLine();
        }

        foreach ($connections as $conn) {
            $raw = $conn->attributes['encrypted_secret'] ?? '';
            $secretEncryptedRaw = $hasSecretEncrypted ? (DB::table('wallet_connections')->where('id', $conn->id)->value('secret_encrypted') ?? '') : '';

            $this->line("   Connection {$conn->id} (store: {$conn->store_id}):");
            $this->line("     encrypted_secret length: " . strlen($raw));
            if ($hasSecretEncrypted && $secretEncryptedRaw !== '') {
                $this->line("     secret_encrypted length: " . strlen($secretEncryptedRaw) . " (has data – run migration to copy)");
            }
            $this->line("     Looks like Laravel payload: " . (strlen($raw) > 0 && str_starts_with($raw, 'eyJ') ? 'yes' : 'no'));

            try {
                if (strlen($raw) > 0) {
                    Crypt::decryptString($raw);
                    $this->info("     Decrypt: OK");
                } else {
                    $this->error("     Decrypt: SKIP (empty). Run: php artisan migrate");
                }
            } catch (\Throwable $e) {
                $this->error("     Decrypt: FAIL – " . $e->getMessage());
            }
            $this->newLine();
        }

        return 0;
    }
}
