<?php

namespace App\Jobs;

use App\Models\Export;
use App\Services\BtcPay\InvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateCsvExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected Export $export
    ) {
        $this->onQueue('exports');
    }

    public function handle(InvoiceService $invoiceService): void
    {
        $this->export->markAsRunning();

        try {
            $store = $this->export->store;
            $filters = $this->export->filters ?? [];
            
            // Build filters for BTCPay API
            $btcpayFilters = [];
            if (isset($filters['status'])) {
                $btcpayFilters['status'] = $filters['status'];
            }

            $filePath = 'exports/' . $this->export->id . '_' . time() . '.csv';
            $fullPath = storage_path('app/' . $filePath);

            // Ensure directory exists
            $directory = dirname($fullPath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $handle = fopen($fullPath, 'w');

            if ($this->export->format === 'standard') {
                $this->writeStandardCsv($handle, $invoiceService, $store, $btcpayFilters);
            } else {
                $this->writeAccountingCsv($handle, $invoiceService, $store, $btcpayFilters);
            }

            fclose($handle);

            // Generate signed URL
            $ttl = (int) env('EXPORT_SIGNED_URL_TTL', 3600);
            $signedUrl = Storage::disk('local')->temporaryUrl(
                $filePath,
                now()->addSeconds($ttl)
            );

            $this->export->markAsFinished(
                $filePath,
                $signedUrl,
                now()->addSeconds($ttl)
            );
        } catch (\Exception $e) {
            $this->export->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    protected function writeStandardCsv($handle, InvoiceService $invoiceService, $store, array $filters): void
    {
        // Write header
        fputcsv($handle, [
            'invoiceId',
            'createdTime',
            'status',
            'amount',
            'currency',
            'paidAmount',
            'paymentMethod',
            'buyerEmail',
            'orderId',
            'checkoutLink',
        ]);

        // Fetch invoices with pagination
        $skip = 0;
        $take = 100;

        do {
            $invoices = $invoiceService->listInvoices($store->btcpay_store_id, $filters, $skip, $take);
            
            foreach ($invoices as $invoice) {
                fputcsv($handle, [
                    $invoice['id'] ?? '',
                    $invoice['createdTime'] ?? '',
                    $invoice['status'] ?? '',
                    $invoice['amount'] ?? '',
                    $invoice['currency'] ?? '',
                    $invoice['paidAmount'] ?? '',
                    $invoice['availablePaymentMethods'] ? implode(',', $invoice['availablePaymentMethods']) : '',
                    $invoice['buyer']['buyerEmail'] ?? '',
                    $invoice['metadata']['orderId'] ?? '',
                    $invoice['checkoutLink'] ?? '',
                ]);
            }

            $skip += $take;
        } while (count($invoices) === $take);
    }

    protected function writeAccountingCsv($handle, InvoiceService $invoiceService, $store, array $filters): void
    {
        // Write header
        fputcsv($handle, [
            'invoice_id',
            'issue_date',
            'settlement_date',
            'gross_amount',
            'currency',
            'payment_method',
            'status',
            'external_reference',
        ]);

        // Fetch invoices with pagination
        $skip = 0;
        $take = 100;

        do {
            $invoices = $invoiceService->listInvoices($store->btcpay_store_id, $filters, $skip, $take);
            
            foreach ($invoices as $invoice) {
                $createdTime = isset($invoice['createdTime']) ? date('Y-m-d', strtotime($invoice['createdTime'])) : '';
                $paidTime = null;
                if (isset($invoice['status']) && $invoice['status'] === 'Settled' && isset($invoice['paidTime'])) {
                    $paidTime = date('Y-m-d', strtotime($invoice['paidTime']));
                }

                fputcsv($handle, [
                    $invoice['id'] ?? '',
                    $createdTime,
                    $paidTime ?? '',
                    $invoice['amount'] ?? '',
                    $invoice['currency'] ?? '',
                    $invoice['availablePaymentMethods'] ? implode(',', $invoice['availablePaymentMethods']) : '',
                    $invoice['status'] ?? '',
                    $invoice['metadata']['orderId'] ?? '',
                ]);
            }

            $skip += $take;
        } while (count($invoices) === $take);
    }
}

