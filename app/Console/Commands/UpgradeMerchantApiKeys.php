<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\BtcPay\Exceptions\BtcPayException;
use App\Services\BtcPay\MerchantApiKeyService;
use Illuminate\Console\Command;

class UpgradeMerchantApiKeys extends Command
{
    protected $signature = 'btcpay:upgrade-merchant-api-keys
                            {--dry-run : Log what would be done without making changes}
                            {--delay=2 : Seconds to wait between users (rate limiting)}';

    protected $description = 'Upgrade existing merchant API keys to current required permissions (e.g. notifications)';

    public function __construct(
        protected MerchantApiKeyService $merchantApiKeyService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $delay = max(0, (int) $this->option('delay'));

        if ($dryRun) {
            $this->warn('DRY RUN - no changes will be made');
        }

        $users = User::whereNotNull('btcpay_user_id')
            ->whereNotNull('btcpay_api_key')
            ->get();

        $this->info("Found {$users->count()} users with BTCPay API keys to upgrade.");

        $upgraded = 0;
        $failed = 0;

        foreach ($users as $user) {
            if ($dryRun) {
                $this->line("Would upgrade: {$user->email} (user_id={$user->id})");
                $upgraded++;
                if ($delay > 0) {
                    sleep($delay);
                }
                continue;
            }

            try {
                if ($this->merchantApiKeyService->upgradeApiKey($user)) {
                    $this->line("Upgraded: {$user->email}");
                    $upgraded++;
                }

                if ($delay > 0) {
                    sleep($delay);
                }
            } catch (BtcPayException $e) {
                $this->error("Failed {$user->email}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Done. Upgraded: {$upgraded}, Failed: {$failed}");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
