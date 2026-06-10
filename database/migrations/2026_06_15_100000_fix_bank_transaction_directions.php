<?php

use App\Enums\BankTransactionDirection;
use App\Models\BankTransaction;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** @var list<string> */
    private const DEBIT_HINTS = [
        'debet na',
        'debetna',
        'debet ',
        'debet.',
        'debit',
        'dbit',
        'odchod',
        'odch.',
        'odchodz',
        'výdaj',
        'vydaj',
        'nákup pos',
        'nakup pos',
        'eur nákup',
        'eur nakup',
        'pos nákup',
        'pos nakup',
        'transakčná daň',
        'transakcna dan',
        'poplatok',
        'výber',
        'vyber',
        'platba kartou',
        'platba prevodom',
        'smerom von',
    ];

    /** @var list<string> */
    private const CREDIT_HINTS = [
        'kredit na',
        'kredit ',
        'credit',
        'príjem',
        'prijem',
        'prijatie',
        'vklad',
        'pripis',
        'pripísan',
        'pripisan',
    ];

    public function up(): void
    {
        BankTransaction::query()
            ->orderBy('id')
            ->chunkById(200, function ($transactions) {
                foreach ($transactions as $transaction) {
                    $direction = $this->inferDirection($transaction);

                    if ($transaction->direction !== $direction) {
                        BankTransaction::query()
                            ->whereKey($transaction->id)
                            ->update(['direction' => $direction->value]);
                    }
                }
            });
    }

    protected function inferDirection(BankTransaction $transaction): BankTransactionDirection
    {
        $haystack = mb_strtolower(trim(implode(' ', array_filter([
            $transaction->reference,
            $transaction->counterparty_name,
        ], fn (?string $h) => $h !== null && trim($h) !== ''))));

        if ($haystack !== '' && $this->containsHint($haystack, self::DEBIT_HINTS)) {
            return BankTransactionDirection::Debit;
        }

        if ($haystack !== '' && $this->containsCreditHint($haystack)) {
            return BankTransactionDirection::Credit;
        }

        return (float) $transaction->amount < 0
            ? BankTransactionDirection::Debit
            : BankTransactionDirection::Credit;
    }

    /**
     * @param  list<string>  $needles
     */
    protected function containsHint(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    protected function containsCreditHint(string $haystack): bool
    {
        if (str_contains($haystack, 'obrat na') && ! str_contains($haystack, 'debet')) {
            return true;
        }

        return $this->containsHint($haystack, self::CREDIT_HINTS);
    }

    public function down(): void
    {
        // Direction corrections are not reversible without a snapshot.
    }
};
