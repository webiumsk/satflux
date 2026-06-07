<?php

namespace App\Services\Invoicing;

use App\Models\ExpenseIsdocCreditBalance;
use App\Models\ExpenseIsdocPackPurchase;
use App\Models\User;
use App\Services\BtcPay\InvoiceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BusinessExpenseIsdocPackService
{
    public function __construct(
        protected InvoiceService $invoiceService,
    ) {}

    /**
     * @return array<int, array{credits: int, price_eur: float}>
     */
    public function availablePacks(): array
    {
        return config('invoicing.expense_isdoc_packs', []);
    }

    /**
     * @return array{credits: int, price_eur: float}
     */
    public function resolvePack(int $credits): array
    {
        foreach ($this->availablePacks() as $pack) {
            if ((int) $pack['credits'] === $credits) {
                return [
                    'credits' => (int) $pack['credits'],
                    'price_eur' => (float) $pack['price_eur'],
                ];
            }
        }

        throw ValidationException::withMessages([
            'pack' => ['Unknown pack size.'],
        ]);
    }

    /**
     * @return array{checkoutLink: string, purchaseId: string, credits: int, price_eur: string}
     */
    public function startPurchase(User $user, int $credits): array
    {
        $pack = $this->resolvePack($credits);
        $storeId = config('services.btcpay.subscription_store_id');
        if (! $storeId) {
            throw ValidationException::withMessages([
                'billing' => ['ISDOC pack billing is not configured.'],
            ]);
        }

        $purchase = ExpenseIsdocPackPurchase::create([
            'user_id' => $user->id,
            'credits' => $pack['credits'],
            'price_eur' => $pack['price_eur'],
            'status' => ExpenseIsdocPackPurchase::STATUS_PENDING,
        ]);

        $invoice = $this->invoiceService->createInvoice($storeId, [
            'amount' => number_format($pack['price_eur'], 2, '.', ''),
            'currency' => 'EUR',
            'metadata' => [
                'purpose' => 'expense_isdoc_pack',
                'packCredits' => (string) $pack['credits'],
                'userId' => (string) $user->id,
                'purchaseId' => $purchase->id,
            ],
            'checkout' => [
                'redirectURL' => rtrim(config('app.url'), '/').'/account/profile?isdoc_pack=paid',
                'expirationMinutes' => 60,
            ],
        ]);

        $invoiceId = $invoice['id'] ?? null;
        $checkoutLink = $invoice['checkoutLink'] ?? null;
        if (! $invoiceId || ! $checkoutLink) {
            throw ValidationException::withMessages([
                'billing' => ['Could not create payment invoice.'],
            ]);
        }

        $purchase->update(['btcpay_invoice_id' => $invoiceId]);

        return [
            'checkoutLink' => $checkoutLink,
            'purchaseId' => $purchase->id,
            'credits' => $pack['credits'],
            'price_eur' => number_format($pack['price_eur'], 2, '.', ''),
        ];
    }

    public function fulfillPaidInvoice(string $btcpayInvoiceId, ?string $userId = null, ?string $purchaseId = null): bool
    {
        $purchase = ExpenseIsdocPackPurchase::query()
            ->where('btcpay_invoice_id', $btcpayInvoiceId)
            ->first();

        if (! $purchase && $purchaseId) {
            $purchase = ExpenseIsdocPackPurchase::query()->find($purchaseId);
        }

        if (! $purchase || $purchase->status === ExpenseIsdocPackPurchase::STATUS_PAID) {
            return false;
        }

        if ($userId && (string) $purchase->user_id !== (string) $userId) {
            return false;
        }

        DB::transaction(function () use ($purchase) {
            $purchase->update([
                'status' => ExpenseIsdocPackPurchase::STATUS_PAID,
                'paid_at' => now(),
            ]);

            $balance = ExpenseIsdocCreditBalance::query()
                ->lockForUpdate()
                ->firstOrCreate(
                    ['user_id' => $purchase->user_id],
                    ['balance' => 0],
                );

            $balance->balance = (int) $balance->balance + (int) $purchase->credits;
            $balance->save();
        });

        return true;
    }

    public function purchasedBalance(User $user): int
    {
        return (int) (ExpenseIsdocCreditBalance::query()
            ->where('user_id', $user->id)
            ->value('balance') ?? 0);
    }

    public function consumePurchasedCredit(User $user): void
    {
        DB::transaction(function () use ($user) {
            $balance = ExpenseIsdocCreditBalance::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $balance || $balance->balance < 1) {
                throw ValidationException::withMessages([
                    'quota' => ['No purchased ISDOC extractions remaining.'],
                ]);
            }

            $balance->decrement('balance');
        });
    }
}
