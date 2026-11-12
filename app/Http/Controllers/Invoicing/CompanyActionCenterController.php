<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Services\Invoicing\CompanyActionCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyActionCenterController extends Controller
{
    public function __invoke(Request $request, Company $company, CompanyActionCenterService $service): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return response()->json(['data' => $service->forCompany($company, $user)]);
    }
}
