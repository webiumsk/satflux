<?php

namespace App\Http\Controllers;

use App\Models\StoreChecklist;
use Illuminate\Http\Request;

class StoreChecklistController extends Controller
{
    /**
     * Get checklist for a store.
     */
    public function index(Request $request)
    {
        $store = $request->route('store');
        
        $checklistItems = $store->checklistItems()->get()->map(function ($item) use ($store) {
            $definition = \App\Services\StoreChecklistService::getChecklistItems($store->wallet_type);
            $itemDef = $definition[$item->item_key] ?? null;
            $btcpayStoreId = $store->btcpay_store_id;
            
            // Replace {storeId} placeholder in link
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
        });

        return response()->json(['data' => $checklistItems]);
    }

    /**
     * Update checklist item (mark as done/undone).
     */
    public function update(Request $request, string $itemKey)
    {
        $store = $request->route('store');
        
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

        $definition = \App\Services\StoreChecklistService::getChecklistItems($store->wallet_type);
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








