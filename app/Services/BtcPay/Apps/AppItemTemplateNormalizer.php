<?php

namespace App\Services\BtcPay\Apps;

class AppItemTemplateNormalizer
{
    /**
     * BTCPay Greenfield AppItem uses AppItemPriceType: Fixed, Topup, Minimum only (no "Free").
     * Satflux UI offers "Free" - map to Fixed with price 0 before sending JSON to BTCPay.
     *
     * @param  mixed  $node  Decoded template or perks array (may be nested)
     * @return mixed
     */
    public static function normalizePriceTypes($node)
    {
        if (! is_array($node)) {
            return $node;
        }
        if (isset($node['priceType'])) {
            $pt = $node['priceType'];
            if ($pt === 'Free' || $pt === 'free') {
                $node['priceType'] = 'Fixed';
                if (! array_key_exists('price', $node) || $node['price'] === null || $node['price'] === '') {
                    $node['price'] = '0';
                }
            }
        }
        foreach ($node as $key => $value) {
            $node[$key] = self::normalizePriceTypes($value);
        }

        return $node;
    }
}
