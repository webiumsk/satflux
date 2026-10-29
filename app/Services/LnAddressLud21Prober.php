<?php

namespace App\Services;

use App\Services\WalletSecurity\Bolt11;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Server-side LUD-21 support probe for individual Lightning addresses -
 * mirrors the BTCPay LnAddress plugin's save-time CheckVerifySupport: fetch
 * the LNURLp descriptor, request an invoice with a clamped amount and check
 * that the callback response carries a usable https verify URL.
 */
class LnAddressLud21Prober
{
    private const TIMEOUT_SECONDS = 8;

    private const MIN_PROBE_MSAT = 1000;

    /** Probe results are user-specific because LNURLp descriptors live under /lnurlp/{user}. */
    private const CACHE_TTL_SECONDS = 300;

    private string $requestStage = 'descriptor';

    /**
     * @return array{lud21: bool, reason: string}
     *                                            reason: 'ok'|'invalid_address'|'unreachable'|'invalid_lnurlp'|'no_verify'|'invalid_verify'
     */
    public function probe(string $address, bool $fresh = false): array
    {
        $trimmed = trim($address);
        if (! preg_match('/^([^@\s;=]+)@([^@\s;=]+\.[^@\s;=]{2,})$/', $trimmed, $matches)) {
            return ['lud21' => false, 'reason' => 'invalid_address'];
        }

        [, $user, $domain] = $matches;
        $domain = strtolower($domain);
        $cacheKey = 'lnaddress-lud21-probe-v3:'.hash('sha256', $user.'@'.$domain);
        $cached = Cache::get($cacheKey);
        if (! $fresh && is_array($cached)) {
            return $cached;
        }
        $result = $this->probeUncached($user, $domain);
        if (in_array($result['reason'], ['ok', 'no_verify'], true)) {
            Cache::put($cacheKey, $result, self::CACHE_TTL_SECONDS);
        }

        return $result;
    }

    /**
     * @return array{lud21: bool, reason: string}
     */
    protected function probeUncached(string $user, string $domain): array
    {
        $lnurlpUrl = 'https://'.$domain.'/.well-known/lnurlp/'.rawurlencode($user);

        if (! $this->isSafeUrl($lnurlpUrl)) {
            return ['lud21' => false, 'reason' => 'invalid_address'];
        }

        $descriptor = $this->getJsonForStage($lnurlpUrl, 'descriptor');
        if ($descriptor === null) {
            return ['lud21' => false, 'reason' => 'unreachable'];
        }

        $callback = $descriptor['callback'] ?? null;
        if (! is_string($callback) || ! $this->isSafeUrl($callback)) {
            return ['lud21' => false, 'reason' => 'invalid_lnurlp'];
        }

        // Clamp like the plugin probe: at least 1 sat, within the wallet's bounds.
        $min = is_numeric($descriptor['minSendable'] ?? null) ? (int) $descriptor['minSendable'] : self::MIN_PROBE_MSAT;
        $max = is_numeric($descriptor['maxSendable'] ?? null) ? (int) $descriptor['maxSendable'] : PHP_INT_MAX;
        $amount = min(max($min, self::MIN_PROBE_MSAT), max($max, 1));

        $separator = str_contains($callback, '?') ? '&' : '?';
        $invoice = $this->getJsonForStage($callback.$separator.'amount='.$amount, 'invoice');
        $pr = $invoice['pr'] ?? null;
        if ($invoice === null || ($invoice['status'] ?? null) === 'ERROR' || ! is_string($pr) || $pr === '') {
            return ['lud21' => false, 'reason' => 'invalid_lnurlp'];
        }

        $verify = $invoice['verify'] ?? null;
        if ($verify === null || $verify === '') {
            // Missing verify is actionable only when the provider actually minted
            // a payable invoice. An error or arbitrary pr must not suggest Cashu.
            if (($descriptor['tag'] ?? null) !== 'payRequest'
                || ! is_numeric($descriptor['minSendable'] ?? null) || ! is_numeric($descriptor['maxSendable'] ?? null)
                || $min < 1 || $max < $min
                || ! $this->isPayableInvoice($pr, $amount)) {
                return ['lud21' => false, 'reason' => 'invalid_lnurlp'];
            }

            return ['lud21' => false, 'reason' => 'no_verify'];
        }
        if (! is_string($verify) || ! $this->isSafeUrl($verify)) {
            return ['lud21' => false, 'reason' => 'invalid_verify'];
        }

        $receipt = $this->getJsonForStage($verify, 'verify');
        if ($receipt === null) {
            return ['lud21' => false, 'reason' => 'unreachable'];
        }
        if (($receipt['status'] ?? null) !== 'OK' || ! is_bool($receipt['settled'] ?? null)
            || ($receipt['pr'] ?? null) !== $pr || $receipt['settled'] !== false
            || ($receipt['preimage'] ?? null) !== null) {
            return ['lud21' => false, 'reason' => 'invalid_verify'];
        }

        return ['lud21' => true, 'reason' => 'ok'];
    }

    private function isPayableInvoice(string $invoice, int $amount): bool
    {
        try {
            $decoded = Bolt11::decode($invoice);

            return $decoded['network'] === 'bc'
                && $decoded['amount_msat'] === $amount
                && is_string($decoded['payment_hash'])
                && strlen($decoded['payment_hash']) === 64
                && now()->timestamp < $decoded['timestamp'] + $decoded['expiry'];
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Syntactic SSRF guard mirroring the plugin's safe-HTTP rules: https only,
     * no userinfo, no custom port, a dotted hostname (no IP literals). DNS is
     * checked separately per request via resolvePublicIps().
     */
    protected function isSafeUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return false;
        }

        if (strtolower($parts['scheme'] ?? '') !== 'https') {
            return false;
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            return false;
        }
        if (isset($parts['port']) && $parts['port'] !== 443) {
            return false;
        }

        $host = strtolower($parts['host'] ?? '');
        if ($host === '' || ! str_contains($host, '.')) {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false || str_starts_with($host, '[')) {
            return false;
        }

        return true;
    }

    /**
     * All public addresses the host resolves to (A and AAAA), or null when
     * resolution fails, returns nothing, or ANY resolved address is private,
     * reserved, loopback or link-local. The result is pinned into the request
     * (CURLOPT_RESOLVE), so the connection cannot re-resolve elsewhere.
     *
     * @return list<string>|null
     */
    protected function resolvePublicIps(string $host): ?array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if ($records === false || $records === []) {
            return null;
        }

        $ips = [];
        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (! is_string($ip) || $ip === '') {
                continue;
            }
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return null;
            }
            $ips[] = $ip;
        }

        return $ips === [] ? null : array_values(array_unique($ips));
    }

    /** @return array<string, mixed>|null */
    private function getJsonForStage(string $url, string $stage): ?array
    {
        $previousStage = $this->requestStage;
        $this->requestStage = $stage;

        try {
            return $this->getJson($url);
        } finally {
            $this->requestStage = $previousStage;
        }
    }

    private function logFailure(string $host, string $category, ?int $status = null): void
    {
        $context = ['stage' => $this->requestStage, 'url_host' => $host, 'category' => $category];
        if ($status !== null) {
            $context['http_status'] = $status;
        }

        Log::info('LUD-21 probe failed', $context);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getJson(string $url): ?array
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $ips = $this->resolvePublicIps($host);
        if ($ips === null) {
            $this->logFailure($host, 'dns_rejected');

            return null;
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withOptions([
                    'allow_redirects' => false,
                    // Pin the connection to the addresses validated above (DNS rebinding guard).
                    'curl' => [CURLOPT_RESOLVE => [$host.':443:'.implode(',', $ips)]],
                ])
                ->acceptJson()
                ->get($url);
        } catch (\Throwable) {
            $this->logFailure($host, 'request_failed');

            return null;
        }

        if (! $response->successful()) {
            $this->logFailure($host, 'http_error', $response->status());

            return null;
        }

        $json = $response->json();
        if (! is_array($json)) {
            $this->logFailure($host, 'invalid_json', $response->status());
        }

        return is_array($json) ? $json : null;
    }
}
