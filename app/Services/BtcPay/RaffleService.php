<?php

namespace App\Services\BtcPay;

use App\Services\BtcPay\Exceptions\BtcPayException;

class RaffleService
{
    public function __construct(protected BtcPayClient $client) {}

    public function probe(string $storeId, string $apiKey): bool
    {
        try {
            $this->withUserKey($apiKey, function () use ($storeId) {
                $this->client->get("/api/v1/stores/{$storeId}/raffle");
            });

            return true;
        } catch (BtcPayException $e) {
            if ($e->getStatusCode() === 404) {
                return false;
            }

            throw $e;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRaffles(string $storeId, string $apiKey): array
    {
        $result = $this->withUserKey($apiKey, function () use ($storeId) {
            return $this->client->get("/api/v1/stores/{$storeId}/raffle");
        });

        return $this->normalizeList($result);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createRaffle(string $storeId, array $payload, string $apiKey): array
    {
        return $this->withUserKey($apiKey, function () use ($storeId, $payload) {
            return $this->client->post("/api/v1/stores/{$storeId}/raffle", $payload);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateRaffle(string $storeId, string $raffleId, array $payload, string $apiKey): array
    {
        return $this->withUserKey($apiKey, function () use ($storeId, $raffleId, $payload) {
            return $this->client->put("/api/v1/stores/{$storeId}/raffle/{$raffleId}", $payload);
        });
    }

    public function deleteRaffle(string $storeId, string $raffleId, string $apiKey): void
    {
        $this->withUserKey($apiKey, function () use ($storeId, $raffleId) {
            $this->client->delete("/api/v1/stores/{$storeId}/raffle/{$raffleId}");
        });
    }

    /**
     * @param  array{count: int, buyerEmail: string, buyerName?: string|null}  $payload
     * @return list<array<string, mixed>>
     */
    public function addManualTickets(string $storeId, string $raffleId, array $payload, string $apiKey): array
    {
        $result = $this->withUserKey($apiKey, function () use ($storeId, $raffleId, $payload) {
            return $this->client->post(
                "/api/v1/stores/{$storeId}/raffle/{$raffleId}/tickets/manual",
                $payload
            );
        });

        return $this->normalizeList($result);
    }

    /**
     * @return array{token: string, expiresAt: string, presenterUrl: string}
     */
    public function createPresenterToken(string $storeId, string $raffleId, string $apiKey): array
    {
        return $this->withUserKey($apiKey, function () use ($storeId, $raffleId) {
            return $this->client->post(
                "/api/v1/stores/{$storeId}/raffle/{$raffleId}/presenter-token",
                []
            );
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function getRaffle(string $storeId, string $raffleId, string $apiKey): array
    {
        return $this->withUserKey($apiKey, function () use ($storeId, $raffleId) {
            return $this->client->get("/api/v1/stores/{$storeId}/raffle/{$raffleId}");
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function openRaffle(string $storeId, string $raffleId, string $apiKey): array
    {
        return $this->withUserKey($apiKey, function () use ($storeId, $raffleId) {
            return $this->client->post("/api/v1/stores/{$storeId}/raffle/{$raffleId}/open", []);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function closeRaffle(string $storeId, string $raffleId, string $apiKey): array
    {
        return $this->withUserKey($apiKey, function () use ($storeId, $raffleId) {
            return $this->client->post("/api/v1/stores/{$storeId}/raffle/{$raffleId}/close", []);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function drawRaffle(string $storeId, string $raffleId, string $apiKey): array
    {
        return $this->withUserKey($apiKey, function () use ($storeId, $raffleId) {
            return $this->client->post("/api/v1/stores/{$storeId}/raffle/{$raffleId}/draw", []);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function completeRaffle(string $storeId, string $raffleId, string $apiKey): array
    {
        return $this->withUserKey($apiKey, function () use ($storeId, $raffleId) {
            return $this->client->post("/api/v1/stores/{$storeId}/raffle/{$raffleId}/complete", []);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listTickets(string $storeId, string $raffleId, string $apiKey): array
    {
        $result = $this->withUserKey($apiKey, function () use ($storeId, $raffleId) {
            return $this->client->get("/api/v1/stores/{$storeId}/raffle/{$raffleId}/tickets");
        });

        return $this->normalizeList($result);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listDrawings(string $storeId, string $raffleId, string $apiKey): array
    {
        $result = $this->withUserKey($apiKey, function () use ($storeId, $raffleId) {
            return $this->client->get("/api/v1/stores/{$storeId}/raffle/{$raffleId}/drawings");
        });

        return $this->normalizeList($result);
    }

    protected function withUserKey(string $userApiKey, callable $fn): mixed
    {
        $originalApiKey = $this->client->getApiKey();
        $this->client->setApiKey($userApiKey);

        try {
            return $fn();
        } finally {
            $this->client->setApiKey($originalApiKey);
        }
    }

    /**
     * @param  array<string, mixed>|list<array<string, mixed>>  $result
     * @return list<array<string, mixed>>
     */
    protected function normalizeList(array $result): array
    {
        if ($result === []) {
            return [];
        }

        if (array_is_list($result)) {
            return $result;
        }

        if (isset($result['data']) && is_array($result['data'])) {
            return array_is_list($result['data']) ? $result['data'] : [$result['data']];
        }

        return [$result];
    }
}
