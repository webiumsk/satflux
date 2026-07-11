<?php

namespace App\Jobs;

use App\Models\Store;
use App\Services\Boltz\SettlementLedgerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Syncs one invoice's payments into the settlement ledger after a payment webhook.
 *
 * Idempotent by design (ledger upserts on payment identity) and unique per
 * store+invoice while queued, so webhook bursts (InvoiceReceivedPayment +
 * InvoiceSettled) collapse into a single pending sync.
 */
class SyncInvoiceSettlements implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60];

    public function __construct(
        public string $storeId,
        public string $invoiceId,
    ) {}

    public function uniqueId(): string
    {
        return "{$this->storeId}:{$this->invoiceId}";
    }

    public function handle(SettlementLedgerService $ledger): void
    {
        $store = Store::find($this->storeId);
        if (! $store) {
            return;
        }

        $rows = $ledger->syncInvoice($store, $this->invoiceId, forgetCache: true);

        Log::info('Settlement ledger synced from webhook', [
            'store_id' => $this->storeId,
            'invoice_id' => $this->invoiceId,
            'rows' => $rows,
        ]);
    }
}
