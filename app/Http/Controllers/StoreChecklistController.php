<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\StoreChecklist;
use App\Services\StoreChecklistService;
use Illuminate\Http\Request;

class StoreChecklistController extends Controller
{
    /**
     * Get checklist for a store.
     */
    public function index(Request $request, Store $store)
    {
        StoreChecklistService::ensureChecklistInitialized($store);

        $definition = StoreChecklistService::getChecklistItems($store->wallet_type ?? '');
        $btcpayStoreId = $store->btcpay_store_id;

        $checklistItems = $store->checklistItems()->get()
            ->filter(fn ($item) => isset($definition[$item->item_key]))
            ->map(function ($item) use ($definition, $btcpayStoreId) {
                $itemDef = $definition[$item->item_key];
                $link = $itemDef['link'] ?? null;
                if ($link && $btcpayStoreId) {
                    $link = str_replace('{storeId}', $btcpayStoreId, $link);
                }

                return [
                    'key' => $item->item_key,
                    'description' => $itemDef['description'] ?? $item->item_key,
                    'link' => $link,
                    'completed_at' => $item->completed_at?->toIso8601String(),
                    'is_completed' => $item->isCompleted(),
                    'optional' => $itemDef['optional'] ?? false,
                ];
            })
            ->values();

        return response()->json(['data' => $checklistItems]);
    }

    /**
     * Update checklist item (mark as done/undone).
     */
    public function update(Request $request, Store $store, string $itemKey)
    {

        $request->validate([
            'completed' => ['required', 'boolean'],
        ]);

        $item = StoreChecklist::where('store_id', $store->id)
            ->where('item_key', $itemKey)
            ->firstOrFail();

        if ($request->completed) {
            $item->markAsCompleted();
        } else {
            $item->markAsIncomplete();
        }

        $definition = StoreChecklistService::getChecklistItems($store->wallet_type ?? '');
        $itemDef = $definition[$itemKey] ?? null;
        $btcpayStoreId = $store->btcpay_store_id;

        $link = $itemDef['link'] ?? null;
        if ($link && $btcpayStoreId) {
            $link = str_replace('{storeId}', $btcpayStoreId, $link);
        }

        return response()->json([
            'data' => [
                'key' => $item->item_key,
                'description' => $itemDef['description'] ?? $item->item_key,
                'link' => $link,
                'completed_at' => $item->completed_at?->toIso8601String(),
                'is_completed' => $item->isCompleted(),
                'optional' => $itemDef['optional'] ?? false,
            ],
        ]);
    }
}
