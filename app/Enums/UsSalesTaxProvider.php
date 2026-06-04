<?php

namespace App\Enums;

enum UsSalesTaxProvider: string
{
    case Manual = 'manual';
    case StripeTax = 'stripe_tax';
    case Avalara = 'avalara';
}
