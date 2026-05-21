<?php

namespace App\Services\Raffle;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RafflePayloadService
{
    /**
     * @return array<string, mixed>
     */
    public function validate(Request $request): array
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
}
