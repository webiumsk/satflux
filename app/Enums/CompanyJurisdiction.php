<?php

namespace App\Enums;

enum CompanyJurisdiction: string
{
    case EuSk = 'eu_sk';
    case EuCz = 'eu_cz';
    case EuOther = 'eu_other';
    case Us = 'us';
    case Uk = 'uk';
    case Offshore = 'offshore';
    case Asia = 'asia';

    public function supportsPayBySquare(): bool
    {
        return match ($this) {
            self::EuSk, self::EuCz, self::EuOther => true,
            default => false,
        };
    }

    public function isUs(): bool
    {
        return $this === self::Us;
    }

    public static function fromCountryCode(?string $country): self
    {
        $c = strtoupper(trim((string) $country));

        return match (true) {
            $c === 'SK' => self::EuSk,
            $c === 'CZ' => self::EuCz,
            $c === 'US' => self::Us,
            $c === 'GB' || $c === 'UK' => self::Uk,
            in_array($c, ['HK', 'SG', 'AE'], true) => self::Asia,
            in_array($c, ['GI', 'KY', 'PA', 'VG', 'BM', 'LU', 'MT', 'EE', 'LV', 'LT'], true) => self::Offshore,
            default => self::EuOther,
        };
    }
}
