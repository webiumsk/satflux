<?php

namespace App\Services\Invoicing;

use App\Models\Company;
use App\Models\CompanyStockItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CompanyStockItemService
{
    public function __construct(
        protected CompanyStockMovementService $movementService,
    ) {}

    /**
     * @return array{data: Collection<int, CompanyStockItem>, meta: array<string, mixed>}
     */
    public function list(Company $company, ?string $search = null): array
    {
        $query = $company->stockItems()->orderBy('name');

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

        $items = $query->get();

        return [
            'data' => $items,
            'meta' => $this->summaryMeta($company, $items),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(Company $company, string $query, int $limit = 10): array
    {
        $q = trim($query);
        if (mb_strlen($q) < 1) {
            return [];
        }

        $pattern = '%'.$q.'%';
        $items = $company->stockItems()
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

        return $items->map(fn (CompanyStockItem $item) => $this->suggesterPayload($item))->all();
    }

    public function create(Company $company, array $data): CompanyStockItem
    {
        $this->assertUniqueSku($company, $data['sku'] ?? null);

        $item = $company->stockItems()->create($data);

        if ($item->track_inventory && (float) $item->quantity_on_hand !== 0.0) {
            $this->movementService->recordManualChange(
                $item,
                0.0,
                'Initial stock level',
            );
        }

        return $item->fresh();
    }

    public function update(Company $company, CompanyStockItem $item, array $data): CompanyStockItem
    {
        if ($item->company_id !== $company->id) {
            abort(404);
        }

        if (array_key_exists('sku', $data)) {
            $this->assertUniqueSku($company, $data['sku'], $item->id);
        }

        $previousQuantity = (float) $item->quantity_on_hand;
        $item->fill($data);
        $item->save();

        $this->movementService->recordManualChange($item, $previousQuantity);

        return $item->fresh();
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

        $item->load(['movements' => fn ($q) => $q->limit(100)]);

        $row = $item->toArray();
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
    protected function suggesterPayload(CompanyStockItem $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'description' => $item->description,
            'sku' => $item->sku,
            'unit' => $item->unit,
            'sale_unit_price' => $item->sale_unit_price,
            'quantity_on_hand' => $item->quantity_on_hand,
            'track_inventory' => $item->track_inventory,
        ];
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
        if ($query->getConnection()->getDriverName() === 'pgsql') {
            $query->where($column, 'ilike', $pattern);
        } else {
            $query->whereRaw('LOWER('.$column.') LIKE ?', [mb_strtolower($pattern)]);
        }
    }
}
