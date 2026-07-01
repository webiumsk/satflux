<?php

namespace App\Services\Invoicing\Wise;

use App\Enums\BankTransactionDirection;
use App\Support\Invoicing\BankSymbolNormalizer;
use App\Support\Invoicing\ParsedBankTransaction;
use Carbon\Carbon;

class WiseStatementMapper
{
    /**
     * @param  array<string, mixed>  $statement
     * @return list<ParsedBankTransaction>
     */
    public function map(array $statement): array
    {
        $transactions = $statement['transactions'] ?? [];
        if (! is_array($transactions)) {
            return [];
        }

        $parsed = [];
        foreach ($transactions as $transaction) {
            if (! is_array($transaction)) {
                continue;
            }
            $row = $this->mapTransaction($transaction);
            if ($row !== null) {
                $parsed[] = $row;
            }
        }

        return $parsed;
    }

    /**
     * @param  array<string, mixed>  $transaction
     */
    protected function mapTransaction(array $transaction): ?ParsedBankTransaction
    {
        $type = strtoupper((string) ($transaction['type'] ?? ''));
        $direction = match ($type) {
            'CREDIT' => BankTransactionDirection::Credit,
            'DEBIT' => BankTransactionDirection::Debit,
            default => null,
        };
        if ($direction === null) {
            return null;
        }

        $amountBlock = is_array($transaction['amount'] ?? null) ? $transaction['amount'] : [];
        $amount = (float) ($amountBlock['value'] ?? 0);
        if ($amount == 0.0) {
            return null;
        }

        $currency = strtoupper((string) ($amountBlock['currency'] ?? 'USD'));
        $details = is_array($transaction['details'] ?? null) ? $transaction['details'] : [];

        $reference = $details['paymentReference']
            ?? $details['description']
            ?? $transaction['referenceNumber']
            ?? null;
        $reference = is_string($reference) ? trim($reference) : null;
        if ($reference === '') {
            $reference = null;
        }

        $counterparty = $details['senderName']
            ?? $details['recipientName']
            ?? $details['merchant']
            ?? null;
        $counterparty = is_string($counterparty) ? trim($counterparty) : null;

        $dateRaw = $transaction['date'] ?? null;
        if (! is_string($dateRaw) || trim($dateRaw) === '') {
            return null;
        }

        $bankTransactionId = isset($transaction['referenceNumber'])
            ? (string) $transaction['referenceNumber']
            : null;

        return new ParsedBankTransaction(
            bookedAt: Carbon::parse($dateRaw),
            amount: abs($amount),
            currency: $currency,
            direction: $direction,
            variableSymbol: null,
            counterpartyName: $counterparty,
            reference: $reference,
            bankTransactionId: $bankTransactionId,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function serializeRows(array $rows): array
    {
        return array_map(
            fn (ParsedBankTransaction $row) => [
                'booked_at' => $row->bookedAt->format(\DateTimeInterface::ATOM),
                'amount' => $row->amount,
                'currency' => $row->currency,
                'direction' => $row->direction->value,
                'variable_symbol' => $row->variableSymbol,
                'constant_symbol' => $row->constantSymbol,
                'specific_symbol' => $row->specificSymbol,
                'counterparty_name' => $row->counterpartyName,
                'counterparty_iban' => $row->counterpartyIban,
                'reference' => $row->reference,
                'bank_transaction_id' => $row->bankTransactionId,
            ],
            $rows,
        );
    }
}
