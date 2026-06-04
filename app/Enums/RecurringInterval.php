<?php

namespace App\Enums;

enum RecurringInterval: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function labelKey(): string
    {
        return match ($this) {
            self::Monthly => 'invoicing.recurring_interval_monthly',
            self::Yearly => 'invoicing.recurring_interval_yearly',
        };
    }
}
