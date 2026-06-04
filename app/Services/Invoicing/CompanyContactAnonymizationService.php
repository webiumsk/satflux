<?php

namespace App\Services\Invoicing;

use App\Enums\BusinessDocumentStatus;
use App\Models\Company;
use App\Models\CompanyContact;

class CompanyContactAnonymizationService
{
    public function hasIssuedDocuments(CompanyContact $contact): bool
    {
        return $contact->documents()
            ->whereIn('status', [
                BusinessDocumentStatus::Issued,
                BusinessDocumentStatus::Paid,
                BusinessDocumentStatus::Cancelled,
            ])
            ->exists();
    }

    /**
     * Remove PII from contact while keeping row for FK / stats. Returns true if anonymized.
     */
    public function anonymize(Company $company, CompanyContact $contact): bool
    {
        if (! $this->hasIssuedDocuments($contact)) {
            return false;
        }

        $suffix = substr((string) $contact->id, 0, 8);

        $contact->update([
            'name' => 'Removed contact '.$suffix,
            'registration_number' => null,
            'email' => null,
            'phone' => null,
            'fax' => null,
            'tax_id' => null,
            'vat_id' => null,
            'street' => null,
            'city' => null,
            'postal_code' => null,
            'state_region' => null,
            'country' => null,
            'bank_account' => null,
            'bank_code' => null,
            'iban' => null,
            'swift' => null,
            'delivery_street' => null,
            'delivery_postal_code' => null,
            'delivery_city' => null,
            'delivery_country' => null,
            'notes' => null,
            'contact_persons' => null,
            'is_active' => false,
        ]);

        return true;
    }
}
