<?php

namespace App\Services\Boltz;

use App\Models\Store;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Availability of Satflux's Boltz-based merchant receiving integration.
 * Mobile-wallet functionality is not evidence that this integration works.
 */
class BoltzAvailabilityService
{
    public const CODE = 'boltz_receiving_unavailable';

    public function receivingEnabled(): bool
    {
        return (bool) config('services.boltz.receiving_enabled', false);
    }

    public function isAffected(Store $store): bool
    {
        return ! $this->receivingEnabled() && $store->wallet_type === 'aqua_boltz';
    }

    /** @return array{active: bool, code: string, new_connections_available: bool} */
    public function payload(Store $store): array
    {
        return [
            'active' => $this->isAffected($store),
            'code' => self::CODE,
            'new_connections_available' => $this->receivingEnabled(),
        ];
    }

    /** Reject new activation without modifying saved wallets or recovery state. */
    public function assertNewConnectionsAvailable(): void
    {
        if (! $this->receivingEnabled()) {
            throw new HttpResponseException(response()->json([
                'code' => self::CODE,
                'message' => 'Lightning receiving through the Boltz integration is unavailable. Choose another supported wallet connection. Existing wallets and payment history are preserved.',
            ], 503));
        }
    }

    public function assertConnectionTypeAvailable(string $type): void
    {
        if (in_array($type, ['aqua_descriptor', 'aqua_boltz'], true)) {
            $this->assertNewConnectionsAvailable();
        }
    }

    /** Cover direct Lightning configuration calls as well as descriptor forms. */
    public function assertConnectionStringAvailable(string $connectionString): void
    {
        if (str_starts_with(strtolower(ltrim($connectionString)), 'ct(')
            || preg_match('/(?:^|;)\s*type\s*=\s*boltz\s*(?:;|$)/i', $connectionString)) {
            $this->assertNewConnectionsAvailable();
        }
    }
}
