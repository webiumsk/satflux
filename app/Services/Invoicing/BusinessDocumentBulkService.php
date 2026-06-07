<?php

namespace App\Services\Invoicing;

use App\Enums\BusinessDocumentQuoteStatus;
use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Models\AuditLog;
use App\Models\BusinessDocument;
use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

class BusinessDocumentBulkService
{
    public function __construct(
        protected BusinessDocumentPdfService $pdfService,
        protected BusinessDocumentMarkPaidService $markPaidService,
        protected DocumentSequenceService $sequenceService,
    ) {}

    /**
     * @return Builder<BusinessDocument>
     */
    public function filteredQuery(Company $company, Request $request): Builder
    {
        $type = $request->get('type', 'invoice');
        $filter = $request->get('filter', 'all');
        $status = $request->get('status');

        $query = BusinessDocument::query()
            ->where('company_id', $company->id)
            ->when($status === 'draft', fn ($q) => $q->where('status', BusinessDocumentStatus::Draft))
            ->when($status !== 'draft' && $type, fn ($q) => $q->where('type', $type));

        $query = $this->applyIssueDateFilter($query, $request);

        $query = $this->applyAdvancedFilters($query, $request);

        if ($type === BusinessDocumentType::Quote->value) {
            return $this->applyQuoteFilter($query, $filter);
        }

        return $query
            ->when($filter === 'paid', fn ($q) => $q->where('status', BusinessDocumentStatus::Paid))
            ->when($filter === 'unpaid', fn ($q) => $q->whereIn('status', [
                BusinessDocumentStatus::Draft,
                BusinessDocumentStatus::Issued,
            ]))
            ->when($filter === 'overdue', fn ($q) => $q
                ->where('status', BusinessDocumentStatus::Issued)
                ->whereDate('due_date', '<', now()->toDateString()));
    }

    /**
     * @param  Builder<BusinessDocument>  $query
     * @return Builder<BusinessDocument>
     */
    protected function applyIssueDateFilter(Builder $query, Request $request): Builder
    {
        if ($request->filled('issue_from')) {
            $query->whereDate('issue_date', '>=', $request->get('issue_from'));
        } elseif ($request->filled('year')) {
            $query->whereYear('issue_date', (int) $request->get('year'));
        }

        if ($request->filled('issue_to')) {
            $query->whereDate('issue_date', '<=', $request->get('issue_to'));
        }

        return $query;
    }

    /**
     * @param  Builder<BusinessDocument>  $query
     * @return Builder<BusinessDocument>
     */
    protected function applyAdvancedFilters(Builder $query, Request $request): Builder
    {
        $documentStatus = $request->get('document_status');
        if ($documentStatus && $documentStatus !== 'all') {
            $enum = BusinessDocumentStatus::tryFrom($documentStatus);
            if ($enum) {
                $query->where('status', $enum);
            }
        }

        if ($request->get('paid_filter') === 'yes') {
            $query->where('status', BusinessDocumentStatus::Paid);
        } elseif ($request->get('paid_filter') === 'no') {
            $query->whereIn('status', [
                BusinessDocumentStatus::Draft,
                BusinessDocumentStatus::Issued,
            ]);
        }

        if ($request->get('due_filter') === 'overdue') {
            $query
                ->where('status', BusinessDocumentStatus::Issued)
                ->whereDate('due_date', '<', now()->toDateString());
        }

        if ($request->filled('due_from')) {
            $query->whereDate('due_date', '>=', $request->get('due_from'));
        }

        if ($request->filled('due_to')) {
            $query->whereDate('due_date', '<=', $request->get('due_to'));
        }

        if ($request->filled('amount_min')) {
            $query->where('total', '>=', (float) $request->get('amount_min'));
        }

        if ($request->filled('amount_max')) {
            $query->where('total', '<=', (float) $request->get('amount_max'));
        }

        if ($request->filled('search')) {
            $term = '%'.addcslashes(mb_strtolower((string) $request->get('search')), '%_\\').'%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(number) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(title, \'\')) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(variable_symbol, \'\')) LIKE ?', [$term])
                    ->orWhereHas('contact', fn ($c) => $c->whereRaw('LOWER(name) LIKE ?', [$term]));
            });
        }

        return $query;
    }

    /**
     * @param  Builder<BusinessDocument>  $query
     * @return Builder<BusinessDocument>
     */
    protected function applyQuoteFilter(Builder $query, string $filter): Builder
    {
        $today = now()->toDateString();

        return match ($filter) {
            'approved' => $query
                ->where('status', BusinessDocumentStatus::Issued)
                ->where('quote_status', BusinessDocumentQuoteStatus::Approved),
            'pending' => $query
                ->where('status', BusinessDocumentStatus::Issued)
                ->where('quote_status', BusinessDocumentQuoteStatus::Pending)
                ->where(function ($q) use ($today) {
                    $q->whereNull('due_date')->orWhereDate('due_date', '>=', $today);
                }),
            'rejected' => $query
                ->where('status', BusinessDocumentStatus::Issued)
                ->where('quote_status', BusinessDocumentQuoteStatus::Rejected),
            'expired' => $query
                ->where('status', BusinessDocumentStatus::Issued)
                ->where(function ($q) use ($today) {
                    $q->where('quote_status', BusinessDocumentQuoteStatus::Expired)
                        ->orWhere(function ($inner) use ($today) {
                            $inner->where('quote_status', BusinessDocumentQuoteStatus::Pending)
                                ->whereDate('due_date', '<', $today);
                        });
                }),
            default => $query,
        };
    }

    /**
     * @return Collection<int, BusinessDocument>
     */
    public function resolveDocuments(Company $company, Request $request): Collection
    {
        $query = $this->filteredQuery($company, $request)
            ->with(['contact:id,name', 'lines', 'store', 'company']);

        if ($request->boolean('select_all')) {
            return $query
                ->orderByDesc('issue_date')
                ->orderByDesc('created_at')
                ->get();
        }

        $ids = $request->input('document_ids', []);

        return $query
            ->whereIn('id', $ids)
            ->get();
    }

    /**
     * @return array{processed: int, skipped: int, message?: string}
     */
    public function markPaid(Collection $documents): array
    {
        $processed = 0;
        $skipped = 0;

        foreach ($documents as $document) {
            if ($document->status !== BusinessDocumentStatus::Issued) {
                $skipped++;

                continue;
            }
            $this->markPaidService->markPaid($document, null, null, 'bulk_manual');
            $processed++;
        }

        return ['processed' => $processed, 'skipped' => $skipped];
    }

    /**
     * @return array{processed: int, skipped: int}
     */
    public function deleteDocuments(Collection $documents): array
    {
        $processed = 0;
        $skipped = 0;
        $typesToSync = [];

        foreach ($documents as $document) {
            if (! $document->canDelete()) {
                $skipped++;

                continue;
            }
            $typesToSync[$document->type->value] = $document->company;
            $document->lines()->delete();
            $document->delete();
            $processed++;
        }

        foreach ($typesToSync as $documentType => $company) {
            $this->sequenceService->syncSeriesAfterDocumentChange($company, $documentType);
        }

        return ['processed' => $processed, 'skipped' => $skipped];
    }

    /**
     * @return array{processed: int, skipped: int}
     */
    public function cancelIssued(Collection $documents): array
    {
        $processed = 0;
        $skipped = 0;

        foreach ($documents as $document) {
            if (! $document->canCancel()) {
                $skipped++;

                continue;
            }
            $document->update([
                'status' => BusinessDocumentStatus::Cancelled,
                'paid_at' => null,
                'amount_paid' => null,
            ]);
            $processed++;
        }

        return ['processed' => $processed, 'skipped' => $skipped];
    }

    public function downloadPdfZip(Company $company, Collection $documents): Response
    {
        $issued = $documents->filter(fn ($d) => $d->status !== BusinessDocumentStatus::Draft);
        if ($issued->isEmpty()) {
            throw ValidationException::withMessages([
                'document_ids' => ['No issued invoices selected for PDF export.'],
            ]);
        }

        $zipPath = Storage::disk('local')->path('temp/invoices-'.uniqid().'.zip');
        if (! is_dir(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0755, true);
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create ZIP archive.');
        }

        foreach ($issued as $document) {
            $pdf = $this->pdfService->renderBinary($document);
            $name = 'invoice-'.($document->number ?? $document->id).'.pdf';
            $zip->addFromString($name, $pdf);
        }
        $zip->close();

        AuditLog::log('business_document.bulk_pdf_zip', 'company', $company->id, [
            'count' => $issued->count(),
        ]);

        return response()->download($zipPath, 'invoices.zip')->deleteFileAfterSend(true);
    }

    public function downloadPdfMerged(Company $company, Collection $documents): Response
    {
        $issued = $documents->filter(fn ($d) => $d->status !== BusinessDocumentStatus::Draft);
        if ($issued->isEmpty()) {
            throw ValidationException::withMessages([
                'document_ids' => ['No issued invoices selected for PDF export.'],
            ]);
        }

        $pdf = $this->pdfService->renderMergedBinary($issued);

        AuditLog::log('business_document.bulk_pdf_merge', 'company', $company->id, [
            'count' => $issued->count(),
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="invoices-merged.pdf"',
        ]);
    }

    public function downloadXlsx(Company $company, Collection $documents): Response
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['Number', 'Status', 'Client', 'Total', 'Currency', 'Issue date', 'Due date', 'Variable symbol'],
        ]);

        $row = 2;
        foreach ($documents as $document) {
            $sheet->fromArray([
                [
                    $document->number,
                    $document->status->value,
                    $document->resolvedBuyer()?->name,
                    $document->total,
                    $document->currency,
                    $document->issue_date?->format('Y-m-d'),
                    $document->due_date?->format('Y-m-d'),
                    $document->variable_symbol,
                ],
            ], null, "A{$row}");
            $row++;
        }

        $path = Storage::disk('local')->path('temp/invoices-'.uniqid().'.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        (new Xlsx($spreadsheet))->save($path);

        AuditLog::log('business_document.bulk_xlsx', 'company', $company->id, [
            'count' => $documents->count(),
        ]);

        return response()->download($path, 'invoices.xlsx')->deleteFileAfterSend(true);
    }
}
