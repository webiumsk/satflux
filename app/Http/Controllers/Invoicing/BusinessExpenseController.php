<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoicing\BulkBusinessExpenseRequest;
use App\Http\Requests\Invoicing\StoreBusinessExpenseRequest;
use App\Models\AuditLog;
use App\Models\BusinessExpense;
use App\Models\BusinessExpenseAttachment;
use App\Models\Company;
use App\Services\Invoicing\BusinessExpenseBulkService;
use App\Services\Invoicing\BusinessExpenseIsdocImportService;
use App\Services\Invoicing\BusinessExpenseIsdocPackService;
use App\Services\Invoicing\BusinessExpenseIsdocQuotaService;
use App\Services\Invoicing\BusinessExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class BusinessExpenseController extends Controller
{
    public function __construct(
        protected BusinessExpenseService $expenseService,
        protected BusinessExpenseBulkService $bulkService,
        protected BusinessExpenseIsdocImportService $isdocImportService,
        protected BusinessExpenseIsdocQuotaService $isdocQuotaService,
        protected BusinessExpenseIsdocPackService $isdocPackService,
    ) {}

    public function index(Request $request, Company $company): JsonResponse
    {
        $expenses = $this->expenseService->filteredQuery($company, $request)
            ->orderByDesc('issue_date')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 25));

        return response()->json($expenses);
    }

    public function bulk(BulkBusinessExpenseRequest $request, Company $company): JsonResponse|Response
    {
        $expenses = $this->bulkService->resolveExpenses($company, $request);

        if ($expenses->isEmpty()) {
            throw ValidationException::withMessages([
                'expense_ids' => ['No expenses matched the selection.'],
            ]);
        }

        $action = $request->input('action');

        AuditLog::log('business_expense.bulk_action', 'company', $company->id, [
            'action' => $action,
            'count' => $expenses->count(),
        ]);

        return match ($action) {
            'mark_paid' => response()->json([
                'data' => $this->bulkService->markPaid($expenses),
            ]),
            'cancel' => response()->json([
                'data' => $this->bulkService->cancel($expenses),
            ]),
            'export_xlsx' => $this->bulkService->downloadXlsx($company, $expenses),
            'attachments_zip' => $this->bulkService->downloadAttachmentsZip($company, $expenses),
            default => abort(400),
        };
    }

    public function store(StoreBusinessExpenseRequest $request, Company $company): JsonResponse
    {
        $expense = $this->expenseService->create(
            $company,
            $request->validated(),
            $request->boolean('mark_paid'),
        );

        AuditLog::log('business_expense.created', 'business_expense', $expense->id, [
            'company_id' => $company->id,
            'internal_number' => $expense->internal_number,
        ]);

        return response()->json(['data' => $expense], 201);
    }

    public function isdocExtractQuota(Request $request, Company $company): JsonResponse
    {
        return response()->json([
            'data' => $this->isdocQuotaService->snapshot($request->user()),
        ]);
    }

    public function purchaseIsdocPack(Request $request, Company $company): JsonResponse
    {
        $request->validate([
            'credits' => ['required', 'integer'],
        ]);

        $checkout = $this->isdocPackService->startPurchase(
            $request->user(),
            (int) $request->input('credits'),
        );

        return response()->json(['data' => $checkout]);
    }

    public function detectIsdoc(Request $request, Company $company): JsonResponse
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'max:10240',
                'extensions:pdf,isdoc,xml,jpg,jpeg,png,webp',
                'mimetypes:application/pdf,application/xml,text/xml,image/jpeg,image/png,image/webp,application/octet-stream',
            ],
        ]);

        $file = $request->file('file');

        return response()->json([
            'data' => [
                'has_isdoc' => $this->isdocImportService->hasIsdocInUpload($file),
                'quota' => $this->isdocQuotaService->snapshot($request->user()),
                'filename' => $file->getClientOriginalName(),
            ],
        ]);
    }

    public function extract(Request $request, Company $company): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'extensions:pdf,isdoc,xml'],
        ]);

        $user = $request->user();
        $this->isdocQuotaService->assertCanExtract($user);

        $draft = $this->isdocImportService->extractFromUpload($request->file('file'));

        $this->isdocQuotaService->recordExtraction($user, null, $company->id);

        return response()->json([
            'data' => $draft,
            'quota' => $this->isdocQuotaService->snapshot($user),
        ]);
    }

    public function importFromDocument(Request $request, Company $company): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'extensions:pdf,isdoc,xml'],
            'mark_paid' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $this->isdocQuotaService->assertCanExtract($user);

        $draft = $this->isdocImportService->extractFromUpload($request->file('file'));

        $expense = $this->expenseService->create(
            $company,
            $draft,
            $request->boolean('mark_paid'),
        );

        $expense = $this->expenseService->storeAttachment($expense, $request->file('file'));

        $this->isdocQuotaService->recordExtraction($user, $expense->id, $company->id);

        AuditLog::log('business_expense.imported', 'business_expense', $expense->id, [
            'company_id' => $company->id,
            'source' => 'isdoc',
            'internal_number' => $expense->internal_number,
        ]);

        $expense->load('attachments');

        return response()->json([
            'data' => $expense,
            'quota' => $this->isdocQuotaService->snapshot($user),
        ], 201);
    }

    public function show(Company $company, BusinessExpense $businessExpense): JsonResponse
    {
        $this->assertExpenseCompany($businessExpense, $company);
        $businessExpense->load('attachments');

        return response()->json(['data' => $businessExpense]);
    }

    public function update(StoreBusinessExpenseRequest $request, Company $company, BusinessExpense $businessExpense): JsonResponse
    {
        $this->assertExpenseCompany($businessExpense, $company);

        $expense = $this->expenseService->update($businessExpense, $request->validated());

        AuditLog::log('business_expense.updated', 'business_expense', $expense->id, [
            'company_id' => $company->id,
        ]);

        return response()->json(['data' => $expense]);
    }

    public function duplicate(Company $company, BusinessExpense $businessExpense): JsonResponse
    {
        $this->assertExpenseCompany($businessExpense, $company);

        $copy = $this->expenseService->duplicate($company, $businessExpense);

        AuditLog::log('business_expense.duplicated', 'business_expense', $copy->id, [
            'company_id' => $company->id,
            'source_id' => $businessExpense->id,
        ]);

        return response()->json(['data' => $copy], 201);
    }

    public function markPaid(Company $company, BusinessExpense $businessExpense): JsonResponse
    {
        $this->assertExpenseCompany($businessExpense, $company);

        $expense = $this->expenseService->markPaid($businessExpense);

        AuditLog::log('business_expense.marked_paid', 'business_expense', $expense->id, [
            'company_id' => $company->id,
        ]);

        return response()->json(['data' => $expense]);
    }

    public function unmarkPaid(Company $company, BusinessExpense $businessExpense): JsonResponse
    {
        $this->assertExpenseCompany($businessExpense, $company);

        $expense = $this->expenseService->unmarkPaid($businessExpense);

        AuditLog::log('business_expense.unmarked_paid', 'business_expense', $expense->id, [
            'company_id' => $company->id,
        ]);

        return response()->json(['data' => $expense]);
    }

    public function destroy(Company $company, BusinessExpense $businessExpense): JsonResponse
    {
        $this->assertExpenseCompany($businessExpense, $company);

        $expense = $this->expenseService->cancel($businessExpense);

        AuditLog::log('business_expense.cancelled', 'business_expense', $expense->id, [
            'company_id' => $company->id,
        ]);

        return response()->json(['data' => $expense]);
    }

    public function uploadAttachment(Request $request, Company $company, BusinessExpense $businessExpense): JsonResponse
    {
        $this->assertExpenseCompany($businessExpense, $company);

        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'extensions:pdf,jpg,jpeg,png,webp,isdoc,xml'],
        ]);

        $expense = $this->expenseService->storeAttachment(
            $businessExpense,
            $request->file('file'),
        );

        AuditLog::log('business_expense.attachment_uploaded', 'business_expense', $expense->id, [
            'company_id' => $company->id,
            'filename' => $expense->original_filename,
        ]);

        return response()->json(['data' => $expense]);
    }

    public function attachment(Company $company, BusinessExpense $businessExpense): Response
    {
        $this->assertExpenseCompany($businessExpense, $company);

        $businessExpense->load('attachments');
        $record = $businessExpense->attachments->first();

        if ($record) {
            return $this->attachmentResponse($record);
        }

        if (! $businessExpense->attachment_path) {
            abort(404);
        }

        $disk = Storage::disk($businessExpense->attachment_disk);
        if (! $disk->exists($businessExpense->attachment_path)) {
            abort(404);
        }

        return response(
            $disk->get($businessExpense->attachment_path),
            200,
            [
                'Content-Type' => $businessExpense->attachment_mime ?? 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.addslashes($businessExpense->original_filename ?? 'attachment').'"',
            ],
        );
    }

    public function downloadStoredAttachment(
        Company $company,
        BusinessExpense $businessExpense,
        BusinessExpenseAttachment $businessExpenseAttachment,
    ): Response {
        $this->assertExpenseCompany($businessExpense, $company);
        $this->assertAttachmentBelongsToExpense($businessExpenseAttachment, $businessExpense);

        return $this->attachmentResponse($businessExpenseAttachment);
    }

    public function destroyStoredAttachment(
        Company $company,
        BusinessExpense $businessExpense,
        BusinessExpenseAttachment $businessExpenseAttachment,
    ): JsonResponse {
        $this->assertExpenseCompany($businessExpense, $company);
        $this->assertAttachmentBelongsToExpense($businessExpenseAttachment, $businessExpense);

        $expense = $this->expenseService->removeAttachment($businessExpenseAttachment);

        AuditLog::log('business_expense.attachment_removed', 'business_expense', $expense->id, [
            'company_id' => $company->id,
            'filename' => $businessExpenseAttachment->original_filename,
        ]);

        return response()->json(['data' => $expense]);
    }

    public function history(Company $company, BusinessExpense $businessExpense): JsonResponse
    {
        $this->assertExpenseCompany($businessExpense, $company);

        $logs = AuditLog::query()
            ->where('target_type', 'business_expense')
            ->where('target_id', $businessExpense->id)
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'created_at' => $log->created_at?->toIso8601String(),
                'user' => $log->user ? [
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                ] : null,
                'metadata' => $log->metadata,
            ]);

        return response()->json(['data' => $logs]);
    }

    protected function assertExpenseCompany(BusinessExpense $expense, Company $company): void
    {
        if ($expense->company_id !== $company->id) {
            throw ValidationException::withMessages([
                'company' => ['Expense does not belong to this company.'],
            ]);
        }
    }

    protected function assertAttachmentBelongsToExpense(
        BusinessExpenseAttachment $attachment,
        BusinessExpense $expense,
    ): void {
        if ($attachment->business_expense_id !== $expense->id) {
            abort(404);
        }
    }

    protected function attachmentResponse(BusinessExpenseAttachment $attachment): Response
    {
        $disk = Storage::disk($attachment->disk);
        if (! $disk->exists($attachment->path)) {
            abort(404);
        }

        return response(
            $disk->get($attachment->path),
            200,
            [
                'Content-Type' => $attachment->mime ?? 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.addslashes($attachment->original_filename ?? 'attachment').'"',
            ],
        );
    }
}
