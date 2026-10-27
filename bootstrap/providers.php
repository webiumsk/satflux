<?php

use App\Providers\ApiDocumentationServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\ComplianceServiceProvider;
use App\Providers\InvoicingComplianceServiceProvider;

return [
    ApiDocumentationServiceProvider::class,
    AppServiceProvider::class,
    ComplianceServiceProvider::class,
    InvoicingComplianceServiceProvider::class,
];
