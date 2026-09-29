<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Move per-company Stripe Tax secrets from plaintext app_settings JSON to
     * the Crypt-encrypted stripe_tax_secret_key_encrypted field.
     */
    public function up(): void
    {
        DB::table('companies')
            ->whereNotNull('app_settings')
            ->orderBy('id')
            ->select(['id'])
            ->chunk(200, function ($companies) {
                foreach ($companies as $company) {
                    // Lock and re-read the row so a concurrent settings save
                    // during the deploy is not overwritten by a stale snapshot.
                    DB::transaction(function () use ($company) {
                        $row = DB::table('companies')->where('id', $company->id)->lockForUpdate()->first(['app_settings']);
                        $settings = json_decode((string) ($row->app_settings ?? ''), true);
                        if (! is_array($settings) || ! array_key_exists('stripe_tax_secret_key', $settings)) {
                            return;
                        }

                        $secret = trim((string) $settings['stripe_tax_secret_key']);
                        unset($settings['stripe_tax_secret_key']);
                        if ($secret !== '' && empty($settings['stripe_tax_secret_key_encrypted'])) {
                            $settings['stripe_tax_secret_key_encrypted'] = Crypt::encryptString($secret);
                        }

                        DB::table('companies')
                            ->where('id', $company->id)
                            ->update(['app_settings' => json_encode($settings)]);
                    });
                }
            });
    }

    /**
     * Irreversible by design: decrypting secrets back to plaintext would undo
     * the point of the migration. The runtime still reads legacy plaintext.
     */
    public function down(): void {}
};
