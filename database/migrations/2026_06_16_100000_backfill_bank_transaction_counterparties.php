<?php

use App\Models\BankTransaction;
use App\Services\Invoicing\BankImport\TatraBankEmailParser;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $parser = new TatraBankEmailParser;

        BankTransaction::query()
            ->orderBy('id')
            ->chunkById(200, function ($transactions) use ($parser) {
                foreach ($transactions as $transaction) {
                    $updates = [];

                    if (trim((string) ($transaction->counterparty_name ?? '')) === '') {
                        $label = $this->inferCounterpartyLabel(
                            $transaction->reference,
                            $transaction->counterparty_name,
                        );
                        if ($label !== null) {
                            $updates['counterparty_name'] = $label;
                        }
                    }

                    if (trim((string) ($transaction->variable_symbol ?? '')) === '' && $transaction->reference) {
                        $rows = $parser->parse('notify@tatrabanka.sk', (string) $transaction->reference, (string) $transaction->reference);
                        $vs = $rows[0]->variableSymbol ?? null;
                        if ($vs !== null) {
                            $updates['variable_symbol'] = $vs;
                        }
                    }

                    if ($updates !== []) {
                        BankTransaction::query()->whereKey($transaction->id)->update($updates);
                    }
                }
            });
    }

    protected function inferCounterpartyLabel(?string $reference, ?string $counterparty): ?string
    {
        foreach ([$counterparty, $reference] as $source) {
            if ($source === null || trim($source) === '') {
                continue;
            }

            $source = trim($source);

            if (preg_match('/EUR\s+N[AÁ]KUP\s+POS/iu', $source)) {
                return 'Platba kartou (POS)';
            }
            if (preg_match('/POS\s+n[aá]kup/iu', $source)) {
                return 'Platba kartou (POS)';
            }
            if (preg_match('/transak[cč]n[aá]\s+d[aá]n/iu', $source)) {
                return 'Transakčná daň';
            }
            if (preg_match('/^(debet|kredit|obrat|stav)\s+na\s+ucte/iu', $source, $m)) {
                return match (strtolower($m[1])) {
                    'debet' => 'Bankový výdaj',
                    'kredit' => 'Bankový príjem',
                    'obrat' => 'Obrat na účte',
                    'stav' => 'Stav na účte',
                    default => null,
                };
            }
        }

        return null;
    }

    public function down(): void
    {
        // Heuristic labels are not reversible.
    }
};
