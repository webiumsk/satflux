<?php

namespace App\Services\Invoicing;

use App\Enums\BusinessDocumentStatus;
use App\Enums\CompanyJurisdiction;
use App\Models\BankTransactionMatch;
use App\Models\BusinessDocument;
use App\Models\BusinessDocumentCompliance;
use App\Models\Company;
use Illuminate\Support\Collection;

class BusinessDocumentListCapabilities
{
    public function __construct(
        protected DocumentSequenceService $sequenceService,
    ) {}

    /**
     * @param  Collection<int, BusinessDocument>  $documents
     * @return array<string, array{can_update: bool, can_delete: bool, can_cancel: bool, can_unmark_paid: bool}>
     */
    public function forDocuments(Collection $documents, Company $company): array
    {
        if ($documents->isEmpty()) {
            return [];
        }

        $ids = $documents->pluck('id')->all();

        $latestIdByType = [];
        foreach ($documents->map(fn (BusinessDocument $document) => $document->type->value)->unique() as $type) {
            $latestIdByType[$type] = $this->sequenceService->latestNumberedDocumentId((string) $company->id, $type);
        }

        $complianceIds = array_fill_keys(
            BusinessDocumentCompliance::query()
                ->whereIn('business_document_id', $ids)
                ->pluck('business_document_id')
                ->all(),
            true,
        );

        $bankMatchedIds = array_fill_keys(
            BankTransactionMatch::query()
                ->whereIn('business_document_id', $ids)
                ->pluck('business_document_id')
                ->all(),
            true,
        );

        $derivedSourceIds = array_fill_keys(
            BusinessDocument::query()
                ->whereIn('source_document_id', $ids)
                ->where('status', '!=', BusinessDocumentStatus::Cancelled)
                ->pluck('source_document_id')
                ->unique()
                ->all(),
            true,
        );

        $result = [];

        foreach ($documents as $document) {
            $hasBlocking = isset($bankMatchedIds[$document->id])
                || isset($derivedSourceIds[$document->id])
                || isset($complianceIds[$document->id]);

            $result[$document->id] = [
                'can_update' => $document->canUpdate($company),
                'can_delete' => $this->canDelete($document, $company, $latestIdByType[$document->type->value] ?? null, $hasBlocking),
                'can_cancel' => $document->canCancel(),
                'can_unmark_paid' => $document->canUnmarkPaid(),
            ];
        }

        return $result;
    }

    protected function canDelete(
        BusinessDocument $document,
        Company $company,
        ?string $latestId,
        bool $hasBlocking
    ): bool {
        if ($document->status !== BusinessDocumentStatus::Draft && $company->jurisdiction === CompanyJurisdiction::EuDe) {
            return false;
        }

        // Mirrors BusinessDocument::canDelete().
        if ($document->status === BusinessDocumentStatus::Draft
            || ($document->status === BusinessDocumentStatus::Cancelled && ($document->number === null || $document->number === ''))) {
            return ! $hasBlocking;
        }

        if (! in_array($document->status, [
            BusinessDocumentStatus::Issued,
            BusinessDocumentStatus::Paid,
            BusinessDocumentStatus::Cancelled,
        ], true)) {
            return false;
        }

        return $latestId === $document->id && ! $hasBlocking;
    }
}
