<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    /**
     * Get dashboard data.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $stores = Store::where('user_id', $user->id)
            ->with(['checklistItems'])
            ->get()
            ->map(function ($store) {
                return [
                    'id' => $store->id,
                    'name' => $store->name,
                    'wallet_type' => $store->wallet_type,
                    'created_at' => $store->created_at,
                    // Lightweight stats (can be cached/optimized later)
                ];
            });

        return response()->json([
            'stores' => $stores,
        ]);
    }
}

