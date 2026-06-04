<?php

namespace App\Services\Invoicing;

use App\Enums\BusinessDocumentQuoteStatus;
use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Models\AuditLog;
use App\Models\BusinessDocument;
use App\Models\Company;
use Illuminate\Validation\ValidationException;

class BusinessDocumentQuoteService
{
    public function approve(Company $company, BusinessDocument $quote): BusinessDocument
    {
        $this->assertCanChangeStatus($company, $quote);

        $quote->update(['quote_status' => BusinessDocumentQuoteStatus::Approved]);

        AuditLog::log('business_document.quote_approved', 'business_document', $quote->id, [
            'company_id' => $company->id,
            'number' => $quote->number,
        ]);

        return $quote->fresh($this->relations());
    }

    public function reject(Company $company, BusinessDocument $quote): BusinessDocument
    {
        $this->assertCanChangeStatus($company, $quote);

        $quote->update(['quote_status' => BusinessDocumentQuoteStatus::Rejected]);

        AuditLog::log('business_document.quote_rejected', 'business_document', $quote->id, [
            'company_id' => $company->id,
            'number' => $quote->number,
        ]);

        return $quote->fresh($this->relations());
    }

    protected function assertCanChangeStatus(Company $company, BusinessDocument $quote): void
    {
        if ($quote->company_id !== $company->id) {
            abort(404);
        }

        if ($quote->type !== BusinessDocumentType::Quote) {
            throw ValidationException::withMessages([
                'type' => ['This action applies only to quotes.'],
            ]);
        }

        if ($quote->status !== BusinessDocumentStatus::Issued) {
            throw ValidationException::withMessages([
                'status' => ['Only issued quotes can be approved or rejected.'],
            ]);
        }

        if (! in_array($quote->resolvedQuoteStatus(), BusinessDocumentQuoteStatus::openForApproval(), true)) {
            throw ValidationException::withMessages([
                'quote_status' => ['This quote can no longer be approved or rejected.'],
            ]);
        }
    }

    /**
     * @return list<string>
     */
    protected function relations(): array
    {
        return [
            'contact:id,name',
            'store:id,name',
            'sourceDocument:id,number,type,status',
            'finalInvoice:id,number,status,source_document_id,type',
        ];
    }
}
