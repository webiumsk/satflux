<?php

namespace App\Services;

use App\Enums\BankTransactionDirection;
use App\Models\AuditLog;
use App\Models\Store;
use App\Models\User;
use App\Services\BtcPay\SepaService;
use App\Services\Invoicing\BankEmailOriginVerifier;
use App\Services\Invoicing\BankImport\TatraBankSepaEvidenceParser;
use App\Support\Invoicing\BankAccountIdentifier;
use App\Support\Invoicing\ParsedBankTransaction;
use Illuminate\Support\Facades\Log;

/**
 * Confirms SEPA payment requests from bank notification emails (b-mail):
 * a store-scoped inbound address receives the bank's credit notification,
 * only an authenticated bank credit with an observed receiving account can
 * reach the plugin's store/account-bound reporting contract.
 */
class SepaEmailConfirmationService
{
    public function __construct(
        protected SepaService $sepaService,
        protected BankEmailOriginVerifier $originVerifier,
    ) {}

    /**
     * @param  list<ParsedBankTransaction>  $rows
     * @return array{store_id: string, reported: int, outcome: ?string}
     */
    public function handle(Store $store, array $payload, array $rows): array
    {
        $credits = array_values(array_filter(
            $rows,
            fn (ParsedBankTransaction $row) => $row->direction === BankTransactionDirection::Credit
        ));

        if ($credits === []) {
            return ['store_id' => $store->id, 'reported' => 0, 'outcome' => null];
        }

        $verifiedDomain = $payload['_verified_bank_domain'] ?? null;
        if (! $this->originVerifier->matchesSender(
            is_string($verifiedDomain) ? $verifiedDomain : null,
            (string) ($payload['from'] ?? ''),
        )) {
            return $this->review($store, 'unverified_bank_origin');
        }

        $eligible = array_filter($credits, fn (ParsedBankTransaction $row) => $row->isExplicitCredit
            && $row->hasExplicitCurrency
            && ! $row->isReferenceAmbiguous
            && BankAccountIdentifier::normalize($row->recipientIban) !== null
            && $this->referenceCandidates($row) !== []);
        if ($eligible === []) {
            try {
                $diagnostics = $this->creditEvidenceDiagnostics($payload, $credits);
            } catch (\Throwable) {
                // Diagnostics must never replace the original review outcome or retain exception text.
                $diagnostics = ['version' => 1, 'status' => 'unavailable'];
            }

            return $this->review($store, 'incomplete_credit_evidence', $diagnostics);
        }

        /** @var User $owner */
        $owner = $store->user;
        $apiKey = $owner->getBtcPayApiKeyOrFail();

        // Old plugins ignore new fields. Never send them an automatic report.
        $settings = $this->sepaService->getSettings($store->btcpay_store_id, $apiKey);
        $contractVersion = $settings['confirmationContractVersion'] ?? null;
        if (! is_int($contractVersion) || $contractVersion < 2) {
            return $this->review($store, 'unsupported_plugin_contract');
        }

        $reported = 0;
        $requiresReview = count($eligible) !== count($credits);
        $finalOutcome = null;
        foreach ($eligible as $index => $row) {
            // Strict bank profiles supply an invoice-independent event identity.
            // Preserve legacy synthetic-row retry compatibility for other rows.
            $dedupKey = $row->sepaCreditIdentity ?? hash('sha256', json_encode([
                $store->id,
                BankAccountIdentifier::normalize($row->recipientIban),
                $row->bankTransactionId,
                hash('sha256', (string) ($payload['body'] ?? '')),
                $index,
                $row->e2eReference,
                $row->variableSymbol,
                $row->currency,
                $row->amount,
            ], JSON_THROW_ON_ERROR));
            $outcome = $this->reportRow($store, $row, $dedupKey, $apiKey);
            if ($outcome === 'unknown') {
                $requiresReview = true;
            } elseif ($outcome !== null) {
                $reported++;
                $finalOutcome = $outcome;
                $requiresReview = $requiresReview || $outcome === 'review';
            }
        }

        if ($requiresReview) {
            $finalOutcome = 'review';
        }

        AuditLog::log('sepa.bmail_processed', 'store', $store->id, [
            'reported' => $reported,
            'outcome' => $finalOutcome,
        ], $owner->id);

        return ['store_id' => $store->id, 'reported' => $reported, 'outcome' => $finalOutcome];
    }

    protected function reportRow(Store $store, ParsedBankTransaction $row, string $dedupKey, string $apiKey): ?string
    {
        $outcome = null;
        foreach ($this->referenceCandidates($row) as $reference) {
            // Let transport errors retry through the existing queue backoff;
            // this stable identity prevents duplicate recording on recovery.
            $result = $this->sepaService->reportPayment($store->btcpay_store_id, [
                'reference' => $reference,
                'amount' => abs($row->amount),
                'currency' => strtoupper($row->currency),
                'creditorIban' => BankAccountIdentifier::normalize($row->recipientIban),
                'bankOriginVerified' => true,
                'dedupKey' => $dedupKey,
            ], $apiKey);

            $outcome = $result['outcome'] ?? null;
            if ($outcome !== null && $outcome !== 'unknown') {
                return $outcome;
            }
        }

        return $outcome;
    }

    /**
     * @return list<string>
     */
    protected function referenceCandidates(ParsedBankTransaction $row): array
    {
        if ($row->isReferenceAmbiguous) {
            return [];
        }

        // Only reference evidence from a proven bank-owned transaction field
        // can identify a request. Only the strict ordered Tatra profile sets
        // this evidence; a label in payer-controlled text is insufficient.
        if ($row->e2eReference !== null) {
            return [$row->e2eReference];
        }
        if ($row->isVariableSymbolExplicit && $row->variableSymbol !== null && $row->variableSymbol !== '') {
            return [$row->variableSymbol];
        }

        return [];
    }

    /** @param list<ParsedBankTransaction> $credits */
    protected function creditEvidenceDiagnostics(array $payload, array $credits): array
    {
        $eligibility = [];
        foreach (array_slice($credits, 0, 3) as $row) {
            $eligibility[] = [
                'explicit_credit' => $row->isExplicitCredit,
                'explicit_currency' => $row->hasExplicitCurrency,
                'unambiguous_reference' => ! $row->isReferenceAmbiguous,
                'valid_receiving_account' => BankAccountIdentifier::normalize($row->recipientIban) !== null,
                'trusted_reference' => $this->referenceCandidates($row) !== [],
                // Presence is informational and never an additional eligibility requirement.
                'strict_profile_identity' => $row->sepaCreditIdentity !== null,
            ];
        }
        $stage = 'not_evaluated';
        if (is_string($payload['_verified_bank_domain'] ?? null) && is_string($payload['from'] ?? null)
            && $this->originVerifier->matchesSender($payload['_verified_bank_domain'], $payload['from'])
            && $this->originVerifier->matchesSender('tatrabanka.sk', $payload['from'])
            && is_string($payload['body'] ?? null) && strlen($payload['body']) <= 65535) {
            $stage = (new TatraBankSepaEvidenceParser)->diagnose($payload['body']);
        }

        return [
            'version' => 1, 'credit_count' => min(count($credits), 3), 'truncated' => count($credits) > 3,
            'eligibility' => $eligibility, 'tatra_profile_stage' => $stage,
        ];
    }

    /** @return array{store_id: string, reported: int, outcome: string} */
    private function review(Store $store, string $reason, ?array $creditEvidence = null): array
    {
        Log::notice('SEPA b-mail requires manual verification', [
            'store_id' => $store->id,
            'reason' => $reason,
        ]);
        $metadata = ['reason' => $reason];
        if ($reason === 'incomplete_credit_evidence' && $creditEvidence !== null) {
            $metadata['credit_evidence'] = $creditEvidence;
        }
        AuditLog::log('sepa.bmail_review_required', 'store', $store->id, $metadata, $store->user_id);

        return ['store_id' => $store->id, 'reported' => 0, 'outcome' => 'review'];
    }
}
