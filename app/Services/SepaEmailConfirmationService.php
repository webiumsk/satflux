<?php

namespace App\Services;

use App\Enums\BankTransactionDirection;
use App\Models\AuditLog;
use App\Models\Store;
use App\Models\User;
use App\Services\BtcPay\SepaService;
use App\Services\Invoicing\BankEmailOriginVerifier;
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
            return $this->review($store, 'incomplete_credit_evidence');
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
            $dedupKey = hash('sha256', json_encode([
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
        // can identify a request. Current email parsers leave these unset; a
        // matching label in payer-controlled text is insufficient.
        if ($row->e2eReference !== null) {
            return [$row->e2eReference];
        }
        if ($row->isVariableSymbolExplicit && $row->variableSymbol !== null && $row->variableSymbol !== '') {
            return [$row->variableSymbol];
        }

        return [];
    }

    /** @return array{store_id: string, reported: int, outcome: string} */
    private function review(Store $store, string $reason): array
    {
        Log::notice('SEPA b-mail requires manual verification', [
            'store_id' => $store->id,
            'reason' => $reason,
        ]);
        AuditLog::log('sepa.bmail_review_required', 'store', $store->id, [
            'reason' => $reason,
        ], $store->user_id);

        return ['store_id' => $store->id, 'reported' => 0, 'outcome' => 'review'];
    }
}
