<?php

namespace App\Services\Invoicing;

use App\Enums\BusinessDocumentStatus;
use App\Enums\BusinessDocumentType;
use App\Models\BusinessDocument;
use App\Models\BusinessDocumentPaymentAttempt;
use App\Models\BusinessDocumentPaymentIntent;
use App\Models\Company;
use App\Models\EphemeralBtcpayCheckout;
use App\Models\Store;
use App\Models\User;
use App\Services\BtcPay\InvoiceService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** A public capability to request Bitcoin payment, without retaining an invoice snapshot. */
class BusinessDocumentPaymentIntentService
{
    private static array $heldLocks = [];

    public function __construct(protected InvoiceService $invoices, protected EphemeralBtcpayCheckoutService $checkouts) {}

    public function locked(int $userId, string $key, callable $callback): mixed
    {
        $name = 'business-payment:'.hash('sha256', $userId.':'.$key);
        if (isset(self::$heldLocks[$name])) {
            return $callback();
        }

        return Cache::lock($name, 300)->block(10, function () use ($name, $callback) {
            self::$heldLocks[$name] = true;
            try {
                return $callback();
            } finally {
                unset(self::$heldLocks[$name]);
            }
        });
    }

    public function prepare(BusinessDocument $document, Store $store, ?string $localId = null): ?BusinessDocumentPaymentIntent
    {
        if (! $document->exists && (! is_string($localId) || $localId === '')) {
            return null;
        }
        $company = $document->company;
        if (! $company instanceof Company) {
            throw ValidationException::withMessages(['company' => ['A valid issuer is required.']]);
        }
        $owner = (int) $company->user_id;
        $key = $document->exists ? 'i:'.$document->id : 'e:'.$localId;

        return $this->locked($owner, $key, function () use ($document, $store, $localId, $owner, $key) {
            if ($owner !== $store->user_id) {
                throw ValidationException::withMessages(['store_id' => ['The selected store does not belong to this document.']]);
            }
            if ($document->exists) {
                $current = $document->fresh(['company', 'store']);
                if (! $current) {
                    return null;
                }
                $document = $current;
                $store = $current->store;
            }
            $company = $document->company;
            if (! $company instanceof Company || (int) $company->user_id !== $owner || ! $store instanceof Store
                || $owner !== $store->user_id || ($document->exists && $store->company_id !== $document->company_id)) {
                throw ValidationException::withMessages(['store_id' => ['The selected store does not belong to this document.']]);
            }
            $intent = BusinessDocumentPaymentIntent::where('user_id', $owner)->where('document_key', $key)->first();
            $terms = $this->terms($document);
            $fingerprint = $this->fingerprint($store->id, $terms);
            $unpayable = ! $document->payment_btc_enabled || $document->typeValue() === BusinessDocumentType::Quote->value
                || ! $document->hasStatus(BusinessDocumentStatus::Issued) || (float) $terms['amount'] <= 0;
            if ($intent) {
                if ($unpayable || $intent->fingerprint !== $fingerprint) {
                    $this->terminal($intent, $document->hasStatus(BusinessDocumentStatus::Paid) ? 'paid' : 'revoked');
                }

                return $intent;
            }
            if ($unpayable) {
                return null;
            }
            $invoiceId = $document->btcpay_invoice_id;
            $history = collect();
            if (! $document->exists) {
                $history = EphemeralBtcpayCheckout::where('user_id', $owner)->where('evolu_document_id', $localId)
                    ->orderBy('created_at')->get();
                $invoiceId = $history->last()?->btcpay_invoice_id;
            }

            return DB::transaction(function () use ($document, $store, $localId, $owner, $key, $terms, $fingerprint, $invoiceId, $history) {
                $intent = BusinessDocumentPaymentIntent::create([
                    'user_id' => $owner, 'store_id' => $store->id, 'store_company_id' => $store->company_id, 'document_key' => $key,
                    'evolu_document_id' => $document->exists ? null : $localId,
                    'business_document_id' => $document->exists ? $document->id : null,
                    'token' => Str::random(64), 'terms' => $terms, 'fingerprint' => $fingerprint,
                    'state' => 'active', 'btcpay_invoice_id' => $invoiceId,
                ]);
                foreach ($history as $old) {
                    $this->rememberAttempt($intent, $old->store_id, $old->btcpay_invoice_id,
                        ['amount' => $old->amount, 'currency' => $old->currency, 'number' => null]);
                }
                if ($document->exists && $invoiceId) {
                    $mapping = EphemeralBtcpayCheckout::where('user_id', $owner)->where('store_id', $store->id)
                        ->where('btcpay_invoice_id', $invoiceId)->first();
                    // A changed document is not evidence of the old provider invoice's original face value.
                    $this->rememberAttempt($intent, $store->id, $invoiceId, [
                        'amount' => $mapping?->amount, 'currency' => $mapping?->currency, 'number' => null,
                    ]);
                }

                return $intent;
            });
        });
    }

    public function terms(BusinessDocument $document): array
    {
        return ['amount' => number_format(max(0, (float) $document->total - (float) $document->amount_paid), 2, '.', ''),
            'currency' => strtoupper((string) $document->currency), 'number' => $document->number];
    }

    private function fingerprint(string $storeId, array $terms): string
    {
        return hash_hmac('sha256', json_encode([$storeId, $terms], JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    public function url(BusinessDocumentPaymentIntent $intent): ?string
    {
        return $intent->state === 'active' ? rtrim(config('app.url'), '/').'/pay/e/'.$intent->token : null;
    }

    public function merchantCheckout(BusinessDocument $document, Store $store, ?string $localId, ?int $reopenVersion = null): array
    {
        $intent = $this->prepare($document, $store, $localId);
        if (! $intent) {
            throw ValidationException::withMessages(['document' => ['An issued unpaid invoice and stable document identity are required.']]);
        }
        if ($reopenVersion !== null) {
            $this->locked($intent->user_id, $intent->document_key, function () use ($intent, $document, $store, $reopenVersion) {
                $intent = $intent->fresh();
                if ($document->exists) {
                    $document = $document->fresh(['company', 'store']);
                    $store = $document?->store;
                    if (! $document || ! $store instanceof Store || $store->user_id !== $intent->user_id
                        || $store->company_id !== $document->company_id) {
                        throw ValidationException::withMessages(['store_id' => ['The selected store does not belong to this document.']]);
                    }
                }
                abort_if($intent->version !== $reopenVersion, 409);
                if (! in_array($intent->state, ['revoked', 'paid'], true) || $intent->payment_method === 'btcpay'
                    || ! $document->hasStatus(BusinessDocumentStatus::Issued) || ! $document->payment_btc_enabled
                    || (float) $this->terms($document)['amount'] <= 0) {
                    throw ValidationException::withMessages(['document' => ['This payment cannot be reopened.']]);
                }
                if (! $this->historyAllowsPayment($intent, recoverTerms: true) || ($intent->btcpay_invoice_id && $this->existing($intent, recoverTerms: true) !== 'replaceable')) {
                    throw ValidationException::withMessages(['document' => ['The earlier payment must be resolved first.']]);
                }
                if ($intent->attempt_id && ! $intent->btcpay_invoice_id) {
                    throw ValidationException::withMessages(['document' => ['The earlier payment must be resolved first.']]);
                }
                $terms = $this->terms($document);
                DB::transaction(function () use ($intent, $store, $terms, $reopenVersion) {
                    $current = BusinessDocumentPaymentIntent::whereKey($intent->id)->lockForUpdate()->firstOrFail();
                    abort_if($current->version !== $reopenVersion, 409);
                    $current->forceFill(['state' => 'active', 'store_id' => $store->id, 'store_company_id' => $store->company_id, 'terms' => $terms,
                        'fingerprint' => $this->fingerprint($store->id, $terms), 'token' => Str::random(64),
                        'version' => $current->version + 1, 'payment_method' => null, 'paid_at' => null,
                        'attempt_id' => null, 'btcpay_invoice_id' => null, 'settled_invoice_id' => null])->save();
                });
            });
        }

        return $this->activate($intent->fresh());
    }

    public function terminal(BusinessDocumentPaymentIntent $intent, string $state): void
    {
        if (in_array($intent->state, ['paid', 'revoked'], true)) {
            return;
        }
        BusinessDocumentPaymentIntent::whereKey($intent->id)->where('version', $intent->version)
            ->whereNotIn('state', ['paid', 'revoked'])->update([
                'state' => $state, 'version' => $intent->version + 1,
                'payment_method' => $state === 'paid' ? 'external' : null,
                'paid_at' => $state === 'paid' ? now() : null,
            ]);
        $intent->refresh();
    }

    public function documentPaid(BusinessDocument $document): void
    {
        $intent = BusinessDocumentPaymentIntent::where('business_document_id', $document->id)->first();
        if ($intent) {
            $this->locked($intent->user_id, $intent->document_key, fn () => $this->terminal($intent->fresh(), 'paid'));
        }
    }

    public function revokeDocument(BusinessDocument $document): void
    {
        $intent = BusinessDocumentPaymentIntent::where('document_key', 'i:'.$document->id)->first();
        if ($intent) {
            $this->locked($intent->user_id, $intent->document_key, fn () => $this->terminal($intent->fresh(), 'revoked'));
        }
    }

    public function payload(BusinessDocumentPaymentIntent $intent): array
    {
        $terms = $intent->terms;

        return ['id' => $intent->id, 'evolu_document_id' => $intent->evolu_document_id,
            'version' => $intent->version, 'state' => $intent->state, 'store_id' => $intent->store_id,
            'amount' => $terms['amount'], 'currency' => $terms['currency'], 'number' => $terms['number'],
            'btcpay_invoice_id' => $intent->settled_invoice_id ?? $intent->btcpay_invoice_id, 'payment_method' => $intent->payment_method,
            'payment_link' => $this->url($intent), 'paid_at' => $intent->paid_at?->toIso8601String()];
    }

    public function updateState(User $user, string $id, int $expectedVersion, string $state): BusinessDocumentPaymentIntent
    {
        $intent = BusinessDocumentPaymentIntent::where('user_id', $user->id)->whereNotNull('evolu_document_id')->findOrFail($id);

        return $this->locked($user->id, $intent->document_key, function () use ($intent, $expectedVersion, $state) {
            $intent = $intent->fresh();
            if ($intent->state === $state) {
                return $intent;
            }
            abort_if($intent->version !== $expectedVersion, 409);
            $this->terminal($intent, $state);

            return $intent;
        });
    }

    public function status(BusinessDocumentPaymentIntent $intent, bool $recoverTerms = false): BusinessDocumentPaymentIntent
    {
        return $this->locked($intent->user_id, $intent->document_key, function () use ($intent, $recoverTerms) {
            $intent = $intent->fresh();
            $this->assertCurrentDocument($intent);
            if (in_array($intent->state, ['active', 'unknown', 'creating'], true) && $this->historyAllowsPayment($intent, $recoverTerms) && $intent->btcpay_invoice_id) {
                $this->existing($intent, $recoverTerms);
            }
            if ($intent->state === 'paid' && $intent->payment_method === 'btcpay') {
                $this->settleDocument($intent);
            }

            return $intent;
        });
    }

    public function activate(BusinessDocumentPaymentIntent $intent, ?string $legacyToken = null): array
    {
        $expectedToken = $intent->token;

        return $this->locked($intent->user_id, $intent->document_key, function () use ($intent, $expectedToken, $legacyToken) {
            $intent = $intent->fresh();
            if (! hash_equals($intent->token, $expectedToken)
                || ($legacyToken !== null && ! BusinessDocument::whereKey($intent->business_document_id)->where('payment_token', $legacyToken)->exists())) {
                return ['checkout_link' => null, 'status' => 'revoked'];
            }
            $this->assertCurrentDocument($intent);
            if ($intent->state !== 'active') {
                return $this->result($intent);
            }
            if (! $this->historyAllowsPayment($intent, recoverTerms: true)) {
                return $this->result($intent);
            }
            if ($intent->btcpay_invoice_id) {
                $existing = $this->existing($intent, recoverTerms: true);
                if ($existing !== 'replaceable') {
                    return is_array($existing) ? $existing : $this->result($intent);
                }
            }
            $store = $this->store($intent);
            $attemptId = (string) Str::uuid();
            $attempt = null;
            $postStarted = false;
            try {
                // Key/client/wallet preconditions run before the durable creation boundary.
                $apiKey = $this->user($store)->getBtcPayApiKeyOrFail();
                $terms = $intent->terms;
                $metadata = ['paymentIntentId' => $intent->id, 'paymentAttemptId' => $attemptId,
                    'documentNumber' => $terms['number']];
                if ($intent->evolu_document_id) {
                    $metadata += ['ephemeral' => true, 'evoluDocumentId' => $intent->evolu_document_id];
                } else {
                    $metadata['businessDocumentId'] = $intent->business_document_id;
                }
                $invoice = $this->invoices->createInvoice($store->btcpay_store_id, [
                    'amount' => $terms['amount'], 'currency' => $terms['currency'], 'metadata' => $metadata,
                    'checkout' => ['expirationMinutes' => 60, 'monitoringMinutes' => 1440],
                ], $apiKey, retry: false, beforePost: function () use ($intent, $store, $attemptId, &$attempt, &$postStarted): bool {
                    // Claim and attempt are one committed write before the single provider POST.
                    $attempt = DB::transaction(function () use ($intent, $store, $attemptId) {
                        $claimed = BusinessDocumentPaymentIntent::whereKey($intent->id)->where('version', $intent->version)
                            ->where('state', 'active')->update(['state' => 'creating', 'attempt_id' => $attemptId,
                                'version' => $intent->version + 1, 'btcpay_invoice_id' => null]);
                        if (! $claimed) {
                            return null;
                        }

                        return BusinessDocumentPaymentAttempt::create(['id' => $attemptId,
                            'intent_id' => $intent->id, 'store_id' => $store->id, 'store_company_id' => $store->company_id,
                            'terms' => $intent->terms, 'state' => 'creating']);
                    });
                    $intent->refresh();
                    if (! $attempt) {
                        return false;
                    }
                    $postStarted = true;

                    return true;
                });
                if (! $attempt instanceof BusinessDocumentPaymentAttempt) {
                    throw new \LogicException('Invoice creation did not claim a durable attempt.');
                }
                if (! is_string($invoice['id'] ?? null) || $invoice['id'] === '' || strlen($invoice['id']) > 128) {
                    throw new \RuntimeException('Uncertain invoice creation.');
                }
                // Keep recovered provider identity even if URL validation or mapping persistence fails.
                $attempt->forceFill(['btcpay_invoice_id' => $invoice['id'], 'state' => 'known'])->save();
                $intent->forceFill(['btcpay_invoice_id' => $invoice['id']])->save();
                if (! $this->safeCheckoutUrl($invoice['checkoutLink'] ?? null)) {
                    throw new \RuntimeException('Uncertain invoice creation.');
                }
                if ($intent->evolu_document_id) {
                    $this->checkouts->registerCheckout($this->user($store), $store, $intent->evolu_document_id,
                        $invoice['id'], (float) $terms['amount'], $terms['currency']);
                } else {
                    BusinessDocument::whereKey($intent->business_document_id)->where('status', BusinessDocumentStatus::Issued->value)->update([
                        'btcpay_invoice_id' => $invoice['id'], 'btcpay_checkout_link' => $invoice['checkoutLink'],
                        'btcpay_checkout_created_at' => now(),
                    ]);
                }
                $completed = BusinessDocumentPaymentIntent::whereKey($intent->id)->where('state', 'creating')
                    ->where('attempt_id', $attemptId)->where('version', $intent->version)
                    ->update(['state' => 'active', 'version' => $intent->version + 1]);
                $intent->refresh();
                if (! $completed) {
                    return $this->result($intent);
                }

                return ['checkout_link' => $invoice['checkoutLink'], 'btcpay_invoice_id' => $invoice['id']];
            } catch (\Throwable) {
                if (! $postStarted) {
                    // No submission boundary was crossed: the existing active offer can be retried.
                    $intent->refresh();

                    return $this->result($intent);
                }
                $attempt?->forceFill(['state' => 'unknown'])->save();
                BusinessDocumentPaymentIntent::whereKey($intent->id)->where('state', 'creating')->where('attempt_id', $attemptId)
                    ->update(['state' => 'unknown', 'version' => DB::raw('version + 1')]);
                $intent->refresh();

                return $this->result($intent);
            }
        });
    }

    private function assertCurrentDocument(BusinessDocumentPaymentIntent $intent): void
    {
        if (! Store::where('user_id', $intent->user_id)->where('company_id', $intent->store_company_id)->whereKey($intent->store_id)->exists()) {
            $this->terminal($intent, 'revoked');

            return;
        }
        if (! str_starts_with($intent->document_key, 'i:')) {
            return;
        }
        $document = BusinessDocument::with(['company', 'store'])->find($intent->business_document_id);
        if (! $document) {
            $this->terminal($intent, 'revoked');

            return;
        }
        if (! $this->matchesDocument($intent, $document)) {
            $this->terminal($intent, $document->hasStatus(BusinessDocumentStatus::Paid) ? 'paid' : 'revoked');
        }
    }

    public function matchesDocument(BusinessDocumentPaymentIntent $intent, BusinessDocument $document): bool
    {
        $company = $document->company;
        $store = $document->store;

        return $company instanceof Company && $store instanceof Store && $company->user_id === $intent->user_id
            && $store->company_id === $document->company_id && $store->user_id === $intent->user_id
            && $document->payment_btc_enabled && $document->hasStatus(BusinessDocumentStatus::Issued)
            && $this->fingerprint((string) $document->store_id, $this->terms($document)) === $intent->fingerprint;
    }

    private function user(Store $store): User
    {
        $user = $store->user;
        if (! $user instanceof User) {
            throw new \RuntimeException('Payment owner unavailable.');
        }

        return $user;
    }

    private function store(BusinessDocumentPaymentIntent $intent): Store
    {
        return Store::with('user')->where('user_id', $intent->user_id)
            ->where('company_id', $intent->store_company_id)->findOrFail($intent->store_id);
    }

    private function rememberAttempt(BusinessDocumentPaymentIntent $intent, ?string $storeId, string $invoiceId, array $terms): void
    {
        BusinessDocumentPaymentAttempt::firstOrCreate(['store_id' => $storeId, 'btcpay_invoice_id' => $invoiceId],
            ['intent_id' => $intent->id, 'store_company_id' => Store::find($storeId)?->company_id, 'terms' => $terms, 'state' => 'known']);
    }

    /** All known older attempts remain relevant, including late settlement after expiry. */
    private function historyAllowsPayment(BusinessDocumentPaymentIntent $intent, bool $recoverTerms = false): bool
    {
        $blocked = false;
        $insufficient = false;
        $attempts = BusinessDocumentPaymentAttempt::where('intent_id', $intent->id)->get();
        foreach ($attempts as $attempt) {
            if ($attempt->btcpay_invoice_id === $intent->btcpay_invoice_id && $attempt->store_id === $intent->store_id) {
                continue;
            }
            if (! $attempt->btcpay_invoice_id || ! $attempt->store_id) {
                $blocked = true;

                continue;
            }
            $state = $this->inspect($intent, $attempt->store_id, $attempt->btcpay_invoice_id, $attempt->terms, $attempt->store_company_id, $recoverTerms, $attempt);
            if ($state['state'] === 'paid') {
                if (strcasecmp((string) $attempt->terms['currency'], $intent->terms['currency']) === 0
                    && (float) $attempt->terms['amount'] + 0.005 >= (float) $intent->terms['amount']) {
                    $this->settled($intent, $attempt->btcpay_invoice_id);

                    return false;
                }
                $insufficient = true;
                $blocked = true;

                continue;
            }
            if ($state['state'] !== 'replaceable') {
                $blocked = true;
            }
        }
        // Pending or unavailable older invoices prohibit creation, but must not hide
        // a confirmed payment on another attempt, including the current checkout.
        if ($blocked && $intent->btcpay_invoice_id && $this->existing($intent, $recoverTerms) === 'paid') {
            return false;
        }
        if ($insufficient) {
            $this->terminal($intent, 'revoked');
        }

        return ! $blocked;
    }

    private function settled(BusinessDocumentPaymentIntent $intent, ?string $invoiceId = null): void
    {
        if ($intent->business_document_id) {
            $document = BusinessDocument::with(['company', 'store'])->find($intent->business_document_id);
            if (! $document || ! $this->matchesDocument($intent, $document)) {
                $this->terminal($intent, 'revoked');

                return;
            }
        }
        $invoiceId ??= $intent->btcpay_invoice_id;
        $changed = BusinessDocumentPaymentIntent::whereKey($intent->id)->where('version', $intent->version)
            ->whereIn('state', ['active', 'creating', 'unknown'])->update([
                'state' => 'paid', 'payment_method' => 'btcpay', 'paid_at' => now(), 'settled_invoice_id' => $invoiceId,
                'version' => $intent->version + 1,
            ]);
        $intent->refresh();
        if (! $changed) {
            return;
        }
        BusinessDocumentPaymentAttempt::where('intent_id', $intent->id)->where('btcpay_invoice_id', $invoiceId)->update(['state' => 'paid']);
        $mapping = EphemeralBtcpayCheckout::where('user_id', $intent->user_id)->where('btcpay_invoice_id', $invoiceId)->first();
        if ($mapping) {
            $this->checkouts->markPaid($mapping);
        }
        $this->settleDocument($intent);
    }

    private function settleDocument(BusinessDocumentPaymentIntent $intent): void
    {
        if ($intent->business_document_id) {
            $document = BusinessDocument::with(['company', 'store'])->find($intent->business_document_id);
            if ($document && $this->matchesDocument($intent, $document)) {
                app(BusinessDocumentMarkPaidService::class)->markPaid($document, (float) $document->total, null, 'btcpay_sync');
            }
        }
    }

    private function existing(BusinessDocumentPaymentIntent $intent, bool $recoverTerms = false): array|string
    {
        $attempt = BusinessDocumentPaymentAttempt::where('intent_id', $intent->id)->where('store_id', $intent->store_id)
            ->where('btcpay_invoice_id', $intent->btcpay_invoice_id)->first();
        $terms = $attempt->terms ?? $intent->terms;
        $state = $this->inspect($intent, $intent->store_id, $intent->btcpay_invoice_id, $terms, $intent->store_company_id, $recoverTerms, $attempt);
        $terms = $attempt->terms ?? $terms;
        $sameCurrency = strcasecmp((string) ($terms['currency'] ?? ''), $intent->terms['currency']) === 0;
        if ($state['state'] === 'paid') {
            if ($sameCurrency && (float) ($terms['amount'] ?? 0) >= (float) $intent->terms['amount']) {
                $this->settled($intent);
            } else {
                $this->terminal($intent, 'revoked');

                return 'unknown';
            }
        }
        if ($state['state'] === 'payable') {
            // A still-payable old amount cannot be reused for edited terms or replaced blindly.
            if (! $sameCurrency || number_format((float) ($terms['amount'] ?? 0), 2, '.', '') !== $intent->terms['amount']) {
                return 'unknown';
            }
            $version = $intent->version;
            $intent->refresh();
            if ($intent->state !== 'active' || $intent->version !== $version) {
                return 'unknown';
            }

            return ['checkout_link' => $state['checkout_link'], 'btcpay_invoice_id' => $intent->btcpay_invoice_id];
        }

        return $state['state'];
    }

    /** Only a fresh, exactly bound response can establish settlement or unpaid expiry. */
    private function inspect(BusinessDocumentPaymentIntent $intent, ?string $storeId, string $invoiceId, array $terms, ?string $companyId, bool $recoverTerms = false, ?BusinessDocumentPaymentAttempt $attempt = null): array
    {
        try {
            $store = Store::with('user')->where('user_id', $intent->user_id)->where('company_id', $companyId)->findOrFail($storeId);
            $key = $this->user($store)->getBtcPayApiKeyOrFail();
            $this->invoices->forgetInvoiceCache($store->btcpay_store_id, $invoiceId, $key);
            $invoice = $this->invoices->getInvoice($store->btcpay_store_id, $invoiceId, $key);
            if (($invoice['id'] ?? null) !== $invoiceId || ! is_numeric($invoice['amount'] ?? null)
                || ! is_finite((float) $invoice['amount']) || (float) $invoice['amount'] <= 0
                || ! is_string($invoice['currency'] ?? null) || ! preg_match('/\A[A-Za-z]{3}\z/', $invoice['currency'])) {
                return ['state' => 'unknown'];
            }
            if ((! is_numeric($terms['amount'] ?? null) || empty($terms['currency'])) && $recoverTerms && $attempt) {
                $terms = ['amount' => number_format((float) $invoice['amount'], 2, '.', ''),
                    'currency' => strtoupper($invoice['currency']), 'number' => $terms['number'] ?? null];
                $attempt->forceFill(['terms' => $terms])->save();
            }
            if (! is_numeric($terms['amount'] ?? null)
                || number_format((float) $invoice['amount'], 2, '.', '') !== number_format((float) $terms['amount'], 2, '.', '')
                || strcasecmp($invoice['currency'], (string) ($terms['currency'] ?? '')) !== 0) {
                return ['state' => 'unknown'];
            }
            if (($invoice['status'] ?? '') === 'Settled'
                && in_array($invoice['additionalStatus'] ?? null, ['None', 'PaidOver', 'PaidLate'], true)
                && is_numeric($invoice['paidAmount'] ?? null) && (float) $invoice['paidAmount'] >= (float) $terms['amount']) {
                return ['state' => 'paid'];
            }
            if (($invoice['status'] ?? '') === 'Expired' && ($invoice['additionalStatus'] ?? null) === 'None'
                && is_numeric($invoice['paidAmount'] ?? null) && (float) $invoice['paidAmount'] === 0.0) {
                return ['state' => 'replaceable'];
            }
            if (! ($invoice['archived'] ?? false) && in_array($invoice['status'] ?? '', ['New', 'Processing'], true)
                && $this->safeCheckoutUrl($invoice['checkoutLink'] ?? null)) {
                return ['state' => 'payable', 'checkout_link' => $invoice['checkoutLink']];
            }
        } catch (\Throwable) {
            // A read outage is never evidence that another invoice may be created.
        }

        return ['state' => 'unknown'];
    }

    public function safeCheckoutUrl(mixed $url): bool
    {
        if (! is_string($url) || strlen($url) > 2048 || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $parts = parse_url($url);
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            return false;
        }
        $host = strtolower($parts['host'] ?? '');
        if (($parts['scheme'] ?? '') !== 'https' && ! config('services.btcpay.allow_insecure_http', false)
            && ! in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true) && str_contains($host, '.')) {
            return false;
        }
        foreach (['public_url', 'base_url'] as $setting) {
            $base = parse_url((string) config('services.btcpay.'.$setting));
            if (($base['scheme'] ?? null) === ($parts['scheme'] ?? null)
                && strtolower($base['host'] ?? '') === strtolower($parts['host'] ?? '')
                && ($base['port'] ?? null) === ($parts['port'] ?? null)
                && in_array($parts['scheme'] ?? '', ['https', 'http'], true)) {
                return true;
            }
        }

        return false;
    }

    private function result(BusinessDocumentPaymentIntent $intent): array
    {
        return ['checkout_link' => null, 'btcpay_invoice_id' => $intent->settled_invoice_id ?? $intent->btcpay_invoice_id,
            'status' => $intent->state, 'paid_at' => $intent->paid_at?->toIso8601String(),
            'payment_method' => $intent->payment_method];
    }
}
