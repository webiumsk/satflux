<?php

namespace App\Enums;

enum BusinessDocumentType: string
{
    case Invoice = 'invoice';
    case Proforma = 'proforma';
    case DeliveryNote = 'delivery_note';
    case OrderReceived = 'order_received';
    case Quote = 'quote';
    case Recurring = 'recurring';
    case CreditNote = 'credit_note';

    public static function mvpEnabled(): array
    {
        return [self::Invoice, self::Proforma, self::Quote, self::CreditNote, self::DeliveryNote, self::OrderReceived];
    }

    public function isMvpEnabled(): bool
    {
        return in_array($this, self::mvpEnabled(), true);
    }
}
