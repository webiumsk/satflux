<?php

namespace App\Services\Invoicing;

use App\Enums\BusinessDocumentType;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Support\Invoicing\CompanyAppSettings;
use Illuminate\Support\Str;

class CompanyPdfFilenameBuilder
{
    public function build(BusinessDocument $document): string
    {
        $document->loadMissing('company', 'contact');
        $company = $document->company;
        $settings = CompanyAppSettings::from($company->app_settings);
        $pattern = (string) $settings->get('pdf_filename_pattern', CompanyAppSettings::DEFAULTS['pdf_filename_pattern']);

        $name = $this->replaceTokens($pattern, $document, $company);
        $name = preg_replace('/[^A-Za-z0-9._\-#]+/', '_', $name) ?? 'invoice';
        $name = trim($name, '._-');
        if ($name === '') {
            $name = 'invoice';
        }

        if (! str_ends_with(strtolower($name), '.pdf')) {
            $name .= '.pdf';
        }

        return $name;
    }

    protected function replaceTokens(string $pattern, BusinessDocument $document, Company $company): string
    {
        $replacements = [
            '#NAZOV#' => $this->sanitizeSegment($document->title ?: $this->typeLabel($document->type)),
            '#TYP#' => $this->typeCode($document->type),
            '#FIRMA#' => $this->sanitizeSegment($company->displayName(), 50),
            '#CISLO#' => $this->sanitizeSegment($document->number ?? $document->id),
            '#KLIENT#' => $this->sanitizeSegment($document->resolvedBuyer()?->name ?? '', 50),
            '#VYSTAVENE#' => $document->issue_date?->format('Y-m-d') ?? '',
            '#SUMA#' => number_format((float) $document->total, 2, '.', ''),
            '#MENA#' => $document->currency ?? $company->default_currency ?? 'EUR',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $pattern);
    }

    protected function typeCode(BusinessDocumentType $type): string
    {
        return match ($type) {
            BusinessDocumentType::Invoice => 'fa',
            default => Str::slug($type->value, '_'),
        };
    }

    protected function typeLabel(BusinessDocumentType $type): string
    {
        return match ($type) {
            BusinessDocumentType::Invoice => 'invoice',
            default => $type->value,
        };
    }

    protected function sanitizeSegment(string $value, ?int $maxLen = null): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
        if ($maxLen !== null && mb_strlen($value) > $maxLen) {
            $value = mb_substr($value, 0, $maxLen);
        }

        return $value;
    }
}
