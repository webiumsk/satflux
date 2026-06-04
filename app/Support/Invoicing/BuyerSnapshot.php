<?php

namespace App\Support\Invoicing;

use App\Models\CompanyContact;

/**
 * Immutable buyer fields captured at document issue (or backfilled on first read).
 */
final class BuyerSnapshot
{
    /**
     * @return array<string, mixed>
     */
    public static function fromContact(CompanyContact $contact): array
    {
        return [
            'name' => $contact->name,
            'registration_number' => $contact->registration_number,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'fax' => $contact->fax,
            'tax_id' => $contact->tax_id,
            'vat_id' => $contact->vat_id,
            'street' => $contact->street,
            'city' => $contact->city,
            'postal_code' => $contact->postal_code,
            'state_region' => $contact->state_region,
            'country' => $contact->country,
            'bank_account' => $contact->bank_account,
            'bank_code' => $contact->bank_code,
            'iban' => $contact->iban,
            'swift' => $contact->swift,
            'delivery_street' => $contact->delivery_street,
            'delivery_postal_code' => $contact->delivery_postal_code,
            'delivery_city' => $contact->delivery_city,
            'delivery_country' => $contact->delivery_country,
            'captured_at' => now()->toIso8601String(),
        ];
    }

    /**
     * In-memory contact for PDF / ISDOC / UBL (not persisted).
     */
    public static function asContact(array $snapshot): CompanyContact
    {
        $data = collect($snapshot)->except(['captured_at'])->all();
        $contact = new CompanyContact($data);
        $contact->exists = false;

        return $contact;
    }
}
