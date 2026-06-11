<?php

namespace App\Services\Invoicing;

use App\Models\Company;
use App\Models\CompanyStockItem;
use App\Models\CompanyWarehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CompanyStockItemService
{
    public function __construct(
        protected CompanyStockMovementService $movementService,
        protected CompanyWarehouseService $warehouseService,
        protected CompanyStockBalanceService $balanceService,
    ) {}

    /**
     * @return array{data: Collection<int, CompanyStockItem>, meta: array<string, mixed>}
     */
    public function list(Company $company, ?string $search = null, ?string $warehouseId = null): array
    {
        $query = $company->stockItems()->with(['balances.warehouse'])->orderBy('name');

        if ($search !== null && trim($search) !== '') {
            $pattern = '%'.trim($search).'%';
            $query->where(function (Builder $q) use ($pattern) {
                $this->whereLikeInsensitive($q, 'name', $pattern);
                $q->orWhere(function (Builder $inner) use ($pattern) {
                    $this->whereLikeInsensitive($inner, 'sku', $pattern);
                });
                $q->orWhere(function (Builder $inner) use ($pattern) {
                    $this->whereLikeInsensitive($inner, 'description', $pattern);
                });
            });
        }

        if ($warehouseId) {
            $query->whereHas('balances', function (Builder $q) use ($warehouseId) {
                $q->where('company_warehouse_id', $warehouseId)
                    ->where('quantity_on_hand', '!=', 0);
            });
        }

        $items = $query->get();

        return [
            'data' => $items->map(fn (CompanyStockItem $item) => $this->listRowPayload($item, $warehouseId)),
            'meta' => $this->summaryMeta($company, $items),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(Company $company, string $query, int $limit = 10, ?string $warehouseId = null): array
    {
        $q = trim($query);
        if (mb_strlen($q) < 1) {
            return [];
        }

        $pattern = '%'.$q.'%';
        $items = $company->stockItems()
            ->with(['balances.warehouse'])
            ->where('exclude_from_suggester', false)
            ->where(function (Builder $builder) use ($pattern) {
                $this->whereLikeInsensitive($builder, 'name', $pattern);
                $builder->orWhere(function (Builder $inner) use ($pattern) {
                    $this->whereLikeInsensitive($inner, 'sku', $pattern);
                });
            })
            ->orderBy('name')
            ->limit(min($limit, 25))
            ->get();

        return $items->map(fn (CompanyStockItem $item) => $this->suggesterPayload($item, $warehouseId))->all();
    }

    public function create(Company $company, array $data): CompanyStockItem
    {
        $this->assertUniqueSku($company, $data['sku'] ?? null);

        $balances = $data['balances'] ?? null;
        $quantityOnHand = $data['quantity_on_hand'] ?? null;
        $warehouseId = $data['warehouse_id'] ?? null;
        unset($data['balances'], $data['quantity_on_hand'], $data['warehouse_id']);

        $item = $company->stockItems()->create($data);
        $item->refresh();

        if ($balances !== null) {
            $this->syncBalances($company, $item, $balances, isCreate: true);
        } elseif ($quantityOnHand !== null && $item->track_inventory) {
            $warehouse = $this->resolveWarehouse($company, $warehouseId);
            $qty = (float) $quantityOnHand;
            if ($qty !== 0.0) {
                $this->balanceService->setQuantity($warehouse, $item, $qty);
                $this->movementService->recordManualChange($item, $warehouse, 0.0, 'Initial stock level');
            }
        }

        return $item->fresh(['balances.warehouse']);
    }

    public function update(Company $company, CompanyStockItem $item, array $data): CompanyStockItem
    {
        if ($item->company_id !== $company->id) {
            abort(404);
        }

        if (array_key_exists('sku', $data)) {
            $this->assertUniqueSku($company, $data['sku'], $item->id);
        }

        $balances = $data['balances'] ?? null;
        $quantityOnHand = array_key_exists('quantity_on_hand', $data) ? $data['quantity_on_hand'] : null;
        $warehouseId = $data['warehouse_id'] ?? null;
        unset($data['balances'], $data['quantity_on_hand'], $data['warehouse_id']);

        $item->fill($data);
        $item->save();

        if ($balances !== null) {
            $this->syncBalances($company, $item, $balances);
        } elseif ($quantityOnHand !== null && $item->track_inventory) {
            $warehouse = $this->resolveWarehouse($company, $warehouseId);
            $previous = $this->balanceService->getQuantity($warehouse, $item);
            $this->balanceService->setQuantity($warehouse, $item, (float) $quantityOnHand);
            $this->movementService->recordManualChange($item, $warehouse, $previous);
        }

        return $item->fresh(['balances.warehouse']);
    }

    public function transfer(
        Company $company,
        CompanyStockItem $item,
        string $fromWarehouseId,
        string $toWarehouseId,
        float $quantity,
        ?string $note = null,
    ): void {
        if ($item->company_id !== $company->id) {
            abort(404);
        }

        $from = $this->findWarehouse($company, $fromWarehouseId);
        $to = $this->findWarehouse($company, $toWarehouseId);

        $this->movementService->recordTransfer($item, $from, $to, $quantity, $note);
    }

    public function delete(Company $company, CompanyStockItem $item): void
    {
        if ($item->company_id !== $company->id) {
            abort(404);
        }

        if ($item->documentLines()->exists()) {
            throw ValidationException::withMessages([
                'stock_item' => ['Stock item is used on documents and cannot be deleted.'],
            ]);
        }

        $item->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function showPayload(Company $company, CompanyStockItem $item): array
    {
        if ($item->company_id !== $company->id) {
            abort(404);
        }

        $item->load([
            'balances.warehouse',
            'movements' => fn ($q) => $q->with('warehouse:id,name')->limit(100),
        ]);

        $row = $item->toArray();
        $row['balances'] = $this->balanceService->balancesPayload($item);
        $row['movements'] = $item->movements->map(fn ($movement) => [
            'id' => $movement->id,
            'created_at' => $movement->created_at?->toIso8601String(),
            'quantity_after' => $movement->quantity_after,
            'quantity_delta' => $movement->quantity_delta,
            'purchase_unit_price' => $movement->purchase_unit_price,
            'sale_unit_price' => $movement->sale_unit_price,
            'note' => $movement->note,
            'source' => $movement->source->value,
            'document_number' => $movement->document_number,
            'document_type' => $movement->document_type,
            'business_document_id' => $movement->business_document_id,
            'company_warehouse_id' => $movement->company_warehouse_id,
            'warehouse_name' => $movement->warehouse?->name,
        ])->all();
        $row['neighbor_ids'] = $this->neighborIds($company, $item);

        return $row;
    }

    /**
     * @return list<string>
     */
    public function neighborIds(Company $company, CompanyStockItem $item): array
    {
        return $company->stockItems()
            ->orderBy('name')
            ->pluck('id')
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $balances
     */
    protected function syncBalances(Company $company, CompanyStockItem $item, array $balances, bool $isCreate = false): void
    {
        foreach ($balances as $row) {
            $warehouse = $this->findWarehouse($company, (string) ($row['warehouse_id'] ?? ''));
            $newQty = (float) ($row['quantity_on_hand'] ?? 0);
            $previous = $this->balanceService->getQuantity($warehouse, $item);

            if (abs($newQty - $previous) < 0.00001) {
                continue;
            }

            $this->balanceService->setQuantity($warehouse, $item, $newQty);

            if ($isCreate) {
                $this->movementService->recordManualChange(
                    $item,
                    $warehouse,
                    0.0,
                    'Initial stock level',
                );
            } else {
                $this->movementService->recordManualChange($item, $warehouse, $previous);
            }
        }
    }

    /**
     * @param  Collection<int, CompanyStockItem>  $items
     * @return array<string, mixed>
     */
    protected function summaryMeta(Company $company, Collection $items): array
    {
        $currency = strtoupper((string) $company->default_currency);
        $purchaseTotal = 0.0;
        $saleTotal = 0.0;

        foreach ($items as $item) {
            $qty = (float) $item->quantity_on_hand;
            if ($item->purchase_unit_price !== null
                && strtoupper((string) $item->purchase_currency) === $currency) {
                $purchaseTotal += $qty * (float) $item->purchase_unit_price;
            }
            if ($item->sale_unit_price !== null) {
                $saleTotal += $qty * (float) $item->sale_unit_price;
            }
        }

        return [
            'item_count' => $items->count(),
            'purchase_value_total' => round($purchaseTotal, 2),
            'sale_value_total' => round($saleTotal, 2),
            'summary_currency' => $currency,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function listRowPayload(CompanyStockItem $item, ?string $warehouseId): array
    {
        $row = $item->toArray();
        $row['balances'] = $this->balanceService->balancesPayload($item);

        if ($warehouseId) {
            $balance = $item->balances->firstWhere('company_warehouse_id', $warehouseId);
            $row['quantity_on_hand'] = $balance ? (float) $balance->quantity_on_hand : 0.0;
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    protected function suggesterPayload(CompanyStockItem $item, ?string $warehouseId = null): array
    {
        $quantitiesByWarehouse = collect($this->balanceService->balancesPayload($item))
            ->mapWithKeys(fn (array $row) => [$row['warehouse_id'] => (float) $row['quantity_on_hand']])
            ->all();

        $selectedWarehouseQty = null;
        $deductOnIssue = null;

        if ($warehouseId && isset($quantitiesByWarehouse[$warehouseId])) {
            $selectedWarehouseQty = $quantitiesByWarehouse[$warehouseId];
            $balanceRow = collect($this->balanceService->balancesPayload($item))
                ->firstWhere('warehouse_id', $warehouseId);
            $deductOnIssue = $balanceRow['deduct_on_issue'] ?? null;
        } elseif ($warehouseId) {
            $warehouse = CompanyWarehouse::query()
                ->where('company_id', $item->company_id)
                ->find($warehouseId);
            $selectedWarehouseQty = 0.0;
            $deductOnIssue = $warehouse?->deduct_on_issue;
        }

        return [
            'id' => $item->id,
            'name' => $item->name,
            'description' => $item->description,
            'sku' => $item->sku,
            'unit' => $item->unit,
            'sale_unit_price' => $item->sale_unit_price,
            'quantity_on_hand' => $selectedWarehouseQty ?? (float) $item->quantity_on_hand,
            'total_on_hand' => (float) $item->quantity_on_hand,
            'quantities_by_warehouse' => $quantitiesByWarehouse,
            'track_inventory' => $item->track_inventory,
            'deduct_on_issue' => $deductOnIssue,
        ];
    }

    protected function resolveWarehouse(Company $company, ?string $warehouseId): CompanyWarehouse
    {
        if ($warehouseId) {
            return $this->findWarehouse($company, $warehouseId);
        }

        return $this->warehouseService->defaultWarehouse($company);
    }

    protected function findWarehouse(Company $company, string $warehouseId): CompanyWarehouse
    {
        $warehouse = $company->warehouses()->where('id', $warehouseId)->first();
        if (! $warehouse) {
            throw ValidationException::withMessages([
                'warehouse_id' => ['Invalid warehouse for this company.'],
            ]);
        }

        return $warehouse;
    }

    protected function assertUniqueSku(Company $company, ?string $sku, ?string $ignoreId = null): void
    {
        $sku = trim((string) $sku);
        if ($sku === '') {
            return;
        }

        $query = $company->stockItems()->where('sku', $sku);
        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'sku' => ['SKU must be unique within this company.'],
            ]);
        }
    }

    protected function whereLikeInsensitive(Builder $query, string $column, string $pattern): void
    {
        if (! in_array($column, ['name', 'sku', 'description'], true)) {
            throw new \InvalidArgumentException('Invalid search column.');
        }

        if ($query->getConnection()->getDriverName() === 'pgsql') {
            $query->where($column, 'ilike', $pattern);
        } else {
            $query->whereRaw('LOWER('.$column.') LIKE ?', [mb_strtolower($pattern)]);
        }
    }
}
