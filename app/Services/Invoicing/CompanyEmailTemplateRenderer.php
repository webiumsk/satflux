<?php

namespace App\Services\Invoicing;

use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\User;
use App\Support\Invoicing\CompanyEmailSettings;
use App\Support\Invoicing\PlaceholderLegacyAliases;

class CompanyEmailTemplateRenderer
{
    public function render(
        Company $company,
        string $templateKey,
        BusinessDocument $document,
        ?User $sender = null,
    ): array {
        $settings = CompanyEmailSettings::from($company->email_settings);
        $templates = $settings->get('templates');
        $tpl = is_array($templates[$templateKey] ?? null)
            ? $templates[$templateKey]
            : (CompanyEmailSettings::defaultTemplates()[$templateKey] ?? ['subject' => '', 'body' => '']);

        $document->loadMissing('contact', 'company', 'sourceDocument');
        $replacements = $this->replacements($company, $document, $sender);

        return [
            'subject' => $this->applyTokens((string) ($tpl['subject'] ?? ''), $replacements),
            'body' => $this->applyTokens((string) ($tpl['body'] ?? ''), $replacements),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function replacements(Company $company, BusinessDocument $document, ?User $sender): array
    {
        $contact = $document->resolvedBuyer();
        $payUrl = app(BusinessDocumentPaymentTokenService::class);
        $payUrl->ensureForDocument($document);
        $onlinePay = $payUrl->payUrl($document) ?? '';

        return PlaceholderLegacyAliases::merge([
            '#MY_COMPANY#' => $company->displayName(),
            '#SENDER_NAME#' => $sender?->name ?? $company->issuer_name ?? '',
            '#CLIENT_NAME#' => $contact?->name ?? '',
            '#TITLE#' => $document->title ?? '',
            '#NUMBER#' => $document->number ?? '',
            '#PROFORMA_NUMBER#' => $document->sourceDocument?->number ?? '',
            '#ORDER_NUMBER#' => '',
            '#DELIVERY_DATE#' => $document->delivery_date?->format('d.m.Y') ?? '',
            '#VALID_UNTIL#' => $document->due_date?->format('d.m.Y') ?? '',
            '#NOTE_ABOVE#' => $document->note_above_lines ?? '',
            '#AMOUNT#' => number_format((float) $document->total, 2, ',', ' ').' '.($document->currency ?? 'EUR'),
            '#PAID_AMOUNT#' => number_format((float) ($document->amount_paid ?? 0), 2, ',', ' '),
            '#LAST_PAYMENT#' => '',
            '#DUE_DATE#' => $document->due_date?->format('d.m.Y') ?? '',
            '#PAYMENT_METHOD#' => '',
            '#IBAN#' => $company->iban ?? '',
            '#ACCOUNT#' => $company->bank_account ?? $company->iban ?? '',
            '#VARIABLE_SYMBOL#' => $document->variable_symbol ?? '',
            '#CONSTANT_SYMBOL#' => $document->constant_symbol ?? '',
            '#SPECIFIC_SYMBOL#' => $document->specific_symbol ?? '',
            '#ONLINE_PAYMENT#' => $onlinePay,
            '#QR#' => '',
        ]);
    }

    /**
     * @param  array<string, string>  $replacements
     */
    protected function applyTokens(string $text, array $replacements): string
    {
        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }
}
