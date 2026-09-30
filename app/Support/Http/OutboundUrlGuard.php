<?php

namespace App\Support\Http;

/**
 * SSRF guard for server-side requests to user-supplied URLs (API key
 * callbacks, integration webhooks).
 *
 * A URL is allowed only when it is https on an allowed port, carries no
 * credentials, names a dotted host (no IP literals) and every A/AAAA record
 * of that host is a public address. The returned Guzzle options pin the
 * connection to exactly those addresses (CURLOPT_RESOLVE) and disable
 * redirects, so neither DNS rebinding nor a 30x can reach an internal host.
 * Same rules as LnAddressLud21Prober.
 */
class OutboundUrlGuard
{
    /**
     * @param  list<int>  $allowedPorts
     * @return array<string, mixed>|null Guzzle options, or null when the URL is not allowed
     */
    public function pinnedOptions(string $url, array $allowedPorts = [443]): ?array
    {
        $parts = parse_url($url);
        if (! is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https') {
            return null;
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $port = (int) ($parts['port'] ?? 443);
        if (! in_array($port, $allowedPorts, true)) {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');
        if ($host === '' || ! str_contains($host, '.') || str_starts_with($host, '[')
            || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return null;
        }

        $ips = $this->resolvePublicIps($host);
        if ($ips === null) {
            return null;
        }

        return [
            'allow_redirects' => false,
            'curl' => [CURLOPT_RESOLVE => [$host.':'.$port.':'.implode(',', $ips)]],
        ];
    }

    /**
     * Every address the host resolves to, or null when resolution fails or
     * ANY address is not public (one private record is enough to refuse).
     *
     * @return list<string>|null
     */
    public function resolvePublicIps(string $host): ?array
    {
        $ips = $this->lookup($host);
        if ($ips === []) {
            return null;
        }

        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                return null;
            }
        }

        return array_values(array_unique($ips));
    }

    public static function isPublicIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            // Carrier-grade NAT (100.64.0.0/10) is not covered by PHP's flags.
            $long = ip2long($ip);

            return ! ($long >= ip2long('100.64.0.0') && $long <= ip2long('100.127.255.255'));
        }

        $packed = inet_pton($ip);
        if ($packed === false) {
            return false;
        }

        // IPv4-mapped (::ffff:a.b.c.d) and NAT64 (64:ff9b::/96) embed an IPv4
        // address that the IPv6 flags do not inspect.
        $embedsIpv4 = str_starts_with($packed, str_repeat("\0", 10)."\xff\xff")
            || str_starts_with($packed, "\x00\x64\xff\x9b".str_repeat("\0", 8));
        if ($embedsIpv4) {
            $ipv4 = inet_ntop(substr($packed, 12));

            return $ipv4 !== false && self::isPublicIp($ipv4);
        }

        return true;
    }

    /**
     * @return list<string>
     */
    protected function lookup(string $host): array
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
