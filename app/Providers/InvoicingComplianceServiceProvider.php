<?php

namespace App\Providers;

use App\Contracts\Invoicing\ComplianceSubmissionGateway;
use App\Services\Invoicing\Compliance\NullComplianceSubmissionGateway;
use App\Services\Invoicing\Efaktura\SapiSkComplianceGateway;
use Illuminate\Support\ServiceProvider;

class InvoicingComplianceServiceProvider extends ServiceProvider
{
    /** @var array<string, class-string<ComplianceSubmissionGateway>> */
    private const GATEWAYS = [
        'sapi_sk' => SapiSkComplianceGateway::class,
    ];

    public function register(): void
    {
        $this->app->singleton(ComplianceSubmissionGateway::class, function ($app) {
            if (! config('efaktura.enabled')) {
                return $app->make(NullComplianceSubmissionGateway::class);
            }

            $provider = (string) config('efaktura.default_provider', 'sapi_sk');
            $gatewayClass = self::GATEWAYS[$provider] ?? null;

            if ($gatewayClass === null) {
                throw new \InvalidArgumentException(
                    "Unknown efaktura provider [{$provider}]. Supported: ".implode(', ', array_keys(self::GATEWAYS)),
                );
            }

            return $app->make($gatewayClass);
        });
    }
}
