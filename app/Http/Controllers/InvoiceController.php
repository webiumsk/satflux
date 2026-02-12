<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateCsvExport;
use App\Jobs\GenerateXlsxExport;
use App\Models\Export;
use App\Services\BtcPay\InvoiceService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceService $invoiceService,
        protected SubscriptionService $subscriptionService
    ) {}

    /**
     * Allowed invoice status values for BTCPay API (whitelist).
     */
    private const ALLOWED_INVOICE_STATUSES = ['New', 'Processing', 'Expired', 'Invalid', 'Settled'];

    /**
     * Max number of invoices per request to avoid DoS and API overload.
     */
    private const MAX_TAKE = 500;

    /**
     * List invoices for a store with optional filters.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:' . implode(',', self::ALLOWED_INVOICE_STATUSES)],
            'date_from' => ['nullable', 'string', 'date_format:Y-m-d'],
            'date_to' => [
                'nullable',
                'string',
                'date_format:Y-m-d',
                function (string $attribute, mixed $value, \Closure $fail) use ($request) {
                    $from = $request->input('date_from');
                    if ($from !== null && $from !== '' && $value < $from) {
                        $fail('date_to must be on or after date_from.');
                    }
                },
            ],
            'skip' => ['nullable', 'integer', 'min:0'],
            'take' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_TAKE],
        ], [
            'status.in' => 'The selected status is invalid.',
            'date_from.date_format' => 'date_from must be Y-m-d (e.g. 2025-01-15).',
            'date_to.date_format' => 'date_to must be Y-m-d (e.g. 2025-01-15).',
        ]);

        $store = $request->route('store');

        // Load merchant API key from store owner
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        // Build filters from validated query parameters
        $filters = [];

        if (!empty($validated['status'])) {
            $filters['status'] = $validated['status'];
        }

        if (!empty($validated['date_from'])) {
            $filters['startDate'] = strtotime($validated['date_from']);
        }

        if (!empty($validated['date_to'])) {
            $filters['endDate'] = strtotime($validated['date_to'] . ' 23:59:59');
        }

        $skip = (int) ($validated['skip'] ?? 0);
        $take = (int) ($validated['take'] ?? 100);
        
        try {
            // Fetch invoices from BTCPay API
            $response = $this->invoiceService->listInvoices(
                $store->btcpay_store_id,
                $filters,
                $skip,
                $take,
                $userApiKey
            );
            
            // BTCPay API returns invoices in the data array
            $invoices = $response['data'] ?? $response;
            
            // Format invoices for frontend
            $formattedInvoices = array_map(function ($invoice) {
                return [
                    'id' => $invoice['id'] ?? null,
                    'invoice_id' => $invoice['id'] ?? null,
                    'status' => $invoice['status'] ?? null,
                    'amount' => $invoice['amount'] ?? null,
                    'currency' => $invoice['currency'] ?? null,
                    'created_time' => $invoice['createdTime'] ?? null,
                    'created_at' => $invoice['createdTime'] ?? null,
                    'paid_time' => $invoice['paidTime'] ?? null,
                    'paid_at' => $invoice['paidTime'] ?? null,
                ];
            }, is_array($invoices) ? $invoices : []);
            
            return response()->json([
                'data' => $formattedInvoices,
                'meta' => [
                    'total' => count($formattedInvoices),
                    'skip' => $skip,
                    'take' => $take,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to load invoices: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export invoices to CSV or XLSX (XLSX requires Pro+ or admin/support).
     * - Small exports (≤1000 invoices): synchronous streaming response
     * - Large exports (>1000 invoices): asynchronous queue job
     */
    public function exportCsv(Request $request)
    {
        $store = $request->route('store');
        $format = $request->query('format', 'csv');

        if ($format === 'xlsx' && !$this->subscriptionService->canUseXlsxExport($request->user())) {
            return response()->json([
                'message' => 'XLSX export is available in Pro and above. Please upgrade.',
            ], 403);
        }

        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        $filters = [];
        if ($request->has('status') && $request->status) {
            $filters['status'] = $request->status;
        }
        if ($request->has('date_from') && $request->date_from) {
            $dateFrom = strtotime($request->date_from);
            if ($dateFrom !== false) {
                $filters['startDate'] = $dateFrom;
            }
        }
        if ($request->has('date_to') && $request->date_to) {
            $dateTo = strtotime($request->date_to . ' 23:59:59');
            if ($dateTo !== false) {
                $filters['endDate'] = $dateTo;
            }
        }

        $exportFormat = $format === 'xlsx' ? 'xlsx' : 'standard';
        $filtersForExport = [
            'date_from' => $request->date_from,
            'date_to' => $request->date_to,
            'status' => $request->status,
        ];

        try {
            $estimatedCount = $this->invoiceService->estimateInvoiceCount(
                $store->btcpay_store_id,
                $filters,
                $userApiKey
            );

            if ($estimatedCount <= 1000) {
                if ($format === 'xlsx') {
                    return $this->streamXlsxExport($store, $filters, $userApiKey);
                }
                return $this->streamCsvExport($store, $filters, $userApiKey);
            }

            $export = Export::create([
                'store_id' => $store->id,
                'user_id' => $request->user()->id,
                'source' => Export::SOURCE_MANUAL,
                'format' => $exportFormat,
                'status' => 'pending',
                'filters' => $filtersForExport,
            ]);

            if ($format === 'xlsx') {
                GenerateXlsxExport::dispatch($export);
            } else {
                GenerateCsvExport::dispatch($export);
            }

            return response()->json([
                'type' => 'asynchronous',
                'export_id' => $export->id,
                'data' => $export,
                'message' => 'Export job queued',
            ], 202);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to export invoices: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Stream CSV export synchronously for small exports.
     */
    protected function streamCsvExport($store, array $filters, string $userApiKey): StreamedResponse
    {
        $filename = 'invoices-' . date('Y-m-d_His') . '.csv';

        return new StreamedResponse(function () use ($store, $filters, $userApiKey) {
            $handle = fopen('php://output', 'w');

            // Write CSV header
            fputcsv($handle, [
                'invoiceId',
                'store',
                'pos',
                'createdTime',
                'status',
                'amount',
                'currency',
                'paidAmount',
                'tax',
                'tip',
                'discount',
                'paymentMethod',
                'buyerEmail',
                'orderId',
                'checkoutLink',
            ]);

            // Fetch and write invoices with pagination
            $skip = 0;
            $take = 100;

            do {
                $response = $this->invoiceService->listInvoices(
                    $store->btcpay_store_id,
                    $filters,
                    $skip,
                    $take,
                    $userApiKey
                );

                // BTCPay API returns invoices in the data array or directly
                $invoices = $response['data'] ?? $response;

                // Ensure it's an array
                if (!is_array($invoices)) {
                    $invoices = [];
                }

                foreach ($invoices as $invoice) {
                    // Safely handle nested arrays
                    $buyerEmail = '';
                    if (isset($invoice['buyer']['buyerEmail'])) {
                        $buyerEmail = $invoice['buyer']['buyerEmail'];
                    }
                    
                    $orderId = '';
                    if (isset($invoice['metadata']['orderId'])) {
                        $orderId = $invoice['metadata']['orderId'];
                    }
                    
                    $paymentMethods = '';
                    if (isset($invoice['availablePaymentMethods']) && is_array($invoice['availablePaymentMethods'])) {
                        $paymentMethods = implode(',', $invoice['availablePaymentMethods']);
                    }

                    $posData = $this->parsePosData($invoice['metadata'] ?? []);

                    fputcsv($handle, [
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
                        $buyerEmail,
                        $orderId,
                        $invoice['checkoutLink'] ?? '',
                    ]);
                }

                $skip += $take;
            } while (count($invoices) === $take);

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Stream XLSX export synchronously for small exports.
     */
    protected function streamXlsxExport($store, array $filters, string $userApiKey): StreamedResponse
    {
        $filename = 'invoices-' . date('Y-m-d_His') . '.xlsx';

        return new StreamedResponse(function () use ($store, $filters, $userApiKey) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Invoices');

            $headers = [
                'invoiceId', 'store', 'pos', 'createdTime', 'status', 'amount', 'currency',
                'paidAmount', 'tax', 'tip', 'discount', 'paymentMethod', 'buyerEmail', 'orderId', 'checkoutLink',
            ];
            $sheet->fromArray($headers, null, 'A1');

            $skip = 0;
            $take = 100;
            $row = 2;

            do {
                $response = $this->invoiceService->listInvoices(
                    $store->btcpay_store_id,
                    $filters,
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
            $writer->setPreCalculateFormulas(false);
            $writer->save('php://output');

            // Free memory after writing
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $writer, $sheet);
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Parse posData from invoice metadata: determine Pos/Eshop label and extract tax, tip, discount.
     */
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

    /**
     * Format createdTime as human-readable EU format (DD.MM.YYYY HH:mm).
     */
    protected function formatCreatedTimeEu(mixed $createdTime): string
    {
        if ($createdTime === null || $createdTime === '') {
            return '';
        }
        $ts = is_numeric($createdTime) ? (float) $createdTime : strtotime($createdTime);
        if ($ts === false) {
            return (string) $createdTime;
        }
        // BTCPay uses seconds; if value looks like milliseconds (13+ digits), convert
        if ($ts > 10000000000) {
            $ts = $ts / 1000;
        }
        $date = \DateTime::createFromFormat('U', (string) (int) $ts);
        return $date ? $date->format('d.m.Y H:i') : (string) $createdTime;
    }
}


