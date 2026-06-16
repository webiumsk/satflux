<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Services\Invoicing\ServerToEvoluMigrationExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
            $includeBranding = $request->boolean('include_branding');
            $result = $exportService->exportForUser($request->user(), [
                'include_attachment_content' => $includeAttachments,
                'include_branding' => $includeBranding,
            ]);

            if (($result['counts']['company'] ?? 0) === 0) {
                return response()->json([
                    'message' => 'No server invoicing data to export.',
                ], 404);
            }

            $payload = [
                'data' => $result['snapshot'],
                'meta' => [
                    'exported_at' => now()->toIso8601String(),
                    'warnings' => $result['warnings'],
                    'counts' => $result['counts'],
                    'include_attachments' => $includeAttachments,
                    'include_branding' => $includeBranding,
                ],
            ];

            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($json === false) {
                throw new \RuntimeException('json_encode failed: '.json_last_error_msg());
            }

            return new JsonResponse($json, 200, [
                'Content-Type' => 'application/json',
            ], 0, true);
        } catch (Throwable $e) {
            $errorId = (string) Str::uuid();
            Log::error('invoicing.migration.export_failed', [
                'error_id' => $errorId,
                'user_id' => $request->user()?->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Invoicing export failed.',
                'error_id' => $errorId,
                'debug' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function exportAttachments(
        Request $request,
        ServerToEvoluMigrationExportService $exportService,
    ): JsonResponse {
        try {
            $result = $exportService->exportAttachmentsForUser($request->user());

            if (($result['counts']['expenseAttachment'] ?? 0) === 0) {
                return response()->json([
                    'message' => 'No server expense attachments to export.',
                ], 404);
            }

            $payload = [
                'data' => $result['snapshot'],
                'meta' => [
                    'exported_at' => now()->toIso8601String(),
                    'warnings' => $result['warnings'],
                    'counts' => $result['counts'],
                ],
            ];

            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($json === false) {
                throw new \RuntimeException('json_encode failed: '.json_last_error_msg());
            }

            return new JsonResponse($json, 200, [
                'Content-Type' => 'application/json',
            ], 0, true);
        } catch (Throwable $e) {
            $errorId = (string) Str::uuid();
            Log::error('invoicing.migration.export_attachments_failed', [
                'error_id' => $errorId,
                'user_id' => $request->user()?->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Expense attachment export failed.',
                'error_id' => $errorId,
                'debug' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
