<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\Boltz\BoltzStoreReadinessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoltzReadinessController extends Controller
{
    public function __construct(protected BoltzStoreReadinessService $readinessService) {}

    /**
     * Informational Boltz readiness snapshot for the store (limits are orientational;
     * the authoritative per-invoice validation happens in BTCPay when the invoice is created).
     */
    public function show(Request $request, Store $store): JsonResponse
    {
        $readiness = $this->readinessService->readiness(
            $store,
            $request->boolean('refresh')
        );

        return response()->json(['data' => $readiness]);
    }
}
