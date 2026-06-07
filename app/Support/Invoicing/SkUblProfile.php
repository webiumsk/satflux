<?php

namespace App\Support\Invoicing;

use App\Enums\CompanyJurisdiction;
use App\Models\Company;
use App\Models\CompanyContact;

/**
 * Slovak Peppol / CIUS helpers for UBL BIS Billing 3.0 export.
 */
final class SkUblProfile
{
    /** Peppol scheme: Slovak IČO (ISO 6523). */
    public const SCHEME_ICO = '0208';

    /** Peppol scheme: Slovak DIČ (SG:DIC). */
    public const SCHEME_DIC = '0245';

    /** @var array<string, string> */
    private const UNIT_ALIASES = [
        'ks.' => 'C62',
        'ks' => 'C62',
        'kus' => 'C62',
        'hod.' => 'HUR',
        'hod' => 'HUR',
        'h' => 'HUR',
        'kg' => 'KGM',
        'g' => 'GRM',
        'l' => 'LTR',
        'm' => 'MTR',
        'm2' => 'MTK',
        'm3' => 'MTQ',
        'bal' => 'PK',
        'bal.' => 'PK',
    ];

    public static function appliesTo(Company|CompanyContact $entity): bool
    {
        return self::countryCode($entity) === 'SK';
    }

    public static function countryCode(Company|CompanyContact $entity): string
    {
        $raw = strtoupper(trim((string) ($entity->country ?? '')));
        if (strlen($raw) === 2) {
            return $raw;
        }

        if ($entity instanceof Company) {
            return match ($entity->jurisdiction) {
                CompanyJurisdiction::EuSk => 'SK',
                CompanyJurisdiction::EuCz => 'CZ',
                default => 'EU',
            };
        }

        return 'SK';
    }

    public static function partyDisplayName(Company|CompanyContact $entity): string
    {
        if ($entity instanceof Company) {
            return $entity->legal_name ?: $entity->trade_name ?: 'Supplier';
        }

        return $entity->name ?: 'Customer';
    }

    /**
     * @return array{scheme: string, id: string}|null
     */
    public static function resolveEndpoint(Company|CompanyContact $entity): ?array
    {
        if ($entity instanceof CompanyContact && $entity->peppol_participant_id) {
            return self::parseParticipantId($entity->peppol_participant_id);
        }

        if (! self::appliesTo($entity)) {
            $id = self::digitsOnly($entity->registration_number ?? $entity->tax_id);

            return $id !== null ? ['scheme' => self::SCHEME_ICO, 'id' => $id] : null;
        }

        $dic = self::digitsOnly($entity->tax_id);
        if ($dic !== null) {
            return ['scheme' => self::SCHEME_DIC, 'id' => $dic];
        }

        $ico = self::digitsOnly($entity->registration_number);
        if ($ico !== null) {
            return ['scheme' => self::SCHEME_ICO, 'id' => $ico];
        }

        return null;
    }

    /**
     * @return array{scheme: string, id: string}|null
     */
    public static function resolveLegalEntityId(Company|CompanyContact $entity): ?array
    {
        $ico = self::digitsOnly($entity->registration_number);
        if ($ico !== null) {
            return ['scheme' => self::SCHEME_ICO, 'id' => $ico];
        }

        return null;
    }

    public static function resolveUnitCode(?string $unit): ?string
    {
        if ($unit === null || $unit === '') {
            return null;
        }

        $normalized = strtolower(trim($unit));
        if (isset(self::UNIT_ALIASES[$normalized])) {
            return self::UNIT_ALIASES[$normalized];
        }

        if (preg_match('/^[A-Z0-9]{2,3}$/', $unit)) {
            return strtoupper($unit);
        }

        return null;
    }

    /**
     * @return array{scheme: string, id: string}|null
     */
    private static function parseParticipantId(string $value): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (str_contains($value, ':')) {
            [$scheme, $id] = explode(':', $value, 2);
            $scheme = trim($scheme);
            $id = self::digitsOnly($id);

            if ($scheme !== '' && $id !== null) {
                return ['scheme' => $scheme, 'id' => $id];
            }
        }

        $digits = self::digitsOnly($value);

        return $digits !== null ? ['scheme' => self::SCHEME_DIC, 'id' => $digits] : null;
    }

    private static function digitsOnly(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value);

        return $digits !== '' ? $digits : null;
    }
}
