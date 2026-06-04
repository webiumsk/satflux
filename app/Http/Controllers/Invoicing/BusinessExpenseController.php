<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoicing\StoreBusinessExpenseRequest;
use App\Models\AuditLog;
use App\Models\BusinessExpense;
use App\Models\Company;
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
    ) {}

    public function index(Request $request, Company $company): JsonResponse
    {
        $expenses = $this->expenseService->filteredQuery($company, $request)
            ->orderByDesc('issue_date')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 25));

        return response()->json($expenses);
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

    public function show(Company $company, BusinessExpense $businessExpense): JsonResponse
    {
        $this->assertExpenseCompany($businessExpense, $company);

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
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,isdoc,xml'],
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

        if (! $businessExpense->hasAttachment()) {
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
}
