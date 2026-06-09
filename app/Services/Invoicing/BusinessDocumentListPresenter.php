<?php

namespace App\Services\Invoicing;

use App\Enums\BusinessDocumentType;
use App\Models\BusinessDocument;
use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BusinessDocumentListPresenter
{
    public function __construct(
        protected BusinessDocumentListCapabilities $capabilities,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function paginated(LengthAwarePaginator $paginator, Company $company): array
    {
        $documents = $paginator->getCollection();
        $capabilityMap = $this->capabilities->forDocuments($documents, $company);

        return [
            'data' => $documents
                ->map(fn (BusinessDocument $document) => $this->item(
                    $document,
                    $capabilityMap[$document->id] ?? [
                        'can_update' => false,
                        'can_delete' => false,
                        'can_cancel' => false,
                        'can_unmark_paid' => false,
                    ],
                ))
                ->values()
                ->all(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    /**
     * @param  array{can_update: bool, can_delete: bool, can_cancel: bool, can_unmark_paid: bool}  $capabilities
     * @return array<string, mixed>
     */
    public function item(BusinessDocument $document, array $capabilities): array
    {
        $item = [
            'id' => $document->id,
            'company_id' => $document->company_id,
            'company_contact_id' => $document->company_contact_id,
            'store_id' => $document->store_id,
            'source_document_id' => $document->source_document_id,
            'type' => $document->type->value,
            'status' => $document->status->value,
            'quote_status' => $document->quote_status?->value,
            'number' => $document->number,
            'title' => $document->title,
            'variable_symbol' => $document->variable_symbol,
            'issue_date' => $document->issue_date?->toDateString(),
            'due_date' => $document->due_date?->toDateString(),
            'currency' => $document->currency,
            'total' => $document->total,
            'email_sent_at' => $document->email_sent_at?->toIso8601String(),
            'can_update' => $capabilities['can_update'],
            'can_delete' => $capabilities['can_delete'],
            'can_cancel' => $capabilities['can_cancel'],
            'can_unmark_paid' => $capabilities['can_unmark_paid'],
        ];

        if ($document->type === BusinessDocumentType::Quote) {
            $item['resolved_quote_status'] = $document->resolvedQuoteStatus()?->value;
        }

        if ($document->relationLoaded('contact') && $document->contact) {
            $item['contact'] = [
                'id' => $document->contact->id,
                'name' => $document->contact->name,
            ];
        }

        if ($document->relationLoaded('store') && $document->store) {
            $item['store'] = [
                'id' => $document->store->id,
                'name' => $document->store->name,
            ];
        }

        if ($document->relationLoaded('sourceDocument') && $document->sourceDocument) {
            $item['source_document'] = $this->relatedDocument($document->sourceDocument);
        }

        if ($document->relationLoaded('finalInvoice') && $document->finalInvoice) {
            $item['final_invoice'] = $this->relatedDocument($document->finalInvoice);
        }

        if ($document->relationLoaded('bankMatch') && $document->bankMatch) {
            $item['bank_match'] = [
                'id' => $document->bankMatch->id,
                'business_document_id' => $document->bankMatch->business_document_id,
                'bank_transaction_id' => $document->bankMatch->bank_transaction_id,
                'match_type' => $document->bankMatch->match_type->value,
                'matched_at' => $document->bankMatch->matched_at?->toIso8601String(),
            ];
        }

        return $item;
    }

    /**
     * @return list<string>
     */
    public static function listColumns(): array
    {
        return [
            'id',
            'company_id',
            'company_contact_id',
            'store_id',
            'source_document_id',
            'type',
            'status',
            'quote_status',
            'number',
            'title',
            'variable_symbol',
            'issue_date',
            'due_date',
            'currency',
            'total',
            'email_sent_at',
            'created_at',
        ];
    }

    /**
     * @return list<string>
     */
    public static function listRelations(): array
    {
        return [
            'contact:id,name',
            'store:id,name',
            'sourceDocument:id,number,type,status',
            'finalInvoice:id,number,status,source_document_id,type',
            'bankMatch:id,business_document_id,bank_transaction_id,match_type,matched_at',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function relatedDocument(BusinessDocument $document): array
    {
        return [
            'id' => $document->id,
            'number' => $document->number,
            'type' => $document->type->value,
            'status' => $document->status->value,
            'source_document_id' => $document->source_document_id,
        ];
    }
}
