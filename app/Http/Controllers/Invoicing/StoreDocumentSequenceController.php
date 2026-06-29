<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\Invoicing\DocumentSequenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StoreDocumentSequenceController extends Controller
{
    public function __construct(
        protected DocumentSequenceService $sequenceService,
    ) {}

    public function preview(Request $request, Store $store): JsonResponse
    {
        $company = $store->company;
        if (! $company) {
            return $this->storeNotLinkedResponse();
        }

        $validated = $request->validate([
            'type' => ['sometimes', 'string', Rule::in([
                'invoice',
                'credit_note',
                'proforma',
                'delivery_note',
                'quote',
                'order_received',
            ])],
            'local_high_counter' => ['sometimes', 'integer', 'min:0', 'max:99999999'],
        ]);
        $type = $validated['type'] ?? 'invoice';

        return response()->json([
            'data' => [
                'document_type' => $type,
                'next_number' => $this->sequenceService->previewNextNumber(
                    $company,
                    $type,
                    $validated['local_high_counter'] ?? null,
                ),
                'next_counter' => $this->sequenceService->previewNextCounter(
                    $company,
                    $type,
                    $validated['local_high_counter'] ?? null,
                ),
            ],
        ]);
    }

    public function reserve(Request $request, Store $store): JsonResponse
    {
        $company = $store->company;
        if (! $company) {
            return $this->storeNotLinkedResponse();
        }

        $validated = $request->validate([
            'document_type' => ['required', 'string', Rule::in([
                'invoice',
                'credit_note',
                'proforma',
                'delivery_note',
                'quote',
                'order_received',
            ])],
            'local_high_counter' => ['sometimes', 'integer', 'min:0', 'max:99999999'],
        ]);

        $number = $this->sequenceService->nextNumber(
            $company,
            $validated['document_type'],
            $validated['local_high_counter'] ?? null,
        );

        return response()->json([
            'data' => [
                'document_type' => $validated['document_type'],
                'number' => $number,
                'counter' => $this->sequenceService->lastIssuedCounter(
                    $company,
                    $validated['document_type'],
                ),
            ],
        ]);
    }

    protected function storeNotLinkedResponse(): JsonResponse
    {
        return response()->json([
            'data' => null,
            'error' => 'store_not_linked',
            'message' => 'Link an invoicing company to this store before reserving document numbers.',
        ]);
    }
}
