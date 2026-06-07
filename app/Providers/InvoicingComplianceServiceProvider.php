<?php

namespace App\Providers;

use App\Contracts\Invoicing\ComplianceSubmissionGateway;
use App\Services\Invoicing\Compliance\NullComplianceSubmissionGateway;
use App\Services\Invoicing\Efaktura\SapiSkComplianceGateway;
use Illuminate\Support\ServiceProvider;

class InvoicingComplianceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ComplianceSubmissionGateway::class, function ($app) {
            if (! config('efaktura.enabled')) {
                return $app->make(NullComplianceSubmissionGateway::class);
            }

            return $app->make(SapiSkComplianceGateway::class);
        });
    }
}
