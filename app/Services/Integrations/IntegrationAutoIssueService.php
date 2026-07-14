<?php

namespace App\Services\Integrations;

use App\Jobs\SendWooAutoInvoiceEmail;
use App\Models\AuditLog;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\CompanyAutoIssueProfile;
use App\Models\IntegrationDocumentInbox;
use App\Services\Invoicing\DocumentSequenceService;
use App\Services\Invoicing\EphemeralDocumentFactory;
use Illuminate\Support\Facades\Log;

/**
 * Headless auto-issue for paid WooCommerce orders (local-first inbox mode).
 *
 * When a PAID order lands in the integration inbox and the company opted in
 * (CompanyAutoIssueProfile synced from the client), the server allocates the
 * invoice number through the shared reservation allocator, stamps the inbox
 * payload and queues the customer email. The inbox entry stays Pending so the
 * merchant's browser later imports it WITH the pre-assigned number - the
 * local Evolu dataset remains the document of record and converges without
 * duplicates (client path: payload.number → applyReservedNumber).
 */
class IntegrationAutoIssueService
{
    public function __construct(
        protected DocumentSequenceService $sequenceService,
        protected EphemeralDocumentFactory $documentFactory,
    ) {}

    public function profileFor(Company $company): ?CompanyAutoIssueProfile
    {
        return CompanyAutoIssueProfile::query()
            ->where('company_id', $company->id)
            ->first();
    }

    /**
     * Issue + queue delivery for a freshly enqueued PAID inbox entry.
     * Idempotent: an already numbered payload is returned unchanged.
     */
    public function maybeAutoIssue(Company $company, IntegrationDocumentInbox $entry): IntegrationDocumentInbox
    {
        $payload = $entry->payload_json;

        if (! empty($payload['number'])) {
            return $entry;
        }
        if (($payload['type'] ?? 'invoice') !== 'invoice') {
            return $entry;
        }
        if (empty($payload['is_paid'])) {
            return $entry;
        }

        $profile = $this->profileFor($company);
        if (! $profile) {
            return $entry;
        }

        // Shared allocator: reservation is idempotent per issue request and
        // raises the counter floor the local-first client allocator respects,
        // so a later browser-side issue can never collide with this number.
        // The synced local high counter covers invoices issued locally BEFORE
        // the shared allocator existed (post-allocator issues are protected
        // by the reservation floor itself).
        $localHighCounters = $profile->profile_json['local_high_counters'] ?? [];
        $localHighCounter = is_array($localHighCounters) && isset($localHighCounters['invoice'])
            ? (int) $localHighCounters['invoice']
            : null;

        $reservation = $this->sequenceService->reserveNumberForIssue(
            $company,
            'invoice',
            'woo-inbox:'.$entry->evolu_document_id,
            $localHighCounter,
        );
        $this->sequenceService->confirmReservation(
            $company,
            'invoice',
            'woo-inbox:'.$entry->evolu_document_id,
            hash('sha256', json_encode($payload) ?: ''),
            // NB: document_number_reservations.confirmed_format_version is varchar(16).
            'woo-auto-v1',
        );

        $payload['number'] = $reservation->number;
        $payload['variable_symbol'] = preg_replace('/\D/', '', $reservation->number) ?: null;
        $payload['issued_at'] = now()->toIso8601String();
        $payload['auto_issued_at'] = now()->toIso8601String();

        $buyerEmail = trim((string) ($payload['buyer']['email'] ?? ''));
        $shouldEmail = $profile->auto_email && $buyerEmail !== '';
        if ($shouldEmail) {
            $payload['email_queued_at'] = now()->toIso8601String();
        }

        $entry->payload_json = $payload;
        $entry->save();

        AuditLog::log('integration_inbox.auto_issued', 'company', $company->id, [
            'inbox_id' => $entry->id,
            'number' => $reservation->number,
            'email_queued' => $shouldEmail,
        ]);

        if ($shouldEmail) {
            SendWooAutoInvoiceEmail::dispatch($entry->id);
        }

        return $entry->fresh() ?? $entry;
    }

    /**
     * In-memory document built from a stamped inbox payload + synced profile -
     * the same factory the ephemeral render/email endpoints use.
     */
    public function buildDocument(Company $company, IntegrationDocumentInbox $entry, CompanyAutoIssueProfile $profile): BusinessDocument
    {
        $snapshotCompany = $this->buildCompany($company, $profile);
        $payload = $entry->payload_json;

        return $this->documentFactory->document($snapshotCompany, [
            'contact' => $this->buyerToContactSnapshot(is_array($payload['buyer'] ?? null) ? $payload['buyer'] : []),
            'document' => [
                'type' => (string) ($payload['type'] ?? 'invoice'),
                'status' => 'issued',
                'number' => $payload['number'] ?? null,
                'variable_symbol' => $payload['variable_symbol'] ?? null,
                'issue_date' => $payload['issue_date'] ?? now()->toDateString(),
                'delivery_date' => $payload['delivery_date'] ?? null,
                'due_date' => $payload['due_date'] ?? null,
                'currency' => (string) ($payload['currency'] ?? $company->default_currency),
                'note_above_lines' => $payload['note_above_lines'] ?? null,
                'internal_note' => $payload['internal_note'] ?? null,
                'payment_btc_enabled' => (bool) ($payload['payment_btc_enabled'] ?? false),
                'payment_bank_enabled' => (bool) ($payload['payment_bank_enabled'] ?? true),
                'discount_percent' => (float) ($payload['discount_percent'] ?? 0),
                'amount_paid' => (float) ($payload['order_total'] ?? 0),
            ],
            'lines' => is_array($payload['lines'] ?? null) ? $payload['lines'] : [],
            'store_id' => $payload['store_id'] ?? null,
            'evolu_document_id' => $entry->evolu_document_id,
        ]);
    }

    public function buildCompany(Company $company, CompanyAutoIssueProfile $profile): Company
    {
        $companyPayload = is_array($profile->profile_json['company'] ?? null)
            ? $profile->profile_json['company']
            : [];

        return $this->documentFactory->snapshotCompany($company, $companyPayload);
    }

    /**
     * WooCommerce buyer keys → BuyerSnapshot/CompanyContact attribute names.
     *
     * @param  array<string, mixed>  $buyer
     * @return array<string, mixed>|null
     */
    public function buyerToContactSnapshot(array $buyer): ?array
    {
        $name = trim((string) ($buyer['company'] ?? '')) ?: trim((string) ($buyer['name'] ?? ''));
        if ($name === '' && trim((string) ($buyer['email'] ?? '')) === '') {
            return null;
        }

        return [
            'name' => $name !== '' ? $name : (string) $buyer['email'],
            'email' => $buyer['email'] ?? null,
            'registration_number' => $buyer['ico'] ?? null,
            'tax_id' => $buyer['dic'] ?? null,
            'vat_id' => $buyer['ic_dph'] ?? null,
            'street' => $buyer['street'] ?? null,
            'city' => $buyer['city'] ?? null,
            'postal_code' => $buyer['zip'] ?? null,
            'country' => $buyer['country'] ?? null,
        ];
    }

    /** Stamp delivery evidence; tolerate a concurrently imported (deleted) entry. */
    public function stampEmailResult(IntegrationDocumentInbox $entry, bool $sent, ?string $error = null): void
    {
        try {
            $payload = $entry->payload_json;
            if ($sent) {
                $payload['emailed_at'] = now()->toIso8601String();
                unset($payload['email_error']);
            } else {
                $payload['email_error'] = $error ?? 'send_failed';
            }
            $entry->payload_json = $payload;
            $entry->save();
        } catch (\Throwable $e) {
            Log::warning('Woo auto-issue: could not stamp email result', [
                'inbox_id' => $entry->id,
                'exception' => $e,
            ]);
        }
    }
}
