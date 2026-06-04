<?php

namespace App\Services\Invoicing\BankImport;

/**
 * SLSP notifications vary; delegate to generic patterns until samples are collected.
 */
class SlspBankEmailParser extends TatraBankEmailParser
{
    public function supports(string $from, string $subject, string $body): bool
    {
        $haystack = strtolower($from.' '.$subject);

        return str_contains($haystack, 'slsp')
            || str_contains($haystack, 'slovenská sporiteľňa')
            || str_contains($haystack, 'slovenska sporitelna');
    }
}
