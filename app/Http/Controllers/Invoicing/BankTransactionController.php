<?php

namespace App\Http\Controllers\Invoicing;

use App\Enums\BankTransactionMatchStatus;
use App\Http\Controllers\Controller;
use App\Models\BankImportBatch;
use App\Models\BankTransaction;
use App\Models\BusinessDocument;
use App\Models\Company;
use App\Services\Invoicing\BankInboundAddressService;
use App\Services\Invoicing\BankStatementImportService;
use App\Services\Invoicing\BankTransactionExpenseService;
use App\Services\Invoicing\BusinessDocumentPaymentMatcher;
use App\Support\Invoicing\BankTransactionListSummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BankTransactionController extends Controller
{
    public function __construct(
        protected BankStatementImportService $importService,
        protected BusinessDocumentPaymentMatcher $matcher,
        protected BankInboundAddressService $inboundAddressService,
        protected BankTransactionExpenseService $expenseFromTransactionService,
    ) {}

    public function index(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'match_status' => ['sometimes', 'string', Rule::in(['unmatched', 'matched', 'ignored'])],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'variable_symbol' => ['sometimes', 'string', 'max:32'],
            'source' => ['sometimes', 'string', Rule::in(['csv', 'camt053', 'manual', 'email'])],
            'per_page' => ['sometimes', 'integer', 'min:5', 'max:100'],
        ]);

        $query = BankTransaction::query()
            ->where('company_id', $company->id)
            ->with([
                'match.document:id,number,status,total,currency,type',
                'expense:id,internal_number,title,status,total,currency',
            ])
            ->orderByDesc('booked_at');

        if (! empty($validated['match_status'])) {
            $query->where('match_status', $validated['match_status']);
        }
        if (! empty($validated['from'])) {
            $query->whereDate('booked_at', '>=', $validated['from']);
        }
        if (! empty($validated['to'])) {
            $query->whereDate('booked_at', '<=', $validated['to']);
        }
        if (! empty($validated['variable_symbol'])) {
            $query->where('variable_symbol', 'like', '%'.$validated['variable_symbol'].'%');
        }
        if (! empty($validated['source'])) {
            $query->where('source', $validated['source']);
        }

        $summary = app(BankTransactionListSummary::class)->forQuery($query);

        $paginated = $query->excludingBalanceSnapshots()->paginate((int) ($validated['per_page'] ?? 25));

        return response()->json([
            'data' => collect($paginated->items())->map(fn (BankTransaction $tx) => $this->transactionPayload($tx)),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'summary' => $summary,
            ],
        ]);
    }

    public function batches(Company $company): JsonResponse
    {
        $batches = BankImportBatch::query()
            ->where('company_id', $company->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $batches->map(fn (BankImportBatch $b) => [
                'id' => $b->id,
                'source' => $b->source->value,
                'filename' => $b->filename,
                'row_count' => $b->row_count,
                'imported_count' => $b->imported_count,
                'skipped_duplicates' => $b->skipped_duplicates,
                'auto_matched_count' => $b->auto_matched_count,
                'created_at' => $b->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function import(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'format' => ['sometimes', 'string', Rule::in(['csv', 'camt053'])],
            'auto_match' => ['sometimes', 'boolean'],
        ]);

        $result = $this->importService->importFile(
            $company,
            $validated['file'],
            $request->user(),
            $validated['format'] ?? null,
        );

        return response()->json([
            'data' => [
                'batch_id' => $result['batch']->id,
                'imported' => $result['imported'],
                'skipped_duplicates' => $result['skipped_duplicates'],
                'auto_matched' => $result['auto_matched'],
            ],
        ]);
    }

    public function autoMatchBatch(Request $request, Company $company, BankImportBatch $batch): JsonResponse
    {
        $this->assertBatchCompany($batch, $company);

        $transactions = BankTransaction::query()
            ->where('bank_import_batch_id', $batch->id)
            ->where('match_status', BankTransactionMatchStatus::Unmatched)
            ->get();

        $result = $this->matcher->autoMatchBatch($transactions, $request->user()->id);
        $batch->increment('auto_matched_count', $result['auto_matched']);

        return response()->json(['data' => $result]);
    }

    public function suggestions(Company $company, BankTransaction $bankTransaction): JsonResponse
    {
        $this->assertTransactionCompany($bankTransaction, $company);

        $suggestions = $this->matcher->suggestions($bankTransaction);

        return response()->json([
            'data' => array_map(fn (array $row) => [
                'document' => [
                    'id' => $row['document']->id,
                    'number' => $row['document']->number,
                    'total' => $row['document']->total,
                    'currency' => $row['document']->currency,
                    'variable_symbol' => $row['document']->variable_symbol,
                    'status' => $row['document']->status->value,
                ],
                'reason' => $row['reason'],
            ], $suggestions),
        ]);
    }

    public function match(
        Request $request,
        Company $company,
        BankTransaction $bankTransaction,
    ): JsonResponse {
        $this->assertTransactionCompany($bankTransaction, $company);

        $validated = $request->validate([
            'business_document_id' => ['required', 'uuid'],
            'matched_amount' => ['sometimes', 'numeric', 'min:0.01'],
        ]);

        $document = BusinessDocument::query()
            ->where('company_id', $company->id)
            ->where('id', $validated['business_document_id'])
            ->firstOrFail();

        $match = $this->matcher->manualMatch(
            $bankTransaction,
            $document,
            isset($validated['matched_amount']) ? (float) $validated['matched_amount'] : null,
            $request->user()->id,
        );

        return response()->json([
            'data' => [
                'match_id' => $match->id,
                'transaction' => $this->transactionPayload($bankTransaction->fresh(['match.document'])),
            ],
        ]);
    }

    public function ignore(Request $request, Company $company, BankTransaction $bankTransaction): JsonResponse
    {
        $this->assertTransactionCompany($bankTransaction, $company);

        $tx = $this->matcher->ignore($bankTransaction, $request->user()->id);

        return response()->json(['data' => $this->transactionPayload($tx)]);
    }

    public function unmatch(Request $request, Company $company, BankTransaction $bankTransaction): JsonResponse
    {
        $this->assertTransactionCompany($bankTransaction, $company);

        if ($bankTransaction->business_expense_id !== null) {
            $bankTransaction->update([
                'business_expense_id' => null,
                'match_status' => BankTransactionMatchStatus::Unmatched,
            ]);

            return response()->json([
                'data' => $this->transactionPayload($bankTransaction->fresh(['match.document', 'expense'])),
            ]);
        }

        $tx = $this->matcher->unmatch($bankTransaction, $request->user()->id);

        return response()->json(['data' => $this->transactionPayload($tx)]);
    }

    public function createExpense(
        Request $request,
        Company $company,
        BankTransaction $bankTransaction,
    ): JsonResponse {
        $this->assertTransactionCompany($bankTransaction, $company);

        $validated = $request->validate([
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'supplier' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:255'],
            'variable_symbol' => ['sometimes', 'nullable', 'string', 'max:32'],
            'constant_symbol' => ['sometimes', 'nullable', 'string', 'max:16'],
            'specific_symbol' => ['sometimes', 'nullable', 'string', 'max:16'],
            'issue_date' => ['sometimes', 'date'],
            'delivery_date' => ['sometimes', 'nullable', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'total' => ['sometimes', 'numeric', 'min:0.01'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'internal_note' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'mark_paid' => ['sometimes', 'boolean'],
        ]);

        $expense = $this->expenseFromTransactionService->createFromTransaction(
            $company,
            $bankTransaction,
            $validated,
        );

        return response()->json([
            'data' => [
                'expense' => $expense,
                'transaction' => $this->transactionPayload(
                    $bankTransaction->fresh(['match.document', 'expense']),
                ),
            ],
        ], 201);
    }

    public function inboundEmailAddress(Company $company): JsonResponse
    {
        $address = $this->inboundAddressService->buildAddress($company);

        return response()->json([
            'data' => [
                'address' => $address,
                'length' => strlen($address),
                'max_length' => $this->inboundAddressService->maxAddressLength(),
                'enabled' => (bool) config('bank_inbound.enabled', false),
            ],
        ]);
    }

    protected function assertTransactionCompany(BankTransaction $transaction, Company $company): void
    {
        if ($transaction->company_id !== $company->id) {
            throw ValidationException::withMessages([
                'bank_transaction' => ['Transaction does not belong to this company.'],
            ]);
        }
    }

    protected function assertBatchCompany(BankImportBatch $batch, Company $company): void
    {
        if ($batch->company_id !== $company->id) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function transactionPayload(BankTransaction $tx): array
    {
        $payload = $tx->toArray();
        $payload['direction'] = $tx->resolvedDirection()->value;
        if ($tx->relationLoaded('match') && $tx->match) {
            $payload['match'] = [
                'id' => $tx->match->id,
                'matched_amount' => $tx->match->matched_amount,
                'match_type' => $tx->match->match_type->value,
                'matched_at' => $tx->match->matched_at?->toIso8601String(),
                'document' => $tx->match->relationLoaded('document') && $tx->match->document
                    ? [
                        'id' => $tx->match->document->id,
                        'number' => $tx->match->document->number,
                        'status' => $tx->match->document->status->value,
                        'type' => $tx->match->document->type->value,
                    ]
                    : null,
            ];
        }

        if ($tx->relationLoaded('expense') && $tx->expense) {
            $payload['expense'] = [
                'id' => $tx->expense->id,
                'internal_number' => $tx->expense->internal_number,
                'title' => $tx->expense->title,
                'status' => $tx->expense->status->value,
                'total' => $tx->expense->total,
                'currency' => $tx->expense->currency,
            ];
        }

        return $payload;
    }
}
