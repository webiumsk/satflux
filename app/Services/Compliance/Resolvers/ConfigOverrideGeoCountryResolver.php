<?php

namespace App\Services\Compliance\Resolvers;

use App\Services\Compliance\GeoCountryResolver;
use App\Services\Compliance\GeoCountryResult;
use Illuminate\Http\Request;

class ConfigOverrideGeoCountryResolver implements GeoCountryResolver
{
    public function resolve(Request $request): ?GeoCountryResult
    {
        $override = config('compliance.geo_country_override');

        if (! is_string($override) || $override === '') {
            return null;
        }

        return new GeoCountryResult(strtoupper($override), 'config_override');
    }
}
