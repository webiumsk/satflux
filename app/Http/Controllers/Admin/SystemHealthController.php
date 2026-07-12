<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemHealthSnapshot;
use App\Services\SystemHealthService;
use App\Support\ErrorRateCounter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemHealthController extends Controller
{
    public function show(SystemHealthService $health): JsonResponse
    {
        $results = $health->runChecks();

        return response()->json([
            'data' => [
                'healthy' => $health->allHealthy($results),
                'checked_at' => now()->toIso8601String(),
                'checks' => $results,
                'error_rate' => [
                    'current_hour' => ErrorRateCounter::currentHourCount(),
                    'previous_hour' => ErrorRateCounter::previousHourCount(),
                    'threshold' => (int) config('monitoring.error_rate_threshold', 25),
                ],
            ],
        ]);
    }

    /** Recent scheduled snapshots for the admin dashboard history strip. */
    public function history(Request $request): JsonResponse
    {
        $limit = min(200, max(1, (int) $request->query('limit', 50)));

        $snapshots = SystemHealthSnapshot::query()
            ->latest('created_at')
            ->limit($limit)
            ->get(['id', 'healthy', 'checks', 'created_at']);

        return response()->json(['data' => $snapshots]);
    }
}
