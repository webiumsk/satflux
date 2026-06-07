<?php

namespace App\Services\Invoicing\Efaktura;

use App\Enums\CompanyJurisdiction;
use App\Models\AuditLog;
use App\Models\BusinessExpense;
use App\Models\Company;
use App\Models\EfakturaInboundReceipt;
use App\Services\Invoicing\BusinessExpenseService;
use App\Support\Invoicing\CompanyEfakturaSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EfakturaInboundService
{
    public function __construct(
        protected SapiSkClient $client,
        protected UblExpenseDraftParser $parser,
        protected BusinessExpenseService $expenseService,
    ) {}

    /**
     * @return array{imported: int, acknowledged: int, skipped: int, failed: int}
     */
    public function pollAll(): array
    {
        $stats = ['imported' => 0, 'acknowledged' => 0, 'skipped' => 0, 'failed' => 0];

        if (! config('efaktura.enabled')) {
            return $stats;
        }

        Company::query()
            ->where('jurisdiction', CompanyJurisdiction::EuSk)
            ->orderBy('id')
            ->chunkById(50, function ($companies) use (&$stats) {
                foreach ($companies as $company) {
                    $companyStats = $this->pollCompany($company);
                    foreach ($companyStats as $key => $value) {
                        $stats[$key] += $value;
                    }
                }
            });

        return $stats;
    }

    /**
     * @return array{imported: int, acknowledged: int, skipped: int, failed: int}
     */
    public function pollCompany(Company $company): array
    {
        $stats = ['imported' => 0, 'acknowledged' => 0, 'skipped' => 0, 'failed' => 0];
        $settings = CompanyEfakturaSettings::fromCompany($company);

        if (! $settings->inboundEnabled() || ! $settings->configured()) {
            return $stats;
        }

        $participantId = (string) $settings->peppolParticipantId();

        try {
            $baseUrl = (string) $settings->sapiBaseUrl();
            $token = $this->client->accessToken(
                (string) $settings->sapiClientId(),
                (string) $settings->sapiClientSecret(),
                $baseUrl,
            );

            $listing = $this->client->listReceivedDocuments($token, $participantId, baseUrl: $baseUrl);
            $documents = $listing['documents'] ?? $listing['items'] ?? [];

            foreach ($documents as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $externalId = (string) ($item['providerDocumentId'] ?? $item['documentId'] ?? $item['id'] ?? '');
                if ($externalId === '') {
                    $stats['failed']++;

                    continue;
                }

                if (EfakturaInboundReceipt::query()
                    ->where('company_id', $company->id)
                    ->where('external_document_id', $externalId)
                    ->exists()) {
                    $stats['skipped']++;

                    continue;
                }

                try {
                    $detail = $this->client->receivedDocument($token, $participantId, $externalId, $baseUrl);
                    $ubl = (string) ($detail['payload'] ?? $detail['ubl'] ?? '');
                    if ($ubl === '') {
                        $stats['failed']++;

                        continue;
                    }

                    $draft = $this->parser->parse($ubl);
                    $expense = $this->expenseService->create($company, $draft);
                    $attachment = $this->storeUblAttachment($expense, $ubl, $externalId);

                    $receipt = EfakturaInboundReceipt::query()->create([
                        'company_id' => $company->id,
                        'external_document_id' => $externalId,
                        'business_expense_id' => $expense->id,
                        'status' => 'imported',
                        'attachment_disk' => $attachment['disk'],
                        'attachment_path' => $attachment['path'],
                        'response_payload' => $detail,
                    ]);

                    $this->client->acknowledgeReceived($token, $participantId, $externalId, $baseUrl);
                    $receipt->update([
                        'status' => 'acknowledged',
                        'acknowledged_at' => now(),
                    ]);

                    AuditLog::log('business_expense.efaktura_inbound_imported', 'business_expense', $expense->id, [
                        'company_id' => $company->id,
                        'external_document_id' => $externalId,
                        'receipt_id' => $receipt->id,
                    ]);

                    $stats['imported']++;
                    $stats['acknowledged']++;
                } catch (\Throwable $e) {
                    report($e);
                    $stats['failed']++;
                    Log::warning('efaktura inbound import failed', [
                        'company_id' => $company->id,
                        'external_document_id' => $externalId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            report($e);
            $stats['failed']++;
        }

        return $stats;
    }

    /**
     * @return array{disk: string, path: string}
     */
    protected function storeUblAttachment(BusinessExpense $expense, string $ubl, string $externalId): array
    {
        $disk = 'local';
        $filename = 'efaktura-'.Str::slug($externalId).'.xml';
        $path = "companies/{$expense->company_id}/expenses/{$expense->id}/{$filename}";

        Storage::disk($disk)->put($path, $ubl);

        $expense->attachments()->create([
            'disk' => $disk,
            'path' => $path,
            'original_filename' => $filename,
            'mime' => 'application/xml',
            'size_bytes' => strlen($ubl),
        ]);

        $expense->update([
            'attachment_disk' => $disk,
            'attachment_path' => $path,
            'original_filename' => $filename,
            'attachment_mime' => 'application/xml',
        ]);

        return ['disk' => $disk, 'path' => $path];
    }
}
