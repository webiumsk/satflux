<?php

namespace App\Support\BtcPay;

/**
 * Normalize BTCPay webhook type strings (legacy PascalCase and Greenfield dot notation).
 */
final class BtcPayWebhookEventType
{
    public static function normalize(string $eventType): string
    {
        $eventType = trim($eventType);

        $legacy = [
            'invoice.created' => 'InvoiceCreated',
            'invoice.receivedPayment' => 'InvoiceReceivedPayment',
            'invoice.processing' => 'InvoiceProcessing',
            'invoice.expired' => 'InvoiceExpired',
            'invoice.settled' => 'InvoiceSettled',
            'invoice.invalid' => 'InvoiceInvalid',
            'invoice.paymentSettled' => 'InvoicePaymentSettled',
            'invoice.expiredPaidPartial' => 'InvoiceExpiredPaidPartial',
            'invoice.paidAfterExpiration' => 'InvoicePaidAfterExpiration',
            'invoice.paid' => 'InvoiceReceivedPayment',
        ];

        return $legacy[$eventType] ?? $eventType;
    }

    public static function isInvoiceSettled(string $eventType): bool
    {
        return self::normalize($eventType) === 'InvoiceSettled';
    }
}
