<?php

namespace App\Services\Invoicing\BankImport;

/** No current SLSP evidence profile is qualified; generic imports never authorize settlement. */
final class SlspBankSepaEvidenceParser
{
    public function parse(string $body): null
    {
        return null;
    }
}
