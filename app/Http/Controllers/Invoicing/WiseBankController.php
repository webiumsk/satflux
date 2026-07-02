<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Invoicing\Wise\WiseBankSyncService;
use App\Support\Invoicing\CompanyWiseSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WiseBankController extends Controller
{
    public function __construct(
        protected WiseBankSyncService $wiseSync,
    ) {}

    public function status(Company $company): JsonResponse
    {
        return response()->json([
            'data' => $this->wiseSync->status($company),
        ]);
    }

    public function connect(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'wise_api_token' => ['required', 'string', 'min:10', 'max:255'],
            'wise_profile_id' => ['sometimes', 'nullable', 'integer'],
            'wise_balance_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        $result = $this->wiseSync->connect($company, $validated);

        return response()->json([
            'message' => 'Wise connected.',
            'data' => array_merge(
                CompanyWiseSettings::fromCompany($company->fresh())->publicPayload(),
                [
                    'profile_id' => $result['profile_id'],
                    'balance_id' => $result['balance_id'],
                ],
            ),
        ]);
    }

    public function sync(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'local_first' => ['sometimes', 'boolean'],
        ]);

        $result = $this->wiseSync->sync(
            $company,
            $request->user(),
            $validated['from'] ?? null,
            $validated['to'] ?? null,
            (bool) ($validated['local_first'] ?? false),
        );

        return response()->json(['data' => $result]);
    }
}
