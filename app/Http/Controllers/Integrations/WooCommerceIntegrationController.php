<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\BusinessDocument;
use App\Models\StoreIntegration;
use App\Services\Integrations\WooCommerceDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WooCommerceIntegrationController extends Controller
{
    public function __construct(
        protected WooCommerceDocumentService $documentService,
    ) {}

    public function connection(Request $request): JsonResponse
    {
        /** @var StoreIntegration $integration */
        $integration = $request->attributes->get('store_integration');

        return response()->json([
            'data' => $this->documentService->connectionInfo($integration),
        ]);
    }

    public function upsertContact(Request $request): JsonResponse
    {
        /** @var StoreIntegration $integration */
        $integration = $request->attributes->get('store_integration');
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'ico' => ['nullable', 'string', 'max:32'],
            'dic' => ['nullable', 'string', 'max:32'],
            'ic_dph' => ['nullable', 'string', 'max:32'],
            'street' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:128'],
            'zip' => ['nullable', 'string', 'max:32'],
            'country' => ['nullable', 'string', 'max:2'],
        ]);

        $contact = $this->documentService->upsertContact($integration, $validated);

        return response()->json(['data' => $contact], 201);
    }

    public function createDocument(Request $request): JsonResponse
    {
        /** @var StoreIntegration $integration */
        $integration = $request->attributes->get('store_integration');
        $validated = $request->validate([
            'type' => ['sometimes', 'string', 'in:invoice,proforma'],
            'woocommerce_order_id' => ['sometimes', 'integer', 'min:1'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'buyer' => ['sometimes', 'array'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.name' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0'],
            'lines.*.unit_price' => ['required', 'numeric'],
            'lines.*.tax_rate' => ['sometimes', 'numeric'],
        ]);

        $document = $this->documentService->createDocument($integration, $validated);

        return response()->json([
            'data' => $this->documentService->serializeDocument($document),
        ], 201);
    }

    public function issueDocument(Request $request, string $documentId): JsonResponse
    {
        /** @var StoreIntegration $integration */
        $integration = $request->attributes->get('store_integration');
        $document = BusinessDocument::findOrFail($documentId);
        $document = $this->documentService->issueDocument($integration, $document);

        return response()->json([
            'data' => $this->documentService->serializeDocument($document),
        ]);
    }

    public function showDocument(Request $request, string $documentId): JsonResponse
    {
        /** @var StoreIntegration $integration */
        $integration = $request->attributes->get('store_integration');
        $document = BusinessDocument::findOrFail($documentId);
        $company = $integration->company ?? $integration->store->company;
        if (! $company || $document->company_id !== $company->id) {
            abort(404);
        }

        return response()->json([
            'data' => $this->documentService->serializeDocument($document),
        ]);
    }
}
