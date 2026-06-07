<?php

namespace App\Services\Integrations;

use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Models\BusinessDocument;
use App\Models\BusinessDocumentLine;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\StoreIntegration;
use App\Services\Invoicing\BusinessDocumentIssueService;
use App\Services\Invoicing\DocumentTotalsCalculator;
use App\Services\SubscriptionService;
use App\Support\Invoicing\CompanyAppSettings;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class WooCommerceDocumentService
{
    public function __construct(
        protected DocumentTotalsCalculator $totalsCalculator,
        protected BusinessDocumentIssueService $issueService,
        protected SubscriptionService $subscriptionService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function connectionInfo(StoreIntegration $integration): array
    {
        $store = $integration->store;
        $user = $store->user;
        $company = $integration->company ?? $store->company;

        return [
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
            ],
            'company' => $company ? [
                'id' => $company->id,
                'name' => $company->legal_name,
            ] : null,
            'invoicing_enabled' => $company && $this->subscriptionService->canUseBusinessInvoicing($user),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function upsertContact(StoreIntegration $integration, array $payload): CompanyContact
    {
        $company = $this->resolveCompany($integration);

        $email = trim((string) ($payload['email'] ?? ''));
        $name = trim((string) ($payload['name'] ?? ''));
        if ($email === '' && $name === '') {
            throw ValidationException::withMessages(['buyer' => ['Buyer name or email is required.']]);
        }

        $contact = null;
        if ($email !== '') {
            $contact = CompanyContact::query()
                ->where('company_id', $company->id)
                ->where('email', $email)
                ->first();
        }

        if (! $contact) {
            $contact = new CompanyContact(['company_id' => $company->id]);
        }

        $contact->fill([
            'name' => $name !== '' ? $name : ($contact->name ?: $email),
            'email' => $email ?: $contact->email,
            'registration_number' => (string) ($payload['ico'] ?? $contact->registration_number),
            'tax_id' => (string) ($payload['dic'] ?? $contact->tax_id),
            'vat_id' => (string) ($payload['ic_dph'] ?? $contact->vat_id),
            'street' => (string) ($payload['street'] ?? $contact->street),
            'city' => (string) ($payload['city'] ?? $contact->city),
            'postal_code' => (string) ($payload['zip'] ?? $contact->postal_code),
            'country' => (string) ($payload['country'] ?? $contact->country),
        ]);
        $contact->save();

        return $contact;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createDocument(StoreIntegration $integration, array $payload): BusinessDocument
    {
        $company = $this->resolveCompany($integration);
        $store = $integration->store;
        $user = $store->user;

        if (! $this->subscriptionService->canUseBusinessInvoicing($user)) {
            throw ValidationException::withMessages([
                'plan' => ['Business invoicing requires a Pro plan.'],
            ]);
        }

        $wcOrderId = (int) ($payload['woocommerce_order_id'] ?? 0);
        if ($wcOrderId > 0) {
            $existing = BusinessDocument::query()
                ->where('company_id', $company->id)
                ->where('internal_note', 'like', '%woocommerce_order_id='.$wcOrderId.'%')
                ->where('status', '!=', BusinessDocumentStatus::Cancelled)
                ->first();
            if ($existing) {
                return $existing->load(['lines', 'contact', 'store']);
            }
        }

        $type = BusinessDocumentType::tryFrom((string) ($payload['type'] ?? 'invoice')) ?? BusinessDocumentType::Invoice;
        if (! $type->isMvpEnabled()) {
            throw ValidationException::withMessages(['type' => ['Document type not supported.']]);
        }

        $buyer = is_array($payload['buyer'] ?? null) ? $payload['buyer'] : [];
        $contact = $this->upsertContact($integration, $buyer);

        $appSettings = CompanyAppSettings::from($company->app_settings);
        $document = new BusinessDocument([
            'company_id' => $company->id,
            'company_contact_id' => $contact->id,
            'store_id' => $store->id,
            'type' => $type,
            'status' => BusinessDocumentStatus::Draft,
            'currency' => (string) ($payload['currency'] ?? $company->default_currency),
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays((int) $appSettings->get('default_invoice_payment_terms_days', 14))->toDateString(),
            'delivery_date' => now()->toDateString(),
            'payment_btc_enabled' => (bool) $store->company_id,
            'payment_bank_enabled' => true,
            'internal_note' => $wcOrderId > 0 ? 'woocommerce_order_id='.$wcOrderId : null,
            'note_above_lines' => $wcOrderId > 0 ? 'WooCommerce order #'.$wcOrderId : null,
            'tags' => $wcOrderId > 0 ? ['woocommerce', 'wc_order:'.$wcOrderId] : ['woocommerce'],
        ]);

        $document->setRelation('company', $company);
        $lines = is_array($payload['lines'] ?? null) ? $payload['lines'] : [];
        $normalized = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $normalized[] = [
                'name' => (string) ($line['name'] ?? 'Item'),
                'quantity' => (float) ($line['quantity'] ?? 1),
                'unit_price' => (float) ($line['unit_price'] ?? 0),
                'tax_rate' => (float) ($line['tax_rate'] ?? 0),
                'unit' => (string) ($line['unit'] ?? 'pcs'),
            ];
        }

        $this->totalsCalculator->applyToDocument($document, $normalized, 0);
        $document->save();
        $this->syncLines($document, $normalized);

        return $document->fresh(['lines', 'contact', 'store', 'company']);
    }

    public function issueDocument(StoreIntegration $integration, BusinessDocument $document): BusinessDocument
    {
        $this->assertDocumentAccess($integration, $document);
        if ($document->status !== BusinessDocumentStatus::Draft) {
            return $document->load(['lines', 'contact', 'store', 'company']);
        }

        return $this->issueService->issue($document);
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeDocument(BusinessDocument $document): array
    {
        $pdfUrl = URL::to('/invoicing/companies/'.$document->company_id.'/documents/'.$document->id.'/pdf');
        $payUrl = $document->payment_token
            ? URL::to('/pay/i/'.$document->payment_token)
            : null;

        return [
            'id' => $document->id,
            'number' => $document->number,
            'status' => $document->status->value,
            'total' => $document->total,
            'currency' => $document->currency,
            'payment_token' => $document->payment_token,
            'pdf_url' => $pdfUrl,
            'payment_url' => $payUrl,
        ];
    }

    protected function resolveCompany(StoreIntegration $integration): Company
    {
        $company = $integration->company ?? $integration->store->company;
        if (! $company) {
            throw ValidationException::withMessages([
                'company' => ['Link a company to this store in Satflux invoicing settings.'],
            ]);
        }

        return $company;
    }

    protected function assertDocumentAccess(StoreIntegration $integration, BusinessDocument $document): void
    {
        $company = $this->resolveCompany($integration);
        if ($document->company_id !== $company->id) {
            abort(404);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    protected function syncLines(BusinessDocument $document, array $lines): void
    {
        $document->lines()->delete();
        foreach ($lines as $index => $line) {
            BusinessDocumentLine::create([
                'business_document_id' => $document->id,
                'sort_order' => $index,
                'name' => $line['name'],
                'quantity' => $line['quantity'],
                'unit' => $line['unit'] ?? 'pcs',
                'unit_price' => $line['unit_price'],
                'tax_rate' => $line['tax_rate'] ?? 0,
                'line_discount_percent' => 0,
                'line_total' => number_format((float) $line['quantity'] * (float) $line['unit_price'], 2, '.', ''),
            ]);
        }
    }
}
