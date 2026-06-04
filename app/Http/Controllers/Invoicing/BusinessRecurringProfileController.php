<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoicing\StoreBusinessRecurringProfileRequest;
use App\Models\BusinessDocument;
use App\Models\BusinessRecurringProfile;
use App\Models\Company;
use App\Services\Invoicing\BusinessRecurringProfileService;
use App\Services\Invoicing\RecurringDocumentGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessRecurringProfileController extends Controller
{
    public function __construct(
        protected BusinessRecurringProfileService $profileService,
        protected RecurringDocumentGeneratorService $generatorService,
    ) {}

    public function index(Request $request, Company $company): JsonResponse
    {
        $query = BusinessRecurringProfile::query()
            ->where('company_id', $company->id)
            ->with('contact:id,name');

        $filter = $request->string('filter', 'all')->toString();
        if ($filter === 'active') {
            $query->where('is_active', true);
        } elseif ($filter === 'inactive') {
            $query->where('is_active', false);
        }

        $profiles = $query
            ->orderBy('next_issue_date')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 25));

        $profiles->getCollection()->transform(
            fn (BusinessRecurringProfile $p) => $this->profileService->serializeProfile($p)
        );

        return response()->json($profiles);
    }

    public function store(StoreBusinessRecurringProfileRequest $request, Company $company): JsonResponse
    {
        $data = $request->validated();
        if (empty($data['next_issue_date'])) {
            $data['next_issue_date'] = $data['first_issue_date'];
        }

        $profile = $this->profileService->create($company, $data);

        return response()->json([
            'data' => $this->profileService->serializeProfile($profile),
        ], 201);
    }

    public function show(Company $company, BusinessRecurringProfile $recurringProfile): JsonResponse
    {
        $this->assertBelongsToCompany($recurringProfile, $company);

        $recurringProfile->load(['lines', 'contact', 'store:id,name']);

        return response()->json([
            'data' => $this->profileService->serializeProfile($recurringProfile),
        ]);
    }

    public function update(
        StoreBusinessRecurringProfileRequest $request,
        Company $company,
        BusinessRecurringProfile $recurringProfile,
    ): JsonResponse {
        $this->assertBelongsToCompany($recurringProfile, $company);

        $data = $request->validated();
        if (empty($data['next_issue_date'])) {
            $data['next_issue_date'] = $data['first_issue_date'];
        }

        $profile = $this->profileService->update($recurringProfile, $company, $data);

        return response()->json([
            'data' => $this->profileService->serializeProfile($profile),
        ]);
    }

    public function destroy(Company $company, BusinessRecurringProfile $recurringProfile): JsonResponse
    {
        $this->assertBelongsToCompany($recurringProfile, $company);
        $recurringProfile->lines()->delete();
        $recurringProfile->delete();

        return response()->json(['message' => 'Recurring profile deleted']);
    }

    public function fromDocument(Company $company, BusinessDocument $businessDocument): JsonResponse
    {
        if ($businessDocument->company_id !== $company->id) {
            abort(404);
        }

        $profile = $this->profileService->createFromDocument($company, $businessDocument);

        return response()->json([
            'data' => $this->profileService->serializeProfile($profile),
        ], 201);
    }

    public function generateNow(Company $company, BusinessRecurringProfile $recurringProfile): JsonResponse
    {
        $this->assertBelongsToCompany($recurringProfile, $company);

        $document = $this->generatorService->generateForProfile($recurringProfile);

        return response()->json([
            'data' => [
                'profile' => $this->profileService->serializeProfile($recurringProfile->fresh(['lines', 'contact'])),
                'document' => $document,
            ],
        ]);
    }

    protected function assertBelongsToCompany(BusinessRecurringProfile $profile, Company $company): void
    {
        if ($profile->company_id !== $company->id) {
            abort(404);
        }
    }
}
