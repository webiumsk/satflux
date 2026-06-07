<?php

namespace App\Support\Invoicing;

use App\Models\Company;

final class BankTransactionDedupe
{
    /**
     * @param  array<string, mixed>  $row
     */
    public static function hash(Company $company, array $row): string
    {
        $parts = [
            $company->id,
            (string) ($row['booked_at'] ?? ''),
            number_format((float) ($row['amount'] ?? 0), 2, '.', ''),
            strtoupper((string) ($row['currency'] ?? 'EUR')),
            (string) ($row['direction'] ?? ''),
            (string) ($row['variable_symbol'] ?? ''),
            (string) ($row['reference'] ?? ''),
            (string) ($row['bank_transaction_id'] ?? ''),
        ];

        return hash('sha256', implode('|', $parts));
    }
}
