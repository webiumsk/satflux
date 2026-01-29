<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\BtcPay\StoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    protected StoreService $storeService;

    public function __construct(StoreService $storeService)
    {
        $this->storeService = $storeService;
    }

    /**
     * Get dashboard data.
     * Stores are loaded from BTCPay API, then merged with local metadata.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Get local stores first - these are the source of truth for what stores belong to this user
        $localStores = Store::where('user_id', $user->id)
            ->with(['checklistItems'])
            ->get()
            ->keyBy('btcpay_store_id');

        // Try to load stores from BTCPay API if merchant has API key
        $btcpayStores = [];
        try {
            if ($user->btcpay_api_key) {
                // Load stores from BTCPay API using merchant token
                $btcpayStores = $this->storeService->listStores($user->btcpay_api_key);
            }
        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            // If API fails, we'll use local stores only
            Log::warning('BTCPay API failed when loading dashboard stores, using local stores only', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Cache the entire dashboard data (stores + revenue) per user
        $cacheKey = "user_{$user->id}_dashboard_data";

        return Cache::remember($cacheKey, 600, function () use ($user, $localStores, $btcpayStores) {
            // Only return stores that exist in BOTH local DB AND BTCPay Server
            if (empty($btcpayStores)) {
                $stores = collect([]);
            } else {
                $btcpayStoreIds = collect($btcpayStores)->map(function ($bs) {
                    return $bs['id'] ?? $bs['storeId'] ?? null;
                })->filter()->values()->toArray();

                $stores = $localStores->filter(function ($localStore) use ($btcpayStoreIds) {
                    return in_array($localStore->btcpay_store_id, $btcpayStoreIds);
                })->map(function ($localStore) use ($btcpayStores) {
                    $btcpayStore = collect($btcpayStores)->first(function ($bs) use ($localStore) {
                        $btcpayStoreId = $bs['id'] ?? $bs['storeId'] ?? null;
                        return $btcpayStoreId === $localStore->btcpay_store_id;
                    });

                    return [
                        'id' => $localStore->id,
                        'name' => $btcpayStore['name'] ?? $localStore->name,
                        'wallet_type' => $localStore->wallet_type,
                        'created_at' => $localStore->created_at ?? ($btcpayStore['created'] ?? now()),
                    ];
                })->values();
            }

            $totalRevenue = 0;
            $revenueByCurrency = [];

            if ($stores->isNotEmpty() && $user->btcpay_api_key) {
                try {
                    $invoiceService = app(\App\Services\BtcPay\InvoiceService::class);
                    foreach ($stores as $store) {
                        try {
                            $localStore = Store::find($store['id']);
                            if ($localStore && $localStore->btcpay_store_id) {
                                $invoices = $invoiceService->listInvoices(
                                    $localStore->btcpay_store_id,
                                    take: 1000,
                                    userApiKey: $user->btcpay_api_key
                                );

                                foreach ($invoices as $invoice) {
                                    $status = strtolower($invoice['status'] ?? '');
                                    if (in_array($status, ['paid', 'complete', 'settled'])) {
                                        $amount = floatval($invoice['amount'] ?? 0);
                                        $currency = strtoupper($invoice['currency'] ?? 'BTC');

                                        if ($currency === 'BTC') {
                                            $totalRevenue += round($amount * 100000000);
                                        } elseif ($currency === 'SATS') {
                                            $totalRevenue += round($amount);
                                        } else {
                                            if (!isset($revenueByCurrency[$currency])) {
                                                $revenueByCurrency[$currency] = 0;
                                            }
                                            $revenueByCurrency[$currency] += $amount;
                                        }
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                            Log::debug('Failed to fetch invoices for store on dashboard', [
                                'store_id' => $store['id'] ?? null,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to calculate total revenue on dashboard', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $formattedBreakdown = [];
            foreach ($revenueByCurrency as $currency => $amount) {
                $formattedBreakdown[] = [
                    'currency' => $currency,
                    'amount' => $amount,
                ];
            }

            return [
                'stores' => $stores,
                'store_count' => $stores->count(),
                'total_revenue' => round($totalRevenue),
                'revenue_breakdown' => $formattedBreakdown,
            ];
        });
    }
}

