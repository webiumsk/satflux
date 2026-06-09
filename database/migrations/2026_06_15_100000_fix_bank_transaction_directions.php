<?php

use App\Models\BankTransaction;
use App\Support\Invoicing\BankTransactionDirectionGuesser;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $guesser = app(BankTransactionDirectionGuesser::class);

        BankTransaction::query()
            ->orderBy('id')
            ->chunkById(200, function ($transactions) use ($guesser) {
                foreach ($transactions as $transaction) {
                    $direction = $guesser->inferFromTransaction($transaction);

                    if ($transaction->direction !== $direction) {
                        BankTransaction::query()
                            ->whereKey($transaction->id)
                            ->update(['direction' => $direction->value]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Direction corrections are not reversible without a snapshot.
    }
};
