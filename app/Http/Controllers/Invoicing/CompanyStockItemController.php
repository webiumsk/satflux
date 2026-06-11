<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoicing\StoreCompanyStockItemRequest;
use App\Http\Requests\Invoicing\UpdateCompanyStockItemRequest;
use App\Models\Company;
use App\Models\CompanyStockItem;
use App\Services\Invoicing\CompanyStockItemImportService;
use App\Services\Invoicing\CompanyStockItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyStockItemController extends Controller
{
    public function __construct(
        protected CompanyStockItemService $stockItemService,
        protected CompanyStockItemImportService $importService,
    ) {}

    public function index(Request $request, Company $company): JsonResponse
    {
        $warehouseId = $this->resolveCompanyWarehouseId($company, $request->input('warehouse_id'));

        $result = $this->stockItemService->list(
            $company,
            $request->input('q'),
            $warehouseId,
        );

        return response()->json([
            'data' => $result['data'],
            'meta' => $result['meta'],
        ]);
    }

    public function search(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:25'],
            'warehouse_id' => ['nullable', 'uuid'],
        ]);

        $warehouseId = $this->resolveCompanyWarehouseId($company, $validated['warehouse_id'] ?? null);

        return response()->json([
            'data' => $this->stockItemService->search(
                $company,
                $validated['q'],
                (int) ($validated['limit'] ?? 10),
                $warehouseId,
            ),
        ]);
    }

    protected function resolveCompanyWarehouseId(Company $company, mixed $warehouseId): ?string
    {
        if ($warehouseId === null || $warehouseId === '') {
            return null;
        }

        $owned = $company->warehouses()->where('id', $warehouseId)->exists();
        if (! $owned) {
            abort(403);
        }

        return (string) $warehouseId;
    }

    public function store(StoreCompanyStockItemRequest $request, Company $company): JsonResponse
    {
        $item = $this->stockItemService->create($company, $request->validated());

        return response()->json([
            'data' => $this->stockItemService->showPayload($company, $item),
        ], 201);
    }

    public function show(Company $company, CompanyStockItem $stockItem): JsonResponse
    {
        $this->assertStockItemBelongsToCompany($stockItem, $company);

        return response()->json([
            'data' => $this->stockItemService->showPayload($company, $stockItem),
        ]);
    }

    public function update(
        UpdateCompanyStockItemRequest $request,
        Company $company,
        CompanyStockItem $stockItem,
    ): JsonResponse {
        $this->assertStockItemBelongsToCompany($stockItem, $company);

        $item = $this->stockItemService->update($company, $stockItem, $request->validated());

        return response()->json(['data' => $this->stockItemService->showPayload($company, $item)]);
    }

    public function destroy(Company $company, CompanyStockItem $stockItem): JsonResponse
    {
        $this->assertStockItemBelongsToCompany($stockItem, $company);
        $this->stockItemService->delete($company, $stockItem);

        return response()->json(['message' => 'Stock item deleted']);
    }

    public function importExample(Company $company): StreamedResponse
    {
        return $this->importService->exampleDownloadResponse();
    }

    public function importPreview(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
            'mapping' => ['nullable', 'string'],
        ]);

        $mapping = $this->decodeImportMapping($validated['mapping'] ?? null);

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
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
            'mapping' => ['nullable', 'string'],
        ]);

        $mapping = $this->decodeImportMapping($validated['mapping'] ?? null);

        try {
            $result = $this->importService->importFile($company, $validated['file'], $mapping);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $result]);
    }

    /**
     * @return array<string, int|string|null>|null
     */
    protected function decodeImportMapping(?string $json): ?array
    {
        if ($json === null || $json === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    protected function assertStockItemBelongsToCompany(CompanyStockItem $stockItem, Company $company): void
    {
        if ($stockItem->company_id !== $company->id) {
            abort(404);
        }
    }
}
