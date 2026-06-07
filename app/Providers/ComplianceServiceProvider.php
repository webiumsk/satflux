<?php

namespace App\Providers;

use App\Services\Compliance\ComplianceGate;
use App\Services\Compliance\DelegatingSanctionsScreeningProvider;
use App\Services\Compliance\GeoCountryResolver;
use App\Services\Compliance\GeoJurisdictionGuard;
use App\Services\Compliance\LocalSanctionsIndex;
use App\Services\Compliance\LocalSanctionsScreeningProvider;
use App\Services\Compliance\NameNormalizer;
use App\Services\Compliance\NullSanctionsScreeningProvider;
use App\Services\Compliance\Resolvers\CfIpCountryResolver;
use App\Services\Compliance\Resolvers\CompositeGeoCountryResolver;
use App\Services\Compliance\Resolvers\ConfigOverrideGeoCountryResolver;
use App\Services\Compliance\Resolvers\MaxMindGeoCountryResolver;
use App\Services\Compliance\SanctionsScreeningProvider;
use Illuminate\Support\ServiceProvider;

class ComplianceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GeoCountryResolver::class, function () {
            return new CompositeGeoCountryResolver([
                new ConfigOverrideGeoCountryResolver,
                new CfIpCountryResolver,
                new MaxMindGeoCountryResolver,
            ]);
        });

        $this->app->singleton(GeoJurisdictionGuard::class);
        $this->app->singleton(NameNormalizer::class);
        $this->app->singleton(LocalSanctionsIndex::class);
        $this->app->singleton(NullSanctionsScreeningProvider::class);
        $this->app->singleton(LocalSanctionsScreeningProvider::class);
        $this->app->singleton(SanctionsScreeningProvider::class, DelegatingSanctionsScreeningProvider::class);
        $this->app->singleton(ComplianceGate::class);
    }
}
