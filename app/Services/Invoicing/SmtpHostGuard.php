<?php

namespace App\Services\Invoicing;

/**
 * Rejects company SMTP hosts that resolve to private/reserved IP ranges,
 * so user-supplied SMTP settings cannot probe the internal network (SSRF).
 */
class SmtpHostGuard
{
    /**
     * @throws \InvalidArgumentException when the host resolves to a private/reserved address
     */
    public function assertAllowed(?string $host): void
    {
        if (config('invoicing.smtp_allow_private_hosts')) {
            return;
        }

        $host = trim((string) $host);
        if ($host === '') {
            throw new \InvalidArgumentException(__('SMTP host is required.'));
        }

        // Strip IPv6 brackets ([::1]) before validation.
        $bareHost = trim($host, '[]');

        $ips = filter_var($bareHost, FILTER_VALIDATE_IP) !== false
            ? [$bareHost]
            : $this->resolve($bareHost);

        if ($ips === []) {
            throw new \InvalidArgumentException(__('SMTP host could not be resolved.'));
        }

        foreach ($ips as $ip) {
            $public = filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );

            if ($public === false) {
                throw new \InvalidArgumentException(__('SMTP host must be a public mail server.'));
            }
        }
    }

    /**
     * Resolve every A/AAAA record - all of them must be public, otherwise a
     * multi-record DNS name could smuggle in a private address.
     *
     * @return list<string>
     */
    protected function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        $ips = [];
        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($ip) && $ip !== '') {
                $ips[] = $ip;
            }
        }

        return $ips;
    }
}
