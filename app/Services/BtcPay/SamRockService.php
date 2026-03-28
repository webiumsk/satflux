<?php

namespace App\Services\BtcPay;

class SamRockService
{
    public function __construct(protected BtcPayClient $client)
    {
    }

    protected function withUserKey(?string $userApiKey, callable $fn)
    {
        $originalApiKey = null;
        if ($userApiKey) {
            $originalApiKey = $this->client->getApiKey();
            $this->client->setApiKey($userApiKey);
        }

        try {
            return $fn();
        } finally {
            if ($userApiKey && $originalApiKey) {
                $this->client->setApiKey($originalApiKey);
            }
        }
    }

    /**
     * @param  array{btc?: bool, btcln?: bool, lbtc?: bool, expiresInSeconds?: int}  $payload
     */
    public function createOtp(string $btcpayStoreId, array $payload, string $apiKey): array
    {
        return $this->withUserKey($apiKey, function () use ($btcpayStoreId, $payload) {
            return $this->client->post("/api/v1/stores/{$btcpayStoreId}/samrock/otps", $payload);
        });
    }

    public function getOtpStatus(string $btcpayStoreId, string $otp, string $apiKey): array
    {
        return $this->withUserKey($apiKey, function () use ($btcpayStoreId, $otp) {
            return $this->client->get("/api/v1/stores/{$btcpayStoreId}/samrock/otps/{$otp}");
        });
    }

    public function deleteOtp(string $btcpayStoreId, string $otp, string $apiKey): array
    {
        return $this->withUserKey($apiKey, function () use ($btcpayStoreId, $otp) {
            return $this->client->delete("/api/v1/stores/{$btcpayStoreId}/samrock/otps/{$otp}");
        });
    }

    public function getOtpQr(string $btcpayStoreId, string $otp, string $apiKey, string $accept = 'image/png'): string
    {
        return $this->withUserKey($apiKey, function () use ($btcpayStoreId, $otp, $accept) {
            return $this->client->getBinary(
                "/api/v1/stores/{$btcpayStoreId}/samrock/otps/{$otp}/qr",
                $accept
            );
        });
    }
}
