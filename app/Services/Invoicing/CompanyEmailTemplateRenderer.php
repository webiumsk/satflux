<?php

namespace App\Services\Invoicing;

use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\User;
use App\Support\Invoicing\CompanyEmailSettings;

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
        $contact = $document->contact;
        $payUrl = app(BusinessDocumentPaymentTokenService::class);
        $payUrl->ensureForDocument($document);
        $onlinePay = $payUrl->payUrl($document) ?? '';

        return [
            '#MOJA_FIRMA#' => $company->displayName(),
            '#MENO#' => $sender?->name ?? $company->issuer_name ?? '',
            '#NAZOV_ODBERATELA#' => $contact?->name ?? '',
            '#NAZOV#' => $document->title ?? '',
            '#CISLO#' => $document->number ?? '',
            '#CISLO_ZAL#' => $document->sourceDocument?->number ?? '',
            '#OBJEDNAVKA#' => '',
            '#DODANIE#' => $document->delivery_date?->format('d.m.Y') ?? '',
            '#PLATI_DO#' => $document->due_date?->format('d.m.Y') ?? '',
            '#POZNAMKA_NAD#' => $document->note_above_lines ?? '',
            '#SUMA#' => number_format((float) $document->total, 2, ',', ' ').' '.($document->currency ?? 'EUR'),
            '#UHRADENA_SUMA#' => number_format((float) ($document->amount_paid ?? 0), 2, ',', ' '),
            '#POSLEDNA_UHRADA#' => '',
            '#SPLATNOST#' => $document->due_date?->format('d.m.Y') ?? '',
            '#FORMA_UHRADY#' => '',
            '#IBAN#' => $company->iban ?? '',
            '#UCET#' => $company->bank_account ?? $company->iban ?? '',
            '#VAR#' => $document->variable_symbol ?? '',
            '#KONSTANTNY#' => $document->constant_symbol ?? '',
            '#SPECIFICKY#' => $document->specific_symbol ?? '',
            '#ONLINE_PLATBA#' => $onlinePay,
            '#QR#' => '',
        ];
    }

    /**
     * @param  array<string, string>  $replacements
     */
    protected function applyTokens(string $text, array $replacements): string
    {
        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }
}
