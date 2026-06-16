<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Services\Invoicing\ServerToEvoluMigrationExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoicingMigrationController extends Controller
{
    public function status(
        Request $request,
        ServerToEvoluMigrationExportService $exportService,
    ): JsonResponse {
        return response()->json([
            'data' => $exportService->statusForUser($request->user()),
        ]);
    }

    public function export(
        Request $request,
        ServerToEvoluMigrationExportService $exportService,
    ): JsonResponse {
        $result = $exportService->exportForUser($request->user());

        if (($result['counts']['company'] ?? 0) === 0) {
            return response()->json([
                'message' => 'No server invoicing data to export.',
            ], 404);
        }

        return response()->json([
            'data' => $result['snapshot'],
            'meta' => [
                'exported_at' => now()->toIso8601String(),
                'warnings' => $result['warnings'],
                'counts' => $result['counts'],
            ],
        ]);
    }
}
