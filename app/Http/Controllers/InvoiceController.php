<?php

namespace App\Http\Controllers;

use App\Services\BtcPay\InvoiceService;
use Illuminate\Http\Request;

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
}

