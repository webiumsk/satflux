<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\IntegrationDocumentInbox;
use App\Models\Store;
use App\Services\Integrations\IntegrationDocumentInboxService;
use App\Services\Integrations\IntegrationInboxDeepLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class IntegrationDocumentInboxController extends Controller
{
    public function __construct(
        protected IntegrationDocumentInboxService $inboxService,
        protected IntegrationInboxDeepLinkService $deepLinkService,
    ) {}

    public function resolveDeepLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store' => ['nullable', 'string', 'max:128'],
            'company' => ['nullable', 'string', 'uuid'],
        ]);

        $data = $this->deepLinkService->resolve(
            $request->user(),
            $validated['store'] ?? null,
            $validated['company'] ?? null,
        );

        return response()->json(['data' => $data]);
    }

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

    public function dismiss(Company $company, IntegrationDocumentInbox $inbox): Response
    {
        $this->inboxService->assertEntryBelongsToCompany($inbox, $company);
        $this->inboxService->dismiss($inbox);

        return response()->noContent();
    }

    public function dismissForStore(Store $store, IntegrationDocumentInbox $inbox): Response
    {
        $this->inboxService->assertEntryBelongsToStore($inbox, $store);
        $this->inboxService->dismiss($inbox);

        return response()->noContent();
    }

    public function markImported(Company $company, IntegrationDocumentInbox $inbox): Response
    {
        $this->inboxService->assertEntryBelongsToCompany($inbox, $company);
        $this->inboxService->markImported($inbox);

        return response()->noContent();
    }

    public function markImportedForStore(Store $store, IntegrationDocumentInbox $inbox): Response
    {
        $this->inboxService->assertEntryBelongsToStore($inbox, $store);
        $this->inboxService->markImported($inbox);

        return response()->noContent();
    }
}
