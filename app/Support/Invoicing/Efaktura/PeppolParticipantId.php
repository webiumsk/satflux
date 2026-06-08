<?php

namespace App\Support\Invoicing\Efaktura;

use App\Models\Company;
use App\Models\CompanyContact;
use App\Support\Invoicing\SkUblProfile;

final class PeppolParticipantId
{
    /**
     * @param  array{scheme: string, id: string}  $endpoint
     */
    public static function format(array $endpoint): string
    {
        return $endpoint['scheme'].':'.$endpoint['id'];
    }

    public static function fromCompany(Company $company): ?string
    {
        $endpoint = SkUblProfile::resolveEndpoint($company);

        return $endpoint !== null ? self::format($endpoint) : null;
    }

    public static function fromContact(CompanyContact $contact): ?string
    {
        $endpoint = SkUblProfile::resolveEndpoint($contact);

        return $endpoint !== null ? self::format($endpoint) : null;
    }
}
