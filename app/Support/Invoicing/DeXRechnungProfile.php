<?php

namespace App\Support\Invoicing;

use App\Enums\CompanyJurisdiction;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\CompanyContact;

/**
 * German XRechnung 3.x CIUS on top of the EN 16931 UBL export - the same
 * document body as Peppol BIS Billing 3.0 with German specifics:
 * CustomizationID, a mandatory BT-10 buyer reference (BR-DE-15, Leitweg-ID
 * for B2G), electronic addresses, and SEPA payment means.
 *
 * v1 targets structural compliance; validation against the official KoSIT
 * schematron is a follow-up (see the jurisdiction plan).
 */
final class DeXRechnungProfile
{
    public const CUSTOMIZATION_ID = 'urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0';

    /** Electronic address scheme: e-mail (EAS "EM"). */
    public const SCHEME_EMAIL = 'EM';

    public static function appliesTo(Company $company): bool
    {
        return JurisdictionRules::normalizeValue($company->jurisdiction)
            === CompanyJurisdiction::EuDe->value;
    }

    /**
     * BT-10 is mandatory in XRechnung. The variable symbol carries the
     * merchant's explicit reference (Leitweg-ID for B2G buyers goes here);
     * the document number is the always-present fallback.
     */
    public static function buyerReference(BusinessDocument $document): string
    {
        $reference = trim((string) $document->variable_symbol);

        return $reference !== '' ? $reference : (string) $document->number;
    }

    /**
     * Electronic address (BT-34/BT-49): an explicit Peppol participant id
     * wins; otherwise the party's e-mail with the EM scheme. German
     * registration numbers (HRB...) never masquerade as Peppol endpoints.
     *
     * @return array{scheme: string, id: string}|null
     */
    public static function electronicAddress(Company|CompanyContact $entity): ?array
    {
        if ($entity instanceof CompanyContact && $entity->peppol_participant_id) {
            return SkUblProfile::resolveEndpoint($entity);
        }

        $email = trim((string) ($entity instanceof Company ? $entity->issuer_email : $entity->email));

        return $email !== '' ? ['scheme' => self::SCHEME_EMAIL, 'id' => $email] : null;
    }

    /**
     * BT-30 legal registration id - German registers (HRB 12345 B) are not
     * numeric-only and carry no ISO 6523 scheme here.
     *
     * @return array{scheme: string|null, id: string}|null
     */
    public static function legalEntityId(Company|CompanyContact $entity): ?array
    {
        $id = trim((string) $entity->registration_number);

        return $id !== '' ? ['scheme' => null, 'id' => $id] : null;
    }
}
