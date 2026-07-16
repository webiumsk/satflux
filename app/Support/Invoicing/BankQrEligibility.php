<?php

namespace App\Support\Invoicing;

use App\Models\BusinessDocument;
use App\Models\Company;

/**
 * Gates shared by every bank-payment QR standard. Which standard fits the
 * PAYER is BankQrGenerator's job - this only says whether any bank QR may
 * appear at all.
 */
final class BankQrEligibility
{
    public static function passes(Company $company, BusinessDocument $document): bool
    {
        if (empty($company->iban)) {
            return false;
        }

        if (! $document->payment_bank_enabled) {
            return false;
        }

        $settings = CompanyAppSettings::from($company->app_settings);
        if (! $settings->bool('show_pay_by_square')) {
            return false;
        }

        return (float) $document->total > 0;
    }
}
