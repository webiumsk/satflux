<?php

namespace App\Services\Invoicing;

use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Models\BusinessDocument;
use App\Models\BusinessDocumentLine;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\User;
use App\Services\BtcPay\InvoiceService;
use Illuminate\Support\Facades\Log;

class SubscriptionBillingInvoiceService
{
    public function __construct(
        protected InvoiceService $invoiceService,
        protected DocumentTotalsCalculator $totalsCalculator,
        protected BusinessDocumentMarkPaidService $markPaidService,
        protected BusinessDocumentEmailService $emailService,
    ) {}

    /**
     * Create a paid business invoice for a settled subscription payment.
     *
     * @param  array<string, mixed>  $invoicePayload
     */
    public function fulfillPaidInvoice(
        User $subscriber,
        string $planRole,
        string $btcpayInvoiceId,
        array $invoicePayload,
    ): ?BusinessDocument {
        $companyId = trim((string) config('invoicing.subscription_billing.company_id', ''));
        if ($companyId === '') {
            Log::debug('Subscription billing invoice skipped: SUBSCRIPTION_BILLING_COMPANY_ID not configured');

            return null;
        }

        $existing = BusinessDocument::query()
            ->where('status', '!=', BusinessDocumentStatus::Cancelled)
            ->where(function ($query) use ($btcpayInvoiceId) {
                $query->where('btcpay_invoice_id', $btcpayInvoiceId)
                    ->orWhere('internal_note', 'like', '%btcpay_invoice_id='.$btcpayInvoiceId.'%');
            })
            ->first();

        if ($existing) {
            return $existing->load(['lines', 'contact', 'company']);
        }

        $company = Company::query()->find($companyId);
        if (! $company) {
            Log::warning('Subscription billing company not found', [
                'company_id' => $companyId,
                'btcpay_invoice_id' => $btcpayInvoiceId,
            ]);

            return null;
        }

        $storeId = config('services.btcpay.subscription_store_id');
        if (! $storeId) {
            Log::warning('Subscription billing invoice skipped: subscription store not configured', [
                'btcpay_invoice_id' => $btcpayInvoiceId,
            ]);

            return null;
        }

        $amounts = $this->resolveEurAmountFromBtcpayInvoice($storeId, $btcpayInvoiceId, $invoicePayload);
        if ($amounts === null) {
            Log::warning('Subscription billing invoice skipped: could not resolve EUR amount', [
                'btcpay_invoice_id' => $btcpayInvoiceId,
                'user_id' => $subscriber->id,
            ]);

            return null;
        }

        $contact = $this->upsertSubscriberContact($company, $subscriber);
        $lineName = $this->lineNameForPlan($planRole);
        $taxRate = $company->vat_payer ? (float) ($company->vat_rate_default ?? 0) : 0.0;
        $currency = (string) config('invoicing.subscription_billing.eur_currency', 'EUR');

        $subscriptionId = $invoicePayload['metadata']['subscriptionId']
            ?? $invoicePayload['subscriptionId']
            ?? $invoicePayload['subscription']['id']
            ?? null;

        $document = new BusinessDocument([
            'company_id' => $company->id,
            'company_contact_id' => $contact->id,
            'store_id' => null,
            'type' => BusinessDocumentType::Invoice,
            'status' => BusinessDocumentStatus::Draft,
            'currency' => $currency,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'delivery_date' => now()->toDateString(),
            'payment_btc_enabled' => false,
            'payment_bank_enabled' => false,
            'btcpay_invoice_id' => $btcpayInvoiceId,
            'internal_note' => $this->buildInternalNote($btcpayInvoiceId, $planRole, $subscriptionId, $amounts),
            'tags' => ['subscription', 'plan:'.$planRole, 'subscriber_user:'.$subscriber->id],
        ]);

        $document->setRelation('company', $company);
        $lines = [[
            'name' => $lineName,
            'quantity' => 1,
            'unit_price' => $amounts['eur'],
            'tax_rate' => $taxRate,
            'unit' => 'pcs',
        ]];

        $this->totalsCalculator->applyToDocument($document, $lines, 0);
        $document->save();
        $this->syncLines($document, $lines);

        $paid = $this->markPaidService->markPaid(
            $document->fresh(['lines', 'contact', 'company']),
            $amounts['eur'],
            null,
            'subscription_webhook',
        );

        // IssueService clears btcpay_invoice_id for merchant checkout; restore subscription payment ref.
        if ($paid->btcpay_invoice_id !== $btcpayInvoiceId) {
            $paid->update(['btcpay_invoice_id' => $btcpayInvoiceId]);
            $paid = $paid->fresh(['lines', 'contact', 'company']);
        }

        $this->sendInvoiceEmail($company, $paid, $contact);

        Log::info('Subscription billing invoice created', [
            'business_document_id' => $paid->id,
            'number' => $paid->number,
            'btcpay_invoice_id' => $btcpayInvoiceId,
            'user_id' => $subscriber->id,
            'plan' => $planRole,
            'eur' => $amounts['eur'],
        ]);

        return $paid->fresh(['lines', 'contact', 'company']);
    }

    /**
     * @param  array<string, mixed>  $invoicePayload
     * @return array{eur: float, sats: int|null, rate: string|null, source: string}|null
     */
    public function resolveEurAmountFromBtcpayInvoice(
        string $storeId,
        string $invoiceId,
        array $invoicePayload,
    ): ?array {
        try {
            $invoice = $this->invoiceService->getInvoice($storeId, $invoiceId);
        } catch (\Throwable $e) {
            $invoice = $invoicePayload;
            Log::debug('Subscription billing: using webhook invoice payload after API failure', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);
        }

        $currency = strtoupper(trim((string) ($invoice['currency'] ?? '')));
        $amount = (float) ($invoice['amount'] ?? 0);
        $fiatCurrencies = ['EUR', 'USD', 'CZK', 'PLN', 'GBP', 'CHF'];

        if (in_array($currency, $fiatCurrencies, true) && $amount > 0) {
            return [
                'eur' => round($amount, 2),
                'sats' => null,
                'rate' => null,
                'source' => 'invoice_fiat',
            ];
        }

        $receivedSats = $this->invoiceService->getReceivedSatsForInvoice($storeId, $invoice);
        $rate = $this->invoiceService->getPaymentRateForInvoice($storeId, $invoiceId);

        if ($receivedSats === null || $receivedSats <= 0 || $rate === null || (float) $rate <= 0) {
            return null;
        }

        $eur = round(($receivedSats / 100_000_000) * (float) $rate, 2);
        if ($eur <= 0) {
            return null;
        }

        return [
            'eur' => $eur,
            'sats' => $receivedSats,
            'rate' => (string) $rate,
            'source' => 'sats_rate',
        ];
    }

    protected function upsertSubscriberContact(Company $company, User $subscriber): CompanyContact
    {
        $email = trim((string) ($subscriber->email ?? ''));
        $name = trim((string) ($subscriber->name ?? ''));
        if ($name === '' && $email !== '') {
            $name = $email;
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
            'name' => $name !== '' ? $name : ($contact->name ?: 'Subscriber'),
            'email' => $email !== '' ? $email : $contact->email,
            'is_active' => true,
        ]);
        $contact->save();

        return $contact;
    }

    protected function lineNameForPlan(string $planRole): string
    {
        $names = config('invoicing.subscription_billing.line_names', []);

        return (string) ($names[$planRole] ?? 'Satflux '.$planRole.' - annual subscription');
    }

    /**
     * @param  array{eur: float, sats: int|null, rate: string|null, source: string}  $amounts
     */
    protected function buildInternalNote(string $btcpayInvoiceId, string $planRole, mixed $subscriptionId, array $amounts): string
    {
        $parts = [
            'btcpay_invoice_id='.$btcpayInvoiceId,
            'subscription_plan='.$planRole,
            'eur_source='.$amounts['source'],
            'eur_amount='.$amounts['eur'],
        ];

        if ($subscriptionId) {
            $parts[] = 'btcpay_subscription_id='.$subscriptionId;
        }
        if ($amounts['sats'] !== null) {
            $parts[] = 'paid_sats='.$amounts['sats'];
        }
        if ($amounts['rate'] !== null) {
            $parts[] = 'btc_eur_rate='.$amounts['rate'];
        }

        return implode('; ', $parts);
    }

    protected function sendInvoiceEmail(Company $company, BusinessDocument $document, CompanyContact $contact): void
    {
        $email = trim((string) ($contact->email ?? ''));
        if ($email === '') {
            Log::warning('Subscription billing invoice email skipped: contact has no email', [
                'business_document_id' => $document->id,
                'contact_id' => $contact->id,
            ]);

            return;
        }

        try {
            $this->emailService->send(
                $company,
                $document->fresh(['lines', 'contact', 'company']),
                $company->user,
                [$email],
            );
        } catch (\Throwable $e) {
            report($e);
            Log::error('Subscription billing invoice email failed', [
                'business_document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);
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
