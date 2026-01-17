<?php

namespace App\Http\Controllers;

use App\Models\App;
use App\Models\Store;
use App\Services\BtcPay\AppService;
use App\Services\BtcPay\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StoreDashboardController extends Controller
{
    protected InvoiceService $invoiceService;
    protected AppService $appService;

    public function __construct(InvoiceService $invoiceService, AppService $appService)
    {
        $this->invoiceService = $invoiceService;
        $this->appService = $appService;
    }

    /**
     * Get dashboard data for a store.
     */
    public function show(Request $request, Store $store)
    {
        $user = $request->user();
        
        // Load merchant API key
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();
        
        // Cache key includes API key hash to prevent cross-merchant cache pollution
        $apiKeyHash = md5($userApiKey);
        $cacheKey = "btcpay:dashboard:{$store->id}:{$apiKeyHash}";
        
        return Cache::remember($cacheKey, 60, function () use ($store, $userApiKey) {
            try {
                // Calculate date 7 days ago
                $sevenDaysAgo = now()->subDays(7)->toIso8601String();
                
                // Get all invoices for calculations
                $allInvoices = $this->invoiceService->listInvoices(
                    $store->btcpay_store_id,
                    [],
                    null,
                    null,
                    $userApiKey
                );
                
                // Count paid invoices in last 7 days
                $paidInvoicesLast7d = 0;
                $totalInvoices = is_array($allInvoices) ? count($allInvoices) : 0;
                
                if (is_array($allInvoices)) {
                    foreach ($allInvoices as $invoice) {
                        $status = $invoice['status'] ?? null;
                        $createdTime = $invoice['createdTime'] ?? null;
                        
                        // Check if invoice is settled/paid
                        if (in_array($status, ['Settled', 'Complete'])) {
                            // Check if created within last 7 days
                            if ($createdTime && strtotime($createdTime) >= strtotime($sevenDaysAgo)) {
                                $paidInvoicesLast7d++;
                            }
                        }
                    }
                }
                
                // Get recent invoices (last 10)
                $recentInvoices = $this->invoiceService->listInvoices(
                    $store->btcpay_store_id,
                    [],
                    0,
                    10,
                    $userApiKey
                );
                
                // Format recent invoices
                $formattedRecentInvoices = [];
                if (is_array($recentInvoices)) {
                    $formattedRecentInvoices = array_map(function ($invoice) {
                        return [
                            'id' => $invoice['id'] ?? null,
                            'invoice_id' => $invoice['id'] ?? null,
                            'status' => $invoice['status'] ?? null,
                            'amount' => $invoice['amount'] ?? null,
                            'currency' => $invoice['currency'] ?? null,
                            'created_time' => $invoice['createdTime'] ?? null,
                        ];
                    }, array_slice($recentInvoices, 0, 10));
                }
                
                // Calculate sales over time (last 7 days and 30 days)
                $salesLast7Days = [];
                $salesLast30Days = [];
                $totalSales7d = 0;
                $totalSales30d = 0;
                
                // Initialize arrays for last 7 days
                for ($i = 6; $i >= 0; $i--) {
                    $date = now()->subDays($i)->startOfDay();
                    $salesLast7Days[$date->format('Y-m-d')] = ['date' => $date->format('M j'), 'count' => 0];
                }
                
                // Initialize arrays for last 30 days (group by week or show last 30 days)
                for ($i = 29; $i >= 0; $i--) {
                    $date = now()->subDays($i)->startOfDay();
                    $salesLast30Days[$date->format('Y-m-d')] = ['date' => $date->format('M j'), 'count' => 0];
                }
                
                if (is_array($allInvoices)) {
                    foreach ($allInvoices as $invoice) {
                        $status = $invoice['status'] ?? null;
                        $createdTime = $invoice['createdTime'] ?? null;
                        
                        // Only count settled/paid invoices
                        if (in_array($status, ['Settled', 'Complete']) && $createdTime) {
                            $invoiceDate = \Carbon\Carbon::parse($createdTime)->startOfDay();
                            $dateKey = $invoiceDate->format('Y-m-d');
                            
                            // Count for last 7 days
                            if ($invoiceDate->isAfter(now()->subDays(7)->startOfDay())) {
                                if (isset($salesLast7Days[$dateKey])) {
                                    $salesLast7Days[$dateKey]['count']++;
                                    $totalSales7d++;
                                }
                            }
                            
                            // Count for last 30 days
                            if ($invoiceDate->isAfter(now()->subDays(30)->startOfDay())) {
                                if (isset($salesLast30Days[$dateKey])) {
                                    $salesLast30Days[$dateKey]['count']++;
                                    $totalSales30d++;
                                }
                            }
                        }
                    }
                }
                
                // Extract top items from invoices (from metadata or item description)
                // BTCPay invoices may have item information in metadata
                $itemCounts = [];
                if (is_array($allInvoices)) {
                    foreach ($allInvoices as $invoice) {
                        $status = $invoice['status'] ?? null;
                        // Only count settled/paid invoices
                        if (in_array($status, ['Settled', 'Complete'])) {
                            // Try to get item name from metadata or itemCode
                            $itemName = null;
                            $itemAmount = floatval($invoice['amount'] ?? 0);
                            $currency = $invoice['currency'] ?? 'EUR';
                            
                            // Check for itemCode first
                            if (isset($invoice['itemCode'])) {
                                $itemName = $invoice['itemCode'];
                            } 
                            // Check metadata for posData or itemName
                            elseif (isset($invoice['metadata']) && is_array($invoice['metadata'])) {
                                if (isset($invoice['metadata']['posData'])) {
                                    $posData = is_string($invoice['metadata']['posData']) 
                                        ? json_decode($invoice['metadata']['posData'], true) 
                                        : $invoice['metadata']['posData'];
                                    if (is_array($posData) && isset($posData['itemCode'])) {
                                        $itemName = $posData['itemCode'];
                                    }
                                }
                                if (!$itemName && isset($invoice['metadata']['itemName'])) {
                                    $itemName = $invoice['metadata']['itemName'];
                                }
                            }
                            
                            if ($itemName) {
                                if (!isset($itemCounts[$itemName])) {
                                    $itemCounts[$itemName] = ['count' => 0, 'total' => 0, 'currency' => $currency];
                                }
                                $itemCounts[$itemName]['count']++;
                                $itemCounts[$itemName]['total'] += $itemAmount;
                            }
                        }
                    }
                }
                
                // Sort items by count and take top 5
                uasort($itemCounts, function($a, $b) {
                    return $b['count'] - $a['count'];
                });
                $topItems = array_slice(array_map(function($name, $data) {
                    return [
                        'name' => $name,
                        'count' => $data['count'],
                        'total' => $data['total'],
                        'currency' => $data['currency'],
                    ];
                }, array_keys($itemCounts), $itemCounts), 0, 5);
                
                // Get apps for the store
                $apps = [];
                $appsGrouped = [
                    'crowdfund' => [],
                    'point_of_sale' => [],
                    'payment_button' => [],
                ];
                
                try {
                    $btcpayApps = $this->appService->listApps($store->btcpay_store_id, $userApiKey);
                    
                    // Get local app metadata
                    $localApps = App::where('store_id', $store->id)
                        ->get()
                        ->keyBy('btcpay_app_id');
                    
                    // Group apps by type
                    if (is_array($btcpayApps)) {
                        foreach ($btcpayApps as $btcpayApp) {
                            $btcpayAppId = $btcpayApp['id'] ?? null;
                            if (!$btcpayAppId) {
                                continue;
                            }
                            
                            $localApp = $localApps->get($btcpayAppId);
                            $appTypeRaw = $localApp ? $localApp->app_type : ($btcpayApp['appType'] ?? 'PointOfSale');
                            $appType = strtolower($appTypeRaw);
                            
                            $formattedApp = [
                                'id' => $localApp ? $localApp->id : null,
                                'name' => $btcpayApp['name'] ?? 'Untitled App',
                                'app_type' => $appTypeRaw, // Keep original format (e.g., 'Crowdfund', 'PointOfSale')
                                'btcpay_app_id' => $btcpayAppId,
                            ];
                            
                            // Group by type (normalize to lowercase for comparison)
                            if ($appType === 'crowdfund') {
                                $appsGrouped['crowdfund'][] = $formattedApp;
                            } elseif ($appType === 'pointofsale' || $appType === 'point_of_sale') {
                                $appsGrouped['point_of_sale'][] = $formattedApp;
                            } elseif ($appType === 'paymentbutton' || $appType === 'payment_button') {
                                $appsGrouped['payment_button'][] = $formattedApp;
                            }
                            
                            $apps[] = $formattedApp;
                        }
                    }
                } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
                    Log::warning('BTCPay apps listing failed for dashboard', [
                        'store_id' => $store->id,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue with empty apps - fallback to local DB if needed
                    $localAppsFallback = App::where('store_id', $store->id)->get();
                    foreach ($localAppsFallback as $localApp) {
                        $appTypeRaw = $localApp->app_type;
                        $appType = strtolower($appTypeRaw);
                        $formattedApp = [
                            'id' => $localApp->id,
                            'name' => $localApp->name,
                            'app_type' => $appTypeRaw, // Keep original format
                            'btcpay_app_id' => $localApp->btcpay_app_id,
                        ];
                        
                        if ($appType === 'crowdfund') {
                            $appsGrouped['crowdfund'][] = $formattedApp;
                        } elseif ($appType === 'pointofsale' || $appType === 'point_of_sale') {
                            $appsGrouped['point_of_sale'][] = $formattedApp;
                        } elseif ($appType === 'paymentbutton' || $appType === 'payment_button') {
                            $appsGrouped['payment_button'][] = $formattedApp;
                        }
                    }
                }
                
                // Determine store status (ready to accept payments if has wallet connection)
                $store->load('walletConnection');
                $hasWalletConnection = $store->walletConnection !== null;
                $isReady = $hasWalletConnection;
                
                return response()->json([
                    'data' => [
                        'paid_invoices_last_7d' => $paidInvoicesLast7d,
                        'total_invoices' => $totalInvoices,
                        'recent_invoices' => $formattedRecentInvoices,
                        'apps' => $appsGrouped,
                        'is_ready' => $isReady,
                        'has_wallet_connection' => $hasWalletConnection,
                        'sales' => [
                            'last_7_days' => array_values($salesLast7Days),
                            'last_30_days' => array_values($salesLast30Days),
                            'total_7d' => $totalSales7d,
                            'total_30d' => $totalSales30d,
                        ],
                        'top_items' => $topItems,
                    ],
                ]);
            } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
                Log::error('BTCPay API error when loading dashboard', [
                    'store_id' => $store->id,
                    'error' => $e->getMessage(),
                ]);
                
                // Return minimal data on error
                return response()->json([
                    'data' => [
                        'paid_invoices_last_7d' => 0,
                        'total_invoices' => 0,
                        'recent_invoices' => [],
                        'apps' => [
                            'crowdfund' => [],
                            'point_of_sale' => [],
                            'payment_button' => [],
                        ],
                        'is_ready' => false,
                        'has_wallet_connection' => false,
                        'sales' => [
                            'last_7_days' => [],
                            'last_30_days' => [],
                            'total_7d' => 0,
                            'total_30d' => 0,
                        ],
                        'top_items' => [],
                        'error' => 'Failed to load dashboard data',
                    ],
                ]);
            }
        });
    }
}

