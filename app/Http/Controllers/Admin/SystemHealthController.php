<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SystemHealthService;
use Illuminate\Http\JsonResponse;

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
            ],
        ]);
    }
}
