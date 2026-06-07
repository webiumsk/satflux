<?php

namespace App\Services\Compliance\Resolvers;

use App\Services\Compliance\GeoCountryResolver;
use App\Services\Compliance\GeoCountryResult;
use Illuminate\Http\Request;

class CfIpCountryResolver implements GeoCountryResolver
{
    public function resolve(Request $request): ?GeoCountryResult
    {
        $country = $request->header('CF-IPCountry');

        if (! is_string($country) || $country === '' || strtoupper($country) === 'XX') {
            return null;
        }

        return new GeoCountryResult(strtoupper($country), 'cf_ip_country');
    }
}
