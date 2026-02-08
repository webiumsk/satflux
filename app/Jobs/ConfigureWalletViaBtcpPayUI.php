<?php

namespace App\Jobs;

use App\Models\WalletConnection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class ConfigureWalletViaBtcpPayUI implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected WalletConnection $walletConnection
    ) {
        $this->onQueue('btcpay-config');
    }

    public function handle(): void
    {
        $config = config('services.btcpay_config_bot');

        if (! ($config['enabled'] ?? false)) {
            Log::info('BTCPay config bot disabled, skipping job', [
                'connection_id' => $this->walletConnection->id,
            ]);

            return;
        }

        $scriptPath = base_path('scripts/btcpay-config-bot/index.js');
        if (! file_exists($scriptPath)) {
            Log::error('BTCPay config bot script not found', ['path' => $scriptPath]);

            return;
        }

        $nodePath = $this->findNode();
        if (! $nodePath) {
            Log::error('Node.js not found, cannot run BTCPay config bot');

            return;
        }

        $env = array_merge(
            $_ENV ?? [],
            [
                'PANEL_URL' => $config['panel_url'],
                'PANEL_BOT_TOKEN' => $config['panel_token'],
                'PANEL_BOT_PASSWORD' => $config['panel_password'],
                'BTCPAY_BASE_URL' => $config['btcpay_base_url'],
                'BTCPAY_BOT_EMAIL' => $config['btcpay_email'],
                'BTCPAY_BOT_PASSWORD' => $config['btcpay_password'],
                'BTCPAY_BOT_CONNECTION_ID' => $this->walletConnection->id,
                'BTCPAY_BOT_HEADLESS' => $config['headless'] ? 'true' : 'false',
            ]
        );
        $env['BTCPAY_BOT_LOG_FILE'] = $config['log_file'] ?? '/tmp/btcpay-config-bot.log';

        $process = new Process(
            [$nodePath, $scriptPath, $this->walletConnection->id],
            base_path('scripts/btcpay-config-bot'),
            $env,
            null,
            120 // timeout 2 minutes
        );

        Log::info('BTCPay config bot starting', [
            'connection_id' => $this->walletConnection->id,
            'store_id' => $this->walletConnection->store_id,
        ]);

        $process->run();

        $output = $process->getOutput();
        $errorOutput = $process->getErrorOutput();

        if ($process->isSuccessful()) {
            Log::info('BTCPay config bot completed', [
                'connection_id' => $this->walletConnection->id,
                'output_preview' => substr($output, 0, 500),
            ]);
        } else {
            Log::error('BTCPay config bot failed', [
                'connection_id' => $this->walletConnection->id,
                'exit_code' => $process->getExitCode(),
                'output' => $output,
                'error_output' => $errorOutput,
            ]);
        }
    }

    protected function findNode(): ?string
    {
        $process = Process::fromShellCommandline('which node || command -v node');
        $process->run();
        if ($process->isSuccessful()) {
            $path = trim($process->getOutput());
            if (! empty($path)) {
                return $path;
            }
        }

        return null;
    }
}
