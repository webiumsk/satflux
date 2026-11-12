<?php

namespace App\Services\Invoicing\Efaktura;

use App\Enums\CompanyJurisdiction;
use App\Models\AuditLog;
use App\Models\BusinessExpense;
use App\Models\Company;
use App\Models\EfakturaInboundReceipt;
use App\Services\Compliance\XmlParser;
use App\Services\Invoicing\BusinessExpenseService;
use App\Support\Invoicing\CompanyEfakturaEligibility;
use App\Support\Invoicing\CompanyEfakturaSettings;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EfakturaInboundService
{
    public function __construct(
        protected SapiSkClient $client,
        protected UblExpenseDraftParser $parser,
        protected BusinessExpenseService $expenseService,
        protected EfakturaInboundInboxService $inboxService,
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

        $eligibility = app(CompanyEfakturaEligibility::class);

        Company::query()
            ->where('jurisdiction', CompanyJurisdiction::EuSk)
            ->orderBy('id')
            ->chunkById(50, function ($companies) use (&$stats, $eligibility) {
                foreach ($companies as $company) {
                    if (! $eligibility->supportsInbound($company)) {
                        continue;
                    }
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
        if (! app(CompanyEfakturaEligibility::class)->supportsInbound($company)) {
            return $stats;
        }

        // A company-wide distributed lock also serializes manual and scheduled polls.
        $lock = Cache::lock('efaktura.inbound.company.'.$company->id, 300);
        if (! $lock->get()) {
            return $stats;
        }

        try {
            return $this->pollLockedCompany($company->fresh() ?? $company);
        } finally {
            $lock->release();
        }
    }

    /** @return array{imported: int, acknowledged: int, skipped: int, failed: int} */
    protected function pollLockedCompany(Company $company): array
    {
        $stats = ['imported' => 0, 'acknowledged' => 0, 'skipped' => 0, 'failed' => 0];
        if (! config('efaktura.enabled') || ! app(CompanyEfakturaEligibility::class)->supportsInbound($company)) {
            return $stats;
        }
        $settings = CompanyEfakturaSettings::fromCompany($company);
        if (! $settings->inboundEnabled() || ! $settings->configured()) {
            return $stats;
        }

        $participantId = (string) $settings->peppolParticipantId();
        $baseUrl = (string) $settings->sapiBaseUrl();
        $clientId = (string) $settings->sapiClientId();
        $secret = (string) $settings->sapiClientSecret();
        $fingerprint = $settings->connectionFingerprint();
        $started = microtime(true);

        try {
            if ($this->client->isEpostman($baseUrl) && ($settings->derivedPeppolParticipantId() === null || $settings->derivedPeppolParticipantId() !== $participantId)) {
                throw new \RuntimeException('The ePostman participant must match the company registration identity.');
            }
            $connectionKey = $this->client->connectionKey($baseUrl, $participantId);
            $origin = $this->client->validateBaseUrl($baseUrl);
            $token = $this->client->accessToken($clientId, $secret, $baseUrl);
            $renewed = false;
            // Only GET and the documented idempotent ACK use this bounded recovery.
            $call = function (callable $operation) use (&$token, &$renewed, $clientId, $secret, $baseUrl) {
                try {
                    return $operation($token);
                } catch (RequestException $exception) {
                    if ($exception->response->status() !== 401 || $renewed) {
                        throw $exception;
                    }
                    $renewed = true;
                    $this->client->forgetAccessToken($clientId, $secret, $baseUrl);
                    $token = $this->client->accessToken($clientId, $secret, $baseUrl);

                    return $operation($token);
                }
            };

            $cursor = null;
            $seen = [];
            $maxPages = max(1, (int) config('efaktura.inbound_max_pages', 100));
            for ($page = 0; $page < $maxPages; $page++) {
                if (microtime(true) - $started > 120) {
                    throw new \RuntimeException('Inbound polling time budget exhausted.');
                }
                $listing = $call(fn (string $token) => $this->client->listReceivedDocuments($token, $participantId, pageToken: $cursor, baseUrl: $baseUrl));
                $documents = $listing['documents'] ?? $listing['items'] ?? [];
                foreach ($documents as $item) {
                    try {
                        if (microtime(true) - $started > 120) {
                            throw new \RuntimeException('Inbound polling time budget exhausted.');
                        }
                        $externalId = is_array($item) ? ($item['providerDocumentId'] ?? $item['documentId'] ?? $item['id'] ?? null) : null;
                        if (! is_string($externalId) || trim($externalId) === '' || strlen($externalId) > 255) {
                            throw new \RuntimeException('Invalid inbound document identifier.');
                        }
                        if ($this->client->isEpostman($baseUrl) && ($item['receiverParticipantId'] ?? null) !== $participantId) {
                            throw new \RuntimeException('Invalid inbound list receiver.');
                        }

                        $receipt = EfakturaInboundReceipt::query()->where('company_id', $company->id)
                            ->where('connection_key', $connectionKey)->where('external_document_id', $externalId)->first();
                        if ($receipt === null && EfakturaInboundReceipt::query()->where('company_id', $company->id)->where('connection_key', 'legacy')->where('external_document_id', $externalId)->exists()) {
                            // Old rows have unknown provider identity. Never silently adopt them.
                            throw new \RuntimeException('Inbound document overlaps a receipt with unknown legacy provenance.');
                        }
                        if ($receipt !== null && $receipt->acknowledged_at !== null) {
                            $stats['skipped']++;

                            continue;
                        }

                        $newContent = $receipt === null || ! $this->receiptHasContent($receipt);
                        if ($newContent) {
                            $detail = $call(fn (string $token) => $this->client->receivedDocument($token, $participantId, $externalId, $baseUrl));
                            $ubl = $detail['payload'] ?? $detail['ubl'] ?? null;
                            if (! is_string($ubl) || trim($ubl) === '' || strlen($ubl) > 10 * 1024 * 1024) {
                                throw new \RuntimeException('Inbound document has no XML original.');
                            }
                            if ($this->client->isEpostman($baseUrl)) {
                                $this->assertEpostmanOriginal($ubl, $participantId);
                            }
                            // Commit custody before any provider ACK; a stale poll may
                            // not import after settings or credentials are revoked.
                            $receipt = DB::transaction(function () use ($company, $fingerprint, $externalId, $ubl, $detail, $receipt, $connectionKey, $origin, $participantId) {
                                $fresh = $this->lockMatchingCompany($company, $fingerprint);
                                $retained = $receipt === null ? null : EfakturaInboundReceipt::query()->whereKey($receipt->id)->where('company_id', $company->id)->lockForUpdate()->firstOrFail();
                                if ($retained?->original_sha256 !== null && ! hash_equals($retained->original_sha256, hash('sha256', $ubl))) {
                                    throw new \RuntimeException('Recovered inbound original does not match retained identity.');
                                }

                                return $this->importInboundDocument($fresh, $externalId, $ubl, $detail, $retained, $connectionKey, $origin, $participantId);
                            });
                        }

                        // A separate transaction preserves the committed original on
                        // ACK failure. Hold the settings lock across the remote ACK
                        // so settings updates serialize before or after this call.
                        DB::transaction(function () use ($company, $fingerprint, $receipt, $call, $participantId, $externalId, $baseUrl) {
                            $this->lockMatchingCompany($company, $fingerprint);
                            $lockedReceipt = EfakturaInboundReceipt::query()->whereKey($receipt->id)->lockForUpdate()->firstOrFail();
                            if (! $this->receiptHasContent($lockedReceipt)) {
                                throw new \RuntimeException('Inbound original custody is not established.');
                            }
                            $call(fn (string $token) => $this->client->acknowledgeReceived($token, $participantId, $externalId, $baseUrl));
                            $lockedReceipt->update(['status' => 'acknowledged', 'acknowledged_at' => now()]);
                        });
                        if ($newContent) {
                            $stats['imported']++;
                            AuditLog::log(
                                $receipt->business_expense_id !== null ? 'business_expense.efaktura_inbound_imported' : 'company.efaktura_inbound_inboxed',
                                $receipt->business_expense_id !== null ? 'business_expense' : 'company',
                                $receipt->business_expense_id ?? $company->id,
                                ['company_id' => $company->id, 'receipt_id' => $receipt->id],
                            );
                        }
                        $stats['acknowledged']++;
                    } catch (\Throwable $exception) {
                        $stats['failed']++;
                        $this->logFailure($company, $exception);
                        // Provider throttling / access revocation must not fan out across a page.
                        if ($exception instanceof RequestException && in_array($exception->response->status(), [401, 403, 423, 429], true)) {
                            throw new \RuntimeException('Inbound provider temporarily unavailable.');
                        }
                    }
                }

                $next = $listing['nextPageToken'] ?? null;
                if ($next === null || $next === '') {
                    break;
                }
                if (isset($seen[$next]) || $page + 1 === $maxPages) {
                    throw new \RuntimeException('Inbound pagination limit or repeated cursor.');
                }
                $seen[$next] = true;
                $cursor = $next;
            }
        } catch (\Throwable $exception) {
            $stats['failed']++;
            $this->logFailure($company, $exception);
        }

        $this->persistInboundPollMeta($company, $stats);

        return $stats;
    }

    /** Settings and credential generations share this server-only fingerprint. */
    protected function lockMatchingCompany(Company $company, string $fingerprint): Company
    {
        $fresh = Company::query()->whereKey($company->id)->lockForUpdate()->first();
        if ($fresh === null || ! config('efaktura.enabled') || ! app(CompanyEfakturaEligibility::class)->supportsInbound($fresh)
            || ! hash_equals($fingerprint, CompanyEfakturaSettings::fromCompany($fresh)->connectionFingerprint())) {
            throw new \RuntimeException('Inbound connection settings changed during polling.');
        }

        return $fresh;
    }

    protected function logFailure(Company $company, \Throwable $exception): void
    {
        // RequestException messages may contain provider bodies and secrets. Never report them.
        Log::warning('efaktura inbound operation failed', [
            'company_id' => $company->id,
            'exception_class' => $exception::class,
            'http_status' => $exception instanceof RequestException ? $exception->response->status() : null,
        ]);
    }

    protected function assertEpostmanOriginal(string $ubl, string $participantId): void
    {
        if (preg_match('/<!\s*(DOCTYPE|ENTITY)\b/i', $ubl)) {
            throw new \RuntimeException('DTD and entities are forbidden in inbound UBL.');
        }
        $root = XmlParser::loadString($ubl, 'inbound UBL');
        $dom = dom_import_simplexml($root);
        $expectedNamespace = match ($root->getName()) {
            'Invoice' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
            'CreditNote' => 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2',
            default => null,
        };
        if ($expectedNamespace === null || $dom->namespaceURI !== $expectedNamespace) {
            throw new \RuntimeException('Unsupported inbound UBL document.');
        }
        $root->registerXPathNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $root->registerXPathNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $receivers = $root->xpath('./cac:AccountingCustomerParty/cac:Party/cbc:EndpointID');
        if ($receivers === false || count($receivers) !== 1 || (string) $receivers[0]['schemeID'].':'.trim((string) $receivers[0]) !== $participantId) {
            throw new \RuntimeException('Inbound UBL receiver does not match this company.');
        }
    }

    /**
     * @param  array{imported: int, acknowledged: int, skipped: int, failed: int}  $stats
     */
    protected function persistInboundPollMeta(Company $company, array $stats): void
    {
        $fingerprint = CompanyEfakturaSettings::fromCompany($company)->connectionFingerprint();
        DB::transaction(function () use ($company, $stats, $fingerprint) {
            $fresh = Company::query()->whereKey($company->id)->lockForUpdate()->first();
            if ($fresh === null || ! hash_equals($fingerprint, CompanyEfakturaSettings::fromCompany($fresh)->connectionFingerprint())) {
                // A completed old poll cannot bless a newly selected/revoked connection.
                return;
            }
            $current = is_array($fresh->app_settings) ? $fresh->app_settings : [];
            $fresh->update([
                'app_settings' => array_merge($current, [
                    'efaktura_inbound_last_poll_at' => now()->toIso8601String(),
                    'efaktura_inbound_last_poll_stats' => $stats,
                ]),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    protected function importInboundDocument(
        Company $company,
        string $externalId,
        string $ubl,
        array $detail,
        ?EfakturaInboundReceipt $existingReceipt = null,
        string $connectionKey = 'legacy',
        ?string $origin = null,
        ?string $participantId = null,
    ): EfakturaInboundReceipt {
        // Local-first companies keep their expenses in Evolu: park the parsed
        // draft + UBL as an inbox item for the client instead of creating a
        // server expense nobody would ever see.
        // The provider detail carries the UBL itself - never persist it in the
        // plain response_payload column (the inbox keeps it encrypted, the
        // server mode as a file).
        $detail = $this->detailWithoutDocumentBody($detail);
        if ($this->client->isEpostman($origin)) {
            $metadata = is_array($detail['metadata'] ?? null) ? $detail['metadata'] : [];
            $detail = [
                'metadata' => array_intersect_key($metadata, array_flip(['documentId', 'documentTypeId', 'processId', 'senderParticipantId', 'receiverParticipantId', 'creationDateTime'])),
                'status' => $detail['status'] ?? null,
                'payloadFormat' => $detail['payloadFormat'] ?? null,
            ];
        }
        $provenance = [
            'connection_key' => $connectionKey,
            'connection_origin' => $origin,
            'receiver_participant_id' => $participantId,
            'original_sha256' => hash('sha256', $ubl),
        ];

        if (! $company->usesServerInvoicing()) {
            $draft = $this->parser->parse($ubl);

            return DB::transaction(function () use ($company, $externalId, $ubl, $draft, $detail, $existingReceipt, $provenance) {
                $receipt = $this->inboxService->storeAsInboxItem($company, $externalId, $ubl, $draft, $detail, $existingReceipt, $provenance);

                return $receipt;
            });
        }

        $attachment = null;

        try {
            return DB::transaction(function () use ($company, $externalId, $ubl, $detail, $existingReceipt, $provenance, &$attachment) {
                $draft = $this->parser->parse($ubl);
                $expense = BusinessExpense::query()->whereKey($existingReceipt?->business_expense_id)->where('company_id', $company->id)->first()
                    ?? $this->expenseService->create($company, $draft);
                $attachment = $this->storeUblAttachment($expense, $ubl, $externalId);

                $attributes = array_merge($provenance, [
                    'company_id' => $company->id,
                    'external_document_id' => $externalId,
                    'business_expense_id' => $expense->id,
                    'status' => 'imported',
                    'attachment_disk' => $attachment['disk'],
                    'attachment_path' => $attachment['path'],
                    'acknowledged_at' => null,
                    'response_payload' => $detail,
                ]);

                if ($existingReceipt !== null) {
                    $existingReceipt->update($attributes);

                    return $existingReceipt->refresh();
                }

                return EfakturaInboundReceipt::query()->create($attributes);
            });
        } catch (\Throwable $e) {
            if (is_array($attachment)) {
                Storage::disk($attachment['disk'])->delete($attachment['path']);
            }

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return array<string, mixed>
     */
    protected function detailWithoutDocumentBody(array $detail): array
    {
        unset($detail['payload'], $detail['ubl'], $detail['document'], $detail['xml']);

        return $detail;
    }

    /**
     * ACK retries require readable retained bytes, not metadata or a historical
     * client handoff whose original availability the server cannot inspect.
     * Missing custody is recovered from the provider before acknowledging.
     */
    protected function receiptHasContent(EfakturaInboundReceipt $receipt): bool
    {
        try {
            if ($receipt->business_expense_id !== null && $receipt->expense()->where('company_id', $receipt->company_id)->exists()
                && is_string($receipt->attachment_disk) && is_string($receipt->attachment_path)
                && Storage::disk($receipt->attachment_disk)->exists($receipt->attachment_path)) {
                $ubl = Storage::disk($receipt->attachment_disk)->get($receipt->attachment_path);
            } elseif ($receipt->evolu_expense_id !== null && is_string($receipt->ubl_encrypted)) {
                $ubl = Crypt::decryptString($receipt->ubl_encrypted);
            } else {
                return false;
            }
            if (! is_string($ubl) || trim($ubl) === '' || strlen($ubl) > 10 * 1024 * 1024
                || (is_string($receipt->original_sha256) && ! hash_equals($receipt->original_sha256, hash('sha256', $ubl)))
                || preg_match('/<!\s*(DOCTYPE|ENTITY)\b/i', $ubl)) {
                return false;
            }
            if ($this->client->isEpostman($receipt->connection_origin)) {
                $this->assertEpostmanOriginal($ubl, (string) $receipt->receiver_participant_id);
            } else {
                $root = XmlParser::loadString($ubl, 'retained inbound UBL');
                if (! in_array($root->getName(), ['Invoice', 'CreditNote'], true)) {
                    return false;
                }
                $this->parser->parse($ubl);
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array{disk: string, path: string}
     */
    protected function storeUblAttachment(BusinessExpense $expense, string $ubl, string $externalId): array
    {
        $disk = 'local';
        $filename = 'efaktura-'.Str::slug($externalId).'.xml';
        $path = "companies/{$expense->company_id}/expenses/{$expense->id}/{$filename}";

        if (! Storage::disk($disk)->put($path, $ubl)) {
            throw new \RuntimeException("Failed to store inbound UBL attachment at [{$path}].");
        }

        $expense->attachments()->updateOrCreate([
            'disk' => $disk,
            'path' => $path,
        ], [
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
