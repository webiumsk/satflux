<?php

namespace App\Services\Invoicing\Efaktura;

use App\Models\Company;
use App\Models\EfakturaInboundReceipt;
use App\Models\User;
use App\Support\Invoicing\CompanyEfakturaEligibility;
use App\Support\Invoicing\CompanyEfakturaSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Inbox for e-invoices received on behalf of a local-first company. The
 * poller (EfakturaInboundService) parks each document as a pending receipt
 * with the parsed expense draft and the UBL encrypted at rest; the client
 * imports it into Evolu (stable id from evolu_expense_id), prepares a custody
 * acknowledgement, then proves the local original again before completing
 * handoff. Preparation never drops the retained server payload.
 */
class EfakturaInboundInboxService
{
    public const CUSTODY_PROTOCOL = 'post_ack_proof_v1';

    /**
     * @param  array<string, mixed>  $draft
     * @param  array<string, mixed>  $detail
     */
    public function storeAsInboxItem(
        Company $company,
        string $externalId,
        string $ubl,
        array $draft,
        array $detail,
        ?EfakturaInboundReceipt $existing = null,
        array $provenance = [],
    ): EfakturaInboundReceipt {
        $attributes = array_merge($provenance, [
            'company_id' => $company->id,
            'external_document_id' => $externalId,
            'business_expense_id' => null,
            'status' => 'imported',
            'inbox_status' => EfakturaInboundReceipt::INBOX_PENDING,
            'evolu_expense_id' => $existing?->evolu_expense_id ?: (string) Str::uuid(),
            'draft_json' => $draft,
            'ubl_encrypted' => Crypt::encryptString($ubl),
            'original_sha256' => hash('sha256', $ubl),
            'external_number' => isset($draft['external_number']) ? Str::limit((string) $draft['external_number'], 120, '') : null,
            'supplier_name' => isset($draft['title']) ? Str::limit((string) $draft['title'], 255, '') : null,
            'total' => isset($draft['total']) ? round((float) $draft['total'], 2) : null,
            'currency' => isset($draft['currency']) ? strtoupper(substr((string) $draft['currency'], 0, 3)) : null,
            'inbox_resolved_at' => null,
            'attachment_disk' => null,
            'attachment_path' => null,
            'acknowledged_at' => null,
            'response_payload' => $detail,
        ]);

        if ($existing !== null) {
            $existing->update($attributes);

            return $existing->refresh();
        }

        return EfakturaInboundReceipt::query()->create($attributes);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listPending(User $user, Company $company): Collection
    {
        $this->assertCompanyAccess($user, $company);

        return EfakturaInboundReceipt::query()
            ->where('company_id', $company->id)
            ->inboxPending()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (EfakturaInboundReceipt $receipt) => $this->serialize($receipt));
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(User $user, Company $company, EfakturaInboundReceipt $receipt): array
    {
        $this->assertCompanyAccess($user, $company);
        $this->assertReceiptBelongsToCompany($receipt, $company);

        return $this->serialize($receipt, withUbl: true);
    }

    /** @return array{state: string, last_poll_at: ?string, failed: int} */
    public function health(User $user, Company $company): array
    {
        $this->assertCompanyAccess($user, $company);
        $settings = CompanyEfakturaSettings::fromCompany($company);
        $last = $settings->values['efaktura_inbound_last_poll_at'] ?? null;
        $last = is_string($last) && $last !== '' ? $last : null;
        $stats = $settings->values['efaktura_inbound_last_poll_stats'] ?? [];
        $failed = is_array($stats) ? max(0, (int) ($stats['failed'] ?? 0)) : 0;
        if (! config('efaktura.enabled') || ! app(CompanyEfakturaEligibility::class)->supportsInbound($company) || ! $settings->inboundEnabled() || ! $settings->configured()) {
            $state = 'disabled';
        } elseif ($last === null) {
            $state = 'not_polled';
        } elseif ($failed > 0) {
            $state = 'failed';
        } else {
            try {
                $at = Carbon::parse($last);
                $state = $at->lt(now()->subMinutes((int) config('efaktura.inbound_health_stale_minutes', 45))) || $at->gt(now()->addMinutes(5)) ? 'stale' : 'ok';
            } catch (\Throwable) {
                $state = 'failed';
            }
        }

        return ['state' => $state, 'last_poll_at' => $last, 'failed' => $failed];
    }

    public function prepareImport(EfakturaInboundReceipt $receipt, Company $company, ?string $originalSha256 = null): string
    {
        $this->assertReceiptBelongsToCompany($receipt, $company);

        return DB::transaction(function () use ($receipt, $company, $originalSha256): string {
            $locked = EfakturaInboundReceipt::query()->whereKey($receipt->id)->where('company_id', $company->id)->lockForUpdate()->firstOrFail();
            $receipt->setRawAttributes($locked->getAttributes(), true);
            $expected = $this->validatedCustodyDigest($receipt, $originalSha256);
            if ($receipt->original_sha256 === null) {
                $receipt->update(['original_sha256' => $expected]);
            }

            return Crypt::encryptString(json_encode($this->custodyClaims($receipt, $expected), JSON_THROW_ON_ERROR));
        });
    }

    public function markImported(EfakturaInboundReceipt $receipt, Company $company, ?string $originalSha256 = null, ?string $custodyToken = null): ?string
    {
        // Old clients only perform preparation and cannot clear the original.
        if ($custodyToken === null) {
            return $this->prepareImport($receipt, $company, $originalSha256);
        }
        $this->assertReceiptBelongsToCompany($receipt, $company);
        DB::transaction(function () use ($receipt, $company, $originalSha256, $custodyToken): void {
            $locked = EfakturaInboundReceipt::query()->whereKey($receipt->id)->where('company_id', $company->id)->lockForUpdate()->firstOrFail();
            $receipt->setRawAttributes($locked->getAttributes(), true);
            $expected = $this->validatedCustodyDigest($receipt, $originalSha256);
            try {
                if ($custodyToken === '' || strlen($custodyToken) > 8192) {
                    throw new \RuntimeException('Invalid custody confirmation.');
                }
                $claims = json_decode(Crypt::decryptString($custodyToken), true, 16, JSON_THROW_ON_ERROR);
                if ($claims !== $this->custodyClaims($receipt, $expected)) {
                    throw new \RuntimeException('Invalid custody confirmation.');
                }
            } catch (\Throwable) {
                throw ValidationException::withMessages(['custody_token' => ['Repeat custody preparation before completing import.']]);
            }
            if ($receipt->inbox_status === EfakturaInboundReceipt::INBOX_IMPORTED) {
                return;
            }
            $receipt->update(['original_sha256' => $expected]);
            $this->resolvePending($receipt, EfakturaInboundReceipt::INBOX_IMPORTED);
        });

        return null;
    }

    private function validatedCustodyDigest(EfakturaInboundReceipt $receipt, ?string $originalSha256): string
    {
        $expected = $receipt->original_sha256;
        if ($receipt->inbox_status === EfakturaInboundReceipt::INBOX_PENDING) {
            try {
                $original = $receipt->ubl_encrypted !== null ? Crypt::decryptString($receipt->ubl_encrypted) : '';
                if ($original === '' || ($expected !== null && ! hash_equals($expected, hash('sha256', $original)))) {
                    throw new \RuntimeException('Retained original is unavailable.');
                }
                $expected ??= hash('sha256', $original);
            } catch (\Throwable) {
                throw ValidationException::withMessages(['original_sha256' => ['The retained original must be recoverable before completing import.']]);
            }
        } elseif ($receipt->inbox_status !== EfakturaInboundReceipt::INBOX_IMPORTED) {
            throw ValidationException::withMessages(['inbox' => ['Inbox item is not pending.']]);
        }
        if (! is_string($originalSha256) || ! preg_match('/^[a-f0-9]{64}$/D', $originalSha256)
            || ! is_string($expected) || ! preg_match('/^[a-f0-9]{64}$/D', $expected) || ! hash_equals($expected, $originalSha256)) {
            throw ValidationException::withMessages(['original_sha256' => ['Confirm the saved original document before completing import.']]);
        }

        return $expected;
    }

    /** @return array<string, int|string|null> */
    private function custodyClaims(EfakturaInboundReceipt $receipt, string $digest): array
    {
        return [
            'purpose' => 'efaktura_inbox_custody', 'version' => 1,
            'company_id' => $receipt->company_id, 'receipt_id' => $receipt->id,
            'connection_key' => $receipt->connection_key, 'original_sha256' => $digest,
            'evolu_expense_id' => $receipt->evolu_expense_id,
        ];
    }

    public function dismiss(EfakturaInboundReceipt $receipt, Company $company): void
    {
        $this->assertReceiptBelongsToCompany($receipt, $company);
        $receipt->refresh();
        if ($receipt->ubl_encrypted !== null) {
            throw ValidationException::withMessages(['inbox' => ['Import the original safely before resolving this document.']]);
        }
        $this->resolvePending($receipt, EfakturaInboundReceipt::INBOX_DISMISSED);
    }

    /**
     * Only pending rows may transition. Verified import replays are handled
     * idempotently by markImported() under the receipt row lock.
     */
    protected function resolvePending(EfakturaInboundReceipt $receipt, string $terminalStatus): void
    {
        $affected = EfakturaInboundReceipt::query()
            ->whereKey($receipt->id)
            ->inboxPending()
            ->update([
                'inbox_status' => $terminalStatus,
                'inbox_resolved_at' => now(),
                'draft_json' => null,
                'ubl_encrypted' => null,
            ]);

        if ($affected === 0) {
            throw ValidationException::withMessages([
                'inbox' => ['Inbox item is not pending.'],
            ]);
        }

        $receipt->refresh();
    }

    public function countPending(Company $company): int
    {
        return EfakturaInboundReceipt::query()->where('company_id', $company->id)->inboxPending()->count();
    }

    /** Pending originals are never expired without a verified durable destination. */
    public function purgeStale(?int $days = null): int
    {
        return 0;
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(EfakturaInboundReceipt $receipt, bool $withUbl = false): array
    {
        $row = [
            'custody_protocol' => self::CUSTODY_PROTOCOL,
            'inbox_id' => $receipt->id,
            'evolu_expense_id' => $receipt->evolu_expense_id,
            'external_document_id' => $receipt->external_document_id,
            'inbox_status' => $receipt->inbox_status,
            'original_sha256' => $receipt->original_sha256 ?? ($receipt->ubl_encrypted !== null ? hash('sha256', Crypt::decryptString($receipt->ubl_encrypted)) : null),
            'created_at' => $receipt->created_at?->toIso8601String(),
            'draft' => $receipt->draft_json,
            'summary' => [
                'supplier_name' => $receipt->supplier_name,
                'external_number' => $receipt->external_number,
                'total' => $receipt->total !== null ? number_format((float) $receipt->total, 2, '.', '') : null,
                'currency' => $receipt->currency,
            ],
        ];

        if ($withUbl) {
            $row['ubl'] = $receipt->ubl_encrypted !== null ? Crypt::decryptString($receipt->ubl_encrypted) : null;
        }

        return $row;
    }

    protected function assertReceiptBelongsToCompany(EfakturaInboundReceipt $receipt, Company $company): void
    {
        if ($receipt->company_id !== $company->id) {
            abort(404);
        }
    }

    public function assertCompanyAccess(User $user, Company $company): void
    {
        if ($company->user_id !== $user->id && ! $user->isSupport() && ! $user->isAdmin()) {
            abort(403, 'Unauthorized access to company');
        }
    }
}
