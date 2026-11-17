<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Services\Invoicing\BusinessExpenseImportService;
use App\Services\Invoicing\BusinessExpenseIsdocQuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EphemeralBusinessExpenseController extends Controller
{
    private const IDENTITY_RULES = [
        'company' => ['prohibited'],
        'company_id' => ['prohibited'],
        'expense' => ['prohibited'],
        'expense_id' => ['prohibited'],
        'user' => ['prohibited'],
        'user_id' => ['prohibited'],
        'evolu_company_id' => ['prohibited'],
        'evolu_expense_id' => ['prohibited'],
    ];

    public function __construct(
        protected BusinessExpenseImportService $documentImportService,
        protected BusinessExpenseIsdocQuotaService $isdocQuotaService,
    ) {}

    public function quota(Request $request): JsonResponse
    {
        $request->validate(self::IDENTITY_RULES);

        return response()->json(['data' => $this->isdocQuotaService->snapshot($request->user())]);
    }

    public function detectIsdoc(Request $request): JsonResponse
    {
        $request->validate(array_merge(self::IDENTITY_RULES, [
            'file' => [
                'required',
                'file',
                'max:10240',
                'extensions:pdf,isdoc,xml,jpg,jpeg,png,webp',
                'mimetypes:application/pdf,application/xml,text/xml,image/jpeg,image/png,image/webp,application/octet-stream',
            ],
        ]));

        $file = $request->file('file');
        $detection = $this->documentImportService->detectFromUpload($file);

        return response()->json([
            'data' => [
                ...$detection,
                'quota' => $this->isdocQuotaService->snapshot($request->user()),
                'filename' => $file->getClientOriginalName(),
            ],
        ]);
    }

    public function extract(Request $request): JsonResponse
    {
        $request->validate(array_merge(self::IDENTITY_RULES, [
            'file' => ['required', 'file', 'max:10240', 'extensions:pdf,isdoc,xml'],
        ]));

        $user = $request->user();
        $this->isdocQuotaService->assertCanExtract($user);
        $draft = $this->documentImportService->extractFromUpload($request->file('file'));
        $this->isdocQuotaService->recordExtraction($user, null, null);

        return response()->json([
            'data' => $draft,
            'quota' => $this->isdocQuotaService->snapshot($user),
        ]);
    }
}
