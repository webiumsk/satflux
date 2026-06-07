<?php

namespace App\Services\Compliance\Resolvers;

use App\Services\Compliance\GeoCountryResolver;
use App\Services\Compliance\GeoCountryResult;
use Illuminate\Http\Request;

class CompositeGeoCountryResolver implements GeoCountryResolver
{
    /**
     * @param  list<GeoCountryResolver>  $resolvers
     */
    public function __construct(
        protected array $resolvers,
    ) {}

    public function resolve(Request $request): ?GeoCountryResult
    {
        foreach ($this->resolvers as $resolver) {
            $result = $resolver->resolve($request);

            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }
}
