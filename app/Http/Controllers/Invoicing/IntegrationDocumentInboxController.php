<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\IntegrationDocumentInbox;
use App\Models\Store;
use App\Services\Integrations\IntegrationDocumentInboxService;
use Illuminate\Http\JsonResponse;

class IntegrationDocumentInboxController extends Controller
{
    public function __construct(
        protected IntegrationDocumentInboxService $inboxService,
    ) {}

    public function index(Company $company): JsonResponse
    {
        $items = $this->inboxService->listForUser(request()->user(), $company);

        return response()->json(['data' => $items->values()]);
    }

    public function indexForStore(Store $store): JsonResponse
    {
        $items = $this->inboxService->listForStore(request()->user(), $store);

        return response()->json(['data' => $items->values()]);
    }

    public function dismiss(Company $company, IntegrationDocumentInbox $inbox): JsonResponse
    {
        $this->inboxService->assertEntryBelongsToCompany($inbox, $company);
        $entry = $this->inboxService->dismiss($inbox);

        return response()->json([
            'data' => $this->inboxService->serializeEntry($entry),
        ]);
    }

    public function dismissForStore(Store $store, IntegrationDocumentInbox $inbox): JsonResponse
    {
        $this->inboxService->assertEntryBelongsToStore($inbox, $store);
        $entry = $this->inboxService->dismiss($inbox);

        return response()->json([
            'data' => $this->inboxService->serializeEntry($entry),
        ]);
    }

    public function markImported(Company $company, IntegrationDocumentInbox $inbox): JsonResponse
    {
        $this->inboxService->assertEntryBelongsToCompany($inbox, $company);
        $entry = $this->inboxService->markImported($inbox);

        return response()->json([
            'data' => $this->inboxService->serializeEntry($entry),
        ]);
    }

    public function markImportedForStore(Store $store, IntegrationDocumentInbox $inbox): JsonResponse
    {
        $this->inboxService->assertEntryBelongsToStore($inbox, $store);
        $entry = $this->inboxService->markImported($inbox);

        return response()->json([
            'data' => $this->inboxService->serializeEntry($entry),
        ]);
    }
}
