<?php

namespace App\Services\Invoicing;

use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Mail\BusinessDocumentEmail;
use App\Models\AuditLog;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Models\User;
use App\Support\PiiRedaction;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class BusinessDocumentEmailService
{
    public function __construct(
        protected CompanyEmailTemplateRenderer $templateRenderer,
        protected BusinessDocumentPdfService $pdfService,
        protected CompanyPdfFilenameBuilder $pdfFilenameBuilder,
        protected CompanyEmailSettingsService $emailSettings,
    ) {}

    /**
     * @return array{subject: string, body: string, body_html: string, to: string|null, template_key: string, attachment_filename: string}
     */
    public function preview(Company $company, BusinessDocument $document, ?User $sender): array
    {
        $this->assertCanEmail($document);
        $document->loadMissing(['contact', 'company']);

        $templateKey = $this->templateKeyFor($document);
        $rendered = $this->templateRenderer->render($company, $templateKey, $document, $sender);

        return [
            'subject' => $rendered['subject'],
            'body' => $rendered['body'],
            'body_html' => $this->bodyToHtml($rendered['body']),
            'to' => $document->resolvedBuyer()?->email,
            'template_key' => $templateKey,
            'attachment_filename' => $this->pdfFilenameBuilder->build($document),
        ];
    }

    /**
     * @return array{subject: string, body: string, body_html: string, to: string|null, template_key: string, attachment_filename: string}
     */
    public function previewEphemeral(Company $company, BusinessDocument $document, ?User $sender): array
    {
        $templateCompany = $document->relationLoaded('company') && $document->company
            ? $document->company
            : $company;
        $templateKey = $this->templateKeyFor($document);
        $rendered = $this->templateRenderer->render($templateCompany, $templateKey, $document, $sender);

        return [
            'subject' => $rendered['subject'],
            'body' => $rendered['body'],
            'body_html' => $this->bodyToHtml($rendered['body']),
            'to' => $document->resolvedBuyer()?->email,
            'template_key' => $templateKey,
            'attachment_filename' => $this->pdfFilenameBuilder->build($document),
        ];
    }

    /**
     * @param  list<string>  $to
     * @param  list<string>  $cc
     * @param  list<string>  $bcc
     * @return array{sent_to: list<string>}
     *
     * @throws TransportExceptionInterface
     */
    public function send(
        Company $company,
        BusinessDocument $document,
        ?User $sender,
        array $to,
        array $cc = [],
        array $bcc = [],
        ?string $subjectOverride = null,
        ?string $bodyOverride = null,
    ): array {
        $this->assertCanEmail($document);
        $document->loadMissing(['contact', 'company', 'lines']);

        $to = $this->normalizeAddresses($to);
        $cc = $this->normalizeAddresses($cc);
        $bcc = $this->normalizeAddresses($bcc);

        if ($to === []) {
            throw ValidationException::withMessages([
                'to' => ['At least one recipient email is required.'],
            ]);
        }

        $templateKey = $this->templateKeyFor($document);
        $rendered = $this->templateRenderer->render($company, $templateKey, $document, $sender);

        $subject = trim($subjectOverride ?? '') !== '' ? trim($subjectOverride) : $rendered['subject'];
        $body = trim($bodyOverride ?? '') !== '' ? trim($bodyOverride) : $rendered['body'];
        $htmlBody = $this->bodyToHtml($body);

        $pdfBinary = $this->pdfService->renderBinary($document);
        $pdfFilename = $this->pdfFilenameBuilder->build($document);

        $mailer = $this->emailSettings->registerCompanyMailer($company);
        [$fromAddress, $fromName] = $this->emailSettings->resolveFromAddress($company);

        $mailable = new BusinessDocumentEmail(
            subjectLine: $subject,
            htmlBody: $htmlBody,
            toAddresses: $to,
            pdfBinary: $pdfBinary,
            pdfFilename: $pdfFilename,
            ccAddresses: $cc,
            bccAddresses: $bcc,
            fromAddress: $fromAddress,
            fromName: $fromName,
        );

        if ($mailer) {
            Mail::mailer($mailer)->send($mailable);
        } else {
            Mail::send($mailable);
        }

        $document->update(['email_sent_at' => now()]);

        AuditLog::log('business_document.email_sent', 'business_document', $document->id, [
            'company_id' => $company->id,
            'number' => $document->number,
            'to_hashes' => PiiRedaction::emailListHashes($to),
            'cc_hashes' => PiiRedaction::emailListHashes($cc),
        ]);

        return [
            'sent_to' => $to,
            'email_sent_at' => $document->fresh()->email_sent_at?->toIso8601String(),
        ];
    }

    /**
     * @param  list<string>  $to
     * @param  list<string>  $cc
     * @param  list<string>  $bcc
     * @return array{sent_to: list<string>}
     *
     * @throws TransportExceptionInterface
     */
    public function sendEphemeral(
        Company $company,
        BusinessDocument $document,
        ?User $sender,
        array $to,
        array $cc = [],
        array $bcc = [],
        ?string $subjectOverride = null,
        ?string $bodyOverride = null,
    ): array {
        $templateCompany = $document->relationLoaded('company') && $document->company
            ? $document->company
            : $company;

        $to = $this->normalizeAddresses($to);
        $cc = $this->normalizeAddresses($cc);
        $bcc = $this->normalizeAddresses($bcc);

        if ($to === []) {
            throw ValidationException::withMessages([
                'to' => ['At least one recipient email is required.'],
            ]);
        }

        $templateKey = $this->templateKeyFor($document);
        $rendered = $this->templateRenderer->render($templateCompany, $templateKey, $document, $sender);

        $subject = trim($subjectOverride ?? '') !== '' ? trim($subjectOverride) : $rendered['subject'];
        $body = trim($bodyOverride ?? '') !== '' ? trim($bodyOverride) : $rendered['body'];
        $htmlBody = $this->bodyToHtml($body);

        $pdfBinary = $this->pdfService->renderBinary($document);
        $pdfFilename = $this->pdfFilenameBuilder->build($document);

        $mailer = $this->emailSettings->registerCompanyMailer($company);
        [$fromAddress, $fromName] = $this->emailSettings->resolveFromAddress($company);

        $mailable = new BusinessDocumentEmail(
            subjectLine: $subject,
            htmlBody: $htmlBody,
            toAddresses: $to,
            pdfBinary: $pdfBinary,
            pdfFilename: $pdfFilename,
            ccAddresses: $cc,
            bccAddresses: $bcc,
            fromAddress: $fromAddress,
            fromName: $fromName,
        );

        if ($mailer) {
            Mail::mailer($mailer)->send($mailable);
        } else {
            Mail::send($mailable);
        }

        AuditLog::log('business_document.ephemeral_email_sent', 'company', $company->id, [
            'document_type' => $document->type?->value,
            'to_hashes' => PiiRedaction::emailListHashes($to),
            'cc_hashes' => PiiRedaction::emailListHashes($cc),
            'line_count' => $document->lines->count(),
        ], $sender?->id);

        return [
            'sent_to' => $to,
            'email_sent_at' => now()->toIso8601String(),
        ];
    }

    public function templateKeyFor(BusinessDocument $document): string
    {
        if ($document->type === BusinessDocumentType::Invoice && $document->source_document_id) {
            $document->loadMissing('sourceDocument');
            if ($document->sourceDocument?->type === BusinessDocumentType::Proforma) {
                return 'invoice_from_proforma';
            }
        }

        return match ($document->type) {
            BusinessDocumentType::CreditNote => 'credit_note',
            BusinessDocumentType::Proforma => 'proforma',
            BusinessDocumentType::DeliveryNote => 'delivery_note',
            BusinessDocumentType::OrderReceived => 'order_received',
            BusinessDocumentType::Quote => 'quote',
            default => 'invoice',
        };
    }

    protected function assertCanEmail(BusinessDocument $document): void
    {
        if ($document->status === BusinessDocumentStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => ['Issue the document before sending by email.'],
            ]);
        }

        if ($document->status === BusinessDocumentStatus::Cancelled) {
            throw ValidationException::withMessages([
                'status' => ['Cancelled documents cannot be emailed.'],
            ]);
        }
    }

    protected function bodyToHtml(string $body): string
    {
        return nl2br(e($body), false);
    }

    /**
     * @param  list<string>  $addresses
     * @return list<string>
     */
    protected function normalizeAddresses(array $addresses): array
    {
        $out = [];
        foreach ($addresses as $addr) {
            $addr = strtolower(trim($addr));
            if ($addr !== '' && filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                $out[] = $addr;
            }
        }

        return array_values(array_unique($out));
    }
}
