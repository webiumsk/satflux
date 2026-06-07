<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Invoicing\BusinessExpenseAttachmentBulkService;
use App\Services\Invoicing\BusinessExpenseExcelImportService;
use App\Support\Invoicing\BusinessExpenseImportFields;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BusinessExpenseImportController extends Controller
{
    public function __construct(
        protected BusinessExpenseExcelImportService $importService,
        protected BusinessExpenseAttachmentBulkService $attachmentBulkService,
    ) {}

    public function fields(): JsonResponse
    {
        return response()->json([
            'data' => BusinessExpenseImportFields::definitions(),
        ]);
    }

    public function example(Company $company): StreamedResponse
    {
        return $this->importService->exampleDownloadResponse();
    }

    public function preview(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'mapping' => ['nullable', 'string'],
        ]);

        $mapping = $this->decodeMapping($validated['mapping'] ?? null);

        try {
            $result = $this->importService->preview($company, $validated['file'], $mapping);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $result]);
    }

    public function import(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'mapping' => ['required', 'string'],
        ]);

        $mapping = $this->decodeMapping($validated['mapping']);
        if ($mapping === null) {
            return response()->json(['message' => 'Invalid column mapping.'], 422);
        }

        try {
            $result = $this->importService->import($company, $validated['file'], $mapping);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $result]);
    }

    public function attachmentsPreview(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:51200', 'mimes:zip,pdf'],
        ]);

        try {
            $result = $this->attachmentBulkService->preview($company, $validated['file']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $result]);
    }

    public function attachmentsImport(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:51200', 'mimes:zip,pdf'],
        ]);

        try {
            $result = $this->attachmentBulkService->import($company, $validated['file']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $result]);
    }

    /**
     * @return array<string, int|string|null>|null
     */
    protected function decodeMapping(?string $json): ?array
    {
        if ($json === null || $json === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }
}
