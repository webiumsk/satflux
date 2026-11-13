<?php

namespace App\Jobs;

use App\Models\BusinessDocument;
use App\Models\Company;
use App\Services\Invoicing\Efaktura\ComplianceSubmissionService;
use App\Services\Invoicing\Efaktura\SapiSkClient;
use App\Support\Invoicing\CompanyEfakturaSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SubmitBusinessDocumentCompliance implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $businessDocumentId,
    ) {
        $this->onQueue((string) config('efaktura.queue', 'default'));
    }

    public function handle(ComplianceSubmissionService $service): void
    {
        $document = BusinessDocument::query()->find($this->businessDocumentId);
        if (! $document) {
            return;
        }
        $company = $document->company;
        if ($company instanceof Company && app(SapiSkClient::class)->isEpostman(CompanyEfakturaSettings::fromCompany($company)->sapiBaseUrl())) {
            return;
        }

        $service->submitNow($document);
    }
}
