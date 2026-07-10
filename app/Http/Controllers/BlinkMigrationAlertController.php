<?php

namespace App\Http\Controllers;

use App\Services\BlinkMigrationAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlinkMigrationAlertController extends Controller
{
    public function __construct(
        protected BlinkMigrationAlertService $alertService,
    ) {}

    public function snooze(Request $request): JsonResponse
    {
        $store = $request->route('store');
        $store = $this->alertService->snooze($store);

        return response()->json([
            'data' => [
                'blink_migration_alert' => $this->alertService->payload($store),
            ],
        ]);
    }

    public function dismiss(Request $request): JsonResponse
    {
        $store = $request->route('store');
        $store = $this->alertService->dismiss($store);

        return response()->json([
            'data' => [
                'blink_migration_alert' => $this->alertService->payload($store),
            ],
        ]);
    }
}
