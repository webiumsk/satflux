<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Services\Invoicing\ServerToEvoluMigrationExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

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
        try {
            $includeAttachments = $request->boolean('include_attachments');
            $result = $exportService->exportForUser($request->user(), [
                'include_attachment_content' => $includeAttachments,
            ]);

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
                    'include_attachments' => $includeAttachments,
                ],
            ], 200, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Throwable $e) {
            Log::error('invoicing.migration.export_failed', [
                'user_id' => $request->user()?->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Invoicing export failed.',
            ], 500);
        }
    }
}
