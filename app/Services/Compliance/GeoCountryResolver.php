<?php

namespace App\Services\Compliance;

use Illuminate\Http\Request;

interface GeoCountryResolver
{
    public function resolve(Request $request): ?GeoCountryResult;
}
