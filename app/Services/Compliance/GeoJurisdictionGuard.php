<?php

namespace App\Services\Compliance;

use Illuminate\Http\Request;

readonly class GeoJurisdictionGuard
{
    public function __construct(
        protected GeoCountryResolver $resolver,
    ) {}

    /**
     * @return array{allowed: bool, country_code: ?string, geo_source: ?string, geo_blocked: bool, decision_reason: ?string}
     */
    public function evaluate(Request $request): array
    {
        if (! config('compliance.geo_block_enabled')) {
            return [
                'allowed' => true,
                'country_code' => null,
                'geo_source' => null,
                'geo_blocked' => false,
                'decision_reason' => null,
            ];
        }

        $geo = $this->resolver->resolve($request);
        $countryCode = $geo?->countryCode;
        $blockedCodes = config('compliance.blocked_country_codes', []);

        if ($countryCode === null) {
            if ($this->shouldAllowUnknownGeoOnLocalRequest($request)) {
                return [
                    'allowed' => true,
                    'country_code' => null,
                    'geo_source' => 'local_dev',
                    'geo_blocked' => false,
                    'decision_reason' => null,
                ];
            }

            $failClosed = (bool) config('compliance.fail_closed');

            return [
                'allowed' => ! $failClosed,
                'country_code' => null,
                'geo_source' => null,
                'geo_blocked' => false,
                'decision_reason' => $failClosed ? 'geo_unknown_fail_closed' : null,
            ];
        }

        $isBlocked = in_array($countryCode, $blockedCodes, true);

        return [
            'allowed' => ! $isBlocked,
            'country_code' => $countryCode,
            'geo_source' => $geo->source,
            'geo_blocked' => $isBlocked,
            'decision_reason' => $isBlocked ? 'geo_blocked_country' : null,
        ];
    }

    /**
     * Loopback/private IPs cannot be geolocated; allow them in local dev only.
     */
    protected function shouldAllowUnknownGeoOnLocalRequest(Request $request): bool
    {
        if (config('app.env') !== 'local') {
            return false;
        }

        $ip = $request->ip();

        if (! is_string($ip) || $ip === '') {
            return false;
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }
}
