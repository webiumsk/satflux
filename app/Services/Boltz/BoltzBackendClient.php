<?php

namespace App\Services\Boltz;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Read-only client for the upstream Boltz backend REST API (api.boltz.exchange/v2).
 *
 * Provides informational pair limits and fees for the swap directions SATFLUX cares about:
 * - reverse: customer Lightning payment -> merchant L-BTC settlement (BTC -> L-BTC)
 * - chain:   treasury sweep L-BTC -> BTC
 *
 * These numbers are the Boltz backend's truth, not the per-store boltz-client daemon's view,
 * so they are always presented as orientational. The authoritative per-invoice validation
 * happens inside the BTCPay Boltz plugin when the invoice payment prompt is created.
 *
 * Caching model per pair:
 * - fresh cache (pairs_cache_ttl): served without hitting the API
 * - last-good cache (pairs_keep_last_good): served with is_stale metadata when a refetch fails
 * - failure backoff (failure_backoff): after a failed fetch the API is not retried until it expires
 */
class BoltzBackendClient
{
    public const SOURCE = 'boltz_public_api';

    /**
     * Normalized pair snapshot.
     *
     * @return array{
     *     min: int, max: int, fee_percentage: float, miner_fees: array<string, int>,
     *     hash: string|null, fetched_at: string
     * }|null null when the API is unavailable and no last-good snapshot exists
     */
    public function getReversePairBtcToLbtc(): ?array
    {
        return $this->getPair('reverse', '/v2/swap/reverse', 'BTC', 'L-BTC');
    }

    /** Chain swap L-BTC -> BTC (treasury sweep direction). Same shape as getReversePairBtcToLbtc(). */
    public function getChainPairLbtcToBtc(): ?array
    {
        return $this->getPair('chain', '/v2/swap/chain', 'L-BTC', 'BTC');
    }

    /**
     * Whether a snapshot is older than the configured staleness threshold.
     */
    public function isStale(array $snapshot): bool
    {
        $fetchedAt = strtotime($snapshot['fetched_at'] ?? '') ?: 0;

        return (time() - $fetchedAt) > (int) config('services.boltz.pairs_stale_after');
    }

    protected function getPair(string $type, string $path, string $from, string $to): ?array
    {
        $freshKey = "boltz:pair:{$type}:{$from}-{$to}:fresh";
        $lastGoodKey = "boltz:pair:{$type}:{$from}-{$to}:last_good";
        $backoffKey = "boltz:pair:{$type}:{$from}-{$to}:backoff";

        $fresh = Cache::get($freshKey);
        if (is_array($fresh)) {
            return $fresh;
        }

        if (! Cache::has($backoffKey)) {
            $snapshot = $this->fetchPair($path, $from, $to);
            if ($snapshot !== null) {
                Cache::put($freshKey, $snapshot, (int) config('services.boltz.pairs_cache_ttl'));
                Cache::put($lastGoodKey, $snapshot, (int) config('services.boltz.pairs_keep_last_good'));

                return $snapshot;
            }

            Cache::put($backoffKey, true, (int) config('services.boltz.failure_backoff'));
        }

        $lastGood = Cache::get($lastGoodKey);

        return is_array($lastGood) ? $lastGood : null;
    }

    protected function fetchPair(string $path, string $from, string $to): ?array
    {
        $base = (string) config('services.boltz.api_url');
        $timeout = (int) config('services.boltz.timeout');

        try {
            $response = Http::timeout($timeout)
                ->connectTimeout(min(5, $timeout))
                ->retry(2, 300, throw: false)
                ->acceptJson()
                ->get($base.$path);

            if (! $response->successful()) {
                Log::warning('Boltz backend API returned non-success', [
                    'path' => $path,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $pair = $response->json("{$from}.{$to}");
            if (! is_array($pair) || ! isset($pair['limits'], $pair['fees'])) {
                Log::warning('Boltz backend API pair missing from response', [
                    'path' => $path,
                    'from' => $from,
                    'to' => $to,
                ]);

                return null;
            }

            $minerFees = $pair['fees']['minerFees'] ?? [];
            // Reverse pairs report flat {claim, lockup}; chain pairs nest user fees under "user".
            if (isset($minerFees['user']) && is_array($minerFees['user'])) {
                $minerFees = array_merge(
                    ['server' => (int) ($minerFees['server'] ?? 0)],
                    array_map('intval', $minerFees['user'])
                );
            } else {
                $minerFees = array_map('intval', array_filter($minerFees, 'is_numeric'));
            }

            return [
                'min' => (int) ($pair['limits']['minimal'] ?? 0),
                'max' => (int) ($pair['limits']['maximal'] ?? 0),
                'fee_percentage' => (float) ($pair['fees']['percentage'] ?? 0),
                'miner_fees' => $minerFees,
                'hash' => isset($pair['hash']) ? (string) $pair['hash'] : null,
                'fetched_at' => now()->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            Log::warning('Boltz backend API fetch failed', [
                'path' => $path,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
