<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Encrypt pre-existing plaintext webhook secrets. The Store model now
     * casts webhook_secret as 'encrypted', so legacy plaintext rows would
     * throw DecryptException on read. Idempotent: rows that already decrypt
     * cleanly are skipped.
     */
    public function up(): void
    {
        DB::table('stores')
            ->whereNotNull('webhook_secret')
            ->orderBy('id')
            ->chunkById(100, function ($stores) {
                foreach ($stores as $store) {
                    if ($this->decryptsCleanly($store->webhook_secret)) {
                        continue;
                    }

                    DB::table('stores')
                        ->where('id', $store->id)
                        ->update(['webhook_secret' => Crypt::encryptString($store->webhook_secret)]);
                }
            }, 'id');
    }

    /**
     * Reverse the migration: decrypt back to plaintext.
     */
    public function down(): void
    {
        DB::table('stores')
            ->whereNotNull('webhook_secret')
            ->orderBy('id')
            ->chunkById(100, function ($stores) {
                foreach ($stores as $store) {
                    try {
                        $plaintext = Crypt::decryptString($store->webhook_secret);
                    } catch (DecryptException) {
                        continue; // already plaintext
                    }

                    DB::table('stores')
                        ->where('id', $store->id)
                        ->update(['webhook_secret' => $plaintext]);
                }
            }, 'id');
    }

    protected function decryptsCleanly(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
};
