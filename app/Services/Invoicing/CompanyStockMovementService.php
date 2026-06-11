<?php

namespace App\Services\Invoicing;

use App\Enums\BusinessDocumentType;
use App\Enums\CompanyStockMovementSource;
use App\Models\BusinessDocument;
use App\Models\CompanyStockItem;
use App\Models\CompanyStockItemMovement;
use Illuminate\Support\Facades\DB;

class CompanyStockMovementService
{
    /**
     * @return list<CompanyStockItemMovement>
     */
    public function applyDocumentIssue(BusinessDocument $document): array
    {
        if (! in_array($document->type, [BusinessDocumentType::Invoice, BusinessDocumentType::CreditNote], true)) {
            return [];
        }

        $document->loadMissing(['lines', 'company']);
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

                $quantity = (float) $line->quantity;
                $delta = $document->type === BusinessDocumentType::CreditNote ? $quantity : -$quantity;

                $movements[] = $this->applyDelta(
                    $item,
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
        if (! in_array($document->type, [BusinessDocumentType::Invoice, BusinessDocumentType::CreditNote], true)) {
            return [];
        }

        $alreadyReversed = CompanyStockItemMovement::query()
            ->where('business_document_id', $document->id)
            ->where('source', CompanyStockMovementSource::DocumentCancel)
            ->exists();

        if ($alreadyReversed) {
            return [];
        }

        $issueMovements = CompanyStockItemMovement::query()
            ->where('business_document_id', $document->id)
            ->where('source', CompanyStockMovementSource::DocumentIssue)
            ->get();

        if ($issueMovements->isEmpty()) {
            return [];
        }

        $movements = [];

        DB::transaction(function () use ($issueMovements, $document, &$movements) {
            foreach ($issueMovements as $issueMovement) {
                $item = CompanyStockItem::query()
                    ->where('id', $issueMovement->company_stock_item_id)
                    ->lockForUpdate()
                    ->first();

                if (! $item || ! $item->track_inventory) {
                    continue;
                }

                $movements[] = $this->applyDelta(
                    $item,
                    -((float) $issueMovement->quantity_delta),
                    CompanyStockMovementSource::DocumentCancel,
                    note: null,
                    document: $document,
                );
            }
        });

        return $movements;
    }

    public function recordManualChange(
        CompanyStockItem $item,
        float $previousQuantity,
        ?string $note = null,
    ): ?CompanyStockItemMovement {
        $delta = (float) $item->quantity_on_hand - $previousQuantity;

        if (abs($delta) < 0.00001) {
            return null;
        }

        return $this->logMovement(
            $item,
            $delta,
            (float) $item->quantity_on_hand,
            CompanyStockMovementSource::Manual,
            $note,
        );
    }

    public function recordImportChange(
        CompanyStockItem $item,
        float $previousQuantity,
        ?string $note = null,
    ): ?CompanyStockItemMovement {
        $delta = (float) $item->quantity_on_hand - $previousQuantity;

        if (abs($delta) < 0.00001 && $note === null) {
            return null;
        }

        return $this->logMovement(
            $item,
            $delta,
            (float) $item->quantity_on_hand,
            CompanyStockMovementSource::Import,
            $note,
        );
    }

    public function applyDelta(
        CompanyStockItem $item,
        float $delta,
        CompanyStockMovementSource $source,
        ?string $note = null,
        ?BusinessDocument $document = null,
    ): CompanyStockItemMovement {
        $item->quantity_on_hand = (float) $item->quantity_on_hand + $delta;
        $item->save();

        return $this->logMovement(
            $item,
            $delta,
            (float) $item->quantity_on_hand,
            $source,
            $note,
            $document,
        );
    }

    protected function logMovement(
        CompanyStockItem $item,
        float $delta,
        float $quantityAfter,
        CompanyStockMovementSource $source,
        ?string $note = null,
        ?BusinessDocument $document = null,
    ): CompanyStockItemMovement {
        return CompanyStockItemMovement::create([
            'company_stock_item_id' => $item->id,
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
}
