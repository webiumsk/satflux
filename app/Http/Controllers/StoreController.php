<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCreateRequest;
use App\Models\Store;
use App\Services\BtcPay\StoreService;
use App\Services\StoreChecklistService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StoreController extends Controller
{
    protected StoreService $storeService;

    public function __construct(StoreService $storeService)
    {
        $this->storeService = $storeService;
    }

    /**
     * List all stores for the authenticated user.
     */
    public function index(Request $request)
    {
        $stores = Store::where('user_id', $request->user()->id)
            ->with(['checklistItems'])
            ->get()
            ->map(function ($store) {
                return $this->formatStore($store);
            });

        return response()->json(['data' => $stores]);
    }

    /**
     * Create a new store.
     */
    public function store(StoreCreateRequest $request)
    {
        return DB::transaction(function () use ($request) {
            // Create store in BTCPay
            $btcpayStore = $this->storeService->createStore([
                'name' => $request->name,
                'defaultCurrency' => $request->default_currency,
                'timeZone' => $request->timezone,
            ]);

            // Create local store record
            $store = Store::create([
                'id' => (string) Str::uuid(),
                'user_id' => $request->user()->id,
                'btcpay_store_id' => $btcpayStore['id'] ?? $btcpayStore['storeId'],
                'name' => $request->name,
                'wallet_type' => $request->wallet_type,
            ]);

            // Initialize checklist
            StoreChecklistService::initializeChecklist($store->id, $request->wallet_type);

            return response()->json([
                'data' => $this->formatStore($store->load('checklistItems')),
                'message' => 'Store created successfully',
            ], 201);
        });
    }

    /**
     * Get a specific store.
     */
    public function show(Request $request)
    {
        $store = $request->route('store');
        return response()->json(['data' => $this->formatStore($store->load('checklistItems'))]);
    }

    /**
     * Format store for API response (never expose btcpay_store_id).
     */
    protected function formatStore(Store $store): array
    {
        return [
            'id' => $store->id,
            'name' => $store->name,
            'wallet_type' => $store->wallet_type,
            'created_at' => $store->created_at,
            'updated_at' => $store->updated_at,
            'checklist_items' => $store->checklistItems->map(function ($item) use ($store) {
                $definition = StoreChecklistService::getChecklistItems($store->wallet_type);
                $itemDef = $definition[$item->item_key] ?? null;
                
                return [
                    'key' => $item->item_key,
                    'description' => $itemDef['description'] ?? $item->item_key,
                    'link' => $itemDef['link'] ?? null,
                    'completed_at' => $item->completed_at,
                    'is_completed' => $item->isCompleted(),
                ];
            })->values(),
        ];
    }
}

