<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Services\Invoicing\CompanyRegistryService;
use App\Support\Invoicing\CompanyRegistryCoverage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyRegistryController extends Controller
{
    public function __construct(
        protected CompanyRegistryService $registry,
    ) {}

    public function coverage(): JsonResponse
    {
        return response()->json([
            'data' => [
                'options' => $this->registry->coverageOptions(),
                'meta' => $this->registry->coverageMeta(),
            ],
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:120'],
            'country' => ['required', 'string', Rule::in(CompanyRegistryCoverage::allCountryCodes())],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ]);

        $country = CompanyRegistryCoverage::normalize($validated['country']);
        if (! CompanyRegistryCoverage::supportsAutocomplete($country)) {
            return response()->json([
                'data' => ['results' => [], 'count' => 0],
                'meta' => $this->registry->coverageMeta(),
            ]);
        }

        $data = $this->registry->search(
            $validated['q'],
            $country,
            (int) ($validated['limit'] ?? 8),
        );

        return response()->json([
            'data' => $data,
            'meta' => $this->registry->coverageMeta(),
        ]);
    }

    public function show(Request $request, string $entityId): JsonResponse
    {
        $validated = $request->validate([
            'country' => ['required', 'string', Rule::in(CompanyRegistryCoverage::allCountryCodes())],
        ]);

        $country = CompanyRegistryCoverage::normalize($validated['country']);
        $detail = $this->registry->findByIdentifier(urldecode($entityId), $country);

        if ($detail === null) {
            return response()->json(['message' => 'Company not found in registry.'], 404);
        }

        return response()->json(['data' => $detail]);
    }
}
