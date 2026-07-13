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

        User::updateOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'E2E Test User',
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => now(),
                'is_guest' => false,
            ],
        );
    }
}
