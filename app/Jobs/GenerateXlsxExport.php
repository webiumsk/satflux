<?php

namespace App\Jobs;

use App\Models\Export;
use App\Notifications\MonthlyExportReadyNotification;
use App\Services\BtcPay\InvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class GenerateXlsxExport implements ShouldQueue
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

            $store->load('user');

            try {
                $store->user->getBtcPayApiKeyOrFail();
            } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
                $this->export->markAsFailed('BTCPay API key not configured. Please contact support.');
                return;
            }

            $btcpayFilters = $this->buildBtcpayFilters($filters);

            $filePath = 'exports/' . $this->export->id . '_' . time() . '.xlsx';
            $fullPath = storage_path('app/' . $filePath);

            $directory = dirname($fullPath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Invoices');

            $headers = [
                'invoiceId', 'store', 'pos', 'createdTime', 'status', 'amount', 'currency',
                'paidAmount', 'tax', 'tip', 'discount', 'paymentMethod', 'buyerEmail', 'orderId', 'checkoutLink',
            ];
            $sheet->fromArray($headers, null, 'A1');

            $userApiKey = $store->user->getBtcPayApiKeyOrFail();
            $skip = 0;
            $take = 100;
            $row = 2;

            do {
                $response = $invoiceService->listInvoices(
                    $store->btcpay_store_id,
                    $btcpayFilters,
                    $skip,
                    $take,
                    $userApiKey
                );
                $invoices = $response['data'] ?? $response;
                if (!is_array($invoices)) {
                    $invoices = [];
                }

                foreach ($invoices as $invoice) {
                    $posData = $this->parsePosData($invoice['metadata'] ?? []);
                    $paymentMethods = isset($invoice['availablePaymentMethods']) && is_array($invoice['availablePaymentMethods'])
                        ? implode(',', $invoice['availablePaymentMethods']) : '';

                    $sheet->fromArray([
                        $invoice['id'] ?? '',
                        $store->name ?? '',
                        $posData['pos'],
                        $this->formatCreatedTimeEu($invoice['createdTime'] ?? null),
                        $invoice['status'] ?? '',
                        $invoice['amount'] ?? '',
                        $invoice['currency'] ?? '',
                        $invoice['paidAmount'] ?? '',
                        $posData['tax'],
                        $posData['tip'],
                        $posData['discount'],
                        $paymentMethods,
                        $invoice['buyer']['buyerEmail'] ?? '',
                        $invoice['metadata']['orderId'] ?? '',
                        $invoice['checkoutLink'] ?? '',
                    ], null, 'A' . $row);
                    $row++;
                }

                $skip += $take;
            } while (count($invoices) === $take);

            $writer = new Xlsx($spreadsheet);
            $writer->save($fullPath);

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

            $user = $this->export->user;
            $store = $this->export->store;
            if ($this->export->source === Export::SOURCE_AUTOMATIC) {
                $dateFrom = $filters['date_from'] ?? null;
                $monthLabel = $dateFrom
                    ? (new \DateTime($dateFrom))->format('F Y')
                    : now()->subMonth()->format('F Y');
                $email = $store->auto_report_email ?: $user->email;
                if ($email) {
                    \Illuminate\Support\Facades\Notification::route('mail', $email)
                        ->notify(new \App\Notifications\MonthlyExportReadyNotification($this->export, $store, $monthLabel));
                }
            } elseif ($this->export->source === Export::SOURCE_MANUAL && $user->email) {
                $label = date('Y-m-d_His');
                \Illuminate\Support\Facades\Notification::route('mail', $user->email)
                    ->notify(new \App\Notifications\ExportReadyNotification($this->export, $store, $label));
            }
        } catch (\Exception $e) {
            $this->export->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    protected function buildBtcpayFilters(array $filters): array
    {
        $btcpayFilters = [];
        if (isset($filters['status'])) {
            $btcpayFilters['status'] = $filters['status'];
        }
        if (isset($filters['date_from']) && $filters['date_from']) {
            $dateFrom = strtotime($filters['date_from']);
            if ($dateFrom !== false) {
                $btcpayFilters['startDate'] = $dateFrom;
            }
        }
        if (isset($filters['date_to']) && $filters['date_to']) {
            $dateTo = strtotime($filters['date_to'] . ' 23:59:59');
            if ($dateTo !== false) {
                $btcpayFilters['endDate'] = $dateTo;
            }
        }
        return $btcpayFilters;
    }

    protected function parsePosData(array $metadata): array
    {
        $posData = $metadata['posData'] ?? $metadata['pos'] ?? $metadata['posId'] ?? null;
        if ($posData === null || $posData === '') {
            return ['pos' => '', 'tax' => '', 'tip' => '', 'discount' => ''];
        }
        $data = is_string($posData) ? json_decode($posData, true) : $posData;
        if (!is_array($data)) {
            return ['pos' => '', 'tax' => '', 'tip' => '', 'discount' => ''];
        }
        $posLabel = '';
        if (isset($data['WooCommerce']) || isset($data['Magento']) || isset($data['PrestaShop']) || isset($data['OpenCart']) || isset($data['Shopify'])) {
            $posLabel = 'Eshop';
        } elseif (isset($data['tax']) || isset($data['tip']) || array_key_exists('cart', $data)) {
            $posLabel = 'PoS';
        }
        $tax = isset($data['tax']) ? (string) $data['tax'] : '';
        $tip = isset($data['tip']) ? (string) $data['tip'] : '';
        $discount = isset($data['discountAmount']) ? (string) $data['discountAmount'] : '';
        return ['pos' => $posLabel, 'tax' => $tax, 'tip' => $tip, 'discount' => $discount];
    }

    protected function formatCreatedTimeEu(mixed $createdTime): string
    {
        if ($createdTime === null || $createdTime === '') {
            return '';
        }
        $ts = is_numeric($createdTime) ? (float) $createdTime : strtotime($createdTime);
        if ($ts === false) {
            return (string) $createdTime;
        }
        if ($ts > 10000000000) {
            $ts = $ts / 1000;
        }
        $date = \DateTime::createFromFormat('U', (string) (int) $ts);
        return $date ? $date->format('d.m.Y H:i') : (string) $createdTime;
    }
}
