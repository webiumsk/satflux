<?php

namespace App\Services\Invoicing;

use App\Support\Http\OutboundUrlGuard;

/**
 * Rejects company SMTP hosts that resolve to private/reserved IP ranges,
 * so user-supplied SMTP settings cannot probe the internal network (SSRF).
 */
class SmtpHostGuard
{
    /** SMTP, SMTPS, submission and the common alternative submission port. */
    public const ALLOWED_PORTS = [25, 465, 587, 2525];

    /**
     * @throws \InvalidArgumentException when the host resolves to a private/reserved address
     *                                   or the port is not a mail port
     */
    public function assertAllowed(?string $host, ?int $port = null): void
    {
        if (config('invoicing.smtp_allow_private_hosts')) {
            return;
        }

        // Without this, a public host could still be used to probe arbitrary
        // services (SSH, databases) through the SMTP test error messages.
        if ($port !== null && ! in_array($port, self::ALLOWED_PORTS, true)) {
            throw new \InvalidArgumentException(__('SMTP port must be 25, 465, 587 or 2525.'));
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
            if (! OutboundUrlGuard::isPublicIp($ip)) {
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
