<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;

/**
 * Deterministic fixture for the Playwright e2e suite (docs/E2E_HARNESS.md).
 * Creates a verified, non-guest email user WITHOUT any BTCPay provisioning -
 * the e2e scenarios deliberately cover only flows that work with an empty
 * store list, because CI has no BTCPay Server.
 */
class E2eTestSeeder extends Seeder
{
    public const EMAIL = 'e2e@satflux.test';

    public const PASSWORD = 'E2e-password-123';

    public function run(): void
    {
        if (App::environment('production')) {
            $this->command?->error('E2eTestSeeder must never run in production.');

            return;
        }

        $user = User::updateOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'E2E Test User',
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => now(),
                'is_guest' => false,
                // A recovery phrase counts as enrolled (fixture key, never a
                // real one): with SEED_FIRST_REGISTRATION=true an un-enrolled
                // email user gets the MANDATORY legacy-migration wizard over
                // every page, which blocks all UI interaction in the specs.
                // The /invoicing device guard is unaffected - it checks
                // sessionStorage, not this flag.
                'guest_recovery_public_key' => hash('sha256', 'satflux-e2e-recovery-fixture'),
                'guest_recovery_enrolled_at' => now(),
            ],
        );

        // BTCPay stub scenarios (E2E_BTCPAY=1, docs/BTCPAY_E2E_SCENARIOS.md):
        // the invoices page requires a merchant API key and store creation
        // assigns the merchant by BTCPay user id - the stub accepts any
        // bearer token, so fixed fake values are enough.
        if (env('E2E_BTCPAY') === '1') {
            $user->forceFill([
                'btcpay_user_id' => 'stub-merchant-user',
                'btcpay_api_key' => 'stub-merchant-key',
            ])->save();
        }
    }
}
