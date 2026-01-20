<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateCsvExport;
use App\Models\Export;
use App\Services\BtcPay\InvoiceService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    protected InvoiceService $invoiceService;

    public function __construct(InvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    /**
     * List invoices for a store with optional filters.
     */
    public function index(Request $request)
    {
        $store = $request->route('store');
        
        // Load merchant API key from store owner
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();
        
        // Build filters from query parameters
        $filters = [];
        
        // Status filter
        if ($request->has('status') && $request->status) {
            $filters['status'] = $request->status;
        }
        
        // Date range filters (BTCPay expects Unix timestamps)
        if ($request->has('date_from') && $request->date_from) {
            // Convert date string to Unix timestamp (seconds)
            $dateFrom = strtotime($request->date_from);
            if ($dateFrom !== false) {
                // BTCPay expects startDate as Unix timestamp in seconds
                $filters['startDate'] = $dateFrom;
            }
        }
        
        if ($request->has('date_to') && $request->date_to) {
            // Convert date string to Unix timestamp (seconds)
            // Add 23:59:59 to include the entire day
            $dateTo = strtotime($request->date_to . ' 23:59:59');
            if ($dateTo !== false) {
                // BTCPay expects endDate as Unix timestamp in seconds
                $filters['endDate'] = $dateTo;
            }
        }
        
        // Pagination
        $skip = $request->get('skip', 0);
        $take = $request->get('take', 100);
        
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
     * Export invoices to CSV with hybrid approach:
     * - Small exports (≤1000 invoices): synchronous streaming response
     * - Large exports (>1000 invoices): asynchronous queue job
     */
    public function exportCsv(Request $request)
    {
        $store = $request->route('store');
        
        // Load merchant API key from store owner
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();
        
        // Build filters from query parameters
        $filters = [];
        
        // Status filter
        if ($request->has('status') && $request->status) {
            $filters['status'] = $request->status;
        }
        
        // Date range filters (BTCPay expects Unix timestamps)
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

        try {
            // Estimate invoice count to decide sync vs async
            $estimatedCount = $this->invoiceService->estimateInvoiceCount(
                $store->btcpay_store_id,
                $filters,
                $userApiKey
            );

            // Threshold: 1000 invoices
            if ($estimatedCount <= 1000) {
                // Synchronous export: stream CSV directly
                return $this->streamCsvExport($store, $filters, $userApiKey);
            } else {
                // Asynchronous export: create job
                $export = Export::create([
                    'store_id' => $store->id,
                    'user_id' => $request->user()->id,
                    'format' => 'standard',
                    'status' => 'pending',
                    'filters' => [
                        'date_from' => $request->date_from,
                        'date_to' => $request->date_to,
                        'status' => $request->status,
                    ],
                ]);

                // Dispatch job
                GenerateCsvExport::dispatch($export);

                return response()->json([
                    'type' => 'asynchronous',
                    'export_id' => $export->id,
                    'data' => $export,
                    'message' => 'Export job queued',
                ], 202);
            }
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
                    
                    fputcsv($handle, [
                        $invoice['id'] ?? '',
                        $invoice['createdTime'] ?? '',
                        $invoice['status'] ?? '',
                        $invoice['amount'] ?? '',
                        $invoice['currency'] ?? '',
                        $invoice['paidAmount'] ?? '',
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
}


