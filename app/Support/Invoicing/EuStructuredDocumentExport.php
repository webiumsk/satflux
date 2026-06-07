<?php

namespace App\Support\Invoicing;

use App\Enums\BusinessDocumentType;
use App\Enums\CompanyJurisdiction;
use App\Models\BusinessDocument;

/**
 * EU structured exports (ISDOC, UBL) shared eligibility rules.
 */
final class EuStructuredDocumentExport
{
    /**
     * @return list<BusinessDocumentType>
     */
    public static function supportedTypes(): array
    {
        return [
            BusinessDocumentType::Invoice,
            BusinessDocumentType::Proforma,
            BusinessDocumentType::CreditNote,
        ];
    }

    public static function supports(BusinessDocument $document): bool
    {
        $document->loadMissing('company');

        if ($document->company->jurisdiction === CompanyJurisdiction::Us) {
            return false;
        }

        if ($document->number === null || $document->number === '') {
            return false;
        }

        return in_array($document->type, self::supportedTypes(), true);
    }
}
