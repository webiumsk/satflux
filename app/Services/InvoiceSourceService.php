<?php

namespace App\Services;

/**
 * Detect payment method / source of a BTCPay invoice from metadata.
 * Used for filtering stats by: pos, pay_button, ln_address, tickets, api, other.
 */
class InvoiceSourceService
{
    public const SOURCE_POS = 'pos';
    public const SOURCE_PAY_BUTTON = 'pay_button';
    public const SOURCE_LN_ADDRESS = 'ln_address';
    public const SOURCE_TICKETS = 'tickets';
    public const SOURCE_API = 'api';
    public const SOURCE_OTHER = 'other';

    public const SOURCES = [
        self::SOURCE_POS,
        self::SOURCE_PAY_BUTTON,
        self::SOURCE_LN_ADDRESS,
        self::SOURCE_TICKETS,
        self::SOURCE_API,
        self::SOURCE_OTHER,
    ];

    /**
     * Detect invoice source from BTCPay invoice array.
     */
    public function detectSource(array $invoice): string
    {
        $metadata = $invoice['metadata'] ?? [];
        if (! is_array($metadata)) {
            return self::SOURCE_OTHER;
        }

        // Explicit source (e.g. set by our apps)
        $explicit = $metadata['source'] ?? $metadata['invoiceSource'] ?? null;
        if (is_string($explicit)) {
            $v = strtolower(trim($explicit));
            if (in_array($v, self::SOURCES, true)) {
                return $v;
            }
            if (in_array($v, ['lightning', 'ln', 'lightning_address'], true)) {
                return self::SOURCE_LN_ADDRESS;
            }
        }

        // Tickets: event/ticket identifiers
        if (isset($metadata['eventId']) || isset($metadata['event_id']) || isset($metadata['ticketEventId'])) {
            return self::SOURCE_TICKETS;
        }
        $posData = $this->parsePosData($metadata);
        if (isset($posData['eventId']) || isset($posData['ticketId']) || isset($posData['event_id'])) {
            return self::SOURCE_TICKETS;
        }

        // PoS: posData with cart / tax / tip (or Eshop)
        if (isset($posData['pos']) && $posData['pos'] !== '') {
            return self::SOURCE_POS;
        }

        // LN Address: common metadata from LN address flows
        if (isset($metadata['lightningAddress']) || isset($metadata['ln_address'])) {
            return self::SOURCE_LN_ADDRESS;
        }

        // Pay Button: often has top-level itemCode/itemDesc, or simple posData with itemCode only
        if (isset($invoice['itemCode']) || isset($metadata['itemCode']) || isset($metadata['itemDesc'])) {
            return self::SOURCE_PAY_BUTTON;
        }
        if (! empty($posData) && isset($posData['itemCode']) && ! isset($posData['cart'])) {
            return self::SOURCE_PAY_BUTTON;
        }

        return self::SOURCE_OTHER;
    }

    /**
     * Parse posData from metadata (PoS vs Eshop vs simple).
     */
    public function parsePosData(array $metadata): array
    {
        $posData = $metadata['posData'] ?? $metadata['pos'] ?? $metadata['posId'] ?? null;
        if ($posData === null || $posData === '') {
            return [];
        }
        $data = is_string($posData) ? json_decode($posData, true) : $posData;
        if (! is_array($data)) {
            return [];
        }
        $out = [];
        if (isset($data['tax']) || isset($data['tip']) || array_key_exists('cart', $data)) {
            $out['pos'] = 'PoS';
        } elseif (isset($data['WooCommerce']) || isset($data['Magento']) || isset($data['Shopify']) || isset($data['PrestaShop']) || isset($data['OpenCart'])) {
            $out['pos'] = 'Eshop';
        }
        foreach (['eventId', 'event_id', 'ticketId', 'itemCode', 'cart'] as $k) {
            if (array_key_exists($k, $data)) {
                $out[$k] = $data[$k];
            }
        }
        return $out;
    }

    /**
     * All source keys for filters (including 'all').
     */
    public static function sourceOptions(): array
    {
        return array_merge(['all' => 'all'], array_combine(self::SOURCES, self::SOURCES));
    }
}
