<?php

namespace App\Services\Tickets;

use App\Models\Store;
use App\Services\BtcPay\Exceptions\BtcPayException;
use App\Services\BtcPay\RaffleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EventBundleService
{
    public function __construct(
        protected RaffleService $raffleService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function validationRules(): array
    {
        return [
            'bundledRaffleId' => ['sometimes', 'nullable', 'uuid'],
            'bundledRaffleTicketsPerAdmission' => ['sometimes', 'integer', 'min:0', 'max:20'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function validateForStore(Request $request, Store $store): ?JsonResponse
    {
        if (! $request->hasAny(['bundledRaffleId', 'bundledRaffleTicketsPerAdmission'])) {
            return null;
        }

        $ticketsPerAdmission = (int) $request->input('bundledRaffleTicketsPerAdmission', 0);
        $raffleId = $request->input('bundledRaffleId');
        $hasRaffleId = is_string($raffleId) && $raffleId !== '';

        if ($ticketsPerAdmission > 0 && ! $hasRaffleId) {
            throw ValidationException::withMessages([
                'bundledRaffleId' => [__('messages.tickets_bundled_raffle_required')],
            ]);
        }

        if ($hasRaffleId && $ticketsPerAdmission < 1) {
            throw ValidationException::withMessages([
                'bundledRaffleTicketsPerAdmission' => [__('messages.tickets_bundled_tickets_required')],
            ]);
        }

        if ($ticketsPerAdmission === 0 && ! $hasRaffleId) {
            return null;
        }

        try {
            $userApiKey = $store->user->getBtcPayApiKeyOrFail();
            $raffles = $this->raffleService->listRaffles($store->btcpay_store_id, $userApiKey);
        } catch (BtcPayException) {
            return response()->json([
                'message' => __('messages.tickets_bundled_raffle_verify_failed'),
                'code' => 'bundled_raffle_verification_failed',
            ], 503);
        }

        $match = null;
        foreach ($raffles as $raffle) {
            if (($raffle['id'] ?? '') === $raffleId) {
                $match = $raffle;
                break;
            }
        }

        if ($match === null) {
            throw ValidationException::withMessages([
                'bundledRaffleId' => [__('messages.tickets_bundled_raffle_invalid')],
            ]);
        }

        if (strcasecmp((string) ($match['status'] ?? ''), 'Open') !== 0) {
            throw ValidationException::withMessages([
                'bundledRaffleId' => [__('messages.tickets_bundled_raffle_not_open')],
            ]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function filterPayload(array $payload): array
    {
        return array_filter(
            $payload,
            fn ($v, $k) => $k === 'bundledRaffleId' || ($v !== null && ($k !== 'eventLogoUrl' || (string) $v !== '')),
            ARRAY_FILTER_USE_BOTH
        );
    }
}
