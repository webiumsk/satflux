<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\EfakturaInboundReceipt;
use App\Models\User;
use App\Services\Invoicing\Efaktura\EfakturaInboundInboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Local-first e-faktura inbox (see EfakturaInboundInboxService). */
class EfakturaInboundInboxController extends Controller
{
    public function __construct(
        protected EfakturaInboundInboxService $inboxService,
    ) {}

    public function index(Request $request, Company $company): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return response()->json(['data' => $this->inboxService->listPending($user, $company)->values()]);
    }

    public function health(Request $request, Company $company): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return response()->json(['data' => $this->inboxService->health($user, $company)]);
    }

    public function show(Request $request, Company $company, EfakturaInboundReceipt $receipt): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return response()->json(['data' => $this->inboxService->detail($user, $company, $receipt)]);
    }

    public function markImported(Request $request, Company $company, EfakturaInboundReceipt $receipt): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $this->inboxService->assertCompanyAccess($user, $company);
        abort_unless($receipt->company_id === $company->id, 404);
        $validated = $request->validate(['original_sha256' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D']]);
        $this->inboxService->markImported($receipt, $company, $validated['original_sha256']);

        return response()->noContent();
    }

    public function dismiss(Request $request, Company $company, EfakturaInboundReceipt $receipt): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $this->inboxService->assertCompanyAccess($user, $company);
        $this->inboxService->dismiss($receipt, $company);

        return response()->noContent();
    }
}
