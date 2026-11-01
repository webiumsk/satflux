<?php

namespace App\Support\Invoicing;

use App\Models\Company;
use App\Models\Store;

/** Transient worker result; never attach decoded bank content to a queued job. */
final readonly class PreparedBankMimePayload
{
    /** @param array{to: string, from: string, subject: string, body: string, headers: string, _verified_bank_domain?: string} $payload */
    public function __construct(
        public Company|Store $owner,
        public array $payload,
    ) {}
}
