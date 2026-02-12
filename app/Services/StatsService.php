<?php

namespace App\Services;

use App\Models\PosOrder;
use App\Models\Store;
use App\Models\User;
use App\Services\BtcPay\InvoiceService;
use Illuminate\Support\Facades\Log;

/**
 * Basic stats: invoice count and paid amount (30d + all time) per store.
 * Advanced stats: per store, per PoS, overall (for Pro).
 */
class StatsService
{
    public function __construct(
        protected InvoiceService $invoiceService
    ) {}

    /**
     * Basic stats for one store (BTCPay invoices: count + paid amount).
     */
    public function getBasicStoreStats(Store $store): array
    {
        try {
            $apiKey = $store->user->getBtcPayApiKeyOrFail();
        } catch (\Throwable $e) {
            return [
                'invoice_count_30d' => 0,
                'invoice_count_all_time' => 0,
                'paid_amount_30d' => 0,
                'paid_amount_all_time' => 0,
                'currency' => $store->default_currency ?? 'EUR',
            ];
        }

        $thirtyDaysAgo = now()->subDays(30)->timestamp;
        $all = $this->fetchAllInvoicesForStore($store->btcpay_store_id, $apiKey);

        $count30d = 0;
        $countAll = 0;
        $amount30d = 0;
        $amountAll = 0;
        $currency = $store->default_currency ?? 'EUR';

        foreach ($all as $inv) {
            $status = $inv['status'] ?? null;
            if (!in_array($status, ['Settled', 'Complete'], true)) {
                continue;
            }
            $countAll++;
            $amount = (float) ($inv['amount'] ?? 0);
            $amountAll += $amount;

            $created = $inv['createdTime'] ?? null;
            $ts = is_numeric($created) ? (int) $created : strtotime($created);
            if ($ts && $ts >= $thirtyDaysAgo) {
                $count30d++;
                $amount30d += $amount;
            }
        }

        return [
            'invoice_count_30d' => $count30d,
            'invoice_count_all_time' => $countAll,
            'paid_amount_30d' => round($amount30d, 2),
            'paid_amount_all_time' => round($amountAll, 2),
            'currency' => $currency,
        ];
    }

    /**
     * Advanced: per store, per PoS, overall (includes pos_orders for cash/card).
     *
     * Revenue totals are grouped by currency to avoid mixing different
     * denominations (e.g. EUR + SATS) into a single sum.
     */
    public function getAdvancedStats(User $user): array
    {
        $storesData = [];
        $overallInvoices30d = 0;
        $overallInvoicesAll = 0;
        // Group revenue by currency to avoid mixing EUR + SATS
        $amountByCurrency30d = [];
        $amountByCurrencyAll = [];

        foreach ($user->stores as $store) {
            $basic = $this->getBasicStoreStats($store);
            $overallInvoices30d += $basic['invoice_count_30d'];
            $overallInvoicesAll += $basic['invoice_count_all_time'];

            $currency = $basic['currency'] ?? 'EUR';
            $amountByCurrency30d[$currency] = round(
                ($amountByCurrency30d[$currency] ?? 0) + $basic['paid_amount_30d'],
                2
            );
            $amountByCurrencyAll[$currency] = round(
                ($amountByCurrencyAll[$currency] ?? 0) + $basic['paid_amount_all_time'],
                2
            );

            $posData = [];
            foreach ($store->posTerminals as $terminal) {
                $orders = $terminal->orders()->where('status', PosOrder::STATUS_PAID)->get();
                $count30d = $orders->filter(fn ($o) => $o->paid_at && $o->paid_at->isAfter(now()->subDays(30)))->count();
                $amount30d = $orders->filter(fn ($o) => $o->paid_at && $o->paid_at->isAfter(now()->subDays(30)))->sum('amount');
                $posData[] = [
                    'pos_terminal_id' => $terminal->id,
                    'name' => $terminal->name,
                    'orders_count_30d' => $count30d,
                    'orders_amount_30d' => round($amount30d, 2),
                    'orders_count_all_time' => $orders->count(),
                    'orders_amount_all_time' => round($orders->sum('amount'), 2),
                ];
            }

            $storesData[] = [
                'store_id' => $store->id,
                'store_name' => $store->name,
                'basic' => $basic,
                'pos_terminals' => $posData,
            ];
        }

        // Build per-currency breakdown arrays
        $revenueBreakdown30d = [];
        foreach ($amountByCurrency30d as $cur => $amount) {
            $revenueBreakdown30d[] = ['currency' => $cur, 'amount' => $amount];
        }
        $revenueBreakdownAll = [];
        foreach ($amountByCurrencyAll as $cur => $amount) {
            $revenueBreakdownAll[] = ['currency' => $cur, 'amount' => $amount];
        }

        // For backward compatibility, provide a single total only when all stores
        // share the same currency; otherwise sum the primary currency only.
        $currencies = array_unique(array_keys($amountByCurrencyAll));
        $primaryCurrency = $currencies[0] ?? 'EUR';

        return [
            'stores' => $storesData,
            'overall' => [
                'invoice_count_30d' => $overallInvoices30d,
                'invoice_count_all_time' => $overallInvoicesAll,
                'paid_amount_30d' => $amountByCurrency30d[$primaryCurrency] ?? 0,
                'paid_amount_all_time' => $amountByCurrencyAll[$primaryCurrency] ?? 0,
                'currency' => $primaryCurrency,
                'revenue_breakdown_30d' => $revenueBreakdown30d,
                'revenue_breakdown_all' => $revenueBreakdownAll,
                'has_mixed_currencies' => count($currencies) > 1,
            ],
        ];
    }

    private function fetchAllInvoicesForStore(string $btcpayStoreId, string $apiKey): array
    {
        $out = [];
        $skip = 0;
        $take = 100;
        do {
            $result = $this->invoiceService->listInvoices($btcpayStoreId, [], $skip, $take, $apiKey);
            $chunk = $result['data'] ?? $result;
            if (!is_array($chunk)) {
                break;
            }
            foreach ($chunk as $inv) {
                $out[] = $inv;
            }
            $skip += $take;
        } while (count($chunk) === $take);
        return $out;
    }
}
