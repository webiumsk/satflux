<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\BtcPay\Exceptions\BtcPayException;
use App\Services\BtcPay\SamRockService;
use App\Services\WalletConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SamRockController extends Controller
{
    public function __construct(
        protected SamRockService $samRockService,
        protected WalletConnectionService $walletConnectionService
    ) {
    }

    public function createOtp(Request $request, Store $store): \Illuminate\Http\JsonResponse
    {
        $this->ensureAquaBoltzStore($store);

        $validated = $request->validate([
            'btc' => ['sometimes', 'boolean'],
            'btcln' => ['sometimes', 'boolean'],
            'lbtc' => ['sometimes', 'boolean'],
            'expires_in_seconds' => ['sometimes', 'integer', 'min:60', 'max:3600'],
        ]);

        $payload = [
            'btc' => (bool) ($validated['btc'] ?? true),
            'btcln' => (bool) ($validated['btcln'] ?? true),
            'lbtc' => (bool) ($validated['lbtc'] ?? false),
            'expiresInSeconds' => (int) ($validated['expires_in_seconds'] ?? 300),
        ];

        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        try {
            $raw = $this->samRockService->createOtp($store->btcpay_store_id, $payload, $userApiKey);
        } catch (BtcPayException $e) {
            $status = $e->getStatusCode();

            return response()->json([
                'message' => $e->getMessage(),
            ], $status >= 400 && $status < 600 ? $status : 502);
        }

        return response()->json([
            'data' => [
                'otp' => $raw['otp'] ?? null,
                'expires_at' => $raw['expiresAt'] ?? null,
                'setup_url' => $raw['setupUrl'] ?? null,
            ],
        ], 201);
    }

    public function getOtpStatus(Store $store, string $otp): \Illuminate\Http\JsonResponse
    {
        $this->ensureAquaBoltzStore($store);

        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        try {
            $raw = $this->samRockService->getOtpStatus($store->btcpay_store_id, $otp, $userApiKey);
        } catch (BtcPayException $e) {
            $status = $e->getStatusCode();

            return response()->json([
                'message' => $e->getMessage(),
            ], $status >= 400 && $status < 600 ? $status : 502);
        }

        return response()->json([
            'data' => [
                'otp' => $raw['otp'] ?? $otp,
                'expires_at' => $raw['expiresAt'] ?? null,
                'setup_url' => $raw['setupUrl'] ?? null,
                'status' => $raw['status'] ?? null,
                'error_message' => $raw['errorMessage'] ?? null,
            ],
        ]);
    }

    public function getOtpQr(Request $request, Store $store, string $otp): Response|JsonResponse
    {
        $this->ensureAquaBoltzStore($store);

        $format = $request->query('format', 'png');
        $accept = $format === 'svg' ? 'image/svg+xml' : 'image/png';

        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        try {
            $body = $this->samRockService->getOtpQr($store->btcpay_store_id, $otp, $userApiKey, $accept);
        } catch (BtcPayException $e) {
            $status = $e->getStatusCode();

            return response()->json([
                'message' => $e->getMessage(),
            ], $status >= 400 && $status < 600 ? $status : 502);
        }

        return response($body, 200, [
            'Content-Type' => $accept,
            'Cache-Control' => 'no-store',
        ]);
    }

    public function deleteOtp(Store $store, string $otp): \Illuminate\Http\JsonResponse
    {
        $this->ensureAquaBoltzStore($store);

        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        try {
            $this->samRockService->deleteOtp($store->btcpay_store_id, $otp, $userApiKey);
        } catch (BtcPayException $e) {
            $status = $e->getStatusCode();

            return response()->json([
                'message' => $e->getMessage(),
            ], $status >= 400 && $status < 600 ? $status : 502);
        }

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function complete(Request $request, Store $store): \Illuminate\Http\JsonResponse
    {
        $this->ensureAquaBoltzStore($store);

        $validated = $request->validate([
            'otp' => ['required', 'string'],
        ]);

        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        try {
            $raw = $this->samRockService->getOtpStatus($store->btcpay_store_id, $validated['otp'], $userApiKey);
        } catch (BtcPayException $e) {
            $status = $e->getStatusCode();

            return response()->json([
                'message' => $e->getMessage(),
            ], $status >= 400 && $status < 600 ? $status : 502);
        }

        $otpStatus = $raw['status'] ?? '';
        if ($otpStatus !== 'success') {
            return response()->json([
                'message' => 'SamRock pairing is not complete yet.',
                'data' => [
                    'status' => $otpStatus,
                    'error_message' => $raw['errorMessage'] ?? null,
                ],
            ], 422);
        }

        $connection = $this->walletConnectionService->markSamRockConnected($store, $request->user());

        try {
            $this->samRockService->deleteOtp($store->btcpay_store_id, $validated['otp'], $userApiKey);
        } catch (BtcPayException) {
            // Best-effort cleanup
        }

        return response()->json([
            'data' => [
                'wallet_connection_id' => $connection->id,
                'status' => $connection->status,
                'configuration_source' => $connection->configuration_source,
            ],
        ]);
    }

    protected function ensureAquaBoltzStore(Store $store): void
    {
        if (($store->wallet_type ?? null) !== 'aqua_boltz') {
            abort(404, 'SamRock pairing is only available for Aqua + Boltz stores.');
        }
    }
}
