<?php

namespace App\Http\Controllers;

use App\Models\PosOrder;
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

        // Only return stores that exist in BOTH local DB AND BTCPay Server
        // Store must be in local DB (metadata) AND merchant must have access via BTCPay API
        if (empty($btcpayStores)) {
            // If no BTCPay stores returned (no API key or API failed), return empty array
            // Store must exist on BTCPay server to be visible
            $stores = collect([]);
        } else {
            // Filter local stores to only include those that exist in BTCPay API response
            $btcpayStoreIds = collect($btcpayStores)->map(function ($bs) {
                return $bs['id'] ?? $bs['storeId'] ?? null;
            })->filter()->values()->toArray();

            $stores = $localStores->filter(function ($localStore) use ($btcpayStoreIds) {
                // Only include if local store's btcpay_store_id exists in BTCPay API response
                return in_array($localStore->btcpay_store_id, $btcpayStoreIds);
            })->map(function ($localStore) use ($btcpayStores) {
                // Find matching BTCPay store data
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

        // Total revenue (sats) and revenue_breakdown by currency: from PosOrders, cached 5 min
        $storeIds = $stores->pluck('id')->toArray();
        $cacheKey = 'dashboard:user:'.$user->id.':revenue';
        $revenueData = Cache::remember($cacheKey, 300, function () use ($storeIds) {
            if (empty($storeIds)) {
                return ['total_sats' => 0, 'breakdown' => []];
            }
            $orders = PosOrder::whereIn('store_id', $storeIds)
                ->where('status', PosOrder::STATUS_PAID)
                ->get(['amount', 'currency']);

            $sats = 0;
            $byCurrency = [];
            foreach ($orders as $order) {
                $currency = strtoupper(trim($order->currency ?? ''));
                $amount = (float) $order->amount;
                if ($currency === 'SATS') {
                    $sats += (int) round($amount);
                } elseif ($currency === 'BTC') {
                    $sats += (int) round($amount * 100_000_000);
                }
                if (!isset($byCurrency[$currency])) {
                    $byCurrency[$currency] = 0;
                }
                $byCurrency[$currency] += $amount;
            }
            $breakdown = [];
            foreach ($byCurrency as $currency => $sum) {
                $breakdown[] = ['currency' => $currency, 'amount' => round($sum, 2)];
            }
            return ['total_sats' => $sats, 'breakdown' => $breakdown];
        });

        return response()->json([
            'stores' => $stores,
            'store_count' => $stores->count(),
            'total_revenue' => (int) $revenueData['total_sats'],
            'revenue_breakdown' => $revenueData['breakdown'],
        ]);
    }

    /**
     * Dashboard stats (sales by store): list of stores + optional 7d/30d sales.
     * Data (sales_7d, sales_30d, total_7d, total_30d) only returned for Pro + admin/support.
     */
    public function stats(Request $request)
    {
        $user = $request->user();
        $storeIdFilter = $request->query('store_id');

        $stores = Store::where('user_id', $user->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name]);

        $plan = $user->currentSubscriptionPlan();
        $planCode = $plan ? strtolower($plan->code ?? '') : 'free';
        $canViewStats = in_array($user->role, ['admin', 'support'], true)
            || in_array($planCode, ['pro', 'enterprise'], true);

        $sales7d = [];
        $sales30d = [];
        $total7d = 0;
        $total30d = 0;

        if ($canViewStats && $stores->isNotEmpty()) {
            $storeIds = $storeIdFilter
                ? (in_array($storeIdFilter, $stores->pluck('id')->toArray()) ? [$storeIdFilter] : [])
                : $stores->pluck('id')->toArray();

            if (! empty($storeIds)) {
                $sevenDaysAgo = now()->subDays(7)->startOfDay();
                $thirtyDaysAgo = now()->subDays(30)->startOfDay();

                $orders7d = PosOrder::whereIn('store_id', $storeIds)
                    ->where('status', PosOrder::STATUS_PAID)
                    ->whereNotNull('paid_at')
                    ->where('paid_at', '>=', $sevenDaysAgo)
                    ->get(['paid_at']);
                $orders30d = PosOrder::whereIn('store_id', $storeIds)
                    ->where('status', PosOrder::STATUS_PAID)
                    ->whereNotNull('paid_at')
                    ->where('paid_at', '>=', $thirtyDaysAgo)
                    ->get(['paid_at']);

                $byDay7 = [];
                for ($i = 6; $i >= 0; $i--) {
                    $d = now()->subDays($i)->startOfDay()->format('Y-m-d');
                    $byDay7[$d] = ['date' => now()->subDays($i)->format('M j'), 'count' => 0];
                }
                $byDay30 = [];
                for ($i = 29; $i >= 0; $i--) {
                    $d = now()->subDays($i)->startOfDay()->format('Y-m-d');
                    $byDay30[$d] = ['date' => now()->subDays($i)->format('M j'), 'count' => 0];
                }

                foreach ($orders7d as $o) {
                    $key = $o->paid_at->startOfDay()->format('Y-m-d');
                    if (isset($byDay7[$key])) {
                        $byDay7[$key]['count']++;
                        $total7d++;
                    }
                }
                foreach ($orders30d as $o) {
                    $key = $o->paid_at->startOfDay()->format('Y-m-d');
                    if (isset($byDay30[$key])) {
                        $byDay30[$key]['count']++;
                        $total30d++;
                    }
                }

                $sales7d = array_values($byDay7);
                $sales30d = array_values($byDay30);
            }
        }

        return response()->json([
            'stores' => $stores,
            'can_view_stats' => $canViewStats,
            'sales_7d' => $sales7d,
            'sales_30d' => $sales30d,
            'total_7d' => $total7d,
            'total_30d' => $total30d,
        ]);
    }
}

