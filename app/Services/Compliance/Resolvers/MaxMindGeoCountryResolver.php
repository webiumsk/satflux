<?php

namespace App\Services\Compliance\Resolvers;

use App\Services\Compliance\GeoCountryResolver;
use App\Services\Compliance\GeoCountryResult;
use GeoIp2\Database\Reader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MaxMindGeoCountryResolver implements GeoCountryResolver
{
    public function resolve(Request $request): ?GeoCountryResult
    {
        $path = config('compliance.maxmind_database_path');

        if (! is_string($path) || ! is_readable($path)) {
            return null;
        }

        $ip = $request->ip();

        if (! is_string($ip) || $ip === '') {
            return null;
        }

        try {
            $reader = new Reader($path);
            $record = $reader->country($ip);
            $code = $record->country->isoCode;

            if (! is_string($code) || $code === '') {
                return null;
            }

            return new GeoCountryResult(strtoupper($code), 'maxmind_geolite2');
        } catch (\Throwable $e) {
            Log::warning('MaxMind geo lookup failed', [
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
