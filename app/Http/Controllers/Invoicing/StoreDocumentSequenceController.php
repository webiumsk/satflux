<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Store;
use App\Services\Invoicing\DocumentSequenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StoreDocumentSequenceController extends Controller
{
    public function __construct(
        protected DocumentSequenceService $sequenceService,
    ) {}

    public function preview(Request $request, Store $store): JsonResponse
    {
        $company = $this->resolveLinkedCompany($store);
        $type = $request->string('type', 'invoice')->toString();

        return response()->json([
            'data' => [
                'document_type' => $type,
                'next_number' => $this->sequenceService->previewNextNumber($company, $type),
            ],
        ]);
    }

    public function reserve(Request $request, Store $store): JsonResponse
    {
        $company = $this->resolveLinkedCompany($store);
        $validated = $request->validate([
            'document_type' => ['required', 'string', Rule::in([
                'invoice',
                'credit_note',
                'proforma',
                'delivery_note',
                'quote',
                'order_received',
            ])],
        ]);

        $number = $this->sequenceService->nextNumber($company, $validated['document_type']);

        return response()->json([
            'data' => [
                'document_type' => $validated['document_type'],
                'number' => $number,
            ],
        ]);
    }

    protected function resolveLinkedCompany(Store $store): Company
    {
        $company = $store->company;
        if (! $company) {
            throw ValidationException::withMessages([
                'store' => ['Link an invoicing company to this store before reserving document numbers.'],
            ]);
        }

        return $company;
    }
}
