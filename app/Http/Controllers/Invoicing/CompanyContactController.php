<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoicing\StoreCompanyContactRequest;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Services\Invoicing\CompanyContactAnonymizationService;
use App\Services\Invoicing\CompanyContactStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyContactController extends Controller
{
    public function __construct(
        protected CompanyContactStatsService $statsService,
    ) {}

    public function index(Request $request, Company $company): JsonResponse
    {
        $query = $company->contacts()->orderBy('name');

        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        if ($search = trim((string) $request->input('q', ''))) {
            $pattern = '%'.$search.'%';
            $query->where(function ($q) use ($pattern) {
                foreach (['name', 'email', 'registration_number', 'tax_id'] as $column) {
                    $q->orWhere(function ($inner) use ($pattern, $column) {
                        $this->whereLikeInsensitive($inner, $column, $pattern);
                    });
                }
            });
        }

        $letter = $request->input('letter');
        if ($letter && $letter !== 'all') {
            if ($letter === '#') {
                $this->whereNameNotStartingWithLetter($query);
            } else {
                $letter = mb_strtoupper(mb_substr($letter, 0, 1));
                $query->where(function ($q) use ($letter) {
                    $this->whereLikeInsensitive($q, 'name', $letter.'%');
                    $q->orWhere(function ($inner) use ($letter) {
                        $this->whereLikeInsensitive($inner, 'name', mb_strtolower($letter).'%');
                    });
                });
            }
        }

        $contacts = $query->get();
        $statsMap = $this->statsService->statsForCompany($company);

        $data = $contacts->map(function (CompanyContact $contact) use ($statsMap) {
            $row = $contact->toArray();
            $row['stats'] = $statsMap[$contact->id] ?? [
                'invoiced_total' => 0,
                'invoiced_count' => 0,
                'overdue_total' => 0,
                'overdue_count' => 0,
                'avg_payment_days' => null,
            ];

            return $row;
        });

        $allForLetters = $company->contacts()->orderBy('name')->get();

        return response()->json([
            'data' => $data,
            'meta' => [
                'letters' => $this->statsService->availableLetters($company, $allForLetters),
                'total' => $allForLetters->count(),
            ],
        ]);
    }

    public function store(StoreCompanyContactRequest $request, Company $company): JsonResponse
    {
        $contact = $company->contacts()->create($request->validated());

        return response()->json(['data' => $this->contactWithStats($company, $contact)], 201);
    }

    public function show(Company $company, CompanyContact $contact): JsonResponse
    {
        return response()->json([
            'data' => $this->contactWithStats($company, $contact),
        ]);
    }

    public function update(StoreCompanyContactRequest $request, Company $company, CompanyContact $contact): JsonResponse
    {
        $contact->update($request->validated());

        return response()->json(['data' => $this->contactWithStats($company, $contact->fresh())]);
    }

    public function destroy(
        Company $company,
        CompanyContact $contact,
        CompanyContactAnonymizationService $anonymization,
    ): JsonResponse {
        if ($anonymization->anonymize($company, $contact)) {
            return response()->json(['message' => 'Contact anonymized (issued documents retain buyer snapshot).']);
        }

        $contact->delete();

        return response()->json(['message' => 'Contact deleted']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function contactWithStats(Company $company, CompanyContact $contact): array
    {
        $row = $contact->toArray();
        $row['stats'] = $this->statsService->statsForContact($company, $contact);

        return $row;
    }

    protected function whereLikeInsensitive($query, string $column, string $pattern): void
    {
        if ($query->getConnection()->getDriverName() === 'pgsql') {
            $query->where($column, 'ilike', $pattern);
        } else {
            $query->whereRaw('LOWER('.$column.') LIKE ?', [mb_strtolower($pattern)]);
        }
    }

    protected function whereNameNotStartingWithLetter($query): void
    {
        if ($query->getConnection()->getDriverName() === 'pgsql') {
            $query->whereRaw("name !~* '^[a-záäčďéíĺľňóôŕšťúýž]'");

            return;
        }

        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZÁÄČĎÉÍĹĽŇÓÔŘŠŤÚÝŽ';
        foreach (mb_str_split($alphabet) as $char) {
            $query->where('name', 'not like', $char.'%');
            $query->where('name', 'not like', mb_strtolower($char).'%');
        }
    }
}
