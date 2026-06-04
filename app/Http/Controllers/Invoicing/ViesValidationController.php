<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoicing\ValidateVatNumberRequest;
use App\Services\Invoicing\ViesVatValidationService;
use Illuminate\Http\JsonResponse;

class ViesValidationController extends Controller
{
    public function __construct(
        protected ViesVatValidationService $vies,
    ) {}

    public function validateVat(ValidateVatNumberRequest $request): JsonResponse
    {
        $country = $request->validated('country');
        $country = is_string($country) ? strtoupper($country) : null;

        $result = $this->vies->validate(
            $request->validated('vat_number'),
            $country,
        );

        return response()->json(['data' => $result->toArray()]);
    }
}
