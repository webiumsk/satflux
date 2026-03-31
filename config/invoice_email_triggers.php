<?php

/**
 * Invoice-only webhook / email rule triggers (BTCPay WebhooksPlugin / Greenfield naming).
 */
return [
    'InvoiceCreated',
    'InvoiceReceivedPayment',
    'InvoiceProcessing',
    'InvoiceExpired',
    'InvoiceSettled',
    'InvoiceInvalid',
    'InvoicePaymentSettled',
    'InvoiceExpiredPaidPartial',
    'InvoicePaidAfterExpiration',
];
