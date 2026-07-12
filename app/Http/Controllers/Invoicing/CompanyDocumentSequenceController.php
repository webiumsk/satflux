<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoicing\StoreCompanyDocumentSequenceRequest;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyDocumentSequence;
use App\Models\DocumentNumberReservation;
use App\Services\Invoicing\DocumentNumberFormatter;
use App\Services\Invoicing\DocumentSequenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CompanyDocumentSequenceController extends Controller
{
    public function __construct(
        protected DocumentSequenceService $sequenceService,
        protected DocumentNumberFormatter $formatter,
    ) {}

    public function preview(Request $request, Company $company): JsonResponse
    {
        $type = $request->string('type', 'invoice')->toString();

        return response()->json([
            'data' => [
                'document_type' => $type,
                'next_number' => $this->sequenceService->previewNextNumber($company, $type),
            ],
        ]);
    }

    public function index(Company $company): JsonResponse
    {
        $rows = $company->documentSequences()
            ->orderBy('document_type')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (CompanyDocumentSequence $series) => $this->serializeSeries($series));

        return response()->json(['data' => $rows]);
    }

    public function store(StoreCompanyDocumentSequenceRequest $request, Company $company): JsonResponse
    {
        $validated = $request->validated();
        $this->formatter->validateFormat($validated['format']);

        if ($request->boolean('is_default', false)) {
            $this->clearDefaultForType($company, $validated['document_type']);
        }

        $series = $company->documentSequences()->create([
            'document_type' => $validated['document_type'],
            'name' => $validated['name'],
            'format' => $validated['format'],
            'reset_period' => $validated['reset_period'],
            'is_default' => $request->boolean('is_default', false),
            'period_key' => $this->sequenceService->currentPeriodKey($validated['reset_period']),
            'last_number' => (int) ($validated['last_number'] ?? 0),
        ]);

        AuditLog::log('company.number_series_created', 'company', $company->id, [
            'series_id' => $series->id,
        ]);

        return response()->json(['data' => $this->serializeSeries($series)], 201);
    }

    public function update(
        StoreCompanyDocumentSequenceRequest $request,
        Company $company,
        CompanyDocumentSequence $sequence,
    ): JsonResponse {
        $this->assertBelongsToCompany($sequence, $company);

        $validated = $request->validated();
        $this->formatter->validateFormat($validated['format']);

        if ($request->boolean('is_default', $sequence->is_default)) {
            $this->clearDefaultForType($company, $validated['document_type'], $sequence->id);
        }

        $periodChanged = $sequence->reset_period !== $validated['reset_period'];
        $sequence->fill([
            'name' => $validated['name'],
            'document_type' => $validated['document_type'],
            'format' => $validated['format'],
            'reset_period' => $validated['reset_period'],
            'is_default' => $request->boolean('is_default', $sequence->is_default),
        ]);

        if (array_key_exists('last_number', $validated)) {
            $sequence->last_number = (int) $validated['last_number'];
        }

        if ($periodChanged) {
            $sequence->period_key = $this->sequenceService->currentPeriodKey($validated['reset_period']);
        }

        $sequence->save();

        AuditLog::log('company.number_series_updated', 'company', $company->id, [
            'series_id' => $sequence->id,
        ]);

        return response()->json(['data' => $this->serializeSeries($sequence->fresh())]);
    }

    public function destroy(Company $company, CompanyDocumentSequence $sequence): JsonResponse
    {
        $this->assertBelongsToCompany($sequence, $company);

        // Number reservations are the audit trail of allocated numbers and
        // reference the sequence with a restricting foreign key - refuse the
        // delete cleanly instead of failing at the database level.
        if (DocumentNumberReservation::query()
            ->where('company_document_sequence_id', $sequence->id)
            ->exists()) {
            throw ValidationException::withMessages([
                'series' => ['Cannot delete a number series with allocated number reservations.'],
            ]);
        }

        if ($sequence->is_default) {
            $others = CompanyDocumentSequence::query()
                ->where('company_id', $company->id)
                ->where('document_type', $sequence->document_type)
                ->where('id', '!=', $sequence->id)
                ->count();

            if ($others === 0) {
                throw ValidationException::withMessages([
                    'series' => ['Cannot delete the only number series for this document type.'],
                ]);
            }

            CompanyDocumentSequence::query()
                ->where('company_id', $company->id)
                ->where('document_type', $sequence->document_type)
                ->where('id', '!=', $sequence->id)
                ->orderBy('id')
                ->limit(1)
                ->update(['is_default' => true]);
        }

        $sequence->delete();

        AuditLog::log('company.number_series_deleted', 'company', $company->id, [
            'series_id' => $sequence->id,
        ]);

        return response()->json(['message' => 'Number series deleted']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeSeries(CompanyDocumentSequence $series): array
    {
        return [
            'id' => $series->id,
            'company_id' => $series->company_id,
            'name' => $series->name,
            'document_type' => $series->document_type,
            'format' => $series->format,
            'reset_period' => $series->reset_period,
            'is_default' => $series->is_default,
            'period_key' => $series->period_key,
            'last_number' => $series->last_number,
            'next_number_preview' => $this->sequenceService->previewNext($series),
        ];
    }

    protected function clearDefaultForType(Company $company, string $documentType, ?int $exceptId = null): void
    {
        $query = CompanyDocumentSequence::query()
            ->where('company_id', $company->id)
            ->where('document_type', $documentType);

        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }

        $query->update(['is_default' => false]);
    }

    protected function assertBelongsToCompany(CompanyDocumentSequence $sequence, Company $company): void
    {
        if ($sequence->company_id !== $company->id) {
            abort(404);
        }
    }
}
