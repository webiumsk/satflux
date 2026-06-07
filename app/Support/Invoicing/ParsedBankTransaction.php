<?php

namespace App\Support\Invoicing;

use App\Enums\BankTransactionDirection;

final class ParsedBankTransaction
{
    public function __construct(
        public readonly \DateTimeInterface $bookedAt,
        public readonly float $amount,
        public readonly string $currency,
        public readonly BankTransactionDirection $direction,
        public readonly ?string $variableSymbol = null,
        public readonly ?string $constantSymbol = null,
        public readonly ?string $specificSymbol = null,
        public readonly ?string $counterpartyName = null,
        public readonly ?string $counterpartyIban = null,
        public readonly ?string $reference = null,
        public readonly ?string $bankTransactionId = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toRow(): array
    {
        return [
            'booked_at' => $this->bookedAt,
            'amount' => abs($this->amount),
            'currency' => strtoupper($this->currency),
            'direction' => $this->direction->value,
            'variable_symbol' => $this->variableSymbol,
            'constant_symbol' => $this->constantSymbol,
            'specific_symbol' => $this->specificSymbol,
            'counterparty_name' => $this->counterpartyName,
            'counterparty_iban' => $this->counterpartyIban,
            'reference' => $this->reference,
            'bank_transaction_id' => $this->bankTransactionId,
        ];
    }
}
