<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\BtcPay\Exceptions\BtcPayException;
use App\Services\BtcPay\RaffleService;
use App\Services\Raffle\RafflePayloadService;
use App\Support\PresenterUrlNormalizer;
use App\Support\RaffleBuyerPrivacy;
use App\Support\RaffleLifecycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RaffleController extends Controller
{
    public function __construct(
        protected RaffleService $raffleService,
        protected RafflePayloadService $rafflePayloadService,
        protected PresenterUrlNormalizer $presenterUrlNormalizer,
    ) {}

    public function status(Request $request, Store $store): JsonResponse
    {
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();
        $apiKeyHash = md5($userApiKey);
        $cacheKey = "btcpay:raffle_probe:{$store->id}:{$apiKeyHash}";

        if ($request->boolean('refresh')) {
            Cache::forget($cacheKey);
        }

        try {
            $available = Cache::remember($cacheKey, 900, function () use ($store, $userApiKey) {
                return $this->raffleService->probe($store->btcpay_store_id, $userApiKey);
            });

            return response()->json(['data' => ['available' => (bool) $available]]);
        } catch (BtcPayException $e) {
            return $this->handleBtcPayError($e);
        }
    }

    public function index(Store $store): JsonResponse
    {
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        try {
            $raffles = $this->raffleService->listRaffles($store->btcpay_store_id, $userApiKey);

            return response()->json([
                'data' => array_map(fn (array $r) => $this->enrichRaffle($r), $raffles),
            ]);
        } catch (BtcPayException $e) {
            return $this->handleBtcPayError($e, pluginAware: true);
        }
    }

    public function store(Request $request, Store $store): JsonResponse
    {
        $user = $store->user;
        $maxRaffles = $user->getMaxRafflesPerStore();
        if ($maxRaffles !== null) {
            $count = 0;
            try {
                $userApiKey = $user->getBtcPayApiKeyOrFail();
                $existing = $this->raffleService->listRaffles($store->btcpay_store_id, $userApiKey);
                $count = is_array($existing) ? count($existing) : 0;
            } catch (BtcPayException) {
                return response()->json([
                    'message' => __('messages.raffles_quota_verification_failed'),
                    'code' => 'raffle_quota_verification_failed',
                ], 503);
            }
            if ($count >= $maxRaffles) {
                return response()->json([
                    'message' => __('messages.raffles_limit_free', ['max' => $maxRaffles]),
                ], 403);
            }
        }

        $payload = $this->rafflePayloadService->validate($request);
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        try {
            $raffle = $this->raffleService->createRaffle($store->btcpay_store_id, $payload, $userApiKey);

            return response()->json(['data' => $this->enrichRaffle($raffle)], 201);
        } catch (BtcPayException $e) {
            return $this->handleBtcPayError($e, pluginAware: true);
        }
    }

    public function update(Request $request, Store $store, string $raffleId): JsonResponse
    {
        $payload = $this->rafflePayloadService->validate($request);
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        try {
            $raffle = $this->raffleService->updateRaffle(
                $store->btcpay_store_id,
                $raffleId,
                $payload,
                $userApiKey
            );

            return response()->json(['data' => $this->enrichRaffle($raffle)]);
        } catch (BtcPayException $e) {
            return $this->handleBtcPayError($e);
        }
    }

    public function presenterToken(Store $store, string $raffleId): JsonResponse
    {
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        try {
            $token = $this->raffleService->createPresenterToken(
                $store->btcpay_store_id,
                $raffleId,
                $userApiKey
            );

            return response()->json(['data' => $this->presenterUrlNormalizer->normalize($token, $raffleId)]);
        } catch (BtcPayException $e) {
            return $this->handleBtcPayError($e);
        }
    }

    public function show(Store $store, string $raffleId): JsonResponse
    {
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        try {
            $raffle = $this->raffleService->getRaffle($store->btcpay_store_id, $raffleId, $userApiKey);

            return response()->json(['data' => $this->enrichRaffle($raffle)]);
        } catch (BtcPayException $e) {
            return $this->handleBtcPayError($e, pluginAware: true);
        }
    }

    public function open(Store $store, string $raffleId): JsonResponse
    {
        return $this->lifecycleAction($store, $raffleId, 'open');
    }

    public function close(Store $store, string $raffleId): JsonResponse
    {
        return $this->lifecycleAction($store, $raffleId, 'close');
    }

    public function draw(Store $store, string $raffleId): JsonResponse
    {
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        try {
            $result = $this->raffleService->drawRaffle($store->btcpay_store_id, $raffleId, $userApiKey);
            if (isset($result['winnerEmail'])) {
                $result['winnerEmail'] = RaffleBuyerPrivacy::maskEmail(
                    is_string($result['winnerEmail'] ?? null) ? $result['winnerEmail'] : null
                );
            }

            return response()->json(['data' => $result]);
        } catch (BtcPayException $e) {
            return $this->handleBtcPayError($e);
        }
    }

    public function destroy(Store $store, string $raffleId): JsonResponse
    {
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        try {
            $this->raffleService->deleteRaffle($store->btcpay_store_id, $raffleId, $userApiKey);

            return response()->json(['data' => ['deleted' => true]]);
        } catch (BtcPayException $e) {
            return $this->handleBtcPayError($e);
        }
    }

    public function complete(Store $store, string $raffleId): JsonResponse
    {
        return $this->lifecycleAction($store, $raffleId, 'complete');
    }

    public function addManualTickets(Request $request, Store $store, string $raffleId): JsonResponse
    {
        $validated = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:100'],
            'buyerEmail' => ['required', 'email', 'max:255'],
            'buyerName' => ['nullable', 'string', 'max:100'],
        ]);

        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        try {
            $tickets = $this->raffleService->addManualTickets(
                $store->btcpay_store_id,
                $raffleId,
                [
                    'count' => (int) $validated['count'],
                    'buyerEmail' => $validated['buyerEmail'],
                    'buyerName' => $validated['buyerName'] ?? null,
                ],
                $userApiKey
            );

            return response()->json([
                'data' => RaffleBuyerPrivacy::maskTicketsBuyerEmails($tickets),
            ], 201);
        } catch (BtcPayException $e) {
            return $this->handleBtcPayError($e);
        }
    }

    public function tickets(Store $store, string $raffleId): JsonResponse
    {
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        try {
            $tickets = $this->raffleService->listTickets($store->btcpay_store_id, $raffleId, $userApiKey);

            return response()->json([
                'data' => RaffleBuyerPrivacy::maskTicketsBuyerEmails($tickets),
            ]);
        } catch (BtcPayException $e) {
            return $this->handleBtcPayError($e);
        }
    }

    public function drawings(Store $store, string $raffleId): JsonResponse
    {
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        try {
            $drawings = $this->raffleService->listDrawings($store->btcpay_store_id, $raffleId, $userApiKey);

            return response()->json([
                'data' => RaffleBuyerPrivacy::maskDrawingsWinnerEmails($drawings),
            ]);
        } catch (BtcPayException $e) {
            return $this->handleBtcPayError($e);
        }
    }

    protected function lifecycleAction(Store $store, string $raffleId, string $action): JsonResponse
    {
        $userApiKey = $store->user->getBtcPayApiKeyOrFail();

        try {
            $raffle = match ($action) {
                'open' => $this->raffleService->openRaffle($store->btcpay_store_id, $raffleId, $userApiKey),
                'close' => $this->raffleService->closeRaffle($store->btcpay_store_id, $raffleId, $userApiKey),
                'complete' => $this->raffleService->completeRaffle($store->btcpay_store_id, $raffleId, $userApiKey),
                default => throw new \InvalidArgumentException("Unknown action: {$action}"),
            };

            return response()->json(['data' => $this->enrichRaffle($raffle)]);
        } catch (BtcPayException $e) {
            return $this->handleBtcPayError($e);
        }
    }

    /**
     * @param  array<string, mixed>  $raffle
     * @return array<string, mixed>
     */
    protected function enrichRaffle(array $raffle): array
    {
        $status = (string) ($raffle['status'] ?? '');

        $raffle['allowedActions'] = RaffleLifecycle::allowedActions($status);
        $raffle['showsPublicLink'] = RaffleLifecycle::showsPublicLink($status);
        $raffle['canDelete'] = RaffleLifecycle::canDelete($status);
        $raffle['canAddManualTickets'] = RaffleLifecycle::canAddManualTickets($status);

        return $raffle;
    }

    protected function handleBtcPayError(BtcPayException $e, bool $pluginAware = false): JsonResponse
    {
        $statusCode = $e->getStatusCode() ?: 500;

        if ($pluginAware && $statusCode === 404) {
            return response()->json([
                'message' => __('raffles.plugin_not_installed'),
                'code' => 'raffle_plugin_unavailable',
            ], 404);
        }

        return response()->json([
            'message' => $e->getMessage(),
        ], $statusCode >= 400 && $statusCode < 600 ? $statusCode : 502);
    }
}
