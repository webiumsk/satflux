<?php

namespace App\Services\Invoicing;

use App\Models\CompanyStockBalance;
use App\Models\CompanyStockItem;
use App\Models\CompanyWarehouse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanyStockBalanceService
{
    public function getQuantity(CompanyWarehouse $warehouse, CompanyStockItem $item): float
    {
        $balance = $this->findBalance($warehouse, $item);

        return $balance ? (float) $balance->quantity_on_hand : 0.0;
    }

    public function getOrCreateBalance(CompanyWarehouse $warehouse, CompanyStockItem $item, bool $lock = false): CompanyStockBalance
    {
        $this->assertSameCompany($warehouse, $item);

        $query = CompanyStockBalance::query()
            ->where('company_warehouse_id', $warehouse->id)
            ->where('company_stock_item_id', $item->id);

        if ($lock) {
            $query->lockForUpdate();
        }

        $balance = $query->first();

        if ($balance) {
            return $balance;
        }

        if (! $lock) {
            return CompanyStockBalance::create([
                'company_warehouse_id' => $warehouse->id,
                'company_stock_item_id' => $item->id,
                'quantity_on_hand' => 0,
            ]);
        }

        try {
            return CompanyStockBalance::create([
                'company_warehouse_id' => $warehouse->id,
                'company_stock_item_id' => $item->id,
                'quantity_on_hand' => 0,
            ]);
        } catch (UniqueConstraintViolationException) {
            return CompanyStockBalance::query()
                ->where('company_warehouse_id', $warehouse->id)
                ->where('company_stock_item_id', $item->id)
                ->lockForUpdate()
                ->firstOrFail();
        }
    }

    public function setQuantity(
        CompanyWarehouse $warehouse,
        CompanyStockItem $item,
        float $newQuantity,
    ): CompanyStockBalance {
        return DB::transaction(function () use ($warehouse, $item, $newQuantity) {
            $balance = $this->getOrCreateBalance($warehouse, $item, lock: true);
            $balance->quantity_on_hand = $newQuantity;
            $balance->save();

            return $balance;
        });
    }

    /**
     * @return array{out: CompanyStockBalance, in: CompanyStockBalance}
     */
    public function transfer(
        CompanyWarehouse $from,
        CompanyWarehouse $to,
        CompanyStockItem $item,
        float $quantity,
    ): array {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => ['Transfer quantity must be positive.'],
            ]);
        }

        if ($from->id === $to->id) {
            throw ValidationException::withMessages([
                'warehouse' => ['Source and destination warehouse must differ.'],
            ]);
        }

        $this->assertSameCompany($from, $item);
        $this->assertSameCompany($to, $item);

        return DB::transaction(function () use ($from, $to, $item, $quantity) {
            $outBalance = $this->getOrCreateBalance($from, $item, lock: true);
            $inBalance = $this->getOrCreateBalance($to, $item, lock: true);

            $outBalance->quantity_on_hand = (float) $outBalance->quantity_on_hand - $quantity;
            $inBalance->quantity_on_hand = (float) $inBalance->quantity_on_hand + $quantity;

            $outBalance->save();
            $inBalance->save();

            return ['out' => $outBalance, 'in' => $inBalance];
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function balancesPayload(CompanyStockItem $item): array
    {
        return $item->balances()
            ->with('warehouse:id,name,type,is_default,deduct_on_issue')
            ->get()
            ->map(fn (CompanyStockBalance $balance) => [
                'warehouse_id' => $balance->company_warehouse_id,
                'warehouse_name' => $balance->warehouse?->name,
                'warehouse_type' => $balance->warehouse?->type?->value,
                'deduct_on_issue' => $balance->warehouse?->deduct_on_issue,
                'quantity_on_hand' => $balance->quantity_on_hand,
            ])
            ->all();
    }

    protected function findBalance(CompanyWarehouse $warehouse, CompanyStockItem $item): ?CompanyStockBalance
    {
        return CompanyStockBalance::query()
            ->where('company_warehouse_id', $warehouse->id)
            ->where('company_stock_item_id', $item->id)
            ->first();
    }

    protected function assertSameCompany(CompanyWarehouse $warehouse, CompanyStockItem $item): void
    {
        if ($warehouse->company_id !== $item->company_id) {
            abort(404);
        }
    }
}
