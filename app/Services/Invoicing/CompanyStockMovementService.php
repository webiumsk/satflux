<?php

namespace App\Services\Invoicing;

use App\Enums\BusinessDocumentType;
use App\Enums\CompanyStockMovementSource;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\CompanyStockItem;
use App\Models\CompanyStockItemMovement;
use App\Models\CompanyWarehouse;
use Illuminate\Support\Facades\DB;

class CompanyStockMovementService
{
    public function __construct(
        protected CompanyWarehouseService $warehouseService,
        protected CompanyStockBalanceService $balanceService,
    ) {}

    /**
     * @return list<CompanyStockItemMovement>
     */
    public function applyDocumentIssue(BusinessDocument $document): array
    {
        if (! in_array($document->type, [BusinessDocumentType::Invoice, BusinessDocumentType::CreditNote, BusinessDocumentType::DeliveryNote], true)) {
            return [];
        }

        $document->loadMissing(['lines.warehouse', 'company']);
        $movements = [];

        DB::transaction(function () use ($document, &$movements) {
            foreach ($document->lines as $line) {
                if (! $line->company_stock_item_id) {
                    continue;
                }

                $item = CompanyStockItem::query()
                    ->where('id', $line->company_stock_item_id)
                    ->where('company_id', $document->company_id)
                    ->lockForUpdate()
                    ->first();

                if (! $item || ! $item->track_inventory) {
                    continue;
                }

                $warehouse = $this->resolveWarehouseForLine($document->company, $line->company_warehouse_id);
                if (! $warehouse->deduct_on_issue) {
                    continue;
                }

                $quantity = (float) $line->quantity;
                $delta = in_array($document->type, [BusinessDocumentType::CreditNote], true)
                    ? $quantity
                    : -$quantity;

                $movements[] = $this->applyDelta(
                    $item,
                    $warehouse,
                    $delta,
                    CompanyStockMovementSource::DocumentIssue,
                    note: null,
                    document: $document,
                );
            }
        });

        return $movements;
    }

    /**
     * @return list<CompanyStockItemMovement>
     */
    public function reverseDocumentCancel(BusinessDocument $document): array
    {
        return $this->reverseIssueMovements($document, CompanyStockMovementSource::DocumentCancel);
    }

    /**
     * Reverse and re-apply issue movements after an issued document's lines
     * changed (server-mode edit): the reversal is recorded as
     * document_adjustment and the stale issue rows are deleted so
     * applyDocumentIssue's idempotency guard lets the fresh lines through
     * (Cursor PR #65).
     *
     * @return list<CompanyStockItemMovement>
     */
    public function rebuildDocumentIssue(BusinessDocument $document): array
    {
        if (! $this->stockRelevantType($document)) {
            return [];
        }

        $movements = [];

        DB::transaction(function () use ($document, &$movements) {
            $movements = array_merge(
                $movements,
                $this->reverseIssueMovements(
                    $document,
                    CompanyStockMovementSource::DocumentAdjustment,
                    deleteIssueMovements: true,
                ),
            );

            $movements = array_merge(
                $movements,
                $this->applyDocumentIssue($document->fresh(['lines', 'company'])),
            );
        });

        return $movements;
    }

    /**
     * @return list<CompanyStockItemMovement>
     */
    protected function reverseIssueMovements(
        BusinessDocument $document,
        CompanyStockMovementSource $reversalSource,
        bool $deleteIssueMovements = false,
    ): array {
        if (! $this->stockRelevantType($document)) {
            return [];
        }

        $movements = [];

        DB::transaction(function () use ($document, $reversalSource, $deleteIssueMovements, &$movements) {
            if (! BusinessDocument::query()->where('id', $document->id)->lockForUpdate()->exists()) {
                return;
            }

            $alreadyReversed = CompanyStockItemMovement::query()
                ->where('business_document_id', $document->id)
                ->where('source', $reversalSource)
                ->exists();

            if ($alreadyReversed && ! $deleteIssueMovements) {
                return;
            }

            $issueMovements = CompanyStockItemMovement::query()
                ->where('business_document_id', $document->id)
                ->where('source', CompanyStockMovementSource::DocumentIssue)
                ->get();

            if ($issueMovements->isEmpty()) {
                return;
            }

            foreach ($issueMovements as $issueMovement) {
                $item = CompanyStockItem::query()
                    ->where('id', $issueMovement->company_stock_item_id)
                    ->lockForUpdate()
                    ->first();

                if (! $item || ! $item->track_inventory || ! $issueMovement->company_warehouse_id) {
                    continue;
                }

                $warehouse = CompanyWarehouse::query()
                    ->where('id', $issueMovement->company_warehouse_id)
                    ->lockForUpdate()
                    ->first();

                if (! $warehouse) {
                    continue;
                }

                $movements[] = $this->applyDelta(
                    $item,
                    $warehouse,
                    -((float) $issueMovement->quantity_delta),
                    $reversalSource,
                    note: null,
                    document: $document,
                );
            }

            if ($deleteIssueMovements) {
                CompanyStockItemMovement::query()
                    ->where('business_document_id', $document->id)
                    ->where('source', CompanyStockMovementSource::DocumentIssue)
                    ->delete();
            }
        });

        return $movements;
    }

    /** Enum-on-string cast duality: the ephemeral context types status/type as string. */
    protected function stockRelevantType(BusinessDocument $document): bool
    {
        return in_array($document->type, [
            BusinessDocumentType::Invoice,
            BusinessDocumentType::CreditNote,
            BusinessDocumentType::DeliveryNote,
        ], true);
    }

    public function recordManualChange(
        CompanyStockItem $item,
        CompanyWarehouse $warehouse,
        float $previousQuantity,
        ?string $note = null,
    ): ?CompanyStockItemMovement {
        $current = $this->balanceService->getQuantity($warehouse, $item);
        $delta = $current - $previousQuantity;

        if (abs($delta) < 0.00001) {
            return null;
        }

        return $this->logMovement(
            $item,
            $warehouse,
            $delta,
            $current,
            CompanyStockMovementSource::Manual,
            $note,
        );
    }

    public function recordImportChange(
        CompanyStockItem $item,
        CompanyWarehouse $warehouse,
        float $previousQuantity,
        ?string $note = null,
    ): ?CompanyStockItemMovement {
        $current = $this->balanceService->getQuantity($warehouse, $item);
        $delta = $current - $previousQuantity;

        if (abs($delta) < 0.00001 && $note === null) {
            return null;
        }

        return $this->logMovement(
            $item,
            $warehouse,
            $delta,
            $current,
            CompanyStockMovementSource::Import,
            $note,
        );
    }

    /**
     * @return array{out: CompanyStockItemMovement, in: CompanyStockItemMovement}
     */
    public function recordTransfer(
        CompanyStockItem $item,
        CompanyWarehouse $from,
        CompanyWarehouse $to,
        float $quantity,
        ?string $note = null,
    ): array {
        return DB::transaction(function () use ($item, $from, $to, $quantity, $note) {
            $balances = $this->balanceService->transfer($from, $to, $item, $quantity);

            $outMovement = $this->logMovement(
                $item,
                $from,
                -$quantity,
                (float) $balances['out']->quantity_on_hand,
                CompanyStockMovementSource::Transfer,
                $note,
            );

            $inMovement = $this->logMovement(
                $item,
                $to,
                $quantity,
                (float) $balances['in']->quantity_on_hand,
                CompanyStockMovementSource::Transfer,
                $note,
            );

            return ['out' => $outMovement, 'in' => $inMovement];
        });
    }

    public function applyDelta(
        CompanyStockItem $item,
        CompanyWarehouse $warehouse,
        float $delta,
        CompanyStockMovementSource $source,
        ?string $note = null,
        ?BusinessDocument $document = null,
    ): CompanyStockItemMovement {
        $balance = $this->balanceService->getOrCreateBalance($warehouse, $item, lock: true);
        $balance->quantity_on_hand = (float) $balance->quantity_on_hand + $delta;
        $balance->save();

        return $this->logMovement(
            $item,
            $warehouse,
            $delta,
            (float) $balance->quantity_on_hand,
            $source,
            $note,
            $document,
        );
    }

    protected function logMovement(
        CompanyStockItem $item,
        CompanyWarehouse $warehouse,
        float $delta,
        float $quantityAfter,
        CompanyStockMovementSource $source,
        ?string $note = null,
        ?BusinessDocument $document = null,
    ): CompanyStockItemMovement {
        return CompanyStockItemMovement::create([
            'company_stock_item_id' => $item->id,
            'company_warehouse_id' => $warehouse->id,
            'company_id' => $item->company_id,
            'quantity_after' => $quantityAfter,
            'quantity_delta' => $delta,
            'purchase_unit_price' => $item->purchase_unit_price,
            'sale_unit_price' => $item->sale_unit_price,
            'note' => $note,
            'source' => $source,
            'business_document_id' => $document?->id,
            'document_number' => $document?->number,
            'document_type' => $document?->type?->value,
            'created_at' => now(),
        ]);
    }

    protected function resolveWarehouseForLine(Company $company, ?string $warehouseId): CompanyWarehouse
    {
        if ($warehouseId) {
            $warehouse = $company->warehouses()
                ->where('id', $warehouseId)
                ->where('is_active', true)
                ->first();

            if ($warehouse) {
                return $warehouse;
            }
        }

        return $this->warehouseService->defaultWarehouse($company);
    }
}
