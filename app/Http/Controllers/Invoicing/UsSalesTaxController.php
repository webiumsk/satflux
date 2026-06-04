<?php

namespace App\Http\Controllers\Invoicing;

use App\Enums\CompanyJurisdiction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invoicing\PreviewUsSalesTaxRequest;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Services\Invoicing\CanonicalInvoiceBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class UsSalesTaxController extends Controller
{
    public function __construct(
        protected CanonicalInvoiceBuilder $canonicalBuilder,
    ) {}

    public function preview(PreviewUsSalesTaxRequest $request, Company $company): JsonResponse
    {
        if ($company->jurisdiction !== CompanyJurisdiction::Us) {
            throw ValidationException::withMessages([
                'company' => ['US sales tax preview is only available for US companies.'],
            ]);
        }

        $contact = null;
        $contactId = $request->validated('company_contact_id');
        if ($contactId) {
            $contact = CompanyContact::query()
                ->where('company_id', $company->id)
                ->where('id', $contactId)
                ->firstOrFail();
        }

        $canonical = $this->canonicalBuilder->fromLinePayloads(
            $company,
            $request->validated('lines'),
            (float) ($request->validated('discount_percent') ?? 0),
            null,
            $contact,
        );

        $currency = strtoupper((string) ($request->validated('currency') ?? $company->default_currency ?? 'USD'));

        return response()->json([
            'data' => [
                'currency' => $currency,
                'subtotal' => $canonical->subtotal,
                'tax_total' => $canonical->taxTotal,
                'total' => $canonical->total,
                'tax_breakdown' => array_map(fn ($row) => [
                    'rate_percent' => $row->ratePercent,
                    'label' => $row->label,
                    'taxable_amount' => $row->taxableAmount,
                    'tax_amount' => $row->taxAmount,
                    'gross_amount' => $row->grossAmount,
                ], $canonical->taxBreakdown),
                'lines' => array_map(fn ($line) => [
                    'name' => $line->name,
                    'tax_rate' => $line->taxRate,
                    'net_amount' => $line->netAmount,
                    'tax_amount' => $line->taxAmount,
                    'gross_amount' => $line->grossAmount,
                ], $canonical->lines),
            ],
        ]);
    }
}
