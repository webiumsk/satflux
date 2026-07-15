<?php

namespace App\Services\Invoicing;

use App\Models\AuditLog;
use App\Models\EphemeralBtcpayCheckout;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Carbon;

class EphemeralBtcpayCheckoutService
{
    public function registerCheckout(
        User $user,
        Store $store,
        string $evoluDocumentId,
        string $btcpayInvoiceId,
        ?float $amount = null,
        ?string $currency = null,
    ): EphemeralBtcpayCheckout {
        return EphemeralBtcpayCheckout::query()->updateOrCreate(
            [
                'store_id' => $store->id,
                'btcpay_invoice_id' => $btcpayInvoiceId,
            ],
            [
                'user_id' => $user->id,
                'evolu_document_id' => $evoluDocumentId,
                'status' => EphemeralBtcpayCheckout::STATUS_PENDING,
                'amount' => $amount,
                'currency' => $currency,
                'paid_at' => null,
            ],
        );
    }

    /**
     * Newest still-pending checkout of a document - the reuse candidate that
     * keeps repeated invoice views from minting a new BTCPay invoice each
     * time (production 2026-07-14: every open of an unpaid invoice created
     * another "New" BTCPay invoice).
     */
    public function findLatestPending(
        User $user,
        Store $store,
        string $evoluDocumentId,
    ): ?EphemeralBtcpayCheckout {
        return EphemeralBtcpayCheckout::query()
            ->where('user_id', $user->id)
            ->where('store_id', $store->id)
            ->where('evolu_document_id', $evoluDocumentId)
            ->where('status', EphemeralBtcpayCheckout::STATUS_PENDING)
            ->latest('created_at')
            ->first();
    }

    public function findLatestPaid(
        User $user,
        Store $store,
        string $evoluDocumentId,
    ): ?EphemeralBtcpayCheckout {
        return EphemeralBtcpayCheckout::query()
            ->where('user_id', $user->id)
            ->where('store_id', $store->id)
            ->where('evolu_document_id', $evoluDocumentId)
            ->where('status', EphemeralBtcpayCheckout::STATUS_PAID)
            ->latest('paid_at')
            ->latest('created_at')
            ->first();
    }

    public function markPaid(EphemeralBtcpayCheckout $checkout): EphemeralBtcpayCheckout
    {
        if (! $checkout->isPaid()) {
            $checkout->update([
                'status' => EphemeralBtcpayCheckout::STATUS_PAID,
                'paid_at' => Carbon::now(),
            ]);
        }

        return $checkout->fresh();
    }

    public function findForUser(
        User $user,
        string $evoluDocumentId,
        string $btcpayInvoiceId,
    ): ?EphemeralBtcpayCheckout {
        return EphemeralBtcpayCheckout::query()
            ->where('user_id', $user->id)
            ->where('evolu_document_id', $evoluDocumentId)
            ->where('btcpay_invoice_id', $btcpayInvoiceId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function markPaidFromWebhook(Store $store, string $btcpayInvoiceId, array $metadata): ?EphemeralBtcpayCheckout
    {
        if (! $this->metadataIndicatesEphemeral($metadata)) {
            return null;
        }

        $checkout = EphemeralBtcpayCheckout::query()
            ->where('store_id', $store->id)
            ->where('btcpay_invoice_id', $btcpayInvoiceId)
            ->first();

        if (! $checkout) {
            return null;
        }

        if ($checkout->isPaid()) {
            return $checkout;
        }

        $checkout = $this->markPaid($checkout);

        AuditLog::log('business_document.ephemeral_btcpay_paid', 'store', (string) $store->id, [
            'checkout_id' => $checkout->id,
            'btcpay_invoice_id' => $btcpayInvoiceId,
            'evolu_document_id' => $checkout->evolu_document_id,
        ], $checkout->user_id);

        return $checkout;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function metadataIndicatesEphemeral(array $metadata): bool
    {
        if (! filter_var($metadata['ephemeral'] ?? false, FILTER_VALIDATE_BOOL)) {
            return false;
        }

        $evoluDocumentId = $metadata['evoluDocumentId'] ?? null;

        return is_string($evoluDocumentId) && $evoluDocumentId !== '';
    }

    /**
     * @return array{status: string, paid_at: string|null, evolu_document_id: string}
     */
    public function statusPayload(EphemeralBtcpayCheckout $checkout): array
    {
        return [
            'status' => $checkout->status,
            'paid_at' => $checkout->paid_at?->toIso8601String(),
            'evolu_document_id' => $checkout->evolu_document_id,
        ];
    }
}
