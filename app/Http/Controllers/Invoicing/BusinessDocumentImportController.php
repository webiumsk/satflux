<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Invoicing\BusinessDocumentExcelImportService;
use App\Support\Invoicing\BusinessDocumentImportFields;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BusinessDocumentImportController extends Controller
{
    public function __construct(
        protected BusinessDocumentExcelImportService $importService,
    ) {}

    public function fields(): JsonResponse
    {
        return response()->json([
            'data' => BusinessDocumentImportFields::definitions(),
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
            'line_name' => ['nullable', 'string', 'max:255'],
            'line_description' => ['nullable', 'string', 'max:5000'],
            'create_contacts' => ['nullable', 'boolean'],
            'date_format' => ['nullable', 'string', Rule::in(['auto', 'dmy_dot', 'ymd_dash', 'mdy_slash'])],
        ]);

        $mapping = $this->decodeMapping($validated['mapping'] ?? null);
        $options = [
            'line_name' => $validated['line_name'] ?? null,
            'line_description' => $validated['line_description'] ?? null,
            'create_contacts' => $request->boolean('create_contacts', true),
            'date_format' => $validated['date_format'] ?? 'auto',
        ];

        try {
            $result = $this->importService->preview($company, $validated['file'], $mapping, $options);
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
            'line_name' => ['nullable', 'string', 'max:255'],
            'line_description' => ['nullable', 'string', 'max:5000'],
            'create_contacts' => ['nullable', 'boolean'],
            'date_format' => ['nullable', 'string', Rule::in(['auto', 'dmy_dot', 'ymd_dash', 'mdy_slash'])],
        ]);

        $mapping = $this->decodeMapping($validated['mapping']);
        if ($mapping === null) {
            return response()->json(['message' => 'Invalid column mapping.'], 422);
        }

        $options = [
            'line_name' => $validated['line_name'] ?? null,
            'line_description' => $validated['line_description'] ?? null,
            'create_contacts' => $request->boolean('create_contacts', true),
            'date_format' => $validated['date_format'] ?? 'auto',
        ];

        try {
            $result = $this->importService->import($company, $validated['file'], $mapping, $options);
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
