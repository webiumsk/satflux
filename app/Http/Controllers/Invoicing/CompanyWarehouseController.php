<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoicing\StoreCompanyWarehouseRequest;
use App\Http\Requests\Invoicing\TransferCompanyStockRequest;
use App\Http\Requests\Invoicing\UpdateCompanyWarehouseRequest;
use App\Models\Company;
use App\Models\CompanyStockItem;
use App\Models\CompanyWarehouse;
use App\Services\Invoicing\CompanyStockItemService;
use App\Services\Invoicing\CompanyWarehouseService;
use Illuminate\Http\JsonResponse;

class CompanyWarehouseController extends Controller
{
    public function __construct(
        protected CompanyWarehouseService $warehouseService,
        protected CompanyStockItemService $stockItemService,
    ) {}

    public function index(Company $company): JsonResponse
    {
        $warehouses = $this->warehouseService->list($company, activeOnly: false);

        return response()->json([
            'data' => $warehouses->map(fn (CompanyWarehouse $w) => $this->warehouseService->payload($w))->all(),
        ]);
    }

    public function store(StoreCompanyWarehouseRequest $request, Company $company): JsonResponse
    {
        $warehouse = $this->warehouseService->create($company, $request->validated());

        return response()->json(['data' => $this->warehouseService->payload($warehouse)], 201);
    }

    public function show(Company $company, CompanyWarehouse $warehouse): JsonResponse
    {
        $this->assertBelongsToCompany($warehouse, $company);

        return response()->json(['data' => $this->warehouseService->payload($warehouse)]);
    }

    public function update(
        UpdateCompanyWarehouseRequest $request,
        Company $company,
        CompanyWarehouse $warehouse,
    ): JsonResponse {
        $this->assertBelongsToCompany($warehouse, $company);

        $warehouse = $this->warehouseService->update($company, $warehouse, $request->validated());

        return response()->json(['data' => $this->warehouseService->payload($warehouse)]);
    }

    public function destroy(Company $company, CompanyWarehouse $warehouse): JsonResponse
    {
        $this->assertBelongsToCompany($warehouse, $company);
        $this->warehouseService->delete($company, $warehouse);

        return response()->json(['message' => 'Warehouse deleted']);
    }

    public function transfer(
        TransferCompanyStockRequest $request,
        Company $company,
        CompanyStockItem $stockItem,
    ): JsonResponse {
        if ($stockItem->company_id !== $company->id) {
            abort(404);
        }

        $validated = $request->validated();
        $this->stockItemService->transfer(
            $company,
            $stockItem,
            $validated['from_warehouse_id'],
            $validated['to_warehouse_id'],
            (float) $validated['quantity'],
            $validated['note'] ?? null,
        );

        return response()->json([
            'data' => $this->stockItemService->showPayload($company, $stockItem->fresh()),
        ]);
    }

    protected function assertBelongsToCompany(CompanyWarehouse $warehouse, Company $company): void
    {
        if ($warehouse->company_id !== $company->id) {
            abort(404);
        }
    }
}
