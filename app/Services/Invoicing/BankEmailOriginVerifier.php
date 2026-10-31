<?php

namespace App\Services\Invoicing;

final class BankEmailOriginVerifier
{
    private const BANK_DOMAINS = ['tatrabanka.sk', 'slsp.sk'];

    /**
     * Accept only receiving-MTA attestations delivered after native webhook
     * authentication. Raw MIME Authentication-Results are never used here.
     */
    public function nativeVerifiedDomain(mixed $attestation, string $from): ?string
    {
        if (! is_array($attestation)
            || ($attestation['dkim_result'] ?? null) !== 'pass'
            || ($attestation['dmarc_result'] ?? null) !== 'pass'
            || ! is_string($attestation['dkim_domain'] ?? null)) {
            return null;
        }

        $domain = strtolower(trim($attestation['dkim_domain']));

        return $this->matchesSender($domain, $from) ? $domain : null;
    }

    public function matchesSender(?string $verifiedDomain, string $from): bool
    {
        if ($verifiedDomain === null) {
            return false;
        }

        $mailbox = trim($from);
        if (preg_match('/\A[^<>]*<([^<>]+)>\z/D', $mailbox, $matches) === 1) {
            $mailbox = trim($matches[1]);
        }
        if (filter_var($mailbox, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        $senderDomain = strtolower(substr($mailbox, strrpos($mailbox, '@') + 1));
        $signingDomain = strtolower(trim($verifiedDomain));
        if (filter_var($signingDomain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            return false;
        }
        foreach (self::BANK_DOMAINS as $bankDomain) {
            if (($senderDomain === $bankDomain || str_ends_with($senderDomain, '.'.$bankDomain))
                && ($signingDomain === $bankDomain || str_ends_with($signingDomain, '.'.$bankDomain))) {
                return true;
            }
        }

        return false;
    }
}
