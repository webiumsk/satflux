<?php

namespace App\Services\Invoicing\BankImport;

use App\Support\Invoicing\ParsedBankTransaction;

interface BankStatementParser
{
    public function supports(string $filename, string $contents): bool;

    /**
     * @return list<ParsedBankTransaction>
     */
    public function parse(string $contents): array;
}
