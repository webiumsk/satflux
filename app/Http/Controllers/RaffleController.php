<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\BtcPay\Exceptions\BtcPayException;
use App\Services\BtcPay\RaffleService;
use App\Support\RaffleBuyerPrivacy;
use App\Support\RaffleLifecycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Cache;

class RaffleController extends Controller
{
    public function __construct(protected RaffleService $raffleService) {}

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
        $payload = $this->validatedRafflePayload($request);
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
        $payload = $this->validatedRafflePayload($request);
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

            return response()->json(['data' => $this->normalizePresenterUrl($token, $raffleId)]);
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
    /**
     * @return array<string, mixed>
     */
    protected function validatedRafflePayload(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'ticketCurrency' => ['nullable', 'string', 'max:16', 'regex:/^[A-Za-z]{3,16}$/'],
            'ticketPrice' => ['nullable', 'numeric', 'gt:0'],
            'ticketPriceSats' => ['nullable', 'integer', 'min:1'],
            'maxTickets' => ['nullable', 'integer', 'min:1'],
        ]);

        $base = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'maxTickets' => array_key_exists('maxTickets', $validated) ? $validated['maxTickets'] : null,
        ];

        $hasCurrencyPrice = ! empty($validated['ticketCurrency']) && isset($validated['ticketPrice']);
        $hasLegacySats = isset($validated['ticketPriceSats']);

        if (! $hasCurrencyPrice && ! $hasLegacySats) {
            throw ValidationException::withMessages([
                'ticketPrice' => [__('raffles.validation_pricing_required')],
            ]);
        }

        if ($hasCurrencyPrice) {
            $currency = strtoupper(trim((string) $validated['ticketCurrency']));
            $price = (float) $validated['ticketPrice'];

            if ($currency === 'SATS' && $price != (int) $price) {
                throw ValidationException::withMessages([
                    'ticketPrice' => [__('raffles.validation_sats_whole_number')],
                ]);
            }

            if ($currency === 'SATS') {
                return array_merge($base, ['ticketPriceSats' => (int) $price]);
            }

            return array_merge($base, [
                'ticketCurrency' => $currency,
                'ticketPrice' => $price,
            ]);
        }

        return array_merge($base, ['ticketPriceSats' => (int) $validated['ticketPriceSats']]);
    }

    /**
     * BTCPay may return a relative URL or one with an internal request host (Docker) when
     * presenter-token is called server-to-server. Always expose the public BTCPay base URL.
     *
     * @param  array<string, mixed>  $tokenPayload
     * @return array<string, mixed>
     */
    protected function normalizePresenterUrl(array $tokenPayload, string $raffleId): array
    {
        $token = (string) ($tokenPayload['token'] ?? '');
        $publicBase = rtrim((string) config('services.btcpay.public_url', ''), '/');

        if ($publicBase === '' || $token === '') {
            return $tokenPayload;
        }

        $path = '/raffle/'.$raffleId.'/present';
        $presenterUrl = (string) ($tokenPayload['presenterUrl'] ?? '');
        $publicOrigin = $this->normalizeHttpOrigin($publicBase);

        $needsRebuild = true;
        if ($presenterUrl !== '' && str_starts_with($presenterUrl, 'http')) {
            $parsed = parse_url($presenterUrl);
            $pathOk = isset($parsed['path']) && str_contains((string) $parsed['path'], $path);
            $presenterOrigin = $this->normalizeHttpOrigin($presenterUrl);
            $originOk = $publicOrigin !== null && $presenterOrigin !== null
                && strcasecmp($publicOrigin, $presenterOrigin) === 0;
            $needsRebuild = ! ($pathOk && $originOk);
        }

        if ($needsRebuild) {
            $tokenPayload['presenterUrl'] = $publicBase.$path.'?token='.rawurlencode($token);
        }

        return $tokenPayload;
    }

    protected function normalizeHttpOrigin(string $url): ?string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        if (($scheme === 'http' && (int) $port === 80) || ($scheme === 'https' && (int) $port === 443)) {
            return "{$scheme}://{$host}";
        }

        return "{$scheme}://{$host}:{$port}";
    }

    protected function enrichRaffle(array $raffle): array
    {
        $status = (string) ($raffle['status'] ?? '');

        $raffle['allowedActions'] = RaffleLifecycle::allowedActions($status);
        $raffle['showsPublicLink'] = RaffleLifecycle::showsPublicLink($status);
        $raffle['canDelete'] = RaffleLifecycle::canDelete($status);

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
