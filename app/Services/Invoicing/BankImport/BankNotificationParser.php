<?php

namespace App\Services\Invoicing\BankImport;

use App\Support\Invoicing\ParsedBankTransaction;

interface BankNotificationParser
{
    public function supports(string $from, string $subject, string $body): bool;

    /**
     * @return list<ParsedBankTransaction>
     */
    public function parse(string $from, string $subject, string $body): array;
}
