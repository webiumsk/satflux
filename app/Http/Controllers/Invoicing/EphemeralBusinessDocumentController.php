<?php

namespace App\Http\Controllers\Invoicing;

use App\Contracts\Invoicing\ComplianceSubmissionGateway;
use App\Enums\BusinessDocumentStatus;
use App\Enums\CompanyJurisdiction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invoicing\EphemeralBusinessDocumentBtcpayRequest;
use App\Http\Requests\Invoicing\EphemeralBusinessDocumentBulkRequest;
use App\Http\Requests\Invoicing\EphemeralBusinessDocumentEfakturaRequest;
use App\Http\Requests\Invoicing\EphemeralBusinessDocumentEmailRequest;
use App\Http\Requests\Invoicing\EphemeralBusinessDocumentPdfRequest;
use App\Models\AuditLog;
use App\Models\BusinessDocument;
use App\Models\BusinessDocumentLine;
use App\Models\Company;
use App\Models\Store;
use App\Models\User;
use App\Services\Invoicing\BusinessDocumentBtcPayService;
use App\Services\Invoicing\BusinessDocumentEmailService;
use App\Services\Invoicing\BusinessDocumentIsdocService;
use App\Services\Invoicing\BusinessDocumentPdfService;
use App\Services\Invoicing\BusinessDocumentUblService;
use App\Services\Invoicing\CompanyPdfFilenameBuilder;
use App\Services\Invoicing\DocumentTotalsCalculator;
use App\Services\Invoicing\Efaktura\EphemeralEfakturaSubmissionService;
use App\Services\Invoicing\EphemeralBtcpayCheckoutService;
use App\Support\Invoicing\BuyerSnapshot;
use App\Support\Invoicing\CompanyEfakturaEligibility;
use App\Support\Invoicing\CompanyEfakturaSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

class EphemeralBusinessDocumentController extends Controller
{
    public function __construct(
        protected DocumentTotalsCalculator $totalsCalculator,
        protected BusinessDocumentPdfService $pdfService,
        protected BusinessDocumentIsdocService $isdocService,
        protected BusinessDocumentUblService $ublService,
        protected BusinessDocumentBtcPayService $btcPayService,
        protected EphemeralBtcpayCheckoutService $ephemeralBtcpayCheckoutService,
        protected CompanyPdfFilenameBuilder $pdfFilenameBuilder,
        protected BusinessDocumentEmailService $emailService,
        protected EphemeralEfakturaSubmissionService $efakturaService,
    ) {}

    public function pdf(EphemeralBusinessDocumentPdfRequest $request, Company $company): Response
    {
        $snapshotCompany = $this->buildSnapshotCompany($company, (array) ($request->validated()['company'] ?? []));

        return $this->respondWithPdf($request, $snapshotCompany, $company);
    }

    public function pdfWithoutCompany(EphemeralBusinessDocumentPdfRequest $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $snapshotCompany = $this->resolveEphemeralCompany($user, (array) ($request->validated()['company'] ?? []));

        return $this->respondWithPdf($request, $snapshotCompany);
    }

    public function emailPreview(EphemeralBusinessDocumentPdfRequest $request, Company $company): JsonResponse
    {
        $snapshotCompany = $this->buildSnapshotCompany($company, (array) ($request->validated()['company'] ?? []));

        return $this->respondWithEmailPreview($request, $snapshotCompany, $company);
    }

    public function emailPreviewWithoutCompany(EphemeralBusinessDocumentPdfRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $snapshotCompany = $this->resolveEphemeralCompany($user, (array) ($request->validated()['company'] ?? []));

        return $this->respondWithEmailPreview($request, $snapshotCompany);
    }

    public function sendEmail(EphemeralBusinessDocumentEmailRequest $request, Company $company): JsonResponse
    {
        $snapshotCompany = $this->buildSnapshotCompany($company, (array) ($request->validated()['company'] ?? []));

        return $this->respondWithSendEmail($request, $snapshotCompany, $company);
    }

    public function sendEmailWithoutCompany(EphemeralBusinessDocumentEmailRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $snapshotCompany = $this->resolveEphemeralCompany($user, (array) ($request->validated()['company'] ?? []));

        return $this->respondWithSendEmail($request, $snapshotCompany);
    }

    public function isdoc(EphemeralBusinessDocumentPdfRequest $request, Company $company): Response
    {
        $snapshotCompany = $this->buildSnapshotCompany($company, (array) ($request->validated()['company'] ?? []));

        return $this->respondWithIsdoc($request, $snapshotCompany, $company);
    }

    public function isdocWithoutCompany(EphemeralBusinessDocumentPdfRequest $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $snapshotCompany = $this->resolveEphemeralCompany($user, (array) ($request->validated()['company'] ?? []));

        return $this->respondWithIsdoc($request, $snapshotCompany);
    }

    public function ubl(EphemeralBusinessDocumentPdfRequest $request, Company $company): Response
    {
        $snapshotCompany = $this->buildSnapshotCompany($company, (array) ($request->validated()['company'] ?? []));

        return $this->respondWithUbl($request, $snapshotCompany, $company);
    }

    public function ublWithoutCompany(EphemeralBusinessDocumentPdfRequest $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $snapshotCompany = $this->resolveEphemeralCompany($user, (array) ($request->validated()['company'] ?? []));

        return $this->respondWithUbl($request, $snapshotCompany);
    }

    public function btcpayCheckout(EphemeralBusinessDocumentBtcpayRequest $request, Company $company): JsonResponse
    {
        $snapshotCompany = $this->buildSnapshotCompany($company, (array) ($request->validated()['company'] ?? []));

        return $this->respondWithBtcpayCheckout($request, $snapshotCompany, $company);
    }

    public function btcpayCheckoutWithoutCompany(EphemeralBusinessDocumentBtcpayRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $snapshotCompany = $this->resolveEphemeralCompany($user, (array) ($request->validated()['company'] ?? []));

        return $this->respondWithBtcpayCheckout($request, $snapshotCompany);
    }

    public function efakturaBridge(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $bridgeCompany = $this->efakturaService->resolveBridgeCompany($user);

        return response()->json([
            'data' => [
                'configured' => $bridgeCompany !== null && config('efaktura.enabled'),
                'bridge_company_id' => $bridgeCompany?->id,
                'bridge_company_name' => $bridgeCompany?->legal_name ?: $bridgeCompany?->trade_name,
            ],
        ]);
    }

    public function efakturaStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $validated = $request->validate([
            'evolu_document_id' => ['required', 'string', 'max:64'],
        ]);

        $row = $this->efakturaService->latestForDocument($user, $validated['evolu_document_id']);

        return response()->json([
            'data' => $row ? [$row->toApiRow()] : [],
        ]);
    }

    public function efakturaSend(EphemeralBusinessDocumentEfakturaRequest $request, Company $company): JsonResponse
    {
        $this->assertEfakturaBridgeCompany($company);
        $snapshotCompany = $this->buildSnapshotCompany($company, (array) ($request->validated()['company'] ?? []));

        return $this->respondWithEfakturaSend($request, $snapshotCompany, $company, $company);
    }

    public function efakturaSendWithoutCompany(EphemeralBusinessDocumentEfakturaRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $snapshotCompany = $this->resolveEphemeralCompany(
            $user,
            (array) ($request->validated()['company'] ?? []),
        );

        $auditCompany = Company::query()
            ->where('user_id', $user->id)
            ->orderBy('created_at')
            ->first();

        if (! $auditCompany) {
            throw ValidationException::withMessages([
                'efaktura' => ['E-faktura bridge requires at least one company on your satflux account for authorization.'],
            ]);
        }

        return $this->respondWithEfakturaSend($request, $snapshotCompany, $auditCompany);
    }

    public function efakturaRefresh(Request $request, Company $company): JsonResponse
    {
        $this->assertEfakturaBridgeCompany($company);

        return $this->respondWithEfakturaRefresh($request);
    }

    public function efakturaRefreshWithoutCompany(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $this->efakturaService->assertBridgeCompany($user);

        return $this->respondWithEfakturaRefresh($request);
    }

    public function bulkPdfZipWithoutCompany(EphemeralBusinessDocumentBulkRequest $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $snapshotCompany = $this->resolveEphemeralCompany($user, (array) ($request->validated()['company'] ?? []));

        return $this->respondWithBulkPdfZip($request, $snapshotCompany);
    }

    public function bulkPdfZip(EphemeralBusinessDocumentBulkRequest $request, Company $company): Response
    {
        $snapshotCompany = $this->buildSnapshotCompany($company, (array) ($request->validated()['company'] ?? []));

        return $this->respondWithBulkPdfZip($request, $snapshotCompany, $company);
    }

    public function bulkPdfMergeWithoutCompany(EphemeralBusinessDocumentBulkRequest $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $snapshotCompany = $this->resolveEphemeralCompany($user, (array) ($request->validated()['company'] ?? []));

        return $this->respondWithBulkPdfMerge($request, $snapshotCompany);
    }

    public function bulkPdfMerge(EphemeralBusinessDocumentBulkRequest $request, Company $company): Response
    {
        $snapshotCompany = $this->buildSnapshotCompany($company, (array) ($request->validated()['company'] ?? []));

        return $this->respondWithBulkPdfMerge($request, $snapshotCompany, $company);
    }

    public function btcpayStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $validated = $request->validate([
            'evolu_document_id' => ['required', 'string', 'max:64'],
            'btcpay_invoice_id' => ['required', 'string', 'max:128'],
        ]);

        $checkout = $this->ephemeralBtcpayCheckoutService->findForUser(
            $user,
            $validated['evolu_document_id'],
            $validated['btcpay_invoice_id'],
        );

        if (! $checkout) {
            return response()->json(['message' => 'Checkout not found.'], 404);
        }

        return response()->json([
            'data' => $this->ephemeralBtcpayCheckoutService->statusPayload($checkout),
        ]);
    }

    protected function respondWithPdf(
        EphemeralBusinessDocumentPdfRequest $request,
        Company $snapshotCompany,
        ?Company $auditCompany = null,
    ): Response {
        $document = $this->buildEphemeralDocument($snapshotCompany, $request->validated());
        $filename = $this->pdfFilenameBuilder->build($document);
        [$auditType, $auditId] = $this->auditTarget($request->user(), $auditCompany, $snapshotCompany);

        AuditLog::log('business_document.ephemeral_pdf_downloaded', $auditType, $auditId, [
            'document_type' => $document->type?->value,
            'line_count' => $document->lines->count(),
            'company_less' => $auditCompany === null,
        ], $request->user()?->id);

        return response($this->pdfService->renderBinary($document), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    protected function respondWithEmailPreview(
        EphemeralBusinessDocumentPdfRequest $request,
        Company $snapshotCompany,
        ?Company $auditCompany = null,
    ): JsonResponse {
        $document = $this->buildEphemeralDocument($snapshotCompany, $request->validated());

        return response()->json([
            'data' => $this->emailService->previewEphemeral($snapshotCompany, $document, $request->user()),
        ]);
    }

    protected function respondWithSendEmail(
        EphemeralBusinessDocumentEmailRequest $request,
        Company $snapshotCompany,
        ?Company $auditCompany = null,
    ): JsonResponse {
        $document = $this->buildEphemeralDocument($snapshotCompany, $request->validated());

        try {
            $result = $this->emailService->sendEphemeral(
                $snapshotCompany,
                $document,
                $request->user(),
                $request->input('to', []),
                $request->input('cc', []),
                $request->input('bcc', []),
                $request->input('subject'),
                $request->input('body'),
            );
        } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e) {
            return response()->json(['message' => 'Email could not be sent: '.$e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Email sent.',
            'data' => $result,
        ]);
    }

    protected function respondWithIsdoc(
        EphemeralBusinessDocumentPdfRequest $request,
        Company $snapshotCompany,
        ?Company $auditCompany = null,
    ): Response {
        $document = $this->buildEphemeralDocument($snapshotCompany, $request->validated());
        $this->assertStructuredExportAllowed($document);

        $filename = ($document->number ?: 'document').'.isdoc';
        [$auditType, $auditId] = $this->auditTarget($request->user(), $auditCompany, $snapshotCompany);

        AuditLog::log('business_document.ephemeral_isdoc_downloaded', $auditType, $auditId, [
            'document_type' => $document->type?->value,
            'line_count' => $document->lines->count(),
            'company_less' => $auditCompany === null,
        ], $request->user()?->id);

        return response($this->isdocService->xml($document, auditDownload: false), 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    protected function respondWithUbl(
        EphemeralBusinessDocumentPdfRequest $request,
        Company $snapshotCompany,
        ?Company $auditCompany = null,
    ): Response {
        $document = $this->buildEphemeralDocument($snapshotCompany, $request->validated());
        $this->assertStructuredExportAllowed($document);

        $filename = ($document->number ?: 'document').'.xml';
        [$auditType, $auditId] = $this->auditTarget($request->user(), $auditCompany, $snapshotCompany);

        AuditLog::log('business_document.ephemeral_ubl_downloaded', $auditType, $auditId, [
            'document_type' => $document->type?->value,
            'line_count' => $document->lines->count(),
            'company_less' => $auditCompany === null,
        ], $request->user()?->id);

        return response($this->ublService->xml($document, auditDownload: false), 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    protected function respondWithBtcpayCheckout(
        EphemeralBusinessDocumentBtcpayRequest $request,
        Company $snapshotCompany,
        ?Company $auditCompany = null,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $store = Store::query()
            ->where('id', $request->validated('store_id'))
            ->where('user_id', $user->id)
            ->with('user')
            ->firstOrFail();

        $document = $this->buildEphemeralDocument($snapshotCompany, $request->validated());
        $document->payment_btc_enabled = (bool) ($request->input('document.payment_btc_enabled', true));
        $document->store_id = $store->id;

        if ($document->status === BusinessDocumentStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => ['Issue the document before creating a Bitcoin checkout.'],
            ]);
        }

        $result = $this->btcPayService->createEphemeralCheckout(
            $document,
            $store,
            $request->input('evolu_document_id'),
        );

        $evoluDocumentId = $request->input('evolu_document_id');
        if (
            is_string($evoluDocumentId)
            && $evoluDocumentId !== ''
            && is_string($result['btcpay_invoice_id'] ?? null)
            && $result['btcpay_invoice_id'] !== ''
        ) {
            $this->ephemeralBtcpayCheckoutService->registerCheckout(
                $user,
                $store,
                $evoluDocumentId,
                $result['btcpay_invoice_id'],
                (float) $document->total,
                $document->currency,
            );
        }

        [$auditType, $auditId] = $this->auditTarget($user, $auditCompany, $snapshotCompany);

        AuditLog::log('business_document.ephemeral_btcpay_checkout', $auditType, $auditId, [
            'document_type' => $document->type?->value,
            'store_id' => $store->id,
            'btcpay_invoice_id' => $result['btcpay_invoice_id'],
            'company_less' => $auditCompany === null,
        ], $user->id);

        return response()->json(['data' => $result]);
    }

    protected function respondWithBulkPdfZip(
        EphemeralBusinessDocumentBulkRequest $request,
        Company $snapshotCompany,
        ?Company $auditCompany = null,
    ): Response {
        $documents = $this->buildEphemeralDocumentsFromBulk($snapshotCompany, $request->validated());
        $issued = $documents->filter(fn (BusinessDocument $document) => $document->status !== BusinessDocumentStatus::Draft);

        if ($issued->isEmpty()) {
            throw ValidationException::withMessages([
                'documents' => ['No issued invoices selected for PDF export.'],
            ]);
        }

        $zipPath = sys_get_temp_dir().'/invoices-'.uniqid('', true).'.zip';

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create ZIP archive.');
        }

        foreach ($issued as $document) {
            $pdf = $this->pdfService->renderBinary($document);
            $name = 'invoice-'.($document->number ?: $document->id).'.pdf';
            $zip->addFromString($name, $pdf);
        }
        $zip->close();

        [$auditType, $auditId] = $this->auditTarget($request->user(), $auditCompany, $snapshotCompany);

        AuditLog::log('business_document.ephemeral_bulk_pdf_zip', $auditType, $auditId, [
            'count' => $issued->count(),
            'company_less' => $auditCompany === null,
        ], $request->user()?->id);

        $binary = file_get_contents($zipPath) ?: '';
        @unlink($zipPath);

        return response($binary, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="invoices.zip"',
        ]);
    }

    protected function respondWithBulkPdfMerge(
        EphemeralBusinessDocumentBulkRequest $request,
        Company $snapshotCompany,
        ?Company $auditCompany = null,
    ): Response {
        $documents = $this->buildEphemeralDocumentsFromBulk($snapshotCompany, $request->validated());
        $issued = $documents->filter(fn (BusinessDocument $document) => $document->status !== BusinessDocumentStatus::Draft);

        if ($issued->isEmpty()) {
            throw ValidationException::withMessages([
                'documents' => ['No issued invoices selected for PDF export.'],
            ]);
        }

        $pdf = $this->pdfService->renderMergedBinary($issued);

        [$auditType, $auditId] = $this->auditTarget($request->user(), $auditCompany, $snapshotCompany);

        AuditLog::log('business_document.ephemeral_bulk_pdf_merge', $auditType, $auditId, [
            'count' => $issued->count(),
            'company_less' => $auditCompany === null,
        ], $request->user()?->id);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="invoices-merged.pdf"',
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return Collection<int, BusinessDocument>
     */
    protected function buildEphemeralDocumentsFromBulk(Company $snapshotCompany, array $validated): Collection
    {
        /** @var array<int, array<string, mixed>> $items */
        $items = array_values((array) ($validated['documents'] ?? []));

        return collect($items)->map(function (array $item) use ($snapshotCompany, $validated) {
            return $this->buildEphemeralDocument($snapshotCompany, [
                'company' => $validated['company'] ?? [],
                'contact' => $item['contact'] ?? null,
                'document' => $item['document'] ?? [],
                'lines' => $item['lines'] ?? [],
            ]);
        });
    }

    protected function respondWithEfakturaSend(
        EphemeralBusinessDocumentEfakturaRequest $request,
        Company $snapshotCompany,
        Company $bridgeCompany,
        ?Company $auditCompany = null,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $document = $this->buildEphemeralDocument($snapshotCompany, $request->validated());
        $evoluDocumentId = (string) $request->validated('evolu_document_id');

        $this->assertEphemeralEfakturaDocument($document, $bridgeCompany);

        $result = $this->efakturaService->submit($user, $bridgeCompany, $document, $evoluDocumentId);
        $row = $this->efakturaService->latestForDocument($user, $evoluDocumentId);

        return response()->json([
            'data' => array_merge($row?->toApiRow() ?? [], [
                'status' => $result->status->value,
                'external_id' => $result->externalId,
                'message' => $result->message,
                'response_payload' => $result->responsePayload,
            ]),
        ]);
    }

    protected function respondWithEfakturaRefresh(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $validated = $request->validate([
            'evolu_document_id' => ['required', 'string', 'max:64'],
        ]);

        $row = $this->efakturaService->refresh($user, $validated['evolu_document_id']);

        return response()->json([
            'data' => $row ? [$row->toApiRow()] : [],
        ]);
    }

    protected function assertEfakturaBridgeCompany(Company $company): void
    {
        if (! config('efaktura.enabled')) {
            throw ValidationException::withMessages([
                'efaktura' => ['E-faktura integration is disabled globally.'],
            ]);
        }

        if (! app(CompanyEfakturaEligibility::class)->supportsCompany($company)) {
            throw ValidationException::withMessages([
                'efaktura' => ['E-faktura is available only for Slovak companies registered as full VAT payers.'],
            ]);
        }

        if (! CompanyEfakturaSettings::fromCompany($company)->configured()) {
            throw ValidationException::withMessages([
                'efaktura' => ['E-faktura credentials are not configured for this company.'],
            ]);
        }
    }

    protected function assertEphemeralEfakturaDocument(BusinessDocument $document, Company $bridgeCompany): void
    {
        if ($document->status !== BusinessDocumentStatus::Issued) {
            throw ValidationException::withMessages([
                'status' => ['Issue the document before sending e-faktura.'],
            ]);
        }

        $snapshotCompany = $document->company;
        $credentialsCompany = $bridgeCompany->replicate();
        $credentialsCompany->exists = false;
        $credentialsCompany->id = $bridgeCompany->id;

        $snapshotSettings = CompanyEfakturaSettings::fromCompany($snapshotCompany);
        if ($snapshotSettings->configured()) {
            $credentialsCompany->app_settings = is_array($snapshotCompany->app_settings)
                ? $snapshotCompany->app_settings
                : [];
            $credentialsCompany->jurisdiction = $snapshotCompany->jurisdiction ?? $credentialsCompany->jurisdiction;
            $credentialsCompany->vat_payer = $snapshotCompany->vat_payer ?? $credentialsCompany->vat_payer;
            $credentialsCompany->vat_status = $snapshotCompany->vat_status ?? $credentialsCompany->vat_status;
        }

        $this->assertEfakturaBridgeCompany($credentialsCompany);

        $document->setRelation('company', $credentialsCompany);

        if (! app(ComplianceSubmissionGateway::class)->supports($document)) {
            throw ValidationException::withMessages([
                'document' => ['Document is not eligible for e-faktura (issued SK B2B invoice or credit note required).'],
            ]);
        }
    }

    protected function assertStructuredExportAllowed(BusinessDocument $document): void
    {
        if ($document->status === BusinessDocumentStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => ['Issue the document before downloading structured export.'],
            ]);
        }

        if (! $this->isdocService->supports($document)) {
            throw ValidationException::withMessages([
                'document' => ['Structured export is not available for this document.'],
            ]);
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function auditTarget(?User $user, ?Company $auditCompany, Company $snapshotCompany): array
    {
        if ($auditCompany !== null) {
            return ['company', $auditCompany->id];
        }

        if ($snapshotCompany->exists) {
            return ['company', $snapshotCompany->id];
        }

        return ['user', (string) ($user?->id ?? '0')];
    }

    /**
     * @param  array<string, mixed>  $companyPayload
     */
    protected function resolveEphemeralCompany(User $user, array $companyPayload): Company
    {
        $template = Company::query()
            ->where('user_id', $user->id)
            ->orderBy('created_at')
            ->first();

        if ($template) {
            return $this->buildSnapshotCompany($template, $companyPayload);
        }

        return $this->buildPayloadOnlyCompany($user, $companyPayload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function buildPayloadOnlyCompany(User $user, array $payload): Company
    {
        $company = new Company([
            'user_id' => $user->id,
            'jurisdiction' => CompanyJurisdiction::EuSk,
            'default_currency' => 'EUR',
            'vat_payer' => false,
        ]);
        $company->exists = false;
        $company->id = (string) Str::uuid();

        $company->forceFill($this->companyPayloadAttributes($payload));

        if (array_key_exists('logo_url', $payload)) {
            $company->setAttribute('ephemeral_logo_url', $payload['logo_url'] ?: null);
        }
        if (array_key_exists('signature_stamp_url', $payload)) {
            $company->setAttribute('ephemeral_signature_stamp_url', $payload['signature_stamp_url'] ?: null);
        }

        if (! $company->jurisdiction) {
            $company->jurisdiction = CompanyJurisdiction::EuSk;
        }

        if (! $company->default_currency) {
            $company->default_currency = 'EUR';
        }

        $this->mergeSnapshotAppSettings($company, $payload);

        return $company;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function buildEphemeralDocument(Company $company, array $payload): BusinessDocument
    {
        /** @var array<string, mixed> $documentPayload */
        $documentPayload = (array) ($payload['document'] ?? []);
        /** @var array<string, mixed>|null $contactPayload */
        $contactPayload = isset($payload['contact']) && is_array($payload['contact']) ? $payload['contact'] : null;
        /** @var array<int, array<string, mixed>> $linesPayload */
        $linesPayload = array_values((array) ($payload['lines'] ?? []));

        $document = new BusinessDocument([
            'company_id' => $company->id,
            'type' => $documentPayload['type'] ?? null,
            'status' => $documentPayload['status'] ?? BusinessDocumentStatus::Issued->value,
            'title' => $documentPayload['title'] ?? null,
            'number' => $documentPayload['number'] ?? null,
            'variable_symbol' => $documentPayload['variable_symbol'] ?? null,
            'constant_symbol' => $documentPayload['constant_symbol'] ?? null,
            'specific_symbol' => $documentPayload['specific_symbol'] ?? null,
            'issue_date' => $documentPayload['issue_date'] ?? null,
            'delivery_date' => $documentPayload['delivery_date'] ?? null,
            'due_date' => $documentPayload['due_date'] ?? null,
            'currency' => $documentPayload['currency'] ?? $company->default_currency,
            'note_above_lines' => $documentPayload['note_above_lines'] ?? null,
            'note_footer' => $documentPayload['note_footer'] ?? $company->legal_footer_note,
            'internal_note' => $documentPayload['internal_note'] ?? null,
            'pdf_locale' => $documentPayload['pdf_locale'] ?? null,
            'pdf_show_signature' => (bool) ($documentPayload['pdf_show_signature'] ?? true),
            'pdf_show_payment_info' => (bool) ($documentPayload['pdf_show_payment_info'] ?? true),
            // Explicitly disabled to keep snapshot rendering side-effect free.
            'payment_btc_enabled' => false,
            'payment_bank_enabled' => (bool) ($documentPayload['payment_bank_enabled'] ?? true),
            'amount_paid' => (float) ($documentPayload['amount_paid'] ?? 0),
            'buyer_snapshot' => $contactPayload,
        ]);

        $document->exists = false;
        $document->id = (string) Str::uuid();
        $document->setRelation('company', $company);

        if ($contactPayload !== null) {
            $document->setRelation('contact', BuyerSnapshot::asContact($contactPayload));
        } else {
            $document->setRelation('contact', null);
        }

        $lineModels = collect($linesPayload)->values()->map(function (array $line, int $index) {
            return new BusinessDocumentLine([
                'sort_order' => $index,
                'name' => (string) ($line['name'] ?? ''),
                'description' => $line['description'] ?? null,
                'quantity' => (float) ($line['quantity'] ?? 1),
                'unit' => $line['unit'] ?? 'ks.',
                'unit_price' => (float) ($line['unit_price'] ?? 0),
                'line_discount_percent' => (float) ($line['line_discount_percent'] ?? 0),
                'tax_rate' => isset($line['tax_rate']) ? (float) $line['tax_rate'] : 0,
                'line_total' => 0,
            ]);
        });
        $document->setRelation('lines', $lineModels);

        $this->totalsCalculator->applyToDocument(
            $document,
            $linesPayload,
            (float) ($documentPayload['discount_percent'] ?? 0)
        );

        return $document;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function buildSnapshotCompany(Company $company, array $payload): Company
    {
        $snapshot = $company->replicate();
        $snapshot->exists = false;
        $snapshot->id = $company->id;

        $snapshot->forceFill($this->companyPayloadAttributes($payload));

        if (array_key_exists('logo_url', $payload)) {
            $snapshot->setAttribute('ephemeral_logo_url', $payload['logo_url'] ?: null);
        }
        if (array_key_exists('signature_stamp_url', $payload)) {
            $snapshot->setAttribute('ephemeral_signature_stamp_url', $payload['signature_stamp_url'] ?: null);
        }

        $this->mergeSnapshotAppSettings($snapshot, $payload);

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function mergeSnapshotAppSettings(Company $company, array $payload): void
    {
        if (! isset($payload['app_settings']) || ! is_array($payload['app_settings'])) {
            return;
        }

        $current = is_array($company->app_settings) ? $company->app_settings : [];
        $company->app_settings = array_merge($current, $payload['app_settings']);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function companyPayloadAttributes(array $payload): array
    {
        return Arr::only($payload, [
            'legal_name',
            'trade_name',
            'registration_number',
            'tax_id',
            'vat_number',
            'street',
            'city',
            'postal_code',
            'country',
            'state_region',
            'iban',
            'bic',
            'bank_name',
            'bank_account',
            'bank_code',
            'default_currency',
            'jurisdiction',
            'vat_payer',
            'vat_rate_default',
            'legal_footer_note',
            'issuer_name',
            'issuer_phone',
            'issuer_email',
            'website',
        ]);
    }
}
